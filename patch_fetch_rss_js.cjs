const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = `        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            btn.disabled = false;
            btn.textContent = 'دریافت فوری';
            if(res.success) showToast(res.data.message || 'با موفقیت دریافت شد', 'success');
            else showToast(res.data.message || 'خطا در دریافت', 'error');
        }).catch(() => { btn.disabled = false; btn.textContent = 'دریافت فوری'; showToast('خطا', 'error'); });
    }`;

let replacement = `        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            btn.disabled = false;
            btn.textContent = 'دریافت فوری';
            if(res.success && res.data.items) {
                let itemsHtml = '';
                if(res.data.items.length === 0) {
                    itemsHtml = '<p style="text-align:center; padding:20px; color:var(--text-muted);">محتوای جدیدی یافت نشد.</p>';
                } else {
                    res.data.items.forEach(item => {
                        let checked = item.selected ? 'checked' : '';
                        let newBadge = item.is_new ? '<span class="ssp-badge ssp-badge-success" style="margin-right:8px; font-size:0.7rem;">جدید</span>' : '';
                        let escapedTitle = escapeHtml(item.title || '');
                        let escapedContent = escapeHtml(item.content || '');
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
                                        <h4 style="margin:0 0 6px; font-size:0.95rem; color:var(--text);">\${escapedTitle} \${newBadge}</h4>
                                        <div style="font-size:0.85rem; color:var(--text-muted); line-height:1.6; max-height:80px; overflow-y:auto; margin-bottom:8px; white-space:pre-wrap;">\${escapedContent}</div>
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
                showToast(res.data.message || 'با موفقیت دریافت شد', 'success');
            }
            else showToast(res.data ? res.data.message : 'خطا در دریافت', 'error');
        }).catch(() => { btn.disabled = false; btn.textContent = 'دریافت فوری'; showToast('خطا', 'error'); });
    }`;

js = js.replace(target, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched fetch RSS preview');
