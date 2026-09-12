const fs = require('fs');
let html = fs.readFileSync('includes/views/portal.php', 'utf8');

let regex = /<div id="pg_jalali_picker"[\s\S]*?(<\/div>\s*<\/div>\s*<\/div>)/;
let newHtml = '<div id="pg_jalali_picker" style="display:none; margin-top:8px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px;"></div>';

html = html.replace(
    /<div id="pg_jalali_picker"[\s\S]*?onclick="document.getElementById\('pg_jalali_picker'\).style.display='none'".*?<\/div>\s*<\/div>/g, 
    newHtml
);

fs.writeFileSync('includes/views/portal.php', html);
