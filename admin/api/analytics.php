<?php
/**
 * Google Analytics 4 Data API - Real Analytics for Dubai Approval Services Admin
 * Uses Service Account authentication (no OAuth popup needed)
 *
 * SETUP:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a project (or select existing)
 * 3. Enable "Google Analytics Data API"
 * 4. Go to IAM & Admin > Service Accounts > Create Service Account
 * 5. Download the JSON key file
 * 6. Upload it as admin/data/ga-credentials.json
 * 7. In GA4 Admin > Property Access Management, add the service account email as Viewer
 * 8. Update GA_PROPERTY_ID below with your numeric GA4 Property ID
 *    (Find it in GA4 > Admin > Property Settings)
 *
 * Tracking ID: G-S1K2YER2RB
 */

header('Content-Type: application/json');
header('Cache-Control: private, max-age=300');

// GA4 Property ID - UPDATE THIS with your numeric property ID from GA4 Admin
// Find it at: GA4 > Admin > Property Settings > Property ID
define('GA_PROPERTY_ID', '524633543');

$credentials_file = dirname(__DIR__) . '/data/ga-credentials.json';
$action = isset($_GET['action']) ? $_GET['action'] : 'overview';

// Check if credentials exist
if (!file_exists($credentials_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'not_configured',
        'message' => 'Google Analytics service account not configured. Upload ga-credentials.json to admin/data/'
    ]);
    exit;
}

// Check if property ID is configured
if (GA_PROPERTY_ID === 'YOUR_PROPERTY_ID_HERE') {
    echo json_encode([
        'success' => false,
        'error' => 'not_configured',
        'message' => 'GA4 Property ID not set. Update GA_PROPERTY_ID in admin/api/analytics.php'
    ]);
    exit;
}

// Read credentials
$creds = json_decode(file_get_contents($credentials_file), true);
if (!$creds || !isset($creds['client_email']) || !isset($creds['private_key'])) {
    echo json_encode([
        'success' => false,
        'error' => 'invalid_credentials',
        'message' => 'Invalid service account JSON file'
    ]);
    exit;
}

// Get access token
$access_token = get_access_token($creds);
if (!$access_token) {
    echo json_encode([
        'success' => false,
        'error' => 'auth_failed',
        'message' => 'Failed to authenticate with Google. Check credentials and ensure Analytics Data API is enabled.'
    ]);
    exit;
}

// Handle actions
switch ($action) {
    case 'overview':
        $data = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'newUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate']
            ]
        ]);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'realtime':
        $data = ga_run_realtime_report($access_token, [
            'metrics' => [['name' => 'activeUsers']]
        ]);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'pages':
        $data = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
            'limit' => 10,
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]]
        ]);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'sources':
        $data = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'sessionSource']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
            'limit' => 10,
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]
        ]);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'countries':
        $data = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'country']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
            'limit' => 10,
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]
        ]);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'devices':
        $data = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'deviceCategory']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]
        ]);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'daily':
        $data = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date'], 'desc' => false]]
        ]);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'all':
        $overview = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'newUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate']
            ]
        ]);
        $realtime = ga_run_realtime_report($access_token, [
            'metrics' => [['name' => 'activeUsers']]
        ]);
        $pages = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
            'limit' => 10,
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]]
        ]);
        $sources = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'sessionSource']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
            'limit' => 10,
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]
        ]);
        $countries = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'country']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
            'limit' => 10,
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]
        ]);
        $devices = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'deviceCategory']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]
        ]);
        $daily = ga_run_report($access_token, [
            'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date'], 'desc' => false]]
        ]);
        echo json_encode([
            'success' => true,
            'overview' => $overview,
            'realtime' => $realtime,
            'pages' => $pages,
            'sources' => $sources,
            'countries' => $countries,
            'devices' => $devices,
            'daily' => $daily
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

// ============================================================
// GA4 API Functions
// ============================================================

function ga_run_report($token, $body) {
    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . GA_PROPERTY_ID . ':runReport';
    return ga_api_call($url, $token, $body);
}

function ga_run_realtime_report($token, $body) {
    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . GA_PROPERTY_ID . ':runRealtimeReport';
    return ga_api_call($url, $token, $body);
}

function ga_api_call($url, $token, $body) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return ['error' => 'API returned ' . $http_code, 'details' => json_decode($response, true)];
    }
    return json_decode($response, true);
}

// ============================================================
// Service Account JWT Authentication
// ============================================================

function get_access_token($creds) {
    $token_file = dirname(__DIR__) . '/data/.ga_token_cache';
    if (file_exists($token_file)) {
        $cached = json_decode(file_get_contents($token_file), true);
        if ($cached && isset($cached['access_token']) && isset($cached['expires_at'])) {
            if ($cached['expires_at'] > time() + 60) {
                return $cached['access_token'];
            }
        }
    }

    $now = time();
    $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'iss' => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ]));

    $signing_input = $header . '.' . $payload;
    $signature = '';
    $key = openssl_pkey_get_private($creds['private_key']);
    if (!$key) return null;
    openssl_sign($signing_input, $signature, $key, OPENSSL_ALGO_SHA256);
    $jwt = $signing_input . '.' . base64url_encode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]),
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!$data || !isset($data['access_token'])) return null;

    $data_dir = dirname(__DIR__) . '/data';
    if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
    file_put_contents($token_file, json_encode([
        'access_token' => $data['access_token'],
        'expires_at' => $now + ($data['expires_in'] ?? 3600)
    ]));

    return $data['access_token'];
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
?>
