const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldCode1 = `        updateWidget('processing', 'Waiting for input...');
        waitForElement(sels.input, 15000).then(function(input) {`;

const newCode1 = `        updateWidget('processing', 'Waiting for input...');
        sendResponseToPlugin(taskId, '', 'processing');
        waitForElement(sels.input, 15000).then(function(input) {`;

if (js.includes(oldCode1)) {
    js = js.replace(oldCode1, newCode1);
    fs.writeFileSync('assets/js/ai-bridge.user.js', js);
    console.log('Patched ai-bridge.user.js to send processing status');
} else {
    console.log('Could not find processing status code');
}
