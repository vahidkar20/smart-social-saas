const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
js = js.replace("fd.append('sort', sortEl.value);", "fd.append('sort_order', sortEl.value);");
js = js.replace("fd.append('template', tplEl.value);", "fd.append('message_template', tplEl.value);");
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched sort and template');
