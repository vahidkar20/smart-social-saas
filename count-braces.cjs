const fs = require('fs');
let code = fs.readFileSync('script3.js', 'utf8');
let open = (code.match(/\{/g) || []).length;
let close = (code.match(/\}/g) || []).length;
console.log(`Open: ${open}, Close: ${close}`);
