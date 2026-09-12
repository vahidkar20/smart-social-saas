const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// For both btn-fetch-rss and btn-fetch-extract:
let targetExtractJS = `// If extracted text exists, use it instead of regular content
                        let displayContent = item.extracted ? item.extracted : item.content;
                        let escapedContent = escapeHtml(displayContent || '');`;

let replacementExtractJS = `let escapedContent = escapeHtml(item.content || '');`;

js = js.replace(targetExtractJS, replacementExtractJS);

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched btn-fetch-extract display');
