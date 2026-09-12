const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');
js = js.replace('@version      4.0.4', '@version      4.0.5');
fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Bumped version to 4.0.5');
