
                            function activateLicense(e) {
                                e.preventDefault();
                                var key = document.getElementById('license_key_input').value.trim();
                                var btn = document.getElementById('license-activate-btn');
                                var msgEl = document.getElementById('license-activate-msg');
                                if (!key) return;
                                btn.disabled = true;
                                btn.textContent = 'در حال فعال‌سازی...';
                                msgEl.style.display = 'none';
                                var fd = new FormData();
                                fd.append('action', 'ssp_activate_license');
                                fd.append('security', '""');
                                fd.append('license_key', key);
                                var ctrl = new AbortController();
                                var tid = setTimeout(function() { ctrl.abort(); }, 30000);
                                fetch('""', {method:'POST', body:fd, signal:ctrl.signal})
                                    .then(function(r){ clearTimeout(tid); return r.json(); })
                                    .then(function(res){
                                        btn.disabled = false;
                                        btn.textContent = 'فعال‌سازی';
                                        msgEl.style.display = 'block';
                                        if (res.success) {
                                            msgEl.style.background = 'rgba(16,185,129,0.12)';
                                            msgEl.style.color = 'var(--success)';
                                            msgEl.textContent = res.data.message;
                                            setTimeout(function(){ location.reload(); }, 1500);
                                        } else {
                                            msgEl.style.background = 'rgba(239,68,68,0.12)';
                                            msgEl.style.color = 'var(--error)';
                                            msgEl.textContent = res.data ? res.data.message : 'خطا در فعال‌سازی لایسنس';
                                        }
                                    })
                                    .catch(function(err){
                                        clearTimeout(tid);
                                        btn.disabled = false;
                                        btn.textContent = 'فعال‌سازی';
                                        msgEl.style.display = 'block';
                                        msgEl.style.background = 'rgba(239,68,68,0.12)';
                                        msgEl.style.color = 'var(--error)';
                                        msgEl.textContent = err.name === 'AbortError' ? 'درخواست تمام شد' : 'خطا در اتصال';
                                    });
                            }
                            