const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

code = code.replace(/document\.getElementById\('log_detail_' \+ id\)/g, "document.getElementById('log-detail-' + id)");

fs.writeFileSync('assets/js/portal-core.js', code);
console.log('Fixed toggleLogDetail bug');
