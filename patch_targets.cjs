const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
js = js.replace("fd.append('targets', JSON.stringify(targets));", "fd.append('target_messengers', targets.join(','));");
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched');
