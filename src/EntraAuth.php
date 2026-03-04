<?php

namespace GlpiPlugin\EntraHierarchy;

use Session;

/**
 * Entra ID OAuth 2.0 / OpenID Connect Authentication
 *
 * Handles user authentication via Microsoft Entra ID using:
 * - OAuth 2.0 Authorization Code Flow with PKCE
 * - OpenID Connect ID Token validation
 * - Microsoft Graph API integration
 */
class EntraAuth
{
    const LOGIN_URL = 'https://login.microsoftonline.com';
    const GRAPH_API_URL = 'https://graph.microsoft.com/v1.0';

    /**
     * Generate PKCE code verifier (43-128 characters)
     *
     * @return string
     */
    private static function generateCodeVerifier()
    {
        return bin2hex(random_bytes(32)); // 64 characters
    }

    /**
     * Generate PKCE code challenge from verifier
     *
     * @param string $codeVerifier
     * @return string
     */
    private static function generateCodeChallenge($codeVerifier)
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Generate cryptographically secure random state parameter
     *
     * @return string
     */
    private static function generateState()
    {
        return bin2hex(random_bytes(16)); // 32 characters
    }

    /**
     * Get authorization URL for OAuth 2.0 flow
     *
     * Implements PKCE (Proof Key for Code Exchange) for enhanced security.
     * Stores code_verifier and state in session for later validation.
     *
     * @param array $config Configuration from EntraConfig
     * @return string Authorization URL
     */
    public static function getAuthorizationUrl($config)
    {
        // Generate PKCE parameters
        $codeVerifier = self::generateCodeVerifier();
        $codeChallenge = self::generateCodeChallenge($codeVerifier);
        $state = self::generateState();

        // Store in session for callback validation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['oauth_code_verifier'] = $codeVerifier;
        $_SESSION['oauth_state'] = $state;

        // Use OAuth-specific credentials, fallback to shared credentials
        // Use empty() to handle both NULL and empty string values
        $clientId = !empty($config['oauth_client_id']) ? $config['oauth_client_id'] : ($config['client_id'] ?? null);
        $tenantId = !empty($config['oauth_tenant_id']) ? $config['oauth_tenant_id'] : ($config['tenant_id'] ?? null);

        // Build authorization URL
        $authUrl = self::LOGIN_URL . "/{$tenantId}/oauth2/v2.0/authorize";

        $params = [
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $config['oauth_redirect_uri'],
            'scope' => 'openid profile email User.Read',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'response_mode' => 'query'
        ];

        error_log("EntraAuth::getAuthorizationUrl() - Generated authorization URL");
        error_log("EntraAuth::getAuthorizationUrl() - Client ID: " . substr($clientId, 0, 8) . "...");
        error_log("EntraAuth::getAuthorizationUrl() - State: " . substr($state, 0, 8) . "...");
        error_log("EntraAuth::getAuthorizationUrl() - Code challenge: " . substr($codeChallenge, 0, 12) . "...");

        return $authUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     *
     * Completes PKCE flow by sending code_verifier.
     * Returns access_token, id_token, and optionally refresh_token.
     *
     * @param array $config Configuration from EntraConfig
     * @param string $authorizationCode Authorization code from callback
     * @param string $codeVerifier PKCE code verifier from session
     * @return array|false Array with tokens or false on failure
     */
    public static function exchangeCodeForTokens($config, $authorizationCode, $codeVerifier)
    {
        // Use OAuth-specific credentials, fallback to shared credentials
        // Use empty() to handle both NULL and empty string values
        $clientId = !empty($config['oauth_client_id']) ? $config['oauth_client_id'] : ($config['client_id'] ?? null);
        $tenantId = !empty($config['oauth_tenant_id']) ? $config['oauth_tenant_id'] : ($config['tenant_id'] ?? null);
        $clientSecret = !empty($config['oauth_client_secret']) ? $config['oauth_client_secret'] : ($config['client_secret'] ?? null);

        $tokenUrl = self::LOGIN_URL . "/{$tenantId}/oauth2/v2.0/token";

        $data = [
            'client_id' => $clientId,
            'code' => $authorizationCode,
            'redirect_uri' => $config['oauth_redirect_uri'],
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier
        ];

        // Add client_secret if available (for confidential clients)
        if (!empty($clientSecret)) {
            $data['client_secret'] = $clientSecret;
        }

        error_log("EntraAuth::exchangeCodeForTokens() - Exchanging authorization code");
        error_log("EntraAuth::exchangeCodeForTokens() - Client ID: " . substr($clientId, 0, 8) . "...");
        error_log("EntraAuth::exchangeCodeForTokens() - Code: " . substr($authorizationCode, 0, 10) . "...");

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            error_log("EntraAuth::exchangeCodeForTokens() - CURL error: {$curlError}");
            return false;
        }

        if ($httpCode !== 200) {
            error_log("EntraAuth::exchangeCodeForTokens() - HTTP {$httpCode} error");
            error_log("EntraAuth::exchangeCodeForTokens() - Response: {$response}");
            return false;
        }

        $result = json_decode($response, true);

        if (!isset($result['access_token']) || !isset($result['id_token'])) {
            error_log("EntraAuth::exchangeCodeForTokens() - Missing tokens in response");
            return false;
        }

        error_log("EntraAuth::exchangeCodeForTokens() - Tokens obtained successfully");

        return [
            'access_token' => $result['access_token'],
            'id_token' => $result['id_token'],
            'refresh_token' => $result['refresh_token'] ?? null,
            'expires_in' => $result['expires_in'] ?? 3600
        ];
    }

    /**
     * Validate ID Token (JWT) using JWKS from Entra ID
     *
     * Downloads public keys from Entra ID JWKS endpoint and validates:
     * - JWT signature (RS256 algorithm)
     * - Issuer (iss claim)
     * - Audience (aud claim)
     * - Expiration (exp claim)
     * - Tenant ID (tid claim)
     *
     * @param string $idToken ID Token (JWT)
     * @param array $config Configuration from EntraConfig
     * @return \stdClass|false Decoded token payload or false on failure
     */
    public static function validateIdToken($idToken, $config)
    {
        // Check if firebase/php-jwt library is available
        if (!class_exists('\Firebase\JWT\JWT')) {
            error_log("EntraAuth::validateIdToken() - firebase/php-jwt library not found. Run: composer require firebase/php-jwt");
            return false;
        }

        // Use OAuth-specific credentials, fallback to shared credentials
        // Use empty() to handle both NULL and empty string values
        $clientId = !empty($config['oauth_client_id']) ? $config['oauth_client_id'] : ($config['client_id'] ?? null);
        $tenantId = !empty($config['oauth_tenant_id']) ? $config['oauth_tenant_id'] : ($config['tenant_id'] ?? null);

        $jwksUrl = self::LOGIN_URL . "/{$tenantId}/discovery/v2.0/keys";

        error_log("EntraAuth::validateIdToken() - Fetching JWKS keys");
        error_log("EntraAuth::validateIdToken() - JWKS URL: {$jwksUrl}");

        // Fetch JWKS keys
        $ch = curl_init($jwksUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $jwksJson = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            error_log("EntraAuth::validateIdToken() - Failed to fetch JWKS: HTTP {$httpCode}, {$curlError}");
            return false;
        }

        $jwks = json_decode($jwksJson, true);
        if (!isset($jwks['keys'])) {
            error_log("EntraAuth::validateIdToken() - Invalid JWKS format");
            return false;
        }

        // FIX: Add "alg" parameter to JWKS keys if missing
        // Microsoft Entra ID keys don't always include "alg", but they use RS256 for ID tokens
        foreach ($jwks['keys'] as &$key) {
            if (!isset($key['alg'])) {
                $key['alg'] = 'RS256';
                error_log("EntraAuth::validateIdToken() - Added missing 'alg' parameter (RS256) to JWKS key");
            }
        }
        unset($key); // Break reference

        try {
            // Allow 60 seconds clock skew
            \Firebase\JWT\JWT::$leeway = 60;

            // Parse JWKS and decode JWT
            $keys = \Firebase\JWT\JWK::parseKeySet($jwks);
            $decoded = \Firebase\JWT\JWT::decode($idToken, $keys);

            error_log("EntraAuth::validateIdToken() - JWT decoded successfully");

            // Validate issuer
            $expectedIssuer = self::LOGIN_URL . "/{$tenantId}/v2.0";
            if ($decoded->iss !== $expectedIssuer) {
                error_log("EntraAuth::validateIdToken() - Invalid issuer: {$decoded->iss}");
                return false;
            }

            // Validate audience
            if ($decoded->aud !== $clientId) {
                error_log("EntraAuth::validateIdToken() - Invalid audience: {$decoded->aud}");
                return false;
            }

            // Validate tenant ID
            if ($decoded->tid !== $tenantId) {
                error_log("EntraAuth::validateIdToken() - Invalid tenant ID: {$decoded->tid}");
                return false;
            }

            // Expiration is automatically validated by JWT library
            error_log("EntraAuth::validateIdToken() - All claims validated successfully");
            error_log("EntraAuth::validateIdToken() - User email: {$decoded->email}");

            return $decoded;

        } catch (\Exception $e) {
            error_log("EntraAuth::validateIdToken() - JWT validation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user information from Microsoft Graph API
     *
     * Optional method - ID token already contains email and name.
     * Use this if you need additional user profile data.
     *
     * @param string $accessToken Access token
     * @return array|false User profile data or false on failure
     */
    public static function getUserInfo($accessToken)
    {
        $url = self::GRAPH_API_URL . '/me';

        error_log("EntraAuth::getUserInfo() - Fetching user profile from Graph API");

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            error_log("EntraAuth::getUserInfo() - Failed to fetch user info: HTTP {$httpCode}, {$curlError}");
            return false;
        }

        $userInfo = json_decode($response, true);

        if (!$userInfo) {
            error_log("EntraAuth::getUserInfo() - Invalid JSON response");
            return false;
        }

        error_log("EntraAuth::getUserInfo() - User info retrieved: " . ($userInfo['userPrincipalName'] ?? 'unknown'));

        return $userInfo;
    }

    /**
     * Find GLPI user by email address
     *
     * Searches in glpi_useremails table for matching email.
     * Only returns users that were previously synced from Entra ID.
     *
     * @param string $email User email address
     * @return int|null GLPI user ID or null if not found
     */
    public static function findGlpiUser($email)
    {
        global $DB;

        error_log("EntraAuth::findGlpiUser() - Searching for email: {$email}");

        // Search in glpi_useremails table
        $result = $DB->request([
            'SELECT' => ['users_id'],
            'FROM' => 'glpi_useremails',
            'WHERE' => [
                'email' => $email
            ],
            'LIMIT' => 1
        ]);

        if ($result->count() > 0) {
            $row = $result->current();
            $userId = $row['users_id'];

            error_log("EntraAuth::findGlpiUser() - User found: ID {$userId}");

            // Verify user is active
            $userResult = $DB->request([
                'SELECT' => ['is_active', 'is_deleted'],
                'FROM' => 'glpi_users',
                'WHERE' => [
                    'id' => $userId
                ],
                'LIMIT' => 1
            ]);

            if ($userResult->count() > 0) {
                $user = $userResult->current();
                if ($user['is_active'] && !$user['is_deleted']) {
                    return $userId;
                } else {
                    error_log("EntraAuth::findGlpiUser() - User found but inactive or deleted");
                    return null;
                }
            }
        }

        error_log("EntraAuth::findGlpiUser() - User not found");
        return null;
    }

    /**
     * Create GLPI session for authenticated user
     *
     * Initializes GLPI session using internal authentication mechanism.
     * This method bypasses standard login but user must exist in database.
     *
     * @param int $userId GLPI user ID
     * @return bool Success status
     */
    public static function createGlpiSession($userId)
    {
        global $DB;

        error_log("EntraAuth::createGlpiSession() - Creating session for user ID: {$userId}");

        // Get user details
        $userResult = $DB->request([
            'SELECT' => ['name', 'password', 'authtype'],
            'FROM' => 'glpi_users',
            'WHERE' => [
                'id' => $userId,
                'is_active' => 1,
                'is_deleted' => 0
            ],
            'LIMIT' => 1
        ]);

        if ($userResult->count() === 0) {
            error_log("EntraAuth::createGlpiSession() - User not found or inactive");
            return false;
        }

        $user = $userResult->current();

        // Use GLPI's Auth class to create proper session
        // Session::init() handles: destroy old session, regenerate ID,
        // populate $_SESSION, load profiles and entities.
        $auth = new \Auth();

        $auth->auth_succeded = true;
        $auth->extauth = 1;
        $auth->user = new \User();
        $auth->user->getFromDB($userId);

        Session::init($auth);

        // Session::init() may set auth_succeded to false if user has no profile/interface
        if (!$auth->auth_succeded) {
            error_log("EntraAuth::createGlpiSession() - Session::init() rejected auth for: {$user['name']}");
            return false;
        }

        error_log("EntraAuth::createGlpiSession() - Session created for: {$user['name']}, glpiID=" . ($_SESSION['glpiID'] ?? 'NOT SET'));

        return true;
    }

    /**
     * Destroy OAuth session data
     *
     * Clears code_verifier and state from session.
     * Call this after successful authentication or on error.
     */
    public static function clearOAuthSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['oauth_code_verifier']);
        unset($_SESSION['oauth_state']);

        error_log("EntraAuth::clearOAuthSession() - OAuth session data cleared");
    }
}
