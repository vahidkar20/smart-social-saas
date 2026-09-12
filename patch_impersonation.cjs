const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ai-browser.php', 'utf8');

const oldCode = `    public function handle_bridge_create_task() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        // If impersonating, use admin's ID for the task (so userscript can find it)
        $actual_user_id = get_current_user_id();
        $is_impersonating = ($actual_user_id !== $user_id) && current_user_can('manage_options');
        $task_owner_id = $is_impersonating ? $actual_user_id : $user_id;`;

const newCode = `    public function handle_bridge_create_task() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        $task_owner_id = $user_id;`;

if (php.includes('$is_impersonating = ($actual_user_id !== $user_id) && current_user_can(\'manage_options\');')) {
    php = php.replace(oldCode, newCode);
    fs.writeFileSync('includes/class-ssp-ai-browser.php', php);
    console.log('Removed impersonation override');
} else {
    console.log('Could not find impersonation code');
}
