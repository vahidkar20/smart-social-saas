
        (function() {
            var ajaxurl = '""';
            var nonce = '""';
            var userPlan = '""';
            var sspSiteUrl = '""';
            var isImpersonating = "";

            // Helper to add impersonate param to FormData (global for all IIFEs)
            window.addImpersonate = function(fd) {
                if (isImpersonating) fd.append('impersonate', '1');
            };

            // Store entity data in JS (no raw tokens in HTML attributes)
            var messengerData = "";
            var wpSiteData = "";
            var rssFeedData = "";
            var profileData = "";
            var schedulesData = "";
            var draftsData = "";
            var templatesData = "";
            var logsData = "";
            var activeProfileId = "";
            window._defaultTemplateId = "";

            // Profile switching
            window.switchProfile = function(profileId) {
                var fd = new FormData();
                fd.append('action', 'ssp_switch_profile');
                fd.append('security', nonce);
                fd.append('profile_id', profileId);
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) location.reload();
                        else showToast(res.data ? res.data.message : 'خطا', 'error');
                    })
                    .catch(function() { showToast('خطا در اتصال', 'error'); });
            };

            window.openProfileModal = function() {
                var modal = document.getElementById('modal_profiles');
                if (modal) modal.classList.add('active');
            };

            window.saveProfile = function() {
                var name = document.getElementById('profile_name').value.trim();
                if (!name) { showToast('نام پروفایل را وارد کنید', 'error'); return; }
                var editId = document.getElementById('profile_edit_id').value;
                var fd = new FormData();
                fd.append('action', editId ? 'ssp_update_profile' : 'ssp_add_profile');
                fd.append('security', nonce);
                fd.append('name', name);
                fd.append('color', document.getElementById('profile_color').value);
                if (editId) fd.append('profile_id', editId);
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) location.reload();
                        else showToast(res.data ? res.data.message : 'خطا', 'error');
                    })
                    .catch(function() { showToast('خطا در اتصال', 'error'); });
            };

            window.deleteProfile = function(id) {
                if (!confirm('آیا از حذف این پروفایل مطمئن هستید؟')) return;
                var fd = new FormData();
                fd.append('action', 'ssp_delete_profile');
                fd.append('security', nonce);
                fd.append('profile_id', id);
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) location.reload();
                        else showToast(res.data ? res.data.message : 'خطا', 'error');
                    })
                    .catch(function() { showToast('خطا در اتصال', 'error'); });
            };

            // ===== Particles Background =====
            (function() {
                var canvas = document.getElementById('sspParticles');
                if (!canvas || window.innerWidth < 768) return;
                var ctx = canvas.getContext('2d');
                var particles = [];
                var particleCount = 40;

                function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
                resize();
                window.addEventListener('resize', resize);

                function getParticleColor() {
                    return document.documentElement.classList.contains('ssp-theme-dark') ? '56, 189, 248' : '79, 70, 229';
                }

                function Particle() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.vx = (Math.random() - 0.5) * 0.4;
                    this.vy = (Math.random() - 0.5) * 0.4;
                    this.radius = Math.random() * 2 + 1;
                }

                for (var i = 0; i < particleCount; i++) particles.push(new Particle());

                function animate() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    var color = getParticleColor();
                    particles.forEach(function(p, i) {
                        p.x += p.vx; p.y += p.vy;
                        if (p.x < 0 || p.x > canvas.width) p.vx *= -1;
                        if (p.y < 0 || p.y > canvas.height) p.vy *= -1;
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(' + color + ', 0.6)';
                        ctx.fill();
                        for (var j = i + 1; j < particles.length; j++) {
                            var p2 = particles[j];
                            var dist = Math.sqrt(Math.pow(p.x - p2.x, 2) + Math.pow(p.y - p2.y, 2));
                            if (dist < 120) {
                                ctx.beginPath();
                                ctx.moveTo(p.x, p.y);
                                ctx.lineTo(p2.x, p2.y);
                                ctx.strokeStyle = 'rgba(' + color + ', ' + (0.15 * (1 - dist/120)) + ')';
                                ctx.lineWidth = 0.5;
                                ctx.stroke();
                            }
                        }
                    });
                    requestAnimationFrame(animate);
                }
                animate();
            })();

            // ===== Theme Toggle =====
            window.toggleTheme = function() {
                var wrap = document.querySelector('.ssp-wrap');
                var html = document.documentElement, body = document.body;
                var toDark = wrap.classList.contains('ssp-theme-light');
                wrap.classList.toggle('ssp-theme-light', !toDark);
                wrap.classList.toggle('ssp-theme-dark', toDark);
                html.classList.toggle('ssp-theme-light', !toDark);
                html.classList.toggle('ssp-theme-dark', toDark);
                body.classList.toggle('ssp-theme-light', !toDark);
                body.classList.toggle('ssp-theme-dark', toDark);
                localStorage.setItem('ssp_theme', toDark ? 'dark' : 'light');
                showToast(toDark ? 'تم تاریک فعال شد' : 'تم روشن فعال شد', 'success');
            };

            // ===== Mobile Drawer =====
            window.toggleDrawer = function() {
                var overlay = document.getElementById('sspDrawerOverlay');
                var drawer = document.getElementById('sspDrawer');
                var fab = document.getElementById('sspFab');
                var isActive = overlay.classList.contains('active');
                if (isActive) {
                    closeDrawer();
                } else {
                    overlay.classList.add('active');
                    drawer.classList.add('active');
                    if (fab) fab.classList.add('open');
                    document.body.style.overflow = 'hidden';
                }
            };
            window.closeDrawer = function() {
                var overlay = document.getElementById('sspDrawerOverlay');
                var drawer = document.getElementById('sspDrawer');
                var fab = document.getElementById('sspFab');
                overlay.classList.remove('active');
                drawer.classList.remove('active');
                if (fab) fab.classList.remove('open');
                document.body.style.overflow = '';
            };
            window.drawerSelect = function(tabId, drawerBtn) {
                var sidebarBtn = document.querySelector('.ssp-sidebar [data-tab="' + tabId + '"]');
                switchTab(tabId, sidebarBtn);
                document.querySelectorAll('.ssp-drawer-item').forEach(function(b) { b.classList.remove('active'); });
                if (drawerBtn) drawerBtn.classList.add('active');
                closeDrawer();
            };

            // ===== Tab Switching =====
            window.switchTab = function(tabId, btn) {
                document.querySelectorAll('.ssp-tab-btn').forEach(function(b) { b.classList.remove('active'); });
                if (btn) {
                    btn.classList.add('active');
                    if (window.innerWidth <= 768) {
                        btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }
                }
                // Sync drawer active state
                document.querySelectorAll('.ssp-drawer-item').forEach(function(b) {
                    b.classList.toggle('active', b.dataset.tab === tabId);
                });
                document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
                var target = document.getElementById('tab-' + tabId);
                if (target) target.classList.add('active');
                localStorage.setItem('ssp_active_tab', tabId);
                window.scrollTo({top: 0, behavior: 'smooth'});

                // Load data for specific tabs
                if (tabId === 'drafts' && typeof loadDrafts === 'function') loadDrafts();
                else if (tabId === 'calendar' && typeof loadCalendar === 'function') loadCalendar();
                else if (tabId === 'template' && typeof loadTemplates === 'function') loadTemplates();
                else if (tabId === 'manual' && typeof loadManualTemplateSelect === 'function') loadManualTemplateSelect();

                // Pick up retry data from localStorage
                if (tabId === 'manual') {
                    var retryData = localStorage.getItem('ssp_retry_data');
                    if (retryData) {
                        try {
                            var data = JSON.parse(retryData);
                            var titleEl = document.getElementById('manual_title');
                            var msgEl = document.getElementById('manual_message');
                            if (titleEl && data.title) titleEl.value = data.title;
                            if (msgEl && data.message) msgEl.value = data.message;
                            localStorage.removeItem('ssp_retry_data');
                            showToast('محتوا بارگذاری شد. تصویر/ویدیو را مجدداً آپلود کنید.', 'info');
                        } catch(e) {}
                    }
                }
            };

            // Restore active tab from localStorage
            (function() {
                var savedTab = localStorage.getItem('ssp_active_tab');
                if (savedTab && document.getElementById('tab-' + savedTab)) {
                    var sidebarBtn = document.querySelector('.ssp-sidebar [data-tab="' + savedTab + '"]');
                    var drawerBtn = document.querySelector('.ssp-drawer [data-tab="' + savedTab + '"]');
                    switchTab(savedTab, sidebarBtn || drawerBtn);
                }
            })();

            // ===== Utility =====
            window.togglePass = function(id) {
                var i = document.getElementById(id);
                if (i) i.type = i.type === 'password' ? 'text' : 'password';
            };

            function escapeHtml(str) {
                if (!str) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }
            window.escapeHtml = escapeHtml;

            // ===== Platform Constants =====
            var platformIcons = {telegram: '\uD83D\uDD35', bale: '\uD83D\uDFE2', eitaa: '\uD83D\uDFE0', rubika: '\uD83D\uDFE3', instagram: '\uD83D\uDCF7', whatsapp: '\uD83D\uDFE2'};
            var platformNames = {telegram: '\u062A\u0644\u06AF\u0631\u0627\u0645', bale: '\u0628\u0644\u0647', eitaa: '\u0627\u06CC\u062A\u0627', rubika: '\u0631\u0648\u0628\u06CC\u06A9\u0627', instagram: '\u0627\u06CC\u0646\u0633\u062A\u0627\u06AF\u0631\u0627\u0645', whatsapp: '\u0648\u0627\u062A\u0633\u0627\u067E'};
            var recLabels = {daily: '\u0631\u0648\u0632\u0627\u0646\u0647', weekly: '\u0647\u0641\u062A\u06AF\u06CC', monthly: '\u0645\u0627\u0647\u0627\u0646\u0647'};

            // ===== Card Helpers =====
            function createMessengerCard(m) {
                var icon = platformIcons[m.platform] || '\uD83D\uDCAC';
                var pname = platformNames[m.platform] || m.platform;
                return '<div class="ssp-item-card ssp-card-enter" data-id="' + m.id + '">' +
                    '<div class="ssp-item-card-head"><div>' +
                    '<div class="ssp-item-card-title">' + icon + ' ' + escapeHtml(m.name) +
                    ' <span class="ssp-badge ' + (m.is_active ? 'active' : 'inactive') + '">' + (m.is_active ? '\u0641\u0639\u0627\u0644' : '\u063A\u06CC\u0631\u0641\u0639\u0627\u0644') + '</span></div>' +
                    '<div class="ssp-item-card-meta">\u067E\u0644\u062A\u0641\u0631\u0645: ' + pname +
                    (m.channel_id ? ' &bull; Chat ID: ' + escapeHtml(m.channel_id) : '') + '</div>' +
                    '</div><div style="display:flex; gap:6px;">' +
                    '<button class="ssp-btn-test btn-test-messenger" data-id="' + m.id + '">\u062A\u0633\u062A</button>' +
                    '<button class="ssp-btn-secondary btn-edit-messenger" data-id="' + m.id + '">\u0648\u06CC\u0631\u0627\u06CC\u0634</button>' +
                    '<button class="ssp-btn-danger btn-delete-messenger" data-id="' + m.id + '">\u062D\u0630\u0641</button>' +
                    '</div></div>' +
                    '<span id="messenger_status_' + m.id + '" class="ssp-connection-status"></span></div>';
            }
            function createWpSiteCard(s) {
                return '<div class="ssp-item-card ssp-card-enter" data-id="' + s.id + '">' +
                    '<div class="ssp-item-card-head"><div>' +
                    '<div class="ssp-item-card-title">\uD83C\uDF10 ' + escapeHtml(s.site_name) +
                    ' <span class="ssp-badge ' + (s.is_active ? 'active' : 'inactive') + '">' + (s.is_active ? '\u0641\u0639\u0627\u0644' : '\u063A\u06CC\u0631\u0641\u0639\u0627\u0644') + '</span>' +
                    (s.auto_publish ? ' <span class="ssp-badge info">\u0627\u0646\u062A\u0634\u0627\u0631 \u062E\u0648\u062F\u06A9\u0627\u0631</span>' : '') + '</div>' +
                    '<div class="ssp-item-card-meta">' + escapeHtml(s.site_url) + ' &bull; ' + escapeHtml(s.username) + '</div>' +
                    '</div><div style="display:flex; gap:6px;">' +
                    '<button class="ssp-btn-test btn-test-wpsite" data-id="' + s.id + '">\u062A\u0633\u062A</button>' +
                    '<button class="ssp-btn-secondary btn-edit-wpsite" data-id="' + s.id + '">\u0648\u06CC\u0631\u0627\u06CC\u0634</button>' +
                    '<button class="ssp-btn-danger btn-delete-wpsite" data-id="' + s.id + '">\u062D\u0630\u0641</button>' +
                    '</div></div>' +
                    '<span id="wp_site_status_' + s.id + '" class="ssp-connection-status"></span></div>';
            }
            function createRssFeedCard(f) {
                var lastFetched = f.last_fetched ? '<div class="ssp-item-card-meta" style="margin-top:4px;"><span style="color:var(--success);">\u0622\u062E\u0631\u06CC\u0646 \u062F\u0631\u06CC\u0627\u0641\u062A: ' + formatJalaliDateTime(f.last_fetched) + '</span>' + (f.fetched_count ? ' \u2022 ' + f.fetched_count + ' \u0622\u06CC\u062A\u0645' : '') + '</div>' : '';
                var extractBadge = f.extract_content ? ' <span class="ssp-badge success" style="background:var(--success);color:#fff;">استخراج کامل</span>' : '';
                return '<div class="ssp-item-card ssp-card-enter" data-id="' + f.id + '">' +
                    '<div class="ssp-item-card-head"><div>' +
                    '<div class="ssp-item-card-title">\uD83D\uDCE1 ' + escapeHtml(f.feed_name) +
                    ' <span class="ssp-badge ' + (f.is_active ? 'active' : 'inactive') + '">' + (f.is_active ? '\u0641\u0639\u0627\u0644' : '\u063A\u06CC\u0631\u0641\u0639\u0627\u0644') + '</span>' +
                    (f.auto_fetch ? ' <span class="ssp-badge info">\u062F\u0631\u06CC\u0627\u0641\u062A \u062E\u0648\u062F\u06A9\u0627\u0631</span>' : '') + extractBadge + '</div>' +
                    '<div class="ssp-item-card-meta">' + escapeHtml(f.feed_url) + '</div>' +
                    lastFetched +
                    '</div><div style="display:flex; gap:6px; flex-wrap:wrap;">' +
                    '<button class="ssp-btn-test btn-fetch-rss" data-id="' + f.id + '">\u062F\u0631\u06CC\u0627\u0641\u062A \u0641\u0648\u0631\u06CC</button>' +
                    '<button class="ssp-btn-primary btn-fetch-extract" data-id="' + f.id + '" style="font-size:0.75rem; padding:4px 10px;">استخراج کامل</button>' +
                    '<button class="ssp-btn-secondary btn-edit-rss" data-id="' + f.id + '">\u0648\u06CC\u0631\u0627\u06CC\u0634</button>' +
                    '<button class="ssp-btn-danger btn-delete-rss" data-id="' + f.id + '">\u062D\u0630\u0641</button>' +
                    '</div></div></div>';
            }
            function createScheduleCard(s) {
                var statusClass = s.status === 'pending' ? 'info' : 'success';
                var statusIcon = s.status === 'pending' ? '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--warning);vertical-align:middle;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' : '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--success);vertical-align:middle;margin-right:2px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                var statusText = s.status === 'pending' ? 'در انتظار' : 'انجام شده';
                var dt = s.scheduled_at ? s.scheduled_at.replace('T', ' ') : '';
                var jalaliDt = formatJalaliDateTime(dt);
                var recLabels = {daily:'روزانه', weekly:'هفتگی', monthly:'ماهانه'};
                var msgPreview = (s.message || '').substring(0, 80);
                var isPast = s.status !== 'pending';
                var borderColor = isPast ? 'var(--success)' : 'var(--accent)';
                return '<div class="ssp-item-card ssp-card-enter" style="border-right:3px solid ' + borderColor + ';">' +
                    '<div class="ssp-item-card-head"><div style="flex:1;">' +
                    '<div class="ssp-item-card-title">' + statusIcon + ' ' + escapeHtml(s.title) +
                    ' <span class="ssp-badge ' + statusClass + '">' + statusText + '</span>' +
                    (s.recurring ? ' <span class="ssp-badge pro">' + (recLabels[s.recurring] || s.recurring) + '</span>' : '') + '</div>' +
                    '<div class="ssp-item-card-meta" style="margin-top:4px;">☰ ' + escapeHtml(jalaliDt) + '</div>' +
                    (msgPreview ? '<div class="ssp-item-card-meta" style="margin-top:4px; font-size:0.8rem;">' + escapeHtml(msgPreview) + '...</div>' : '') +
                    '</div><div style="display:flex; gap:6px; align-items:start;">' +
                    '<button class="ssp-btn-danger btn-delete-schedule" data-id="' + s.id + '">حذف</button>' +
                    '</div></div></div>';
            }
            // ===== SPA Helpers =====
            function removeEmptyState(listId) {
                var el = document.querySelector('#' + listId + ' .ssp-empty');
                if (el) el.remove();
            }
            function showEmptyState(listId, icon, text, subtext, actionBtnHtml = '') {
                var list = document.getElementById(listId);
                if (list && list.children.length === 0) {
                    list.innerHTML = '<div class="ssp-empty"><div class="ssp-empty-icon" style="font-size:3.5rem; color:var(--text-subtle); margin-bottom:16px;">' + icon + '</div><h4 style="margin:0 0 8px; font-size:1.1rem; color:var(--text);">' + text + '</h4>' + (subtext ? '<p style="font-size:0.9rem; color:var(--text-muted); max-width:400px; margin:0 auto 20px;">' + subtext + '</p>' : '') + (actionBtnHtml ? '<div>'+actionBtnHtml+'</div>' : '') + '</div>';
                }
            }
            function showSaved(id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.classList.add('show');
                setTimeout(function() { el.classList.remove('show'); }, 2500);
            }
            window.selectProvider = function(key, el) {
                document.querySelectorAll('.ssp-ai-provider-card').forEach(function(c) { c.classList.remove('selected'); });
                el.classList.add('selected');
                document.getElementById('ai_provider').value = key;

                // Update model hint
                var hints = {
                    'groq': 'مدل‌های Groq (رایگان و سریع): llama-3.3-70b-versatile, llama-3.1-8b-instant, mixtral-8x7b-32768',
                    'deepseek': 'مدل‌های DeepSeek: deepseek-chat, deepseek-reasoner',
                    'openai': 'مدل‌های OpenAI: gpt-4o-mini, gpt-4o, gpt-3.5-turbo',
                    'anthropic': 'مدل‌های Claude: claude-3-haiku, claude-3-sonnet, claude-3-opus',
                    'gemini': 'مدل‌های Gemini: gemini-2.0-flash, gemini-1.5-pro, gemini-1.5-flash',
                    'openrouter': 'مدل‌های متنوع: هر مدلی از OpenAI, Anthropic, Meta و...'
                };
                var hintEl = document.getElementById('model_hint');
                if (hintEl && hints[key]) {
                    hintEl.textContent = hints[key];
                }
            };

            window.togglePromptMode = function(mode) {
                document.querySelectorAll('input[name="ai_prompt_mode"]').forEach(function(r) {
                    r.checked = r.value === mode;
                });
                var section = document.getElementById('ai_custom_prompt_section');
                section.style.display = mode === 'advanced' ? 'block' : 'none';
                var cards = section.parentElement.querySelectorAll('.ssp-feature-card');
                if (cards.length >= 2) {
                    cards[0].classList.toggle('active', mode === 'simple');
                    cards[1].classList.toggle('active', mode === 'advanced');
                }
            };

            // ===== Browser Bridge Mode =====

            window.selectAIMode = function(mode) {
                document.getElementById('ssp_ai_mode').value = mode;
                document.querySelectorAll('input[name="ssp_ai_mode"]').forEach(function(r) {
                    r.checked = r.value === mode;
                });
                var modeCards = document.querySelectorAll('#tab-ai > form > .ssp-grid-2')[0];
                if (modeCards) {
                    var cards = modeCards.querySelectorAll('.ssp-feature-card');
                    if (cards.length >= 2) {
                        cards[0].classList.toggle('active', mode === 'api');
                        cards[1].classList.toggle('active', mode === 'browser');
                    }
                }
                var apiSettings = document.getElementById('api_mode_settings');
                var browserSettings = document.getElementById('browser_mode_settings');
                if (apiSettings) apiSettings.style.display = mode === 'api' ? 'block' : 'none';
                if (browserSettings) browserSettings.style.display = mode === 'browser' ? 'block' : 'none';
            };

            window.selectChatbot = function(bot) {
                document.getElementById('ssp_ai_chatbot').value = bot;
                var section = document.getElementById('browser_mode_settings');
                if (section) {
                    var cards = section.querySelectorAll('.ssp-grid-2 .ssp-feature-card');
                    cards.forEach(function(c, i) {
                        c.classList.toggle('active', (i === 0 && bot === 'deepseek') || (i === 1 && bot === 'chatgpt'));
                    });
                }
            };

            window.setupBrowserBridge = function() {
                var btn = document.getElementById('bridge_setup_btn');
                btn.classList.add('loading');
                var fd = new FormData();
                fd.append('action', 'ssp_bridge_setup');
                fd.append('security', nonce);
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        btn.classList.remove('loading');
                        if (res.success) {
                            var d = res.data;
                            var scriptUrl = d.direct_url || (d.script_url + '?token=' + encodeURIComponent(d.token) + '&site=' + encodeURIComponent(d.site_url));
                            var resultEl = document.getElementById('bridge_setup_result');
                            resultEl.innerHTML = '<div class="ssp-card" style="background:var(--success-soft); border-color:var(--success);">' +
                                '<p><strong>مراحل نصب اسکریپت در SnapMonkey:</strong></p>' +
                                '<ol style="font-size:0.85rem; color:#374151; margin:8px 0; padding-right:20px;">' +
                                '<li style="margin-bottom:6px;">در SnapMonkey روی دکمه <strong>+ New Script</strong> بزنید</li>' +
                                '<li style="margin-bottom:6px;">کد اسکریپت را کپی کرده، در ویرایشگر جایگزین کنید و <strong>Save</strong> را بزنید</li>' +
                                '<li style="margin-bottom:6px;">یا لینک زیر را در یک تب جدید مرورگر باز کنید تا دیالوگ نصب باز شود</li>' +
                                '</ol>' +
                                '<div style="display:flex; gap:8px; align-items:center; margin:8px 0;">' +
                                '<code id="bridge_script_url" style="word-break:break-all; flex:1; padding:10px 12px; background:white; border:1px solid #d1d5db; border-radius:8px; direction:ltr; text-align:left; font-size:0.8rem; color:#374151; display:block;">' + scriptUrl + '</code>' +
                                '<button type="button" class="ssp-btn-primary" onclick="navigator.clipboard.writeText(document.getElementById(\'bridge_script_url\').textContent).then(function(){showToast(\'کپی شد!\',\'success\');})" style="white-space:nowrap;">کپی لینک</button>' +
                                '</div>' +
                                '</div>';

                            // Pre-configure bookmarklet
                            var bkCode = "javascript:void(function(){var t='" + d.token + "';var u='" + d.site_url + "';var tid=localStorage.getItem('ssp_bridge_pending_task');if(!tid){alert('No pending task found. Generate content from the plugin first.');return;}var msgs=document.querySelectorAll('[data-message-author-role=assistant] .markdown');if(!msgs.length)msgs=document.querySelectorAll('.ds-markdown--block');if(!msgs.length){alert('No AI response found.');return;}var txt=msgs[msgs.length-1].innerText;fetch(u+'/wp-json/ssp/v1/ai-bridge/response',{method:'POST',headers:{'Content-Type':'application/json','X-SSP-Bridge-Token':t},body:JSON.stringify({task_id:tid,response_text:txt,status:'completed'})}).then(function(){alert('Response sent to plugin!');}).catch(function(e){alert('Error: '+e.message);});}())";
                            document.getElementById('bookmarklet_link').href = bkCode;

                            showToast('تنظیمات آماده شد!', 'success');
                        } else {
                            showToast(res.data.message || 'خطا', 'error');
                        }
                    })
                    .catch(function() { btn.classList.remove('loading'); showToast('خطا در ارتباط با سرور', 'error'); });
            };

            window.testBrowserBridge = function() {
                showToast('لطفاً به سایت چت‌بات بروید و ویجت سبز را بررسی کنید.', 'success');
                var chatbot = document.getElementById('ssp_ai_chatbot').value;
                var urls = { 'deepseek': 'https://chat.deepseek.com/', 'chatgpt': 'https://chatgpt.com/' };
                window.open(urls[chatbot] || urls['deepseek'], '_blank');
            };

            // ===== Browser Mode Generation =====

            // ===== Browser Bridge State =====
            window._bridgeCurrentTool = null;
            window._bridgeCurrentPrompt = null;
            window._bridgeCurrentContext = null;
            window._bridgeCurrentToolFn = null;
            window._bridgeModalState = null;
            window._bridgeChatbotTab = null;
            window._bridgeBrainstormResult = null;
            window._bridgePostgenResult = null;

            window.openChatbotTab = function() {
                var chatbot = document.getElementById('ssp_ai_chatbot').value;
                var urls = { 'deepseek': 'https://chat.deepseek.com/', 'chatgpt': 'https://chatgpt.com/' };
                var url = urls[chatbot] || urls['deepseek'];
                window._bridgeChatbotTab = window.open(url, '_blank');
                if (!window._bridgeChatbotTab || window._bridgeChatbotTab.closed || typeof window._bridgeChatbotTab.closed === 'undefined') {
                    // Popup was blocked
                    updateBridgeModalState('error');
                    var statusEl = document.getElementById('bridge-wait-status');
                    if (statusEl) {
                        statusEl.innerHTML = 'پاپ‌آپ مسدود شد! لطفاً پاپ‌آپ را برای این سایت فعال کنید یا <a href="' + url + '" target="_blank" style="color:#4f46e5;text-decoration:underline;">اینجا کلیک کنید</a>';
                    }
                    return;
                }
            };

            // ===== Modal State Machine =====
            var bridgeStates = {

                'success':   { step: 3, status: 'پاسخ با موفقیت دریافت شد!', progress: 100, icon1: 'done', icon2: 'done', icon3: 'done' }
            };
            function getStepIcon(state) {
                if (state === 'done') return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>';
                if (state === 'spinner') return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><circle cx="12" cy="12" r="3"/></svg>';
                if (state === 'error') return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
            }
            // ===== Manual Send =====
            window.manualSend = function(e) {
                e.preventDefault();
                var btn = document.getElementById('manual_send_btn');
                setBtnLoading(btn, true);
                var fd = new FormData(e.target);
                fd.append('action', 'ssp_manual_send');
                fd.append('security', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(r => r.json())
                    .then(res => {
                        setBtnLoading(btn, false);
                        if (res.success) {
                            showToast('پیام ارسال شد!', 'success');
                            e.target.reset();
                        } else {
                            showToast(res.data ? res.data.message : 'خطا در ارسال', 'error');
                        }
                    })
                    .catch(() => {
                        setBtnLoading(btn, false);
                        showToast('خطا در ارتباط با سرور', 'error');
                    });
            };

            // ===== AI Post Generation (API & Browser) =====
            window.pgGeneratePost = function() {
                var btn = document.getElementById('pg_gen_btn');
                setBtnLoading(btn, true);
                var fd = new FormData();
                fd.append('action', 'ssp_generate_post');
                fd.append('security', nonce);
                fd.append('topic', document.getElementById('pg_topic').value);
                
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(r => r.json())
                    .then(res => {
                        setBtnLoading(btn, false);
                        if (res.success) {
                            showToast('تولید محتوا موفق بود', 'success');
                            if (res.data.content) {
                                document.getElementById('pg_post_result').style.display = 'block';
                                document.getElementById('pg_res_content').value = res.data.content.message || '';
                            }
                        } else {
                            showToast(res.data ? res.data.message : 'خطا', 'error');
                        }
                    })
                    .catch(() => { setBtnLoading(btn, false); showToast('خطا در ارتباط', 'error'); });
            };

            window.pgGeneratePostViaBrowser = function() {
                var btn = document.getElementById('pg_post_browser_btn');
                setBtnLoading(btn, true);
                var topic = document.getElementById('pg_topic').value;
                if (!topic) { setBtnLoading(btn, false); showToast('موضوع را وارد کنید', 'error'); return; }
                
                var fd = new FormData();
                fd.append('action', 'ssp_bridge_create_task');
                fd.append('security', nonce);
                fd.append('type', 'post');
                fd.append('prompt', 'یک پست شبکه‌های اجتماعی درباره: ' + topic);
                
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(r => r.json())
                    .then(res => {
                        setBtnLoading(btn, false);
                        if (res.success && res.data.task_id) {
                            showToast('درخواست به چت‌بات ارسال شد. لطفا تب چت‌بات را باز نگه دارید.', 'info');
                            // Start polling for this task ID (simplified)
                        } else {
                            showToast(res.data ? res.data.message : 'خطا', 'error');
                        }
                    })
                    .catch(() => { setBtnLoading(btn, false); showToast('خطا در ارتباط', 'error'); });
            };

            // ===== AI Product Generation (API & Browser) =====
            window.pgGenerateWithAI = function() {
                var btn = document.getElementById('pg_ai_btn');
                if(!btn) return;
                setBtnLoading(btn, true);
                var fd = new FormData();
                fd.append('action', 'ssp_generate_product_ai');
                fd.append('security', nonce);
                // add other fields
                ['title', 'category', 'price', 'brand', 'tags'].forEach(k => {
                    var el = document.getElementById('pg_prod_' + k);
                    if(el) fd.append(k, el.value);
                });
                
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(r => r.json())
                    .then(res => {
                        setBtnLoading(btn, false);
                        if (res.success) {
                            showToast('تولید محتوای محصول موفق بود', 'success');
                            if (res.data.content) {
                                document.getElementById('pg_prod_desc').value = res.data.content.description || '';
                                document.getElementById('pg_prod_short_desc').value = res.data.content.short_description || '';
                            }
                        } else {
                            showToast(res.data ? res.data.message : 'خطا', 'error');
                        }
                    })
                    .catch(() => { setBtnLoading(btn, false); showToast('خطا در ارتباط', 'error'); });
            };

            window.pgGenerateViaBrowser = function() {
                var btn = document.getElementById('pg_browser_btn');
                if(!btn) return;
                setBtnLoading(btn, true);
                
                var fd = new FormData();
                fd.append('action', 'ssp_bridge_create_task');
                fd.append('security', nonce);
                fd.append('type', 'product');
                var title = document.getElementById('pg_prod_title') ? document.getElementById('pg_prod_title').value : '';
                fd.append('prompt', 'توضیحات کامل برای این محصول بنویس: ' + title);
                
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(r => r.json())
                    .then(res => {
                        setBtnLoading(btn, false);
                        if (res.success && res.data.task_id) {
                            showToast('درخواست به چت‌بات ارسال شد.', 'info');
                        } else {
                            showToast(res.data ? res.data.message : 'خطا', 'error');
                        }
                    })
                    .catch(() => { setBtnLoading(btn, false); showToast('خطا در ارتباط', 'error'); });
            };
        })();



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
    fd.append('action', 'ssp_save_template_item');
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

        