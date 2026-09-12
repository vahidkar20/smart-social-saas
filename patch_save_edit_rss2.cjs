const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

js = js.replace("if(document.getElementById('edit_rss_extract')) fd.append('extract_full', document.getElementById('edit_rss_extract').checked ? 1 : 0);", "if(document.getElementById('edit_rss_extract')) fd.append('extract_content', document.getElementById('edit_rss_extract').checked ? 1 : 0);");

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched saveEditRssFeed payload');
