const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ai-browser.php', 'utf8');

const oldCode1 = `        // Store result
        $result_data = [
            'task_id'       => $task_id,
            'response_text' => $response_text,
            'status'        => $status,
            'received_at'   => current_time('mysql'),
        ];
        set_transient("ssp_bridge_result_{$task_id}", $result_data, SSP_BROWSER_BRIDGE_TASK_TTL);

        // Update task status
        $task['status'] = 'completed';
        set_transient("ssp_bridge_task_{$task_id}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);

        // Cleanup active task index
        $this->cleanup_bridge_task($user_id, $task_id);`;

const newCode1 = `        if ($status === 'processing') {
            $task['status'] = 'processing';
            set_transient("ssp_bridge_task_{$task_id}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);
            return new WP_REST_Response(['status' => 'ok'], 200);
        }

        // Store result
        $result_data = [
            'task_id'       => $task_id,
            'response_text' => $response_text,
            'status'        => $status,
            'received_at'   => current_time('mysql'),
        ];
        set_transient("ssp_bridge_result_{$task_id}", $result_data, SSP_BROWSER_BRIDGE_TASK_TTL);

        // Update task status
        $task['status'] = 'completed';
        set_transient("ssp_bridge_task_{$task_id}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);

        // Cleanup active task index
        $this->cleanup_bridge_task($user_id, $task_id);`;

if (php.includes('// Store result')) {
    php = php.replace(oldCode1, newCode1);
    fs.writeFileSync('includes/class-ssp-ai-browser.php', php);
    console.log('Patched bridge_receive_response');
} else {
    console.log('Could not find // Store result code');
}
