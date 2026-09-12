
                                function submitEmailSettings(e) {
                                    e.preventDefault();
                                    var email = document.getElementById('email_address').value;
                                    if (!email) { showToast('آدرس ایمیل را وارد کنید', 'error'); return; }
                                    var btn = document.getElementById('save_email_btn');
                                    var msgEl = document.getElementById('email-settings-msg');
                                    btn.disabled = true;
                                    btn.textContent = 'در حال ذخیره...';
                                    msgEl.style.display = 'none';
                                    var fd = new FormData();
                                    fd.append('action', 'ssp_save_email_settings');
                                    fd.append('security', '""');
                                    fd.append('email_enabled', document.getElementById('email_enabled').checked ? '1' : '0');
                                    fd.append('email_address', document.getElementById('email_address').value);
                                    fd.append('email_on_failure', document.getElementById('email_on_failure').checked ? '1' : '0');
                                    fd.append('email_on_license_expiry', document.getElementById('email_on_license_expiry').checked ? '1' : '0');
                                    fd.append('email_daily_summary', document.getElementById('email_daily_summary').checked ? '1' : '0');
                                    var ctrl = new AbortController();
                                    var tid = setTimeout(function() { ctrl.abort(); }, 30000);
                                    fetch('""', {method:'POST', body:fd, signal:ctrl.signal})
                                        .then(function(r){ clearTimeout(tid); return r.json(); })
                                        .then(function(res){
                                            btn.disabled = false;
                                            btn.textContent = 'ذخیره تنظیمات';
                                            msgEl.style.display = 'block';
                                            if (res.success) {
                                                msgEl.style.background = 'rgba(16,185,129,0.12)';
                                                msgEl.style.color = 'var(--success)';
                                                msgEl.textContent = res.data.message;
                                            } else {
                                                msgEl.style.background = 'rgba(239,68,68,0.12)';
                                                msgEl.style.color = 'var(--error)';
                                                msgEl.textContent = res.data ? res.data.message : 'خطا در ذخیره';
                                            }
                                        })
                                        .catch(function(err){
                                            clearTimeout(tid);
                                            btn.disabled = false;
                                            btn.textContent = 'ذخیره تنظیمات';
                                            msgEl.style.display = 'block';
                                            msgEl.style.background = 'rgba(239,68,68,0.12)';
                                            msgEl.style.color = 'var(--error)';
                                            msgEl.textContent = err.name === 'AbortError' ? 'درخواست تمام شد' : 'خطا در اتصال';
                                        });
                                }
                                function testEmail() {
                                    var email = document.getElementById('email_address').value;
                                    if (!email) { showToast('آدرس ایمیل را وارد کنید', 'error'); return; }
                                    var msgEl = document.getElementById('email-settings-msg');
                                    msgEl.style.display = 'none';
                                    var fd = new FormData();
                                    fd.append('action', 'ssp_test_email');
                                    fd.append('security', '""');
                                    fd.append('email_address', email);
                                    var ctrl = new AbortController();
                                    var tid = setTimeout(function() { ctrl.abort(); }, 30000);
                                    fetch('""', {method:'POST', body:fd, signal:ctrl.signal})
                                        .then(function(r){ clearTimeout(tid); var ct = r.headers.get('content-type') || ''; if (ct.indexOf('json') === -1) { return r.text().then(function(t){ throw new Error('Server returned non-JSON: ' + t.substring(0, 200)); }); } return r.json(); })
                                        .then(function(res){
                                            msgEl.style.display = 'block';
                                            if (res.success) {
                                                msgEl.style.background = 'rgba(16,185,129,0.12)';
                                                msgEl.style.color = 'var(--success)';
                                            } else {
                                                msgEl.style.background = 'rgba(239,68,68,0.12)';
                                                msgEl.style.color = 'var(--error)';
                                            }
                                            msgEl.textContent = res.data ? res.data.message : 'انجام شد';
                                        })
                                        .catch(function(err){
                                            clearTimeout(tid);
                                            msgEl.style.display = 'block';
                                            msgEl.style.background = 'rgba(239,68,68,0.12)';
                                            msgEl.style.color = 'var(--error)';
                                            msgEl.textContent = err.name === 'AbortError' ? 'درخواست تمام شد' : ('خطا: ' + (err.message || 'ارتباط با سرور برقرار نشد'));
                                        });
                                }
                                