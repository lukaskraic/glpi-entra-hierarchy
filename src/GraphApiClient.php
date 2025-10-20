<?php

namespace GlpiPlugin\EntraHierarchy;

/**
 * Microsoft Graph API Client
 */
class GraphApiClient
{
    private $clientId;
    private $clientSecret;
    private $tenantId;
    private $accessToken;
    private $tokenExpiry;

    const GRAPH_API_URL = 'https://graph.microsoft.com/v1.0';
    const LOGIN_URL = 'https://login.microsoftonline.com';

    /**
     * Constructor
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $tenantId
     */
    public function __construct($clientId, $clientSecret, $tenantId)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tenantId = $tenantId;
    }

    /**
     * Get access token using client credentials flow
     *
     * @return string|false
     */
    private function getAccessToken()
    {
        // Return cached token if still valid
        if ($this->accessToken && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }

        $url = self::LOGIN_URL . "/{$this->tenantId}/oauth2/v2.0/token";

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials'
        ];

        $ch = curl_init($url);
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
            error_log("CURL error getting access token: $curlError");
            return false;
        }

        if ($httpCode !== 200) {
            error_log("Failed to get access token. HTTP Code: $httpCode, Response: $response");
            return false;
        }

        $result = json_decode($response, true);

        if (isset($result['access_token'])) {
            $this->accessToken = $result['access_token'];
            $this->tokenExpiry = time() + ($result['expires_in'] ?? 3600) - 60; // 60s buffer
            return $this->accessToken;
        }

        return false;
    }

    /**
     * Make a GET request to Graph API
     *
     * @param string $endpoint
     * @return array|false
     */
    private function get($endpoint)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }

        $url = self::GRAPH_API_URL . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Graph API request failed. Endpoint: $endpoint, HTTP Code: $httpCode, Response: $response");
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Get all users from Entra ID with comprehensive data
     *
     * @return array
     */
    public function getAllUsers()
    {
        $users = [];
        // Request all available user properties
        $select = implode(',', [
            'id',
            'userPrincipalName',
            'displayName',
            'mail',
            'givenName',
            'surname',
            'jobTitle',
            'department',
            'companyName',
            'officeLocation',
            'mobilePhone',
            'businessPhones',
            'employeeId',
            'employeeType',
            'userType',
            'accountEnabled',
            'country',
            'city',
            'state',
            'streetAddress',
            'postalCode',
            'usageLocation',
            'preferredLanguage'
        ]);

        $endpoint = "/users?\$select={$select}";

        do {
            $result = $this->get($endpoint);

            if (!$result || !isset($result['value'])) {
                break;
            }

            $users = array_merge($users, $result['value']);

            // Handle pagination
            $endpoint = isset($result['@odata.nextLink'])
                ? str_replace(self::GRAPH_API_URL, '', $result['@odata.nextLink'])
                : null;

        } while ($endpoint);

        return $users;
    }

    /**
     * Get user's manager from Entra ID
     *
     * @param string $userId Entra ID user ID
     * @return array|null
     */
    public function getUserManager($userId)
    {
        $endpoint = "/users/{$userId}/manager?\$select=id,userPrincipalName,displayName,mail";
        $result = $this->get($endpoint);

        if (!$result) {
            return null;
        }

        return $result;
    }

    /**
     * Get user by ID
     *
     * @param string $userId
     * @return array|false
     */
    public function getUser($userId)
    {
        $endpoint = "/users/{$userId}?\$select=id,userPrincipalName,displayName,mail,givenName,surname,jobTitle,department";
        return $this->get($endpoint);
    }

    /**
     * Test connection to Microsoft Graph API
     *
     * @return bool
     */
    public function testConnection()
    {
        error_log("GraphApiClient::testConnection() - Starting connection test");
        error_log("GraphApiClient::testConnection() - Client ID: " . substr($this->clientId, 0, 8) . "...");
        error_log("GraphApiClient::testConnection() - Tenant ID: " . substr($this->tenantId, 0, 8) . "...");

        $token = $this->getAccessToken();
        if (!$token) {
            error_log("GraphApiClient::testConnection() - Failed to get access token");
            return false;
        }

        error_log("GraphApiClient::testConnection() - Access token obtained successfully");

        // Try to get users (requires Directory.Read.All which should be configured)
        // Using $top=1 to only get one user for testing
        $result = $this->get('/users?$top=1');

        if ($result === false) {
            error_log("GraphApiClient::testConnection() - Failed to get users");
            return false;
        }

        if (!isset($result['value']) || !is_array($result['value'])) {
            error_log("GraphApiClient::testConnection() - Invalid response format");
            return false;
        }

        error_log("GraphApiClient::testConnection() - Connection test successful, found " . count($result['value']) . " user(s)");
        return true;
    }
}
