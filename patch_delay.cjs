const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
js = js.replace("fd.append('new_delay', delayVal);", "fd.append('new_delay_value', delayVal);");
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched delay names');
