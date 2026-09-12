
// ===== EXTENDED JAVASCRIPT FOR DASHBOARD =====

// Global Variables for Calendar
let currentCalDate = new Date();

window.calendarPrevMonth = function() {
    currentCalDate.setMonth(currentCalDate.getMonth() - 1);
    loadCalendar();
};
window.calendarNextMonth = function() {
    currentCalDate.setMonth(currentCalDate.getMonth() + 1);
    loadCalendar();
};

window.loadCalendar = function() {
    let calEl = document.getElementById('content_calendar');
    let labelEl = document.getElementById('calendar_month_label');
    if (!calEl || !labelEl) return;

    let y = currentCalDate.getFullYear();
    let m = currentCalDate.getMonth();

    // formatting label (Shamsi/Gregorian depending on locale, we use simple Gregorian for now since JS Shamsi needs external library, or we just format it as YYYY-MM)
    labelEl.innerText = currentCalDate.toLocaleDateString('fa-IR', { year: 'numeric', month: 'long' });

    let firstDay = new Date(y, m, 1).getDay();
    let daysInMonth = new Date(y, m + 1, 0).getDate();

    // Map schedules and logs by date
    let datesMap = {};
    if (schedulesData && Array.isArray(schedulesData)) {
        schedulesData.forEach(s => {
            if(s.scheduled_at) {
                let d = s.scheduled_at.split(' ')[0]; // YYYY-MM-DD
                if(!datesMap[d]) datesMap[d] = { scheduled: 0, logs: 0 };
                datesMap[d].scheduled++;
            }
        });
    }
    if (logsData && Array.isArray(logsData)) {
        logsData.forEach(l => {
            if(l.created_at) {
                let d = l.created_at.split(' ')[0];
                if(!datesMap[d]) datesMap[d] = { scheduled: 0, logs: 0 };
                datesMap[d].logs++;
            }
        });
    }

    calEl.innerHTML = '';
    
    // Header
    const days = ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش']; // assuming JS getDay() starts Sunday(0)
    days.forEach(d => {
        calEl.innerHTML += `<div style="text-align:center; font-weight:bold; font-size:0.8rem; padding:5px; color:var(--text-muted);">${d}</div>`;
    });

    for (let i = 0; i < firstDay; i++) {
        calEl.innerHTML += `<div></div>`;
    }

    for (let i = 1; i <= daysInMonth; i++) {
        let currentDayStr = `${y}-${String(m+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
        let data = datesMap[currentDayStr] || {scheduled:0, logs:0};
        
        let indicators = '';
        if (data.scheduled > 0) {
            indicators += `<div style="width:6px;height:6px;border-radius:50%;background:var(--warning);display:inline-block;margin:1px;"></div>`;
        }
        if (data.logs > 0) {
            indicators += `<div style="width:6px;height:6px;border-radius:50%;background:var(--success);display:inline-block;margin:1px;"></div>`;
        }
        
        let isToday = new Date().toISOString().split('T')[0] === currentDayStr;
        let border = isToday ? 'border:1px solid var(--accent);' : 'border:1px solid var(--border);';
        
        calEl.innerHTML += `
            <div style="background:var(--card); ${border} border-radius:8px; padding:10px; min-height:80px; display:flex; flex-direction:column; justify-content:space-between; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='var(--bg-alt)'" onmouseout="this.style.background='var(--card)'">
                <div style="font-weight:bold; font-size:0.9rem;">${i}</div>
                <div style="text-align:right;">${indicators}</div>
            </div>
        `;
    }
};

window.loadDrafts = function() {
    let listEl = document.getElementById('drafts_list');
    if (!listEl) return;
    if (!draftsData || draftsData.length === 0) {
        listEl.innerHTML = `<div class="ssp-empty"><div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div><p>هنوز پیش‌نویسی ندارید.</p></div>`;
        return;
    }
    
    let html = '';
    draftsData.forEach(d => {
        html += `
            <div class="ssp-card ssp-card-enter" style="margin-bottom:12px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <h4 style="margin:0 0 8px;">${escapeHtml(d.title || 'بدون عنوان')}</h4>
                        <p style="margin:0; font-size:0.85rem; color:var(--text-muted); max-height:40px; overflow:hidden;">${escapeHtml(d.content)}</p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="ssp-btn-secondary" onclick="useDraft(${d.id})">استفاده</button>
                        <button class="ssp-btn-danger" onclick="deleteDraft(${d.id})">حذف</button>
                    </div>
                </div>
            </div>
        `;
    });
    listEl.innerHTML = html;
};

window.useDraft = function(id) {
    let draft = draftsData.find(d => d.id == id);
    if(draft) {
        let titleEl = document.getElementById('manual_title');
        let msgEl = document.getElementById('manual_message');
        if(titleEl) titleEl.value = draft.title || '';
        if(msgEl) msgEl.value = draft.content || '';
        switchTab('manual', document.querySelector('.ssp-sidebar [data-tab="manual"]'));
        showToast('پیش‌نویس بارگذاری شد', 'success');
    }
};

window.deleteDraft = function(id) {
    if(!confirm('آیا از حذف این پیش‌نویس مطمئن هستید؟')) return;
    let fd = new FormData();
    fd.append('action', 'ssp_delete_draft');
    fd.append('security', nonce);
    fd.append('draft_id', id);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                draftsData = draftsData.filter(d => d.id != id);
                loadDrafts();
                showToast('حذف شد', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};

window.saveDraft = function() {
    let btn = document.getElementById('save_draft_btn');
    if(!btn) return;
    let title = document.getElementById('draft_title').value;
    let content = document.getElementById('draft_content').value;
    let hashtags = document.getElementById('draft_hashtags').value;
    if(!content) { showToast('متن پیش‌نویس الزامی است', 'error'); return; }
    
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_save_draft');
    fd.append('security', nonce);
    fd.append('title', title);
    fd.append('content', content);
    fd.append('hashtags', hashtags);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                document.getElementById('draft_title').value = '';
                document.getElementById('draft_content').value = '';
                document.getElementById('draft_hashtags').value = '';
                showSaved('draft_saved');
                // ideally refetch or reload page, simple way is just reload for drafts to sync
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};

// Templates Implementation
window.loadTemplates = function() {
    let listEl = document.getElementById('template_library_list');
    if (!listEl) return;
    if (!templatesData || templatesData.length === 0) {
        listEl.innerHTML = `<div class="ssp-empty"><div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div><p>هنوز قالبی ذخیره نکرده‌اید.</p></div>`;
        return;
    }
    renderTemplates(templatesData);
};

window.filterTemplates = function(cat, btn) {
    document.querySelectorAll('.tpl-filter').forEach(b => b.classList.remove('active'));
    if(btn) btn.classList.add('active');
    
    if(cat === 'all') renderTemplates(templatesData);
    else renderTemplates(templatesData.filter(t => t.category === cat));
};

function renderTemplates(templates) {
    let listEl = document.getElementById('template_library_list');
    if(!templates || templates.length === 0) {
        listEl.innerHTML = `<div class="ssp-empty"><p>قالبی در این دسته یافت نشد.</p></div>`;
        return;
    }
    let html = '';
    templates.forEach(t => {
        html += `
            <div class="ssp-card ssp-card-enter" style="margin-bottom:12px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <h4 style="margin:0 0 4px;">${escapeHtml(t.name || 'بدون نام')} <span class="ssp-badge">${escapeHtml(t.category)}</span></h4>
                        <p style="margin:0; font-size:0.85rem; color:var(--text-muted); max-height:20px; overflow:hidden;">${escapeHtml(t.content)}</p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="ssp-btn-danger" onclick="deleteTemplate(${t.id})">حذف</button>
                    </div>
                </div>
            </div>
        `;
    });
    listEl.innerHTML = html;
}

window.deleteTemplate = function(id) {
    if(!confirm('آیا از حذف این قالب مطمئن هستید؟')) return;
    let fd = new FormData();
    fd.append('action', 'ssp_delete_template_item');
    fd.append('security', nonce);
    fd.append('template_id', id);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                templatesData = templatesData.filter(t => t.id != id);
                loadTemplates();
                showToast('حذف شد', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};

window.openTemplateCreateModal = function() {
    alert("This feature is best used by navigating to 'Create Template' section or configuring default message format.");
};


window.loadManualTemplateSelect = function() {
    let sel = document.getElementById('manual_template_select');
    let container = document.getElementById('manual_template_selector');
    if (!sel || !container) return;
    
    if (templatesData && templatesData.length > 0) {
        container.style.display = 'block';
        let html = '<option value="">-- انتخاب قالب ذخیره شده --</option>';
        templatesData.forEach(t => {
            html += `<option value="${t.id}">${escapeHtml(t.name)}</option>`;
        });
        sel.innerHTML = html;
        
        if (window._defaultTemplateId) {
            sel.value = window._defaultTemplateId;
            onManualTemplateSelect();
        }
    } else {
        container.style.display = 'none';
    }
};

window.onManualTemplateSelect = function() {
    let sel = document.getElementById('manual_template_select');
    if (!sel || !sel.value) return;
    
    let t = templatesData.find(x => x.id == sel.value);
    if (t) {
        let msgEl = document.getElementById('manual_message');
        if (msgEl) {
            let content = t.content;
            if (t.hashtags) content += '\n\n' + t.hashtags;
            if (t.signature) content += '\n\n' + t.signature;
            msgEl.value = content;
        }
    }
};

window.saveTemplate = function(e) {
    e.preventDefault();
    let btn = document.getElementById('save_template_btn');
    let template = document.getElementById('msg_template').value;
    let hashtags = document.getElementById('hashtags').value;
    let signature = document.getElementById('signature').value;
    
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_save_settings');
    fd.append('section', 'template');
    fd.append('security', nonce);
    fd.append('name', 'قالب عمومی پیش‌فرض');
    fd.append('category', 'general');
    fd.append('content', template);
    fd.append('hashtags', hashtags);
    fd.append('signature', signature);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showSaved('template_saved');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};
