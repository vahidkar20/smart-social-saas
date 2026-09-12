const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// Inside btn-fetch-rss and btn-fetch-extract, we already get 'id' (feed_id)
// We should store it on the modal or a hidden input.

let targetPreview = `document.getElementById('rss_preview_items').innerHTML = itemsHtml;`;
let replacementPreview = `document.getElementById('rss_preview_items').innerHTML = itemsHtml;
                document.getElementById('rss_preview_items').setAttribute('data-feed-id', id);`;

js = js.replace(targetPreview, replacementPreview);
js = js.replace(targetPreview, replacementPreview); // for btn-fetch-extract as well

let targetSend = `fd.append('items', JSON.stringify(selected));`;
let replacementSend = `fd.append('items', JSON.stringify(selected));
    let feedId = document.getElementById('rss_preview_items').getAttribute('data-feed-id');
    if(feedId) fd.append('feed_id', feedId);`;

js = js.replace(targetSend, replacementSend);
js = js.replace(targetSend, replacementSend); // For rssSaveDrafts maybe?
js = js.replace(targetSend, replacementSend); // For rssScheduleItems maybe?

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched rss preview modal with feed_id');
