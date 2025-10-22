<?php

namespace GlpiPlugin\EntraHierarchy;

use CommonDBTM;
use CronTask;
use User;

/**
 * EntraSync class for synchronizing users and hierarchy from Entra ID
 */
class EntraSync extends CommonDBTM
{
    public static $rightname = 'config';

    /**
     * Cron task to synchronize organizational hierarchy
     *
     * @param CronTask $task
     * @return int 0: nothing to do, 1: done with success
     */
    public static function cronSyncEntraHierarchy($task)
    {
        global $DB;

        $startTime = microtime(true);
        $syncStartTime = date('Y-m-d H:i:s');
        $task->log(__('Starting Entra ID hierarchy synchronization...', 'glpientrahierarchy'));

        // Check if plugin is configured
        $config = EntraConfig::getConfig();
        if (!$config || !$config['sync_enabled']) {
            $task->log(__('Sync is disabled or plugin is not configured', 'glpientrahierarchy'));
            return 0;
        }

        // Check if current time is within sync window
        $currentHour = (int)date('H');
        $syncHourMin = (int)($config['sync_hourmin'] ?? 0);
        $syncHourMax = (int)($config['sync_hourmax'] ?? 24);

        if ($currentHour < $syncHourMin || $currentHour >= $syncHourMax) {
            $task->log(sprintf(
                __('Sync skipped: current hour %d is outside sync window (%02d:00 - %02d:00)', 'glpientrahierarchy'),
                $currentHour,
                $syncHourMin,
                $syncHourMax
            ));
            return 0;
        }

        // Initialize Graph API client
        $graphClient = new GraphApiClient(
            $config['client_id'],
            $config['client_secret'],
            $config['tenant_id']
        );

        // Test connection
        if (!$graphClient->testConnection()) {
            $task->log(__('Failed to connect to Microsoft Graph API', 'glpientrahierarchy'));
            self::logSync('failed', 'Failed to connect to Microsoft Graph API', 0, 0, 0, 0, 0);
            return 0;
        }

        $task->log(__('Successfully connected to Microsoft Graph API', 'glpientrahierarchy'));

        // Get all users from Entra ID
        $entraUsers = $graphClient->getAllUsers();
        $task->log(sprintf(__('Found %d users in Entra ID', 'glpientrahierarchy'), count($entraUsers)));

        // Apply filters
        $filteredUsers = self::applyFilters($entraUsers, $config);
        $task->log(sprintf(__('After applying filters: %d users', 'glpientrahierarchy'), count($filteredUsers)));

        $stats = [
            'total' => count($entraUsers),
            'filtered' => count($entraUsers) - count($filteredUsers),
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'deactivated' => 0,
            'deleted' => 0
        ];

        // Process each user
        foreach ($filteredUsers as $entraUser) {
            try {
                $result = self::processUser($entraUser, $graphClient, $task);

                if ($result === 'created') {
                    $stats['created']++;
                } elseif ($result === 'updated') {
                    $stats['updated']++;
                }
                $stats['synced']++;

            } catch (\Exception $e) {
                $stats['failed']++;
                $task->log(sprintf(
                    __('Failed to process user %s: %s', 'glpientrahierarchy'),
                    $entraUser['userPrincipalName'] ?? $entraUser['id'],
                    $e->getMessage()
                ));
            }
        }

        // Handle users deleted/deactivated from Entra ID
        $deletedUsersAction = $config['deleted_users_action'] ?? 'keep_active';
        $deletedStats = self::handleDisabledAndDeletedUsers($syncStartTime, $deletedUsersAction, $task);
        $stats['deactivated'] = $deletedStats['deactivated'];
        $stats['deleted'] = $deletedStats['deleted'];

        if ($stats['deactivated'] > 0 || $stats['deleted'] > 0) {
            $task->log(sprintf(
                __('Handled deleted Entra users: %d deactivated, %d deleted', 'glpientrahierarchy'),
                $stats['deactivated'],
                $stats['deleted']
            ));
        }

        // Update last sync time
        $DB->update(
            'glpi_plugin_entrahierarchy_configs',
            ['last_sync' => date('Y-m-d H:i:s')],
            ['id' => $config['id']]
        );

        $duration = round(microtime(true) - $startTime, 2);

        $task->log(sprintf(
            __('Sync completed: %d synced (%d created, %d updated), %d failed, %d deactivated, %d deleted in %s seconds', 'glpientrahierarchy'),
            $stats['synced'],
            $stats['created'],
            $stats['updated'],
            $stats['failed'],
            $stats['deactivated'],
            $stats['deleted'],
            $duration
        ));

        // Log to database
        self::logSync(
            'success',
            'Sync completed successfully',
            $stats['synced'],
            $stats['created'],
            $stats['updated'],
            $stats['failed'],
            $duration,
            $stats['deactivated'],
            $stats['deleted']
        );

        $task->addVolume($stats['synced']);
        return 1;
    }

    /**
     * Process a single user - create/update in GLPI and set supervisor
     *
     * @param array $entraUser User data from Entra ID
     * @param GraphApiClient $graphClient
     * @param CronTask|null $task
     * @return string 'created', 'updated', or 'skipped'
     */
    private static function processUser($entraUser, $graphClient, $task = null)
    {
        global $DB;

        $entraId = $entraUser['id'];
        $upn = $entraUser['userPrincipalName'] ?? '';
        $email = $entraUser['mail'] ?? $upn;

        // Check if user already exists in mapping table
        $mapping = $DB->request([
            'FROM' => 'glpi_plugin_entrahierarchy_usermaps',
            'WHERE' => ['entra_id' => $entraId]
        ])->current();

        $glpiUserId = null;
        $action = 'skipped';

        if ($mapping) {
            // User exists in mapping
            $glpiUserId = $mapping['users_id'];

            // Verify GLPI user still exists
            $user = new User();
            if (!$user->getFromDB($glpiUserId)) {
                // User was deleted, remove mapping
                $DB->delete('glpi_plugin_entrahierarchy_usermaps', ['id' => $mapping['id']]);
                $mapping = false;
                $glpiUserId = null;
            }
        }

        if (!$mapping) {
            // Try to find user by email or UPN
            $user = new User();
            $userResult = $DB->request([
                'FROM' => 'glpi_users',
                'WHERE' => [
                    'OR' => [
                        'name' => $upn,
                        'name' => $email
                    ]
                ],
                'LIMIT' => 1
            ]);

            if ($userResult->count() > 0) {
                // User exists in GLPI but not in mapping - update basic fields
                $glpiUserId = $userResult->current()['id'];
                $updated = self::updateGlpiUser($glpiUserId, $entraUser);
                if ($updated) {
                    $action = 'updated';
                    if ($task) {
                        $task->log(sprintf(__('Found and updated existing user: %s', 'glpientrahierarchy'), $upn));
                    }
                }
            } else {
                // Create new user
                $glpiUserId = self::createGlpiUser($entraUser);
                if ($glpiUserId) {
                    $action = 'created';
                    if ($task) {
                        $task->log(sprintf(__('Created user: %s', 'glpientrahierarchy'), $upn));
                    }
                }
            }

            // Create mapping with all Entra data
            if ($glpiUserId) {
                $DB->insert('glpi_plugin_entrahierarchy_usermaps', [
                    'users_id' => $glpiUserId,
                    'entra_id' => $entraId,
                    'entra_upn' => $upn,
                    'entra_email' => $email,
                    'entra_display_name' => $entraUser['displayName'] ?? null,
                    'entra_job_title' => $entraUser['jobTitle'] ?? null,
                    'entra_department' => $entraUser['department'] ?? null,
                    'entra_company_name' => $entraUser['companyName'] ?? null,
                    'entra_office_location' => $entraUser['officeLocation'] ?? null,
                    'entra_mobile_phone' => $entraUser['mobilePhone'] ?? null,
                    'entra_business_phones' => isset($entraUser['businessPhones']) ? json_encode($entraUser['businessPhones']) : null,
                    'entra_employee_id' => $entraUser['employeeId'] ?? null,
                    'entra_employee_type' => $entraUser['employeeType'] ?? null,
                    'entra_user_type' => $entraUser['userType'] ?? null,
                    'entra_account_enabled' => isset($entraUser['accountEnabled']) ? ($entraUser['accountEnabled'] ? 1 : 0) : null,
                    'last_sync' => date('Y-m-d H:i:s'),
                    'date_creation' => date('Y-m-d H:i:s')
                ]);
            }
        } else {
            // Update mapping timestamp and Entra data
            $DB->update(
                'glpi_plugin_entrahierarchy_usermaps',
                [
                    'entra_upn' => $upn,
                    'entra_email' => $email,
                    'entra_display_name' => $entraUser['displayName'] ?? null,
                    'entra_job_title' => $entraUser['jobTitle'] ?? null,
                    'entra_department' => $entraUser['department'] ?? null,
                    'entra_company_name' => $entraUser['companyName'] ?? null,
                    'entra_office_location' => $entraUser['officeLocation'] ?? null,
                    'entra_mobile_phone' => $entraUser['mobilePhone'] ?? null,
                    'entra_business_phones' => isset($entraUser['businessPhones']) ? json_encode($entraUser['businessPhones']) : null,
                    'entra_employee_id' => $entraUser['employeeId'] ?? null,
                    'entra_employee_type' => $entraUser['employeeType'] ?? null,
                    'entra_user_type' => $entraUser['userType'] ?? null,
                    'entra_account_enabled' => isset($entraUser['accountEnabled']) ? ($entraUser['accountEnabled'] ? 1 : 0) : null,
                    'last_sync' => date('Y-m-d H:i:s')
                ],
                ['id' => $mapping['id']]
            );

            // Update GLPI user basic fields for existing users
            if ($glpiUserId) {
                $updated = self::updateGlpiUser($glpiUserId, $entraUser);
                if ($updated && $action !== 'created') {
                    $action = 'updated';
                }
            }
        }

        // Synchronize accountEnabled to is_active in GLPI
        if ($glpiUserId) {
            $user = new User();
            if ($user->getFromDB($glpiUserId)) {
                $isActive = isset($entraUser['accountEnabled']) && $entraUser['accountEnabled'] ? 1 : 0;
                $currentIsActive = $user->fields['is_active'];

                if ($currentIsActive != $isActive) {
                    $user->update([
                        'id' => $glpiUserId,
                        'is_active' => $isActive
                    ]);
                    if ($action !== 'created') {
                        $action = 'updated';
                    }
                }
            }
        }

        // Sync supervisor/manager if user exists and manual override is not set
        if ($glpiUserId && (!$mapping || !$mapping['manual_supervisor'])) {
            $updated = self::syncSupervisor($glpiUserId, $entraId, $graphClient, $task);
            if ($updated && $action !== 'created') {
                $action = 'updated';
            }
        }

        // Apply automatic mapping from Entra fields (for both new and existing users)
        if ($glpiUserId) {
            $config = EntraConfig::getConfig();
            self::applyAutoMapping($glpiUserId, $entraUser, $config);
        }

        return $action;
    }

    /**
     * Create a new GLPI user from Entra ID data
     *
     * @param array $entraUser
     * @return int|false User ID or false on failure
     */
    private static function createGlpiUser($entraUser)
    {
        $user = new User();

        // Set is_active based on accountEnabled from Entra ID
        $isActive = isset($entraUser['accountEnabled']) && $entraUser['accountEnabled'] ? 1 : 0;

        $userData = [
            'name' => $entraUser['userPrincipalName'] ?? $entraUser['mail'],
            'realname' => $entraUser['surname'] ?? '',
            'firstname' => $entraUser['givenName'] ?? '',
            'usertitles_id' => 0,
            '_useremails' => [$entraUser['mail'] ?? ''],
            'is_active' => $isActive,
            'authtype' => 1, // External auth
            'auths_id' => 0,
            'comment' => __('Created by Entra Hierarchy plugin', 'glpientrahierarchy')
        ];

        $userId = $user->add($userData);

        // Assign default profile and entity to new user
        if ($userId) {
            $config = EntraConfig::getConfig();

            if ($config && $config['default_profiles_id'] > 0) {
                $profileUser = new \Profile_User();
                $profileUser->add([
                    'users_id' => $userId,
                    'profiles_id' => $config['default_profiles_id'],
                    'entities_id' => $config['default_entities_id'] ?? 0,
                    'is_recursive' => $config['profile_is_recursive'] ?? 1,
                    'is_dynamic' => 1
                ]);

                error_log("EntraSync::createGlpiUser() - Assigned profile {$config['default_profiles_id']} and entity {$config['default_entities_id']} to user {$userId}");
            }

            // Assign default group
            if ($config && $config['default_groups_id'] > 0) {
                $groupUser = new \Group_User();
                $groupUser->add([
                    'users_id' => $userId,
                    'groups_id' => $config['default_groups_id'],
                    'is_dynamic' => 1
                ]);

                error_log("EntraSync::createGlpiUser() - Assigned group {$config['default_groups_id']} to user {$userId}");
            }

            // Update user with location, category, and language
            if ($config) {
                $updateData = ['id' => $userId];

                if ($config['default_locations_id'] > 0) {
                    $updateData['locations_id'] = $config['default_locations_id'];
                }

                if ($config['default_usercategories_id'] > 0) {
                    $updateData['usercategories_id'] = $config['default_usercategories_id'];
                }

                if (!empty($config['default_language'])) {
                    $updateData['language'] = $config['default_language'];
                }

                if (count($updateData) > 1) { // More than just 'id'
                    $user->update($updateData);
                    error_log("EntraSync::createGlpiUser() - Updated user {$userId} with location, category, language");
                }
            }

            // Apply automatic mapping from Entra fields
            if ($config) {
                self::applyAutoMapping($userId, $entraUser, $config);
            }
        }

        return $userId ?: false;
    }

    /**
     * Update existing GLPI user from Entra ID data
     *
     * Updates basic user fields (name, phone, title) for existing users.
     * Also ensures default profile/entity settings are applied if user has dynamic profile assignment.
     *
     * @param int $glpiUserId GLPI user ID
     * @param array $entraUser Entra user data
     * @return bool True if user was updated
     */
    private static function updateGlpiUser($glpiUserId, $entraUser)
    {
        global $DB;

        $user = new User();
        if (!$user->getFromDB($glpiUserId)) {
            return false;
        }

        $updateData = ['id' => $glpiUserId];
        $changed = false;

        // Ensure default profile/entity is assigned if configured
        // Only update dynamic profiles (is_dynamic = 1) to avoid overwriting manual assignments
        $config = EntraConfig::getConfig();
        if ($config && $config['default_profiles_id'] > 0) {
            // Check current profile assignment
            $profileResult = $DB->request([
                'FROM' => 'glpi_profiles_users',
                'WHERE' => [
                    'users_id' => $glpiUserId,
                    'is_dynamic' => 1 // Only check dynamic (auto-assigned) profiles
                ],
                'LIMIT' => 1
            ]);

            if ($profileResult->count() > 0) {
                $currentProfile = $profileResult->current();

                // If current profile doesn't match configured default, update it
                if ($currentProfile['profiles_id'] != $config['default_profiles_id'] ||
                    $currentProfile['entities_id'] != ($config['default_entities_id'] ?? 0)) {

                    $DB->update(
                        'glpi_profiles_users',
                        [
                            'profiles_id' => $config['default_profiles_id'],
                            'entities_id' => $config['default_entities_id'] ?? 0,
                            'is_recursive' => $config['profile_is_recursive'] ?? 1
                        ],
                        [
                            'users_id' => $glpiUserId,
                            'is_dynamic' => 1
                        ]
                    );

                    error_log("EntraSync::updateGlpiUser() - Updated profile for user {$glpiUserId} from {$currentProfile['profiles_id']} to {$config['default_profiles_id']}");
                    $changed = true;
                }
            } else {
                // User has no dynamic profile - add one
                $profileUser = new \Profile_User();
                $profileUser->add([
                    'users_id' => $glpiUserId,
                    'profiles_id' => $config['default_profiles_id'],
                    'entities_id' => $config['default_entities_id'] ?? 0,
                    'is_recursive' => $config['profile_is_recursive'] ?? 1,
                    'is_dynamic' => 1
                ]);

                error_log("EntraSync::updateGlpiUser() - Added missing profile {$config['default_profiles_id']} to user {$glpiUserId}");
                $changed = true;
            }
        }

        // Update realname (surname)
        $newRealname = $entraUser['surname'] ?? '';
        if ($user->fields['realname'] !== $newRealname) {
            $updateData['realname'] = $newRealname;
            $changed = true;
        }

        // Update firstname (givenName)
        $newFirstname = $entraUser['givenName'] ?? '';
        if ($user->fields['firstname'] !== $newFirstname) {
            $updateData['firstname'] = $newFirstname;
            $changed = true;
        }

        // Update phone (businessPhones[0])
        $newPhone = '';
        if (isset($entraUser['businessPhones']) && is_array($entraUser['businessPhones']) && count($entraUser['businessPhones']) > 0) {
            $newPhone = $entraUser['businessPhones'][0];
        }
        if ($user->fields['phone'] !== $newPhone) {
            $updateData['phone'] = $newPhone;
            $changed = true;
        }

        // Update mobile (mobilePhone)
        $newMobile = $entraUser['mobilePhone'] ?? '';
        if ($user->fields['mobile'] !== $newMobile) {
            $updateData['mobile'] = $newMobile;
            $changed = true;
        }

        // Update phone2 (businessPhones[1] if exists)
        $newPhone2 = '';
        if (isset($entraUser['businessPhones']) && is_array($entraUser['businessPhones']) && count($entraUser['businessPhones']) > 1) {
            $newPhone2 = $entraUser['businessPhones'][1];
        }
        if ($user->fields['phone2'] !== $newPhone2) {
            $updateData['phone2'] = $newPhone2;
            $changed = true;
        }

        // Update job title (store in comment field for now - GLPI doesn't have dedicated field)
        // Note: usertitles_id is a dropdown, we would need to create/find matching title
        // For now, we'll update the comment field with job title info
        if (!empty($entraUser['jobTitle'])) {
            $newComment = sprintf(__('Job Title: %s (synced from Entra ID)', 'glpientrahierarchy'), $entraUser['jobTitle']);
            // Only update if comment doesn't already contain this info
            if (strpos($user->fields['comment'], $entraUser['jobTitle']) === false) {
                $updateData['comment'] = $newComment;
                $changed = true;
            }
        }

        if ($changed) {
            $user->update($updateData);
            error_log("EntraSync::updateGlpiUser() - Updated user {$glpiUserId} ({$entraUser['userPrincipalName']})");
            return true;
        }

        return false;
    }

    /**
     * Sync user's supervisor from Entra ID
     *
     * @param int $glpiUserId
     * @param string $entraId
     * @param GraphApiClient $graphClient
     * @param CronTask|null $task
     * @return bool True if supervisor was updated
     */
    private static function syncSupervisor($glpiUserId, $entraId, $graphClient, $task = null)
    {
        global $DB;

        // Get manager from Entra ID
        $manager = $graphClient->getUserManager($entraId);

        $supervisorId = null;

        if ($manager) {
            // Find manager in GLPI mapping
            $managerMapping = $DB->request([
                'FROM' => 'glpi_plugin_entrahierarchy_usermaps',
                'WHERE' => ['entra_id' => $manager['id']]
            ])->current();

            if ($managerMapping) {
                $supervisorId = $managerMapping['users_id'];
            } else {
                // Manager not yet in GLPI - try to find by UPN or email
                $managerUpn = $manager['userPrincipalName'] ?? '';
                $managerEmail = $manager['mail'] ?? $managerUpn;

                if ($managerUpn || $managerEmail) {
                    $result = $DB->request([
                        'FROM' => 'glpi_users',
                        'WHERE' => [
                            'OR' => [
                                'name' => $managerUpn,
                                'name' => $managerEmail
                            ]
                        ],
                        'LIMIT' => 1
                    ]);

                    if ($result->count() > 0) {
                        $supervisorId = $result->current()['id'];
                    }
                }
            }
        }

        // Update supervisor in GLPI
        $user = new User();
        if ($user->getFromDB($glpiUserId)) {
            $currentSupervisor = $user->fields['users_id_supervisor'];

            if ($currentSupervisor != $supervisorId) {
                $user->update([
                    'id' => $glpiUserId,
                    'users_id_supervisor' => $supervisorId ?? 0
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Log sync results to database
     *
     * @param string $status
     * @param string $message
     * @param int $synced
     * @param int $created
     * @param int $updated
     * @param int $failed
     * @param float $duration
     * @param int $deactivated
     * @param int $deleted
     */
    private static function logSync($status, $message, $synced, $created, $updated, $failed, $duration, $deactivated = 0, $deleted = 0)
    {
        global $DB;

        $DB->insert('glpi_plugin_entrahierarchy_synclogs', [
            'date' => date('Y-m-d H:i:s'),
            'status' => $status,
            'message' => $message,
            'users_synced' => $synced,
            'users_created' => $created,
            'users_updated' => $updated,
            'users_failed' => $failed,
            'users_deactivated' => $deactivated,
            'users_deleted' => $deleted,
            'duration' => $duration
        ]);
    }

    /**
     * Get cron info
     *
     * @param string $name
     * @return array
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'SyncEntraHierarchy':
                return [
                    'description' => __('Synchronize organizational hierarchy from Microsoft Entra ID', 'glpientrahierarchy'),
                    'parameter'   => __('Runs every 30 minutes by default', 'glpientrahierarchy')
                ];
        }
        return [];
    }

    /**
     * Manual sync triggered from UI
     *
     * @return array Results with status, message, and stats
     */
    public static function manualSync()
    {
        global $DB;

        $startTime = microtime(true);
        $syncStartTime = date('Y-m-d H:i:s');
        $result = [
            'success' => false,
            'message' => '',
            'stats' => []
        ];

        // Check if plugin is configured
        $config = EntraConfig::getConfig();
        if (!$config) {
            $result['message'] = __('Plugin is not configured', 'glpientrahierarchy');
            return $result;
        }

        // Initialize Graph API client
        $graphClient = new GraphApiClient(
            $config['client_id'],
            $config['client_secret'],
            $config['tenant_id']
        );

        // Test connection
        if (!$graphClient->testConnection()) {
            $result['message'] = __('Failed to connect to Microsoft Graph API', 'glpientrahierarchy');
            self::logSync('failed', 'Manual sync failed - connection error', 0, 0, 0, 0, 0);
            return $result;
        }

        // Get all users from Entra ID
        $entraUsers = $graphClient->getAllUsers();

        // Apply filters
        $filteredUsers = self::applyFilters($entraUsers, $config);

        $stats = [
            'total' => count($entraUsers),
            'filtered' => count($entraUsers) - count($filteredUsers),
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'deactivated' => 0,
            'deleted' => 0
        ];

        // Process each user
        foreach ($filteredUsers as $entraUser) {
            try {
                $processResult = self::processUser($entraUser, $graphClient, null);

                if ($processResult === 'created') {
                    $stats['created']++;
                } elseif ($processResult === 'updated') {
                    $stats['updated']++;
                }
                $stats['synced']++;

            } catch (\Exception $e) {
                $stats['failed']++;
                error_log('EntraSync manual sync error: ' . $e->getMessage());
            }
        }

        // Handle users deleted/deactivated from Entra ID
        $deletedUsersAction = $config['deleted_users_action'] ?? 'keep_active';
        $deletedStats = self::handleDisabledAndDeletedUsers($syncStartTime, $deletedUsersAction, null);
        $stats['deactivated'] = $deletedStats['deactivated'];
        $stats['deleted'] = $deletedStats['deleted'];

        // Update last sync time
        $DB->update(
            'glpi_plugin_entrahierarchy_configs',
            ['last_sync' => date('Y-m-d H:i:s')],
            ['id' => $config['id']]
        );

        $duration = round(microtime(true) - $startTime, 2);

        // Log to database
        self::logSync(
            'success',
            'Manual sync completed successfully',
            $stats['synced'],
            $stats['created'],
            $stats['updated'],
            $stats['failed'],
            $duration,
            $stats['deactivated'],
            $stats['deleted']
        );

        $result['success'] = true;
        $result['message'] = sprintf(
            __('Sync completed: %d synced (%d created, %d updated), %d failed, %d deactivated, %d deleted in %s seconds', 'glpientrahierarchy'),
            $stats['synced'],
            $stats['created'],
            $stats['updated'],
            $stats['failed'],
            $stats['deactivated'],
            $stats['deleted'],
            $duration
        );
        $result['stats'] = $stats;

        return $result;
    }

    /**
     * Apply filters to Entra users based on configuration
     *
     * @param array $users Array of Entra users
     * @param array $config Configuration with filter settings
     * @return array Filtered array of users
     */
    private static function applyFilters($users, $config)
    {
        $filtered = [];

        foreach ($users as $user) {
            // Filter: Account enabled
            if ($config['sync_filter_account_enabled']) {
                if (!isset($user['accountEnabled']) || !$user['accountEnabled']) {
                    continue;
                }
            }

            // Filter: User type (Member, Guest, etc.)
            if (!empty($config['sync_filter_user_type'])) {
                $userType = $user['userType'] ?? '';
                if (strcasecmp($userType, $config['sync_filter_user_type']) !== 0) {
                    continue;
                }
            }

            // Filter: Employee types (comma-separated list)
            if (!empty($config['sync_filter_employee_types'])) {
                $allowedTypes = array_map('trim', explode(',', $config['sync_filter_employee_types']));
                $userEmployeeType = $user['employeeType'] ?? '';

                if (empty($userEmployeeType) || !in_array($userEmployeeType, $allowedTypes)) {
                    continue;
                }
            }

            // Filter: Require job title
            if ($config['sync_filter_require_job_title']) {
                if (empty($user['jobTitle'])) {
                    continue;
                }
            }

            // Filter: Department (exact match or comma-separated list)
            if (!empty($config['sync_filter_department'])) {
                $allowedDepts = array_map('trim', explode(',', $config['sync_filter_department']));
                $userDept = $user['department'] ?? '';

                if (empty($userDept) || !in_array($userDept, $allowedDepts)) {
                    continue;
                }
            }

            // Filter: Company name (exact match or comma-separated list)
            if (!empty($config['sync_filter_company_name'])) {
                $allowedCompanies = array_map('trim', explode(',', $config['sync_filter_company_name']));
                $userCompany = $user['companyName'] ?? '';

                if (empty($userCompany) || !in_array($userCompany, $allowedCompanies)) {
                    continue;
                }
            }

            // User passed all filters
            $filtered[] = $user;
        }

        return $filtered;
    }

    /**
     * Handle users who are deactivated or deleted from Entra ID
     * Only affects users that have been synced from Entra (have mapping record)
     *
     * @param string $syncStartTime Timestamp when current sync started
     * @param string $action Action to take: 'keep_active', 'deactivate', or 'delete'
     * @param CronTask|null $task Optional cron task for logging
     * @return array Array with 'deactivated' and 'deleted' counts
     */
    private static function handleDisabledAndDeletedUsers($syncStartTime, $action, $task = null)
    {
        global $DB;

        $stats = [
            'deactivated' => 0,
            'deleted' => 0
        ];

        // If action is 'keep_active', do nothing
        if ($action === 'keep_active') {
            return $stats;
        }

        // Find mappings where last_sync is older than current sync
        // These are users that were in Entra before but are now missing (deleted from Entra)
        $orphanedMappings = $DB->request([
            'FROM' => 'glpi_plugin_entrahierarchy_usermaps',
            'WHERE' => [
                'OR' => [
                    ['last_sync' => ['<', $syncStartTime]],
                    ['last_sync' => null]
                ]
            ]
        ]);

        foreach ($orphanedMappings as $mapping) {
            $glpiUserId = $mapping['users_id'];
            $entraId = $mapping['entra_id'];
            $upn = $mapping['entra_upn'] ?? 'unknown';

            // Verify user exists in GLPI
            $user = new User();
            if (!$user->getFromDB($glpiUserId)) {
                // User already deleted from GLPI, just remove mapping
                $DB->delete('glpi_plugin_entrahierarchy_usermaps', ['id' => $mapping['id']]);
                continue;
            }

            if ($action === 'deactivate') {
                // Deactivate user in GLPI
                $user->update([
                    'id' => $glpiUserId,
                    'is_active' => 0
                ]);
                $stats['deactivated']++;

                if ($task) {
                    $task->log(sprintf(
                        __('Deactivated user (deleted from Entra): %s', 'glpientrahierarchy'),
                        $upn
                    ));
                }

            } elseif ($action === 'delete') {
                // Delete user from GLPI
                if ($user->delete(['id' => $glpiUserId], true)) {
                    // Also delete mapping
                    $DB->delete('glpi_plugin_entrahierarchy_usermaps', ['id' => $mapping['id']]);
                    $stats['deleted']++;

                    if ($task) {
                        $task->log(sprintf(
                            __('Deleted user (deleted from Entra): %s', 'glpientrahierarchy'),
                            $upn
                        ));
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Apply automatic mapping from Entra fields to GLPI entities
     *
     * @param int $glpiUserId GLPI user ID
     * @param array $entraUser Entra user data
     * @param array|false $config Plugin configuration
     * @return void
     */
    private static function applyAutoMapping($glpiUserId, $entraUser, $config)
    {
        global $DB;

        if (!$config) {
            return;
        }

        // Automap: department → group
        if (!empty($config['automap_department_to_group']) && !empty($entraUser['department'])) {
            $department = trim($entraUser['department']);

            // Find or create group
            $groupResult = $DB->request([
                'FROM' => 'glpi_groups',
                'WHERE' => ['name' => $department],
                'LIMIT' => 1
            ]);

            $groupId = null;
            if ($groupResult->count() > 0) {
                $groupId = $groupResult->current()['id'];
            } else {
                // Create new group
                $group = new \Group();
                $groupId = $group->add([
                    'name' => $department,
                    'comment' => 'Auto-created from Entra ID department field',
                    'is_recursive' => 1
                ]);
            }

            // Assign user to group if not already assigned
            if ($groupId) {
                $existingAssignment = $DB->request([
                    'FROM' => 'glpi_groups_users',
                    'WHERE' => [
                        'users_id' => $glpiUserId,
                        'groups_id' => $groupId
                    ],
                    'LIMIT' => 1
                ]);

                if ($existingAssignment->count() === 0) {
                    $groupUser = new \Group_User();
                    $groupUser->add([
                        'users_id' => $glpiUserId,
                        'groups_id' => $groupId,
                        'is_dynamic' => 1
                    ]);

                    error_log("EntraSync::applyAutoMapping() - Mapped user {$glpiUserId} to department group: {$department} (ID: {$groupId})");
                }
            }
        }

        // Automap: company → entity
        if (!empty($config['automap_company_to_entity']) && !empty($entraUser['companyName'])) {
            $companyName = trim($entraUser['companyName']);

            // Find matching entity by name
            $entityResult = $DB->request([
                'FROM' => 'glpi_entities',
                'WHERE' => ['name' => $companyName],
                'LIMIT' => 1
            ]);

            if ($entityResult->count() > 0) {
                $entityId = $entityResult->current()['id'];

                // Update user's entity in Profile_User
                $DB->update(
                    'glpi_profiles_users',
                    ['entities_id' => $entityId],
                    ['users_id' => $glpiUserId]
                );

                error_log("EntraSync::applyAutoMapping() - Mapped user {$glpiUserId} to company entity: {$companyName} (ID: {$entityId})");
            }
        }

        // Automap: office → location
        if (!empty($config['automap_office_to_location']) && !empty($entraUser['officeLocation'])) {
            $officeLocation = trim($entraUser['officeLocation']);

            // Find matching location by name
            $locationResult = $DB->request([
                'FROM' => 'glpi_locations',
                'WHERE' => ['name' => $officeLocation],
                'LIMIT' => 1
            ]);

            if ($locationResult->count() > 0) {
                $locationId = $locationResult->current()['id'];

                // Update user's location
                $user = new User();
                $user->update([
                    'id' => $glpiUserId,
                    'locations_id' => $locationId
                ]);

                error_log("EntraSync::applyAutoMapping() - Mapped user {$glpiUserId} to office location: {$officeLocation} (ID: {$locationId})");
            }
        }
    }

    /**
     * Get type name
     *
     * @param int $nb
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return __('Entra Hierarchy Sync', 'glpientrahierarchy');
    }
}
