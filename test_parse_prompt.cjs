const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');
if (js.includes('handleTask(taskId, prompt)')) {
    console.log('Script includes handleTask(taskId, prompt)');
}
