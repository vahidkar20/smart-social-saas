const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ai-browser.php', 'utf8');

const oldCode = `    public function bridge_get_pending_task($request) {
        $token = $request->get_header('X-SSP-Bridge-Token') ?: $request->get_header('x-ssp-bridge-token') ?: ($_SERVER['HTTP_X_SSP_BRIDGE_TOKEN'] ?? '') ?: $request->get_param('token');
        $user_id = $this->validate_bridge_token($token);
        if (!$user_id) {
            return new WP_REST_Response(['error' => 'Invalid token'], 401);
        }

        $active_ids = is_array($__tmp = get_user_meta($user_id, 'ssp_bridge_active_tasks', true)) ? $__tmp : [];
        $found = null;

        foreach ($active_ids as $tid) {
            $task = get_transient("ssp_bridge_task_{$tid}");
            if ($task && $task['status'] === 'pending') {
                $found = $task;
                // Mark as 'sent' to prevent duplicate delivery
                $task['status'] = 'sent';
                set_transient("ssp_bridge_task_{$tid}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);
                break;
            }
        }`;

const newCode = `    public function bridge_get_pending_task($request) {
        $token = $request->get_header('X-SSP-Bridge-Token') ?: $request->get_header('x-ssp-bridge-token') ?: ($_SERVER['HTTP_X_SSP_BRIDGE_TOKEN'] ?? '') ?: $request->get_param('token');
        $user_id = $this->validate_bridge_token($token);
        if (!$user_id) {
            error_log('[SSP Bridge Pending] Invalid token: ' . $token);
            return new WP_REST_Response(['error' => 'Invalid token'], 401);
        }

        $active_ids = is_array($__tmp = get_user_meta($user_id, 'ssp_bridge_active_tasks', true)) ? $__tmp : [];
        error_log('[SSP Bridge Pending] User: ' . $user_id . ' Active tasks count: ' . count($active_ids) . ' token: ' . substr($token, 0, 15) . '...');
        $found = null;

        foreach ($active_ids as $tid) {
            $task = get_transient("ssp_bridge_task_{$tid}");
            if ($task) {
                error_log('[SSP Bridge Pending] Task ' . $tid . ' status: ' . $task['status']);
                if ($task['status'] === 'pending') {
                    $found = $task;
                    // Mark as 'sent' to prevent duplicate delivery
                    $task['status'] = 'sent';
                    set_transient("ssp_bridge_task_{$tid}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);
                    break;
                }
            } else {
                error_log('[SSP Bridge Pending] Task ' . $tid . ' not found in transient');
            }
        }`;

if (php.includes('public function bridge_get_pending_task($request) {')) {
    php = php.replace(oldCode, newCode);
    fs.writeFileSync('includes/class-ssp-ai-browser.php', php);
    console.log('Patched bridge_get_pending_task');
} else {
    console.log('Could not find bridge_get_pending_task code');
}
