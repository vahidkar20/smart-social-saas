const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// For addRssFeed
let targetAdd = `let max = document.getElementById('new_feed_max_length');`;
let replacementAdd = `let mt = document.getElementById('new_feed_message_template');
    if(mt) fd.append('message_template', mt.value);
    
    let max = document.getElementById('new_feed_max_length');`;
js = js.replace(targetAdd, replacementAdd);

// For saveEditRssFeed
let targetEdit = `if(document.getElementById('edit_rss_content_mode')) fd.append('content_mode', document.getElementById('edit_rss_content_mode').value);`;
let replacementEdit = `if(document.getElementById('edit_rss_content_mode')) fd.append('content_mode', document.getElementById('edit_rss_content_mode').value);
    if(document.getElementById('edit_rss_message_template')) fd.append('message_template', document.getElementById('edit_rss_message_template').value);`;
js = js.replace(targetEdit, replacementEdit);

// For edit click load data
let targetEditClick = `if(document.getElementById('edit_rss_content_mode')) document.getElementById('edit_rss_content_mode').value = rss.content_mode || 'summary';`;
let replacementEditClick = `if(document.getElementById('edit_rss_content_mode')) document.getElementById('edit_rss_content_mode').value = rss.content_mode || 'summary';
                    if(document.getElementById('edit_rss_message_template')) document.getElementById('edit_rss_message_template').value = rss.message_template || '';`;
js = js.replace(targetEditClick, replacementEditClick);

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched js');
