const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-ai.js', 'utf8');

let pgUi = `
window.pgToggleView = function(view) {
    let raw = document.getElementById('pg_result_content');
    let visual = document.getElementById('pg_result_preview');
    let rawBtn = document.getElementById('pg_view_raw_btn');
    let visualBtn = document.getElementById('pg_view_visual_btn');
    
    if (view === 'raw') {
        if(raw) raw.style.display = 'block';
        if(visual) visual.style.display = 'none';
        if(rawBtn) { rawBtn.style.borderColor = 'var(--accent)'; rawBtn.style.color = 'var(--accent)'; }
        if(visualBtn) { visualBtn.style.borderColor = 'var(--border)'; visualBtn.style.color = 'inherit'; }
    } else {
        if(raw) raw.style.display = 'none';
        if(visual) {
            visual.style.display = 'block';
            if(raw) visual.innerHTML = raw.value.replace(/\\n/g, '<br>');
        }
        if(visualBtn) { visualBtn.style.borderColor = 'var(--accent)'; visualBtn.style.color = 'var(--accent)'; }
        if(rawBtn) { rawBtn.style.borderColor = 'var(--border)'; rawBtn.style.color = 'inherit'; }
    }
};

window.switchPgMediaTab = function(tab) {
    let upload = document.getElementById('pg_media_upload_wrap');
    let url = document.getElementById('pg_media_url_wrap');
    let ai = document.getElementById('pg_media_ai_wrap');
    
    if(upload) upload.style.display = 'none';
    if(url) url.style.display = 'none';
    if(ai) ai.style.display = 'none';
    
    let wrap = document.getElementById('pg_media_' + tab + '_wrap');
    if(wrap) wrap.style.display = 'block';
};
`;

core = core + "\n" + pgUi;

fs.writeFileSync('assets/js/portal-ai.js', core);

let portalCore = fs.readFileSync('assets/js/portal-core.js', 'utf8');
['pgToggleView', 'switchPgMediaTab'].forEach(fn => {
    portalCore = portalCore.replace(`'${fn}', `, "");
});
fs.writeFileSync('assets/js/portal-core.js', portalCore);
