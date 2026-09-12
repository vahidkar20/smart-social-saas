const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

js = js.replace(/fd\.append\('items', JSON\.stringify\(selected\)\);[\s\S]*?(?=fetch\(ajaxurl)/g, "fd.append('items', JSON.stringify(selected));\n    let fId = document.getElementById('rss_preview_items').getAttribute('data-feed-id');\n    if(fId) fd.append('feed_id', fId);\n\n    ");

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('fixed js properly');
