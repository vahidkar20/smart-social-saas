const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// Also fixing addChildButtonField if it's there
code = code.replace(/window\.addChildButtonField = function\(\) \{ showToast\('افزودن زیردکمه', 'info'\); \};/, '');

fs.writeFileSync('assets/js/portal-core.js', code);
