<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 *///---------- SSO Service Provider Client Side ---

//********** [USER EDITABLE] *******************************
require_once __DIR__ . '/includes/sso-config.php';
require_once __DIR__ . '/includes/sso-flow.php';

$__ssoConfig = sso_shared_config();
$site_id = $__ssoConfig['site_id']; //<---- Get from SSO Admin
$SSO_IDP_DOMAIN = $__ssoConfig['idp_domain']; //<---- URL for SSO Servers (override via env SSO_IDP_DOMAIN)

// Auto-detect current site origin (scheme + host + optional subfolder), proxy aware.
$detect_scheme = (function (): string {
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $candidate = trim(explode(',', (string)$forwardedProto)[0]);
    if ($candidate === 'https' || $candidate === 'http') {
        return $candidate;
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }
    return 'http';
})();
$detect_host = (function (): string {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return trim(explode(',', (string)$host)[0]);
})();
$base_path = (function (): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    // Drop trailing /pages or /ajax to anchor at app root
    $dir = preg_replace('#/(pages|ajax)(/.*)?$#', '', $dir);
    return ($dir === '/' ? '' : $dir);
})();
$origin = $detect_scheme . '://' . $detect_host;
$project_root = rtrim($origin . $base_path, '/');

// SP endpoints (no hardcoded domain; works on root or subfolder)
// OneID callback for this app returns to sso_sp_client.php, so keep the vendor
// login page endpoint aligned with the actual callback receiver.
$SSO_SP_LOGINPAGE = $project_root . '/sso_sp_client.php';  //<---- SSO callback receiver URL
$SSO_SP_DASHBOARD = $project_root . '/login.php';  //<----- local completion page after token validation

//echo json_encode(LOCAL_COOKIES_HANDLER());
//return;
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

function SSO_CORRELATION_ID(): string {
	$current = trim((string)($_SESSION['sso_correlation_id'] ?? ''));
	if ($current !== '') return $current;
	$current = bin2hex(random_bytes(8));
	$_SESSION['sso_correlation_id'] = $current;
	return $current;
}

function SSO_LOG_EVENT(string $event, array $context = []): void {
	$context['correlation_id'] = SSO_CORRELATION_ID();
	$context['event'] = $event;
	$context['time'] = date('c');
	error_log('[OneID SSO] ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function SSO_CLEAR_COOKIE(): void {
	$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
		|| strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https';
	setcookie('sso_cre', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
	unset($_COOKIE['sso_cre']);
}

function SSO_SET_FAILURE_ALERT(string $reason): void {
	$map = [
		'invalid_token' => ['login_sso_token_invalid_title', 'login_sso_token_invalid_msg'],
		'invalid_site' => ['login_sso_site_invalid_title', 'login_sso_site_invalid_msg'],
		'service_unreachable' => ['login_sso_service_unreachable_title', 'login_sso_service_unreachable_msg'],
		'invalid_response' => ['login_sso_response_invalid_title', 'login_sso_response_invalid_msg'],
		'identity_invalid' => ['login_sso_payload_invalid_title', 'login_sso_payload_invalid_msg'],
	];
	[$title, $text] = $map[$reason] ?? $map['invalid_response'];
	$_SESSION['alert'] = ['type' => 'sweet', 'title' => $title, 'text' => $text, 'icon' => 'warning', 'confirm' => true, 'close_on_confirm' => true, 'is_key' => true];
	$_SESSION['sso_last_failure_reference'] = SSO_CORRELATION_ID();
}

// If your site are using PHP Sessions keys $_SESSION.
// This no longer finalizes a local app login; it only stores SSO handoff state.
function LOCAL_SESSION_HANDLER($IDP_RESPOND_USER_PACKET){
	$handoff = SSO_BUILD_AUTH_HANDOFF($IDP_RESPOND_USER_PACKET);
	$_SESSION['user_name'] = $handoff['resolved_login_id'] ?? '';
	$_SESSION['sso_auth_handoff'] = [
		'valid_token' => true,
		'resolved_login_id' => $handoff['resolved_login_id'],
		'resolved_source' => $handoff['resolved_source'],
		'data3_valid' => $handoff['data3_valid'],
		'data4_valid' => $handoff['data4_valid'],
		'identity_valid' => $handoff['identity_valid'],
		'identity_conflict' => $handoff['identity_conflict'],
		'identity_resolution' => $handoff['identity_resolution'],
		'oneid_user_category' => $handoff['oneid_user_category'],
		'oneid_user_type' => $handoff['oneid_user_type'],
		'issued_at' => time(),
		'expires_at' => time() + 300,
		'nonce' => bin2hex(random_bytes(16)),
		'correlation_id' => SSO_CORRELATION_ID(),
		'consumed_at' => null,
	];
}
//If your site are using Cookies.
// call this functions anywhere and to get the data from cookies, use LOCAL_COOKIES_HANDLER()->data1
function LOCAL_COOKIES_HANDLER(){
	if(isset($_COOKIE['sso_cre'])) {
		return json_decode($_COOKIE["sso_cre"]);
	}
}
//********* [END OF USER EDITABLE] *************************











//Do not Edit Below this line -------










//Thank you for not editing below this line




//----------------- FOR Debugging purposes. REMOVE BEFORE PRODUCTION
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
//----------------- END OF DEBUGGING

define('SSO_IDP_DOMAIN', str_replace(':/','://', trim(preg_replace('/\/+/', '/', $SSO_IDP_DOMAIN), '/')));
define('SSO_SP_LOGINPAGE', str_replace(':/','://', trim(preg_replace('/\/+/', '/', $SSO_SP_LOGINPAGE), '/')));
define('SSO_SP_DASHBOARD', str_replace(':/','://', trim(preg_replace('/\/+/', '/', $SSO_SP_DASHBOARD), '/')));
date_default_timezone_set("Asia/Kuala_Lumpur");
$SP_current_page = GET_CURRENT_PAGE_URI();
function SSO_REDIRECT($url): void {
	header('Location: ' . $url);
	exit;
}
function SSO_FAIL(string $reason): void {
	$count = max(0, (int)($_SESSION['sso_failure_count'] ?? 0)) + 1;
	$_SESSION['sso_failure_count'] = $count;
	unset($_SESSION['sso_auth_handoff'], $_SESSION['sso_login_initiated_at']);
	SSO_CLEAR_COOKIE();
	SSO_SET_FAILURE_ALERT($reason);
	SSO_LOG_EVENT('failure', ['reason' => $reason, 'failure_count' => $count]);
	$loginPage = preg_replace('~/login\.php$~', '/index.php', SSO_SP_DASHBOARD) ?: SSO_SP_DASHBOARD;
	SSO_REDIRECT($loginPage);
}
function SSO_VERIFY_TOKEN(string $token, string $siteId): array {
	$payload = json_encode(['flag' => 1, 'data' => ['site_id' => $siteId, 'token' => $token]]);
	$apiResult = SSO_API_REQUEST((string)$payload, SSO_IDP_DOMAIN);
	if (!$apiResult['ok']) {
		SSO_LOG_EVENT('api_unreachable', ['http_code' => $apiResult['http_code'], 'error' => $apiResult['error']]);
		return ['status' => 'service_unreachable'];
	}
	$decoded = json_decode((string)$apiResult['body'], true);
	if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
		SSO_LOG_EVENT('api_invalid_json', ['http_code' => $apiResult['http_code']]);
		return ['status' => 'invalid_response'];
	}
	return SSO_CLASSIFY_API_RESPONSE($decoded);
}
function SSO_COMPLETE_VERIFIED_TOKEN(string $token, array $result): void {
	$packet = is_array($result['packet'] ?? null) ? $result['packet'] : [];
	$handoff = SSO_BUILD_AUTH_HANDOFF($packet);
	if (empty($handoff['identity_valid']) || !empty($handoff['identity_conflict'])) {
		SSO_FAIL('identity_invalid');
	}
	COOKIE_SETTER($token, $packet);
	LOCAL_SESSION_HANDLER($packet);
	$_SESSION['sso_failure_count'] = 0;
	unset($_SESSION['sso_login_initiated_at']);
	SSO_LOG_EVENT('token_verified', ['identity_source' => $handoff['resolved_source'], 'reissued' => !empty($result['reissued'])]);
	SSO_REDIRECT(SSO_SP_DASHBOARD);
}
if (!defined('SSO_SP_CLIENT_NOAUTO')) {
	$incomingToken = trim((string)($_GET['new_sso_cre'] ?? ''));
	if ($incomingToken !== '') {
		// A fresh callback must always win over stale browser state.
		SSO_CLEAR_COOKIE();
		$result = SSO_VERIFY_TOKEN($incomingToken, (string)$site_id);
		if (($result['status'] ?? '') !== 'valid') SSO_FAIL((string)($result['status'] ?? 'invalid_response'));
		$verifiedToken = !empty($result['reissued']) ? (string)($result['token'] ?? '') : $incomingToken;
		SSO_COMPLETE_VERIFIED_TOKEN($verifiedToken, $result);
	}

	$cookie = isset($_COOKIE['sso_cre']) ? json_decode((string)$_COOKIE['sso_cre'], true) : null;
	$cookieToken = is_array($cookie) ? trim((string)($cookie['sso_cre'] ?? '')) : '';
	if ($cookieToken !== '') {
		$result = SSO_VERIFY_TOKEN($cookieToken, (string)$site_id);
		if (($result['status'] ?? '') !== 'valid') SSO_FAIL((string)($result['status'] ?? 'invalid_response'));
		$verifiedToken = !empty($result['reissued']) ? (string)($result['token'] ?? '') : $cookieToken;
		SSO_COMPLETE_VERIFIED_TOKEN($verifiedToken, $result);
	}

	SSO_CLEAR_COOKIE();
	$_SESSION['sso_login_initiated_at'] = time();
	$_SESSION['sso_correlation_id'] = bin2hex(random_bytes(8));
	SSO_LOG_EVENT('login_initiated');
	SSO_REDIRECT(SSO_IDP_DOMAIN . '/?site_id=' . rawurlencode((string)$site_id));
}

function SSO_API_REQUEST($API_DATA, $SSO_IDP_DOMAIN): array {
	    $API_URII = SSO_IDP_DOMAIN.'/api.php';
	    if (!function_exists('curl_init')) return ['ok' => false, 'body' => '', 'error' => 'curl_extension_unavailable', 'http_code' => 0];
	    $ch = curl_init();
	    if ($ch === false) return ['ok' => false, 'body' => '', 'error' => 'curl_init_failed', 'http_code' => 0];
	    curl_setopt($ch, CURLOPT_URL, $API_URII);
	    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
	    curl_setopt($ch,CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: text/plain'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($API_DATA));

	    $result = curl_exec($ch);
	    $error = curl_error($ch);
	    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	    curl_close($ch);
	    $ok = is_string($result) && $result !== '' && $httpCode >= 200 && $httpCode < 300;
	    return ['ok' => $ok, 'body' => is_string($result) ? $result : '', 'error' => $error, 'http_code' => $httpCode];
}

function API_REQUEST($API_DATA,$SSO_IDP_DOMAIN){
	$result = SSO_API_REQUEST($API_DATA, $SSO_IDP_DOMAIN);
	return $result['body'];
}
//--------- END OF SSO Checker

function COOKIE_SETTER($sso_cre,$respond_user_packet){
		$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
			|| strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https';
		// The browser only needs the opaque credential for the vendor refresh flow.
		$cookieData = ["sso_dt" => date('Y-m-d H:i:s'), "sso_cre" => (string)$sso_cre];
		setcookie('sso_cre', json_encode($cookieData), [
			'expires' => time() + 3600,
			'path' => '/',
			'secure' => $isHttps,
			'httponly' => true,
			'samesite' => 'Lax',
		]);
}

function GET_CURRENT_PAGE_URI(){
	$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
	$protoCandidate = trim(explode(',', (string)$forwardedProto)[0]);
	if ($protoCandidate === 'https' || $protoCandidate === 'http') {
		$protocol = $protoCandidate . '://';
	} else {
		$serverPort = (int)($_SERVER['SERVER_PORT'] ?? 80);
		$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $serverPort === 443) ? "https://" : "http://";
	}
	$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
	$host = trim(explode(',', (string)$host)[0]);
	$uri = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
	return rtrim($protocol . $host . '/' . ltrim((string)$uri, '/'), '/');
}

function SSO_LOGOUT(){
	SSO_REDIRECT(SSO_IDP_DOMAIN);
}


function check_cookie_time($time) {
    // Creating DateTime Objects
	$dateTimeObject1 = date_create($time); 
	$dateTimeObject2 = date_create(date('Y-m-d H:i:s')); 
	    
	// Calculating the difference between DateTime Objects
	$interval = date_diff($dateTimeObject1, $dateTimeObject2); 
	$min = $interval->days * 24 * 60;
	$min += $interval->h * 60;
	$min += $interval->i;
	$check_result = 0; //0- no refresh require, 1- require refresh;
	if($min >1){
		return 1;
	}else{
		return 0;
	}
}

?>
