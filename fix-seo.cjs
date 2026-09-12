const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

core = core.replace("document.getElementById('seo_sub_' + tabId);", "document.getElementById('seo-subtab-' + tabId);");
core = core.replace("parent.querySelectorAll('.seo-sub-view');", "parent.querySelectorAll('.seo-subtab-content');");

fs.writeFileSync('assets/js/portal-core.js', core);
