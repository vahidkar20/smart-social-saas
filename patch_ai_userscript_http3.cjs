const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

js = js.replace(/console\.warn\('\[SSP Bridge\] GM_xmlhttpRequest POST error, falling back to fetch', err\);\s*fallbackToFetch\(\);/g, 
`console.warn('[SSP Bridge] GM_xmlhttpRequest POST error, falling back to fetch', err);
                            clearTimeout(timeoutId);
                            fallbackToFetch();`);

js = js.replace(/console\.warn\('\[SSP Bridge\] GM_xmlhttpRequest POST timeout, falling back to fetch'\);\s*fallbackToFetch\(\);/g, 
`console.warn('[SSP Bridge] GM_xmlhttpRequest POST timeout, falling back to fetch');
                            clearTimeout(timeoutId);
                            fallbackToFetch();`);

js = js.replace(/console\.warn\('\[SSP Bridge\] GM_xmlhttpRequest GET error, falling back to fetch', err\);\s*fallbackToFetch\(\);/g, 
`console.warn('[SSP Bridge] GM_xmlhttpRequest GET error, falling back to fetch', err);
                            clearTimeout(timeoutId);
                            fallbackToFetch();`);

js = js.replace(/console\.warn\('\[SSP Bridge\] GM_xmlhttpRequest GET timeout, falling back to fetch'\);\s*fallbackToFetch\(\);/g, 
`console.warn('[SSP Bridge] GM_xmlhttpRequest GET timeout, falling back to fetch');
                            clearTimeout(timeoutId);
                            fallbackToFetch();`);

fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Fixed multiple fallbacks');
