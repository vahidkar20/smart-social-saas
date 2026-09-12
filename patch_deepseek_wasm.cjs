const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

js = js.replace(/@version      4.1.1/, '@version      4.1.2');

// Fix the issue where WASM errors might break the flow by adding a catch all to the event dispatchers
const handleTaskRegex = /            var input = inputGetter\(\);\n            if \(input\) \{\n                input\.dispatchEvent\(new Event\('input', \{ bubbles: true \}\)\);\n            \}/g;
js = js.replace(handleTaskRegex, `            var input = inputGetter();
            if (input) {
                try { input.dispatchEvent(new Event('input', { bubbles: true, composed: true })); } catch(e){}
            }`);

fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Bumped version and patched event dispatchers');
