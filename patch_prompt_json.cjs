const fs = require('fs');

let phpBrowser = fs.readFileSync('includes/class-ssp-ai-browser.php', 'utf8');

const regexCreateTask = /private function create_bridge_task\(\$user_id, \$prompt, \$context = \[\]\) \{([\s\S]*?)return \$task_data;\n    \}/g;
const match = regexCreateTask.exec(phpBrowser);

if (match) {
    const oldCode = match[0];
    const newCode = `private function create_bridge_task($user_id, $prompt, $context = []) {
        $task_id = 'br_' . wp_generate_password(16, false);

        // Ensure prompt is properly formatted for browser delivery.
        // It should just be the raw text prompt, let the browser handle JSON encoding in the fetch request,
        // but we need to make sure the PHP output is a clean string.
        // If it's an array, encode it nicely. If it's a string, leave it.
        $clean_prompt = is_array($prompt) ? json_encode($prompt, JSON_UNESCAPED_UNICODE) : (string) $prompt;

        $task_data = [
            'task_id'    => $task_id,
            'user_id'    => $user_id,
            'prompt'     => $clean_prompt,
            'context'    => $context,
            'status'     => 'pending',
            'created_at' => current_time('mysql'),
        ];

        set_transient("ssp_bridge_task_{$task_id}", $task_data, SSP_BROWSER_BRIDGE_TASK_TTL);

        // Maintain index of active tasks per user
        $active_ids = is_array($__tmp = get_user_meta($user_id, 'ssp_bridge_active_tasks', true)) ? $__tmp : [];
        $active_ids[] = $task_id;
        update_user_meta($user_id, 'ssp_bridge_active_tasks', $active_ids);

        return $task_data;
    }`;
    
    phpBrowser = phpBrowser.replace(oldCode, newCode);
    fs.writeFileSync('includes/class-ssp-ai-browser.php', phpBrowser);
    console.log("Patched create_bridge_task to ensure clean prompt");
} else {
    console.log("Could not find create_bridge_task");
}

let phpProduct = fs.readFileSync('includes/class-ssp-ajax-product.php', 'utf8');
if (phpProduct.includes("create_bridge_task")) {
    console.log("Found create_bridge_task calls in product ajax");
    // Just verifying they exist.
}

