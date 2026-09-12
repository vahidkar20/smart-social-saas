

window.showToast = function(msg, type = 'info') {
    let container = document.getElementById('ssp-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ssp-toast-container';
        container.style.position = 'fixed';
        container.style.bottom = '32px';
        container.style.left = '32px';
        container.style.zIndex = '9999';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '10px';
        document.body.appendChild(container);
    }
    
    let toast = document.createElement('div');
    toast.className = 'ssp-toast toast-' + type;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.innerHTML = (type === 'success' ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>' : (type === 'error' ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' : '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>')) + msg;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
};


document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.ssp-logs-table').forEach(table => {
        let headers = [];
        table.querySelectorAll('th').forEach(th => headers.push(th.innerText.trim()));
        if (headers.length > 0) {
            table.querySelectorAll('tbody tr').forEach(row => {
                row.querySelectorAll('td').forEach((td, index) => {
                    if (headers[index]) {
                        td.setAttribute('data-label', headers[index]);
                    }
                });
            });
        }
    });
});


            window.setBtnLoading = function(btn, isLoading) {
                if (!btn) return;
                if (isLoading) {
                    if (!btn.dataset.originalText) btn.dataset.originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="ssp-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin 0.75s linear infinite;"></span> ' + (btn.dataset.loadingText || 'در حال پردازش...');
                } else {
                    btn.disabled = false;
                    if (btn.dataset.originalText) btn.innerHTML = btn.dataset.originalText;
                }
            };

        (function(){
            var t = localStorage.getItem('ssp_theme');
            var theme = (t === 'dark') ? 'ssp-theme-dark' : 'ssp-theme-light';
            document.documentElement.classList.add(theme);
            document.body.classList.add('ssp-page-active', theme);
            document.addEventListener('DOMContentLoaded', function() {
                var wrap = document.querySelector('.ssp-wrap');
                if (wrap) {
                    wrap.classList.remove('ssp-theme-light', 'ssp-theme-dark');
                    wrap.classList.add(theme);
                }
                var header = document.querySelector('header.site-head');
                if (header) {
                    var lastScroll = 0;
                    window.addEventListener('scroll', function() {
                        var cur = window.pageYOffset || document.documentElement.scrollTop;
                        if (cur > 50 && cur > lastScroll) {
                            header.classList.add('header-hidden');
                        } else {
                            header.classList.remove('header-hidden');
                        }
                        lastScroll = cur <= 0 ? 0 : cur;
                    }, { passive: true });
                }
            });
        })();

        