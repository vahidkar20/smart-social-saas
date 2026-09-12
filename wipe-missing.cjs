const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

core = core.replace(/let missingFns = \[[\s\S]*?\];/, "let missingFns = [];");

fs.writeFileSync('assets/js/portal-core.js', core);
