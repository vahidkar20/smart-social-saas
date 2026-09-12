const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let targetRegex = /if\(e\.target && e\.target\.classList\.contains\('btn-fetch-extract'\)\) \{\s*showToast\('در حال استخراج کامل \(تست\)\.\.\.', 'info'\);\s*\}/;

let replacement = `let target_btn_fetch_extract = e.target.closest ? e.target.closest('.btn-fetch-extract') : (e.target.classList.contains('btn-fetch-extract') ? e.target : null);
    if(target_btn_fetch_extract) {
        let id = target_btn_fetch_extract.getAttribute('data-id');
        let btn = target_btn_fetch_extract;
        btn.disabled = true;
        btn.textContent = '...';
        showToast('در حال استخراج کامل (زمان‌بر است)...', 'info');
        let fd = new FormData();
        fd.append('action', 'ssp_fetch_rss_now');
        fd.append('security', nonce);
        fd.append('feed_id', id);
        fd.append('extract_now', '1');
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            btn.disabled = false;
            btn.textContent = 'استخراج کامل';
            if(res.success && res.data.items) {
                let itemsHtml = '';
                if(res.data.items.length === 0) {
                    itemsHtml = '<p style="text-align:center; padding:20px; color:var(--text-muted);">محتوای جدیدی یافت نشد.</p>';
                } else {
                    res.data.items.forEach(item => {
                        let checked = item.selected ? 'checked' : '';
                        let newBadge = item.is_new ? '<span class="ssp-badge ssp-badge-success" style="margin-right:8px; font-size:0.7rem;">جدید</span>' : '';
                        let extractBadge = item.extracted ? '<span class="ssp-badge ssp-badge-warning" style="margin-right:8px; font-size:0.7rem;">متن استخراج‌شده</span>' : '';
                        let escapedTitle = escapeHtml(item.title || '');
                        // If extracted text exists, use it instead of regular content
                        let displayContent = item.extracted ? item.extracted : item.content;
                        let escapedContent = escapeHtml(displayContent || '');
                        let escapedUrl = escapeHtml(item.url || '');
                        let escapedGuid = escapeHtml(item.guid || '');
                        
                        itemsHtml += \`
                            <div class="ssp-card rss-item-card" style="margin-bottom:12px; border:1px solid var(--border); border-radius:8px; padding:12px;" 
                                 data-title="\${escapedTitle}" 
                                 data-content="\${escapedContent}" 
                                 data-url="\${escapedUrl}" 
                                 data-guid="\${escapedGuid}">
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <input type="checkbox" \${checked} style="margin-top:4px;">
                                    <div style="flex:1;">
                                        <h4 style="margin:0 0 6px; font-size:0.95rem; color:var(--text);">\${escapedTitle} \${newBadge} \${extractBadge}</h4>
                                        <div style="font-size:0.85rem; color:var(--text-muted); line-height:1.6; max-height:150px; overflow-y:auto; margin-bottom:8px; white-space:pre-wrap; border-right:2px solid var(--accent); padding-right:8px;">\${escapedContent}</div>
                                        \${item.url ? \`<a href="\${escapedUrl}" target="_blank" style="font-size:0.8rem; color:var(--primary); text-decoration:none;">مشاهده لینک اصلی ↗</a>\` : ''}
                                    </div>
                                </div>
                            </div>
                        \`;
                    });
                }
                document.getElementById('rss_preview_items').innerHTML = itemsHtml;
                let modal = document.getElementById('modal_rss_preview');
                if(modal) {
                    modal.style.display = 'flex';
                }
            }
            else showToast(res.data ? res.data.message : 'خطا در دریافت', 'error');
        }).catch(() => { btn.disabled = false; btn.textContent = 'استخراج کامل'; showToast('خطا', 'error'); });
    }`;

js = js.replace(targetRegex, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched btn-fetch-extract');
