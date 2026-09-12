const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

js = js.replace(/            send: function\(input\) {/, 
`            send: function(input) {
                // Ensure value is definitely set before we send
                if (input && input.tagName === 'TEXTAREA') {
                   input.dispatchEvent(new Event('input', { bubbles: true }));
                }`);

fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Added pre-send input trigger');
