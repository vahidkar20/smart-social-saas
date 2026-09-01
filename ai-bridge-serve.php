<?php
/**
 * Serves the AI Browser Bridge userscript with embedded token.
 * Called via: ai-bridge-serve.php?token=xxx&site=xxx
 */

// Load WordPress (walk up from plugin dir to find wp-load.php)
$wp_load = dirname(__FILE__);
$found = false;
for ($i = 0; $i < 10; $i++) {
    if (file_exists($wp_load . '/wp-load.php')) {
        require_once $wp_load . '/wp-load.php';
        $found = true;
        break;
    }
    $wp_load = dirname($wp_load);
}

if (!$found) {
    status_header(500);
    echo 'WordPress not found';
    exit;
}

// Prevent direct access without parameters
if (empty($_GET['token']) || empty($_GET['site'])) {
    status_header(400);
    echo 'Missing parameters';
    exit;
}

$token = sanitize_text_field($_GET['token']);
$site = esc_url_raw($_GET['site']);

// Read the base userscript
$script_path = plugin_dir_path(__FILE__) . 'assets/js/ai-bridge.user.js';
$script = @file_get_contents($script_path);

if ($script === false) {
    status_header(500);
    echo 'Script file not found at: ' . $script_path;
    exit;
}

// Inject token and site URL
$injected = str_replace(
    ["'{{SSP_BRIDGE_TOKEN}}'", "'{{SSP_WP_SITE_URL}}'"],
    ["'" . addslashes($token) . "'", "'" . addslashes($site) . "'"],
    $script
);

// Serve as JavaScript so userscript managers (SnapMonkey/Tampermonkey) can intercept it
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
echo $injected;
exit;
