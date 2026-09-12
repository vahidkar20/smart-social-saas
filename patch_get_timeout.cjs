const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const regexGetTimeout = /GM_xmlhttpRequest GET error[\s\S]*?fallbackToFetch\(\);\s*\}, 8000\);/g;

js = js.replace(/console\.warn\('\[SSP Bridge\] GM_xmlhttpRequest hung, falling back to fetch'\);\s*fallbackToFetch\(\);\s*\}, 8000\);/g, 
`console.warn('[SSP Bridge] GM_xmlhttpRequest hung, falling back to fetch');
                fallbackToFetch();
            }, 3000);`); // Use 3s instead of 8s for hangs

fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Reduced manual timeout');
