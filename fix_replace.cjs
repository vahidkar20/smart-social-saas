const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

js = js.replace(/fd\.append\('items', JSON\.stringify\(selected\)\);\n    let feedId = document\.getElementById\('rss_preview_items'\)\.getAttribute\('data-feed-id'\);\n    if\(feedId\) fd\.append\('feed_id', feedId\);\n    let feedId = document\.getElementById\('rss_preview_items'\)\.getAttribute\('data-feed-id'\);\n    if\(feedId\) fd\.append\('feed_id', feedId\);\n    let feedId = document\.getElementById\('rss_preview_items'\)\.getAttribute\('data-feed-id'\);\n    if\(feedId\) fd\.append\('feed_id', feedId\);/g, "fd.append('items', JSON.stringify(selected));\n    let feedId = document.getElementById('rss_preview_items').getAttribute('data-feed-id');\n    if(feedId) fd.append('feed_id', feedId);");

js = js.replace(/fd\.append\('items', JSON\.stringify\(selected\)\);/g, "fd.append('items', JSON.stringify(selected));\n    let feedId2 = document.getElementById('rss_preview_items').getAttribute('data-feed-id');\n    if(feedId2) fd.append('feed_id', feedId2);");

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('fixed js replaces');
