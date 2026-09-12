const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

js = js.replace(/var timeoutId = setTimeout\(function\(\) \{\s*reject\(new Error\('Manual timeout'\)\);\s*\}, 10000\);/g, 
`var timeoutId = setTimeout(function() {
                console.warn('[SSP Bridge] GM_xmlhttpRequest hung, falling back to fetch');
                fallbackToFetch();
            }, 8000);`);

fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Patched manual timeout to fallback');
