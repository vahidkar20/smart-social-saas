const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

js = js.replace(/window\.closeModal = function\(id\) \{\s*let modal = document\.getElementById\(id\);\s*if \(modal\) \{\s*modal\.classList\.remove\('active'\);\s*modal\.style\.display = 'none';\s*\}\s*\};/g, "window.closeModal = function(id) {\n    let modal = document.getElementById(id);\n    if (modal) {\n        modal.classList.remove('active');\n        modal.style.display = '';\n    }\n};");

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched closeModal');
