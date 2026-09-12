const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ai-browser.php', 'utf8');

const oldCode1 = `        if (empty($task_id) || empty($response_text)) {
            error_log('[SSP Bridge] Missing task_id or response!');
            return new WP_REST_Response(['error' => 'Missing task_id or response'], 400);
        }`;

const newCode1 = `        if (empty($task_id) || (empty($response_text) && $status !== 'processing')) {
            error_log('[SSP Bridge] Missing task_id or response!');
            return new WP_REST_Response(['error' => 'Missing task_id or response'], 400);
        }`;

if (php.includes(oldCode1)) {
    php = php.replace(oldCode1, newCode1);
    fs.writeFileSync('includes/class-ssp-ai-browser.php', php);
    console.log('Patched bridge_receive_response empty check');
} else {
    console.log('Could not find empty check code');
}
