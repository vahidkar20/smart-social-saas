const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

js = js.replace(/if \(!picker\.innerHTML\.trim\(\)\) \{/, 'if (true) {');

fs.writeFileSync('assets/js/portal-core.js', js);
