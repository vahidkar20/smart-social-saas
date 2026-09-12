<?php
/**
 * AI Browser Bridge Standalone Endpoint
 * Serves userscript with token injection on GET, handles responses on POST.
 */
if (!defined('ABSPATH')) {
    // Bootstrap WordPress if accessed standalone
    $wp_load_path = dirname(__FILE__, 4) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    }
}

// Handle GET: Serve the userscript with token & site injected
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = sanitize_text_field($_GET['token'] ?? '');
    $site  = esc_url_raw($_GET['site'] ?? '');

    if (empty($site) && function_exists('home_url')) {
        $site = home_url();
    }

    $script_path = __DIR__ . '/assets/js/ai-bridge.user.js';
    if (!file_exists($script_path)) {
        header('HTTP/1.1 404 Not Found');
        echo '// Userscript file not found';
        exit;
    }

    $script_content = file_get_contents($script_path);

    // Inject token and site
    if (!empty($token)) {
        $script_content = str_replace('{{SSP_BRIDGE_TOKEN}}', $token, $script_content);
    }
    if (!empty($site)) {
        $script_content = str_replace('{{SSP_WP_SITE_URL}}', rtrim($site, '/'), $script_content);
    }

    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Access-Control-Allow-Origin: *');
    header('Content-Disposition: inline; filename="ai-bridge.user.js"');
    echo $script_content;
    exit;
}

// Handle POST: Receive task response fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true) ?: $_POST;

    $task_id = sanitize_text_field($data['task_id'] ?? '');
    $content = wp_unslash($data['response_text'] ?? $data['response'] ?? $data['content'] ?? '');
    $status  = sanitize_text_field($data['status'] ?? 'completed');

    if ($task_id && $content && function_exists('set_transient')) {
        set_transient("ssp_bridge_result_{$task_id}", [
            'task_id'       => $task_id,
            'response_text' => $content,
            'status'        => $status,
            'received_at'   => current_time('mysql'),
        ], 600);

        if (function_exists('update_option')) {
            update_option('ssp_bridge_task_' . $task_id, $content, false);
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(['success' => true, 'status' => 'ok']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
