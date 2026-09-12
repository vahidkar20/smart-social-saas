const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
js = js.replace("let typeEl = document.getElementById('dist_schedule_type');", "let scheduleTypeEl = document.getElementById('dist_schedule_type');");
js = js.replace("let scheduleType = typeEl ? typeEl.value : 'daily';", "let scheduleType = scheduleTypeEl ? scheduleTypeEl.value : 'daily';");
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched typeEl');
