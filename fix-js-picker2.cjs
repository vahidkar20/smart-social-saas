const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

js = js.replace(/if \(true\) \{/, "if (!document.getElementById(prefix + '_year')) {");

fs.writeFileSync('assets/js/portal-core.js', js);
