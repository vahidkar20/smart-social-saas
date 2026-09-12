const fs = require('fs');
let css = fs.readFileSync('assets/css/portal.css', 'utf8');

css = css.replace(/\.ssp-sidebar\s*\{\s*background:\s*#ffffff;/g, ".ssp-sidebar {\n    background: var(--card);");

fs.writeFileSync('assets/css/portal.css', css);
console.log('patched portal.css sidebar bg');
