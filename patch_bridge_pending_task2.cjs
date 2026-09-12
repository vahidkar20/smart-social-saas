const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ai-browser.php', 'utf8');

const oldCode = `        foreach ($active_ids as $tid) {
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

const newCode = `        foreach ($active_ids as $tid) {
            $task = get_transient("ssp_bridge_task_{$tid}");
            if ($task) {
                error_log('[SSP Bridge Pending] Task ' . $tid . ' status: ' . $task['status']);
                // If it's pending OR sent, we return it. 
                // "sent" means we returned it before, but the client hasn't started processing it yet (which would change it to 'processing').
                // If the client fell back to fetch(), it might request it again.
                if ($task['status'] === 'pending' || $task['status'] === 'sent') {
                    $found = $task;
                    $task['status'] = 'sent';
                    set_transient("ssp_bridge_task_{$tid}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);
                    break;
                }
            } else {
                error_log('[SSP Bridge Pending] Task ' . $tid . ' not found in transient');
            }
        }`;

if (php.includes('if ($task[\'status\'] === \'pending\') {')) {
    php = php.replace(oldCode, newCode);
    fs.writeFileSync('includes/class-ssp-ai-browser.php', php);
    console.log('Patched bridge_get_pending_task to allow "sent"');
} else {
    console.log('Could not find bridge_get_pending_task code');
}
