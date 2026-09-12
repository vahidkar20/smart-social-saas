const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldCode = `    function pollForTasks() {
        if (isProcessing) return;

        console.log('[SSP Bridge] Polling for tasks... Token: ' + BRIDGE_TOKEN.substring(0, 12) + '... Site: ' + WP_SITE_URL);`;

const newCode = `    var isPolling = false;
    function pollForTasks() {
        if (isProcessing || isPolling) return;
        isPolling = true;

        console.log('[SSP Bridge] Polling for tasks... Token: ' + BRIDGE_TOKEN.substring(0, 12) + '... Site: ' + WP_SITE_URL);`;

if (js.includes('if (isProcessing) return;')) {
    js = js.replace(oldCode, newCode);
    js = js.replace(/console\.error\('\[SSP Bridge\] Poll error:', err\);\s*\}\);/g, `console.error('[SSP Bridge] Poll error:', err);\n            isPolling = false;\n        });`);
    js = js.replace(/handleTask\(res\.task_id, res\.prompt\);\s*\}\s*\}\)\.catch/g, `handleTask(res.task_id, res.prompt);\n            }\n            isPolling = false;\n        }).catch`);
    fs.writeFileSync('assets/js/ai-bridge.user.js', js);
    console.log('Added isPolling lock');
} else {
    console.log('Could not find pollForTasks');
}
