const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
js = js.replace('window.rssData && Array.isArray(window.rssData)', 'window.rssFeedData && Array.isArray(window.rssFeedData)');
js = js.replace('window.rssData.find', 'window.rssFeedData.find');
js = js.replace('rss.name', 'rss.feed_name');
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched rssFeedData');
