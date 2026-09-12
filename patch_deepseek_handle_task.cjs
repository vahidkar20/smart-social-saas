const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldHandle = `            updateWidget('processing', 'Setting prompt...');
            var success = setInputValue(input, prompt);
            if (!success) throw new Error('Failed to set input value');

            return new Promise(function(r) { setTimeout(r, 800); });
        }).then(function() {
            updateWidget('processing', 'Sending...');
            var input = sels.input();
            if (sels.send) {
                sels.send(input);
            } else if (input) {
                sels.sendViaEnter(input);
            }`;

const newHandle = `            updateWidget('processing', 'Setting prompt...');
            var success = setInputValue(input, prompt);
            if (!success) throw new Error('Failed to set input value');

            return new Promise(function(r) { setTimeout(r, 1200); });
        }).then(function() {
            updateWidget('processing', 'Sending...');
            var input = sels.input();
            
            // Try one more input event right before click just in case
            if (input && input.tagName === 'TEXTAREA') {
               input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            
            if (sels.send) {
                sels.send(input);
            } else if (input) {
                sels.sendViaEnter(input);
            }`;

js = js.replace(oldHandle, newHandle);
fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Patched handleTask timing and trigger');
