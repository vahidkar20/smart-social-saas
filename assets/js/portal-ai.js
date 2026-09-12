/**
 * SnapSocial AI Studio - Unified AI Engine & Workflow
 * Orchestrates Brainstorming, Social Post Generator, Article/Content Generator,
 * WooCommerce Product Generator, Batch Content Engine, and Cross-Tool Flow.
 */

(function(window) {
    'use strict';

    // Global AI Studio Workflow & State Synchronization
    window.sspAiWorkflow = {
        currentTopic: '',
        currentBrief: '',
        currentType: 'post',

        setIdea: function(title, brief, type) {
            this.currentTopic = title || '';
            this.currentBrief = brief || '';
            this.currentType = type || 'post';
        },

        applyToInputs: function() {
            // Apply to PostGen
            let pgTopic = document.getElementById('pg_topic');
            let pgDetails = document.getElementById('pg_details');
            if (pgTopic && this.currentTopic) pgTopic.value = this.currentTopic;
            if (pgDetails && this.currentBrief) pgDetails.value = this.currentBrief;

            // Apply to ContentGen
            let cgName = document.getElementById('cg_ai_name');
            let cgBrief = document.getElementById('cg_ai_brief');
            if (cgName && this.currentTopic) cgName.value = this.currentTopic;
            if (cgBrief && this.currentBrief) cgBrief.value = this.currentBrief;

            // Apply to ProductGen
            let prodName = document.getElementById('pg_ai_name');
            let prodBrief = document.getElementById('pg_ai_brief');
            if (prodName && this.currentTopic) prodName.value = this.currentTopic;
            if (prodBrief && this.currentBrief) prodBrief.value = this.currentBrief;
        }
    };

    // Shared Robust AI Text & JSON Cleaners
    function cleanAiRawText(raw) {
        if (!raw || typeof raw !== 'string') return '';
        let str = raw;
        // 1. Remove XML thought tags <think>...</think> and <thought>...</thought>
        str = str.replace(/<think>[\s\S]*?<\/think>/gi, '');
        str = str.replace(/<thought>[\s\S]*?<\/thought>/gi, '');
        // 2. Remove leading thought headers
        str = str.replace(/^(?:thinking|thought(?:\s+for\s+\d+\s+seconds)?|reasoning)\b[\s\S]*?\n\n/gi, '');
        // 3. Remove standalone thinking / reasoning lines
        str = str.replace(/^(?:thinking|thought|reasoning|در حال تفکر|تفکر)\b[^\n]*\n?/gim, '');
        return str.trim();
    }

    function extractJsonFromText(text) {
        if (!text || typeof text !== 'string') return null;
        let cleaned = cleanAiRawText(text);

        // 1. Direct JSON parse
        try {
            return JSON.parse(cleaned);
        } catch(e){}

        // 2. Markdown code block
        let codeMatch = cleaned.match(/```(?:json)?\s*([\s\S]*?)```/i);
        if (codeMatch && codeMatch[1]) {
            try {
                return JSON.parse(codeMatch[1].trim());
            } catch(e){}
        }

        // 3. Outermost object { ... }
        let firstBrace = cleaned.indexOf('{');
        let lastBrace = cleaned.lastIndexOf('}');
        if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
            let candidate = cleaned.substring(firstBrace, lastBrace + 1);
            try {
                return JSON.parse(candidate);
            } catch(e){}
            // Clean trailing commas before } or ]
            let fixed = candidate.replace(/,\s*([\}\]])/g, '$1');
            try {
                return JSON.parse(fixed);
            } catch(e){}
        }

        // 4. Outermost array [ ... ]
        let firstBracket = cleaned.indexOf('[');
        let lastBracket = cleaned.lastIndexOf(']');
        if (firstBracket !== -1 && lastBracket !== -1 && lastBracket > firstBracket) {
            let candidate = cleaned.substring(firstBracket, lastBracket + 1);
            try {
                return JSON.parse(candidate);
            } catch(e){}
            let fixed = candidate.replace(/,\s*([\}\]])/g, '$1');
            try {
                return JSON.parse(fixed);
            } catch(e){}
        }

        return null;
    }

    // Helper: Safely trigger tab switch
    window.sspSwitchToAiTab = function(tabName) {
        let btn = document.querySelector(`.ssp-sidebar [data-tab="${tabName}"]`);
        if (typeof window.switchTab === 'function') {
            window.switchTab(tabName, btn);
        }
    };

    // 1-Click Transfers between AI Studio tools
    window.transferIdeaToPost = function(title, desc) {
        window.sspAiWorkflow.setIdea(title, desc, 'post');
        window.sspSwitchToAiTab('postgen');
        setTimeout(function() {
            let topicEl = document.getElementById('pg_topic');
            let detailsEl = document.getElementById('pg_details');
            if (topicEl) {
                topicEl.value = title || '';
                topicEl.focus();
                topicEl.style.transition = 'box-shadow 0.3s';
                topicEl.style.boxShadow = '0 0 0 3px rgba(79, 70, 229, 0.35)';
                setTimeout(() => { if (topicEl) topicEl.style.boxShadow = ''; }, 1500);
            }
            if (detailsEl && desc) detailsEl.value = desc;
            if (typeof showToast === 'function') showToast('ایده با موفقیت به بخش تولید پست منتقل شد', 'success');
        }, 120);
    };

    window.transferIdeaToArticle = function(title, desc) {
        window.sspAiWorkflow.setIdea(title, desc, 'article');
        window.sspSwitchToAiTab('contentgen');
        setTimeout(function() {
            let nameEl = document.getElementById('cg_ai_name');
            let briefEl = document.getElementById('cg_ai_brief');
            if (nameEl) {
                nameEl.value = title || '';
                nameEl.focus();
                nameEl.style.transition = 'box-shadow 0.3s';
                nameEl.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.35)';
                setTimeout(() => { if (nameEl) nameEl.style.boxShadow = ''; }, 1500);
            }
            if (briefEl && desc) briefEl.value = desc;
            if (typeof showToast === 'function') showToast('ایده با موفقیت به بخش تولید مقاله منتقل شد', 'success');
        }, 120);
    };

    window.transferIdeaToProduct = function(title, desc) {
        window.sspAiWorkflow.setIdea(title, desc, 'product');
        window.sspSwitchToAiTab('productgen');
        setTimeout(function() {
            let nameEl = document.getElementById('pg_ai_name');
            let briefEl = document.getElementById('pg_ai_brief');
            if (nameEl) {
                nameEl.value = title || '';
                nameEl.focus();
                nameEl.style.transition = 'box-shadow 0.3s';
                nameEl.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.35)';
                setTimeout(() => { if (nameEl) nameEl.style.boxShadow = ''; }, 1500);
            }
            if (briefEl && desc) briefEl.value = desc;
            if (typeof showToast === 'function') showToast('ایده با موفقیت به بخش تولید محصول منتقل شد', 'success');
        }, 120);
    };

    window.copyIdeaText = function(text) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                if (typeof showToast === 'function') showToast('متن با موفقیت کپی شد', 'success');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    };

    function fallbackCopy(text) {
        let ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            if (typeof showToast === 'function') showToast('متن کپی شد', 'success');
        } catch(e) {
            if (typeof showToast === 'function') showToast('خطا در کپی متن', 'error');
        }
        document.body.removeChild(ta);
    }

    /* =========================================================================
       1. AI BRAINSTORMING / IDEATION
       ========================================================================= */
    window.bsGenerate = function() {
        let btn = document.getElementById('bs_gen_btn');
        let topicEl = document.getElementById('bs_topic');
        let countEl = document.getElementById('bs_count');
        let typeEl = document.getElementById('bs_type');
        let errEl = document.getElementById('bs_error');

        if (errEl) errEl.style.display = 'none';

        let topic = topicEl ? topicEl.value.trim() : '';
        if (!topic) {
            if (typeof showToast === 'function') showToast('لطفاً موضوع مورد نظر را وارد کنید', 'error');
            if (topicEl) topicEl.focus();
            return;
        }

        let count = countEl ? countEl.value : '10';
        let type = typeEl ? typeEl.value : 'all';

        if (btn) setBtnLoading(btn, true);

        let fd = new FormData();
        fd.append('action', 'ssp_brainstorm_ideas');
        fd.append('security', window.nonce || '');
        fd.append('topic', topic);
        fd.append('count', count);
        fd.append('type', type);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    if (res.data.mode === 'browser') {
                        if (typeof showToast === 'function') showToast('درخواست به چت‌بات ارسال شد. در حال پردازش...', 'info');
                        window._bridgeCurrentTool = 'brainstorm';
                        if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                        if (typeof window.startBridgePolling === 'function') {
                            window.startBridgePolling(res.data.task_id, function(bridgeData) {
                                if (btn) setBtnLoading(btn, false);
                                let ideas = [];
                                let parsed = bridgeData.parsed || extractJsonFromText(bridgeData.raw);
                                
                                // 1. Check parsed structure
                                if (parsed) {
                                    if (Array.isArray(parsed)) ideas = parsed;
                                    else if (parsed.ideas && Array.isArray(parsed.ideas)) ideas = parsed.ideas;
                                    else if (parsed.items && Array.isArray(parsed.items)) ideas = parsed.items;
                                }

                                // 2. Fallback to line-by-line parsing if not JSON
                                if (ideas.length === 0 && bridgeData.raw) {
                                    let cleanText = cleanAiRawText(bridgeData.raw);
                                    let lines = cleanText.split(/\r?\n/);
                                    lines.forEach(line => {
                                        let clean = line.replace(/^[\d+\.\-\*\#\s]+/, '').trim();
                                        if (!clean || /^(thinking|thought|reasoning|در حال تفکر|تفکر|```|{|}|\[|\])/i.test(clean)) return;
                                        if (clean.length >= 6) {
                                            let parts = clean.split(':', 2);
                                            ideas.push({
                                                title: parts[0].trim(),
                                                description: (parts[1] || 'ایده پیشنهادی هوش مصنوعی').trim(),
                                                type: 'post',
                                                angle: 'ایده جذاب'
                                            });
                                        }
                                    });
                                }

                                renderBrainstormIdeas(ideas, topic);
                                if (ideas.length > 0) {
                                    if (typeof showToast === 'function') showToast(`${ideas.length} ایده با موفقیت دریافت شد!`, 'success');
                                } else {
                                    if (typeof showToast === 'function') showToast('ایده‌ای استخراج نشد. لطفاً مجدداً امتحان کنید.', 'warning');
                                }
                            }, function() { if (btn) setBtnLoading(btn, false); });
                        }
                        return;
                    }

                    if (btn) setBtnLoading(btn, false);
                    let ideas = res.data.ideas || [];
                    renderBrainstormIdeas(ideas, topic);
                    if (typeof showToast === 'function') showToast(`${ideas.length} ایده جذاب تولید شد!`, 'success');
                } else {
                    if (btn) setBtnLoading(btn, false);
                    let errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در تولید ایده';
                    if (errEl) {
                        errEl.textContent = errMsg;
                        errEl.style.display = 'block';
                    }
                    if (typeof showToast === 'function') showToast(errMsg, 'error');
                }
            })
            .catch(err => {
                if (btn) setBtnLoading(btn, false);
                if (typeof showToast === 'function') showToast('خطای شبکه در ارتباط با هوش مصنوعی', 'error');
            });
    };

    function renderBrainstormIdeas(ideas, topic) {
        let resultsWrap = document.getElementById('bs_results');
        let grid = document.getElementById('bs_ideas_grid');
        let countBadge = document.getElementById('bs_count_badge');

        if (!grid) return;
        grid.innerHTML = '';

        if (!ideas || ideas.length === 0) {
            grid.innerHTML = '<div class="ssp-empty"><p>ایده‌ای یافت نشد. لطفاً موضوع دیگری را امتحان کنید.</p></div>';
            if (resultsWrap) resultsWrap.style.display = 'block';
            return;
        }

        if (countBadge) countBadge.textContent = `${ideas.length} ایده آماده`;

        ideas.forEach((item, index) => {
            let title = typeof item === 'string' ? item : (item.title || item.name || 'ایده محتوا');
            let desc = typeof item === 'object' ? (item.description || item.pitch || item.angle || '') : '';
            let type = typeof item === 'object' ? (item.type || 'post') : 'post';
            let angle = typeof item === 'object' ? (item.angle || 'ایده خلاقانه') : 'ایده';

            let typeBadgeColor = '#4f46e5';
            let typeLabel = '📱 پست شبکه‌ها';
            if (type === 'article') {
                typeBadgeColor = '#059669';
                typeLabel = '📝 مقاله سئو';
            } else if (type === 'product') {
                typeBadgeColor = '#2563eb';
                typeLabel = '🛍️ محصول ووکامرس';
            }

            let card = document.createElement('div');
            card.className = 'ssp-card ssp-idea-card';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.justifyContent = 'space-between';
            card.style.padding = '16px';
            card.style.borderRadius = '12px';
            card.style.border = '1px solid var(--border)';
            card.style.background = 'var(--card)';
            card.style.transition = 'all 0.2s ease';

            let escapedTitle = escapeHtml(title);
            let escapedDesc = escapeHtml(desc);
            let safeTitleJs = escapeJsArg(title);
            let safeDescJs = escapeJsArg(desc);

            card.innerHTML = `
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-size:0.75rem; font-weight:700; color:#fff; background:${typeBadgeColor}; padding:3px 8px; border-radius:6px;">${typeLabel}</span>
                        <span style="font-size:0.75rem; color:var(--text-muted); background:var(--bg-alt); padding:3px 8px; border-radius:6px;">${escapeHtml(angle)}</span>
                    </div>
                    <h4 style="margin:0 0 8px; font-size:0.95rem; font-weight:700; color:var(--text); line-height:1.5;">${escapedTitle}</h4>
                    ${escapedDesc ? `<p style="font-size:0.83rem; color:var(--text-muted); line-height:1.6; margin:0 0 14px;">${escapedDesc}</p>` : ''}
                </div>
                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:8px; display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="ssp-btn-primary" onclick="transferIdeaToPost('${safeTitleJs}', '${safeDescJs}')" style="font-size:0.75rem; padding:5px 10px; flex:1; min-width:90px; text-align:center;">
                        ✍️ ساخت پست
                    </button>
                    <button type="button" class="ssp-btn-secondary" onclick="transferIdeaToArticle('${safeTitleJs}', '${safeDescJs}')" style="font-size:0.75rem; padding:5px 10px; flex:1; min-width:90px; text-align:center;">
                        📝 ساخت مقاله
                    </button>
                    <button type="button" class="ssp-btn-secondary" onclick="transferIdeaToProduct('${safeTitleJs}', '${safeDescJs}')" style="font-size:0.75rem; padding:5px 10px; flex:1; min-width:90px; text-align:center;">
                        🛍️ ساخت محصول
                    </button>
                    <button type="button" class="ssp-btn-secondary" onclick="copyIdeaText('${safeTitleJs}\\n${safeDescJs}')" title="کپی متن" style="font-size:0.75rem; padding:5px 8px;">
                        📋
                    </button>
                </div>
            `;
            grid.appendChild(card);
        });

        if (resultsWrap) {
            resultsWrap.style.display = 'block';
            resultsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /* =========================================================================
       2. SOCIAL POST GENERATOR
       ========================================================================= */
    window.pgGeneratePost = function() {
        let btn = document.getElementById('pg_gen_btn') || document.getElementById('pg_ai_btn');
        let topicEl = document.getElementById('pg_topic');
        let styleEl = document.getElementById('pg_style');
        let lengthEl = document.getElementById('pg_length');
        let detailsEl = document.getElementById('pg_details');
        let errEl = document.getElementById('pg_gen_error') || document.getElementById('pg_ai_error');

        if (errEl) errEl.style.display = 'none';

        let topic = topicEl ? topicEl.value.trim() : '';
        if (!topic) {
            if (typeof showToast === 'function') showToast('لطفاً موضوع / عنوان پست را وارد کنید', 'error');
            if (topicEl) topicEl.focus();
            return;
        }

        let style = styleEl ? styleEl.value : 'general';
        let length = lengthEl ? lengthEl.value : '2000';
        let details = detailsEl ? detailsEl.value.trim() : '';

        if (btn) setBtnLoading(btn, true);

        let fd = new FormData();
        fd.append('action', 'ssp_generate_product_ai');
        fd.append('security', window.nonce || '');
        fd.append('product_name', topic);
        fd.append('product_brief', details);
        fd.append('content_type', 'post');
        fd.append('prompt_mode', style);
        fd.append('content_length', length);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    let populatePostFields = function(data, tokensUsed) {
                        let title = data.title || topic;
                        let message = data.message || data.content || data.description || '';
                        let hashtags = data.hashtags || '';
                        if (Array.isArray(hashtags)) hashtags = hashtags.join(' ');

                        let titleEl = document.getElementById('pg_result_title');
                        let contentEl = document.getElementById('pg_result_content');
                        let previewEl = document.getElementById('pg_result_preview');
                        let hashtagsEl = document.getElementById('pg_result_hashtags');
                        let resultWrap = document.getElementById('pg_post_result');
                        let tokensBadge = document.getElementById('pg_gen_tokens');

                        if (titleEl) titleEl.value = title;
                        if (contentEl) contentEl.value = message;
                        if (previewEl) previewEl.innerHTML = formatPostText(message);
                        if (hashtagsEl) hashtagsEl.value = hashtags;
                        if (tokensBadge && tokensUsed) {
                            tokensBadge.textContent = `${tokensUsed} توکن`;
                            tokensBadge.style.display = 'inline-block';
                        }

                        if (resultWrap) {
                            resultWrap.style.display = 'block';
                            resultWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    };

                    if (res.data.mode === 'browser') {
                        if (typeof showToast === 'function') showToast('درخواست به چت‌بات ارسال شد. در حال پردازش...', 'info');
                        window._bridgeCurrentTool = 'postgen';
                        if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                        if (typeof window.startBridgePolling === 'function') {
                            window.startBridgePolling(res.data.task_id, function(bridgeData) {
                                if (btn) setBtnLoading(btn, false);
                                let data = bridgeData.parsed || extractJsonFromText(bridgeData.raw) || {};
                                
                                // Clean up and normalize fields
                                if (!data.message && !data.content && bridgeData.raw) {
                                    let clean = cleanAiRawText(bridgeData.raw);
                                    let lines = clean.split(/\r?\n/);
                                    if (lines.length > 1 && !data.title) {
                                        data.title = lines[0].replace(/^#+\s*/, '').trim();
                                        data.message = lines.slice(1).join('\n').trim();
                                    } else {
                                        data.message = clean;
                                    }
                                }

                                if (data.name && !data.title) data.title = data.name;
                                if (data.content && !data.message) data.message = data.content;
                                if (data.description && !data.message) data.message = data.description;
                                if (data.message) data.message = cleanAiRawText(data.message);
                                if (data.title) data.title = cleanAiRawText(data.title);

                                populatePostFields(data, null);
                                if (typeof showToast === 'function') showToast('پست شبکه‌های اجتماعی با موفقیت دریافت و در فرم قرار گرفت!', 'success');
                            }, function() { if (btn) setBtnLoading(btn, false); });
                        }
                        return;
                    }

                    if (btn) setBtnLoading(btn, false);
                    let data = res.data.data || res.data;
                    populatePostFields(data, res.data.tokens_used);
                    if (typeof showToast === 'function') showToast('پست شبکه‌های اجتماعی با موفقیت تولید شد', 'success');
                } else {
                    if (btn) setBtnLoading(btn, false);
                    let errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در تولید پست';
                    if (errEl) {
                        errEl.textContent = errMsg;
                        errEl.style.display = 'block';
                    }
                    if (typeof showToast === 'function') showToast(errMsg, 'error');
                }
            })
            .catch(err => {
                if (btn) setBtnLoading(btn, false);
                if (typeof showToast === 'function') showToast('خطای سرور در ارتباط با AI', 'error');
            });
    };

    window.pgGeneratePostWithAI = window.pgGeneratePost;

    window.pgToggleView = function(view) {
        let raw = document.getElementById('pg_result_content');
        let visual = document.getElementById('pg_result_preview');
        let rawBtn = document.getElementById('pg_view_raw_btn');
        let visualBtn = document.getElementById('pg_view_visual_btn');

        if (view === 'raw') {
            if (raw) raw.style.display = 'block';
            if (visual) visual.style.display = 'none';
            if (rawBtn) { rawBtn.style.borderColor = 'var(--accent)'; rawBtn.style.color = 'var(--accent)'; }
            if (visualBtn) { visualBtn.style.borderColor = 'var(--border)'; visualBtn.style.color = 'inherit'; }
        } else {
            if (raw) raw.style.display = 'none';
            if (visual) {
                visual.style.display = 'block';
                if (raw) visual.innerHTML = formatPostText(raw.value);
            }
            if (visualBtn) { visualBtn.style.borderColor = 'var(--accent)'; visualBtn.style.color = 'var(--accent)'; }
            if (rawBtn) { rawBtn.style.borderColor = 'var(--border)'; rawBtn.style.color = 'inherit'; }
        }
    };

    function formatPostText(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    }

    window.pgSendPost = function() {
        let contentEl = document.getElementById('pg_result_content');
        let hashtagsEl = document.getElementById('pg_result_hashtags');
        let message = contentEl ? contentEl.value.trim() : '';

        if (!message) {
            let visual = document.getElementById('pg_result_preview');
            if (visual && visual.innerText) message = visual.innerText.trim();
        }

        if (!message) {
            if (typeof showToast === 'function') showToast('متن پستی برای ارسال وجود ندارد', 'error');
            return;
        }

        let hashtags = hashtagsEl ? hashtagsEl.value.trim() : '';
        let fullMessage = hashtags ? `${message}\n\n${hashtags}` : message;

        // Collect selected messengers
        let checkedMessengers = Array.from(document.querySelectorAll('input[name="pg_dest_messengers[]"]:checked')).map(cb => cb.value);
        if (checkedMessengers.length === 0) {
            // Fallback checking property directly
            Array.from(document.querySelectorAll('input[name="pg_dest_messengers[]"]')).forEach(cb => {
                if (cb.checked) checkedMessengers.push(cb.value);
            });
        }

        if (checkedMessengers.length === 0) {
            if (typeof showToast === 'function') showToast('لطفاً حداقل یک پیام‌رسان مقصد را انتخاب کنید', 'error');
            return;
        }

        let imageUrl = '';
        let imgUrlEl = document.getElementById('pg_image_url');
        if (imgUrlEl && imgUrlEl.value) imageUrl = imgUrlEl.value;

        let fd = new FormData();
        fd.append('action', 'ssp_manual_send');
        fd.append('security', window.nonce || '');
        fd.append('message', fullMessage);
        fd.append('messengers', JSON.stringify(checkedMessengers));
        if (imageUrl) fd.append('image_url', imageUrl);

        if (typeof showToast === 'function') showToast('در حال ارسال پیام به کانال‌ها...', 'info');

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (typeof showToast === 'function') showToast('پیام با موفقیت به پیام‌رسان‌های انتخاب شده ارسال شد!', 'success');
                } else {
                    let msg = (res.data && res.data.message) ? res.data.message : 'خطا در ارسال پیام';
                    if (typeof showToast === 'function') showToast(msg, 'error');
                }
            })
            .catch(() => {
                if (typeof showToast === 'function') showToast('خطای شبکه در ارسال پیام', 'error');
            });
    };

    window.pgCopyPost = function() {
        let titleEl = document.getElementById('pg_result_title');
        let contentEl = document.getElementById('pg_result_content');
        let hashtagsEl = document.getElementById('pg_result_hashtags');

        let text = '';
        if (titleEl && titleEl.value.trim()) text += titleEl.value.trim() + '\n\n';
        if (contentEl && contentEl.value.trim()) text += contentEl.value.trim();
        if (hashtagsEl && hashtagsEl.value.trim()) text += '\n\n' + hashtagsEl.value.trim();

        if (text) {
            copyIdeaText(text);
        } else {
            if (typeof showToast === 'function') showToast('متنی برای کپی وجود ندارد', 'error');
        }
    };

    window.pgSaveAsDraftPost = function() {
        let titleEl = document.getElementById('pg_result_title');
        let contentEl = document.getElementById('pg_result_content');
        let hashtagsEl = document.getElementById('pg_result_hashtags');

        let content = contentEl ? contentEl.value.trim() : '';
        if (!content) {
            if (typeof showToast === 'function') showToast('متنی برای ذخیره در پیش‌نویس‌ها وجود ندارد', 'error');
            return;
        }

        let title = (titleEl && titleEl.value.trim()) ? titleEl.value.trim() : 'پست تولید شده با هوش مصنوعی';
        let hashtags = hashtagsEl ? hashtagsEl.value.trim() : '';
        if (hashtags) content += '\n\n' + hashtags;

        let fd = new FormData();
        fd.append('action', 'ssp_save_draft');
        fd.append('security', window.nonce || '');
        fd.append('title', title);
        fd.append('content', content);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (typeof showToast === 'function') showToast('پست به عنوان پیش‌نویس با موفقیت ذخیره شد', 'success');
                } else {
                    let msg = (res.data && res.data.message) ? res.data.message : 'خطا در ذخیره';
                    if (typeof showToast === 'function') showToast(msg, 'error');
                }
            })
            .catch(() => {
                if (typeof showToast === 'function') showToast('خطای شبکه در ذخیره پیش‌نویس', 'error');
            });
    };

    window.pgResetPostForm = function() {
        if (!confirm('آیا مایلید فیلدهای فرم پست را ریست کنید؟')) return;
        ['pg_topic', 'pg_details', 'pg_result_title', 'pg_result_content', 'pg_result_hashtags', 'pg_image_url'].forEach(id => {
            let el = document.getElementById(id);
            if (el) el.value = '';
        });
        let previewEl = document.getElementById('pg_result_preview');
        if (previewEl) previewEl.innerHTML = '';
        let resWrap = document.getElementById('pg_post_result');
        if (resWrap) resWrap.style.display = 'none';
        if (typeof showToast === 'function') showToast('فرم پاک شد', 'info');
    };

    /* =========================================================================
       3. ARTICLE & CONTENT GENERATOR (WORDPRESS)
       ========================================================================= */
    window.cgGenerateWithAI = function() {
        let btn = document.getElementById('cg_ai_btn') || document.getElementById('cg_gen_btn');
        let nameEl = document.getElementById('cg_ai_name');
        let briefEl = document.getElementById('cg_ai_brief');
        let modeEl = document.getElementById('cg_prompt_mode');
        let customPromptEl = document.getElementById('cg_custom_prompt');
        let contentTypeEl = document.getElementById('cg_content_type');
        let errEl = document.getElementById('cg_ai_error');

        if (errEl) errEl.style.display = 'none';

        let name = nameEl ? nameEl.value.trim() : '';
        if (!name) {
            if (typeof showToast === 'function') showToast('لطفاً عنوان یا موضوع مقاله را وارد کنید', 'error');
            if (nameEl) nameEl.focus();
            return;
        }

        let brief = briefEl ? briefEl.value.trim() : '';
        let mode = modeEl ? modeEl.value : 'default_post';
        let customPrompt = customPromptEl ? customPromptEl.value.trim() : '';
        let contentType = contentTypeEl ? contentTypeEl.value : 'post';

        if (btn) setBtnLoading(btn, true);

        let fd = new FormData();
        fd.append('action', 'ssp_generate_product_ai');
        fd.append('security', window.nonce || '');
        fd.append('product_name', name);
        fd.append('product_brief', brief);
        fd.append('content_type', contentType);
        fd.append('prompt_mode', mode);
        fd.append('custom_prompt', customPrompt);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    let populateArticleFields = function(data, tokensUsed) {
                        let title = data.title || data.name || name;
                        let content = data.content || data.description || data.message || '';
                        let tags = data.tags || [];
                        if (Array.isArray(tags)) tags = tags.join(', ');
                        let metaTitle = data.meta_title || title;
                        let metaDesc = data.meta_description || data.excerpt || '';

                        let titleInput = document.getElementById('cg_post_title');
                        let contentInput = document.getElementById('cg_post_content') || document.getElementById('cg_content_editor');
                        let tagsInput = document.getElementById('cg_post_tags');
                        let metaTitleInput = document.getElementById('cg_meta_title');
                        let metaDescInput = document.getElementById('cg_meta_description');
                        let tokensBadge = document.getElementById('cg_ai_tokens');

                        if (titleInput) titleInput.value = title;
                        if (contentInput) contentInput.value = content;
                        if (tagsInput && tags) tagsInput.value = tags;
                        if (metaTitleInput && metaTitle) metaTitleInput.value = metaTitle;
                        if (metaDescInput && metaDesc) metaDescInput.value = metaDesc;

                        if (tokensBadge && tokensUsed) {
                            tokensBadge.textContent = `${tokensUsed} توکن`;
                            tokensBadge.style.display = 'inline-block';
                        }

                        // Auto-load categories for the selected site if not yet loaded
                        if (typeof window.cgSiteChanged === 'function') {
                            window.cgSiteChanged();
                        }
                    };

                    if (res.data.mode === 'browser') {
                        if (typeof showToast === 'function') showToast('درخواست به چت‌بات ارسال شد. در حال پردازش...', 'info');
                        window._bridgeCurrentTool = 'contentgen';
                        if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                        if (typeof window.startBridgePolling === 'function') {
                            window.startBridgePolling(res.data.task_id, function(bridgeData) {
                                if (btn) setBtnLoading(btn, false);
                                let data = bridgeData.parsed || extractJsonFromText(bridgeData.raw) || {};

                                // Clean up and normalize fields
                                if (!data.content && !data.description && bridgeData.raw) {
                                    let clean = cleanAiRawText(bridgeData.raw);
                                    let lines = clean.split(/\r?\n/);
                                    if (lines.length > 1 && !data.title) {
                                        data.title = lines[0].replace(/^#+\s*/, '').trim();
                                        data.content = lines.slice(1).join('\n').trim();
                                    } else {
                                        data.content = clean;
                                    }
                                }

                                if (data.name && !data.title) data.title = data.name;
                                if (data.message && !data.content) data.content = data.message;
                                if (data.description && !data.content) data.content = data.description;
                                if (data.content) data.content = cleanAiRawText(data.content);
                                if (data.title) data.title = cleanAiRawText(data.title);
                                if (data.excerpt && !data.meta_description) data.meta_description = data.excerpt;

                                populateArticleFields(data, null);
                                if (typeof showToast === 'function') showToast('مقاله با موفقیت دریافت و در فرم قرار گرفت!', 'success');
                            }, function() { if (btn) setBtnLoading(btn, false); });
                        }
                        return;
                    }

                    if (btn) setBtnLoading(btn, false);
                    let data = res.data.data || res.data;
                    populateArticleFields(data, res.data.tokens_used);
                    if (typeof showToast === 'function') showToast('مقاله با موفقیت تولید شد و در فرم جای گرفت!', 'success');
                } else {
                    if (btn) setBtnLoading(btn, false);
                    let errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در تولید مقاله';
                    if (errEl) {
                        errEl.textContent = errMsg;
                        errEl.style.display = 'block';
                    }
                    if (typeof showToast === 'function') showToast(errMsg, 'error');
                }
            })
            .catch(err => {
                if (btn) setBtnLoading(btn, false);
                if (typeof showToast === 'function') showToast('خطای سرور در تولید مقاله', 'error');
            });
    };

    window.cgSiteChanged = function() {
        let siteSelect = document.getElementById('cg_target_site');
        let catSelect = document.getElementById('cg_wp_categories');
        let contentTypeEl = document.getElementById('cg_content_type');
        if (!siteSelect || !catSelect) return;

        let siteId = siteSelect.value;
        if (!siteId) return;

        catSelect.innerHTML = '<option value="">در حال دریافت دسته‌ها...</option>';

        let fd = new FormData();
        fd.append('action', 'ssp_fetch_woo_categories');
        fd.append('security', window.nonce || '');
        fd.append('site_id', siteId);
        fd.append('content_type', contentTypeEl ? contentTypeEl.value : 'post');

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data && Array.isArray(res.data.categories)) {
                    catSelect.innerHTML = '<option value="">بدون دسته‌بندی</option>' +
                        res.data.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
                } else {
                    catSelect.innerHTML = '<option value="">دسته‌ای یافت نشد</option>';
                }
            })
            .catch(() => {
                catSelect.innerHTML = '<option value="">خطا در دریافت دسته‌ها</option>';
            });
    };

    window.cgPublish = function() {
        let btn = document.getElementById('cg_publish_btn');
        let siteSelect = document.getElementById('cg_target_site');
        let contentTypeEl = document.getElementById('cg_content_type');
        let titleEl = document.getElementById('cg_post_title');
        let contentEl = document.getElementById('cg_post_content') || document.getElementById('cg_content_editor');
        let statusEl = document.getElementById('cg_post_status');
        let catEl = document.getElementById('cg_wp_categories');
        let tagsEl = document.getElementById('cg_post_tags');
        let metaTitleEl = document.getElementById('cg_meta_title');
        let metaDescEl = document.getElementById('cg_meta_description');
        let fileEl = document.getElementById('cg_thumbnail_file');
        let urlEl = document.getElementById('cg_thumbnail_url');
        let resizeEl = document.getElementById('cg_image_resize');
        let resultEl = document.getElementById('cg_result');

        let title = titleEl ? titleEl.value.trim() : '';
        let content = contentEl ? contentEl.value.trim() : '';

        if (!title) {
            if (typeof showToast === 'function') showToast('عنوان مقاله نمی‌تواند خالی باشد', 'error');
            if (titleEl) titleEl.focus();
            return;
        }

        if (!content) {
            if (typeof showToast === 'function') showToast('محتوای مقاله نمی‌تواند خالی باشد', 'error');
            if (contentEl) contentEl.focus();
            return;
        }

        let siteId = siteSelect ? siteSelect.value : '';
        if (!siteId) {
            if (typeof showToast === 'function') showToast('لطفاً یک سایت مقصد انتخاب کنید', 'error');
            return;
        }

        if (btn) setBtnLoading(btn, true);
        if (resultEl) resultEl.style.display = 'none';

        let fd = new FormData();
        fd.append('action', 'ssp_generate_product');
        fd.append('security', window.nonce || '');
        fd.append('site_id', siteId);
        fd.append('content_type', contentTypeEl ? contentTypeEl.value : 'post');
        fd.append('post_title', title);
        fd.append('description', content);
        fd.append('status', statusEl ? statusEl.value : 'publish');
        if (catEl && catEl.value) fd.append('category_ids', catEl.value);
        if (tagsEl && tagsEl.value) fd.append('tags', tagsEl.value);
        if (metaTitleEl && metaTitleEl.value) fd.append('meta_title', metaTitleEl.value);
        if (metaDescEl && metaDescEl.value) fd.append('meta_description', metaDescEl.value);
        if (urlEl && urlEl.value) fd.append('thumbnail_url', urlEl.value);
        if (resizeEl && resizeEl.value) fd.append('image_resize', resizeEl.value);
        if (fileEl && fileEl.files && fileEl.files[0]) {
            fd.append('image_file', fileEl.files[0]);
        }

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (btn) setBtnLoading(btn, false);
                if (res.success) {
                    let postUrl = res.data ? res.data.url : '';
                    let successMsg = res.data ? res.data.message : 'محتوا با موفقیت در سایت منتشر شد!';
                    if (typeof showToast === 'function') showToast(successMsg, 'success');

                    if (resultEl) {
                        resultEl.style.display = 'block';
                        resultEl.style.background = 'var(--success-soft, #ecfdf5)';
                        resultEl.style.border = '1px solid var(--success, #10b981)';
                        resultEl.innerHTML = `
                            <div style="color:var(--success, #065f46); font-weight:700; margin-bottom:8px;">✅ ${escapeHtml(successMsg)}</div>
                            ${postUrl ? `<a href="${encodeURI(postUrl)}" target="_blank" style="color:var(--accent); text-decoration:underline; font-size:0.9rem;">مشاهده مقاله در سایت ↗</a>` : ''}
                        `;
                    }
                } else {
                    let errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در انتشار';
                    if (typeof showToast === 'function') showToast(errMsg, 'error');
                    if (resultEl) {
                        resultEl.style.display = 'block';
                        resultEl.style.background = 'var(--error-soft, #fef2f2)';
                        resultEl.style.border = '1px solid var(--error, #ef4444)';
                        resultEl.innerHTML = `<div style="color:var(--error, #991b1b); font-weight:600;">❌ ${escapeHtml(errMsg)}</div>`;
                    }
                }
            })
            .catch(() => {
                if (btn) setBtnLoading(btn, false);
                if (typeof showToast === 'function') showToast('خطای شبکه در انتشار محتوا', 'error');
            });
    };

    window.cgPreview = function() {
        let titleEl = document.getElementById('cg_post_title');
        let contentEl = document.getElementById('cg_post_content') || document.getElementById('cg_content_editor');

        let title = titleEl ? titleEl.value : 'پیش‌نمایش مقاله';
        let content = contentEl ? contentEl.value : '';

        if (!content) {
            if (typeof showToast === 'function') showToast('محتوایی برای پیش‌نمایش وجود ندارد', 'error');
            return;
        }

        let modal = getOrCreateModal('ssp_preview_modal');
        modal.title.textContent = title;
        modal.body.innerHTML = `
            <div style="font-family: inherit; line-height: 1.8; color: var(--text);">
                <h1 style="font-size: 1.5rem; margin-bottom: 16px; border-bottom: 2px solid var(--border); padding-bottom: 10px;">${escapeHtml(title)}</h1>
                <div style="font-size: 0.95rem;">${content}</div>
            </div>
        `;
        modal.show();
    };

    window.cgSaveAsDraft = function() {
        let titleEl = document.getElementById('cg_post_title');
        let contentEl = document.getElementById('cg_post_content') || document.getElementById('cg_content_editor');

        let content = contentEl ? contentEl.value.trim() : '';
        if (!content) {
            if (typeof showToast === 'function') showToast('محتوایی برای ذخیره وجود ندارد', 'error');
            return;
        }

        let title = (titleEl && titleEl.value.trim()) ? titleEl.value.trim() : 'مقاله پیش‌نویس';

        let fd = new FormData();
        fd.append('action', 'ssp_save_draft');
        fd.append('security', window.nonce || '');
        fd.append('title', title);
        fd.append('content', content);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (typeof showToast === 'function') showToast('مقاله به عنوان پیش‌نویس ذخیره شد', 'success');
                } else {
                    if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در ذخیره', 'error');
                }
            })
            .catch(() => {
                if (typeof showToast === 'function') showToast('خطای شبکه در ذخیره مقاله', 'error');
            });
    };

    window.cgResetForm = function() {
        if (!confirm('آیا از ریست کردن فرم مقاله اطمینان دارید؟')) return;
        ['cg_ai_name', 'cg_ai_brief', 'cg_post_title', 'cg_post_tags', 'cg_meta_title', 'cg_meta_description', 'cg_thumbnail_url'].forEach(id => {
            let el = document.getElementById(id);
            if (el) el.value = '';
        });
        let content = document.getElementById('cg_post_content') || document.getElementById('cg_content_editor');
        if (content) content.value = '';
        if (typeof showToast === 'function') showToast('فرم پاک شد', 'info');
    };

    /* =========================================================================
       4. WOOCOMMERCE PRODUCT GENERATOR
       ========================================================================= */
    window.pgGenerateWithAI = function() {
        let btn = document.getElementById('pg_ai_btn');
        let nameEl = document.getElementById('pg_ai_name');
        let briefEl = document.getElementById('pg_ai_brief');
        let modeEl = document.getElementById('pg_prompt_mode');
        let customPromptEl = document.getElementById('pg_custom_prompt');
        let errEl = document.getElementById('pg_ai_error');

        if (errEl) errEl.style.display = 'none';

        let name = nameEl ? nameEl.value.trim() : '';
        if (!name) {
            if (typeof showToast === 'function') showToast('لطفاً نام یا عنوان محصول را وارد کنید', 'error');
            if (nameEl) nameEl.focus();
            return;
        }

        let brief = briefEl ? briefEl.value.trim() : '';
        let mode = modeEl ? modeEl.value : 'default_product';
        let customPrompt = customPromptEl ? customPromptEl.value.trim() : '';

        if (btn) setBtnLoading(btn, true);

        let fd = new FormData();
        fd.append('action', 'ssp_generate_product_ai');
        fd.append('security', window.nonce || '');
        fd.append('product_name', name);
        fd.append('product_brief', brief);
        fd.append('content_type', 'product');
        fd.append('prompt_mode', mode);
        fd.append('custom_prompt', customPrompt);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    let populateProductFields = function(data, tokensUsed) {
                        let nameInput = document.getElementById('pg_product_name');
                        let skuInput = document.getElementById('pg_sku');
                        let regPriceInput = document.getElementById('pg_regular_price');
                        let salePriceInput = document.getElementById('pg_sale_price');
                        let shortDescInput = document.getElementById('pg_short_desc');
                        let descInput = document.getElementById('pg_description');
                        let metaTitleInput = document.getElementById('pg_meta_title');
                        let metaDescInput = document.getElementById('pg_meta_description');
                        let tokensBadge = document.getElementById('pg_ai_tokens');

                        if (nameInput) nameInput.value = data.name || data.title || name;
                        if (skuInput && data.sku) skuInput.value = data.sku;
                        if (regPriceInput && (data.regular_price || data.price)) regPriceInput.value = data.regular_price || data.price;
                        if (salePriceInput && data.sale_price) salePriceInput.value = data.sale_price;
                        if (shortDescInput && (data.short_description || data.short_desc)) shortDescInput.value = data.short_description || data.short_desc;
                        if (descInput && (data.description || data.content)) descInput.value = data.description || data.content;
                        if (metaTitleInput && data.meta_title) metaTitleInput.value = data.meta_title;
                        if (metaDescInput && (data.meta_description || data.meta_desc)) metaDescInput.value = data.meta_description || data.meta_desc;

                        if (tokensBadge && tokensUsed) {
                            tokensBadge.textContent = `${tokensUsed} توکن`;
                            tokensBadge.style.display = 'inline-block';
                        }

                        if (typeof window.pgSiteChanged === 'function') {
                            window.pgSiteChanged();
                        }
                    };

                    if (res.data.mode === 'browser') {
                        if (typeof showToast === 'function') showToast('درخواست به چت‌بات ارسال شد. در حال پردازش...', 'info');
                        window._bridgeCurrentTool = 'productgen';
                        if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                        if (typeof window.startBridgePolling === 'function') {
                            window.startBridgePolling(res.data.task_id, function(bridgeData) {
                                if (btn) setBtnLoading(btn, false);
                                let data = bridgeData.parsed || extractJsonFromText(bridgeData.raw) || {};

                                // Clean up and normalize product fields
                                if (!data.description && !data.short_description && bridgeData.raw) {
                                    data.description = cleanAiRawText(bridgeData.raw);
                                }

                                if (data.title && !data.name) data.name = data.title;
                                if (data.price && !data.regular_price) data.regular_price = data.price;
                                if (data.short_desc && !data.short_description) data.short_description = data.short_desc;
                                if (data.desc && !data.description) data.description = data.desc;
                                if (data.content && !data.description) data.description = data.content;
                                if (data.meta_desc && !data.meta_description) data.meta_description = data.meta_desc;
                                if (data.name) data.name = cleanAiRawText(data.name);
                                if (data.short_description) data.short_description = cleanAiRawText(data.short_description);
                                if (data.description) data.description = cleanAiRawText(data.description);

                                populateProductFields(data, null);
                                if (typeof showToast === 'function') showToast('اطلاعات محصول با موفقیت از چت‌بات استخراج و در فرم قرار گرفت!', 'success');
                            }, function() { if (btn) setBtnLoading(btn, false); });
                        }
                        return;
                    }

                    if (btn) setBtnLoading(btn, false);
                    let data = res.data.data || res.data;
                    populateProductFields(data, res.data.tokens_used);
                    if (typeof showToast === 'function') showToast('اطلاعات محصول با موفقیت توسط هوش مصنوعی تکمیل شد!', 'success');
                } else {
                    if (btn) setBtnLoading(btn, false);
                    let errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در تولید اطلاعات محصول';
                    if (errEl) {
                        errEl.textContent = errMsg;
                        errEl.style.display = 'block';
                    }
                    if (typeof showToast === 'function') showToast(errMsg, 'error');
                }
            })
            .catch(err => {
                if (btn) setBtnLoading(btn, false);
                if (typeof showToast === 'function') showToast('خطای شبکه در تولید اطلاعات محصول', 'error');
            });
    };

    window.pgPublish = function() {
        let btn = document.getElementById('pg_publish_btn');
        let siteSelect = document.getElementById('pg_target_site');
        let nameEl = document.getElementById('pg_product_name');
        let skuEl = document.getElementById('pg_sku');
        let catEl = document.getElementById('pg_woo_categories');
        let statusEl = document.getElementById('pg_product_status');
        let regPriceEl = document.getElementById('pg_regular_price');
        let salePriceEl = document.getElementById('pg_sale_price');
        let shortDescEl = document.getElementById('pg_short_desc');
        let descEl = document.getElementById('pg_description');
        let weightEl = document.getElementById('pg_weight');
        let lengthEl = document.getElementById('pg_length');
        let widthEl = document.getElementById('pg_width');
        let heightEl = document.getElementById('pg_height');
        let metaTitleEl = document.getElementById('pg_meta_title');
        let metaDescEl = document.getElementById('pg_meta_description');
        let fileEl = document.getElementById('pg_thumbnail_file');
        let urlEl = document.getElementById('pg_thumbnail_url');
        let resizeEl = document.getElementById('pg_image_resize');
        let galleryFilesEl = document.getElementById('pg_gallery_files');
        let galleryUrlsEl = document.getElementById('pg_gallery_urls');
        let resultEl = document.getElementById('pg_result');

        let name = nameEl ? nameEl.value.trim() : '';
        if (!name) {
            if (typeof showToast === 'function') showToast('نام محصول نمی‌تواند خالی باشد', 'error');
            if (nameEl) nameEl.focus();
            return;
        }

        let siteId = siteSelect ? siteSelect.value : '';
        if (!siteId) {
            if (typeof showToast === 'function') showToast('لطفاً سایت مقصد را انتخاب کنید', 'error');
            return;
        }

        if (btn) setBtnLoading(btn, true);
        if (resultEl) resultEl.style.display = 'none';

        let fd = new FormData();
        fd.append('action', 'ssp_generate_product');
        fd.append('security', window.nonce || '');
        fd.append('site_id', siteId);
        fd.append('content_type', 'product');
        fd.append('product_name', name);
        if (skuEl && skuEl.value) fd.append('sku', skuEl.value);
        if (catEl && catEl.value) fd.append('category_ids', catEl.value);
        if (statusEl && statusEl.value) fd.append('status', statusEl.value);
        if (regPriceEl && regPriceEl.value) fd.append('regular_price', regPriceEl.value);
        if (salePriceEl && salePriceEl.value) fd.append('sale_price', salePriceEl.value);
        if (shortDescEl && shortDescEl.value) fd.append('short_description', shortDescEl.value);
        if (descEl && descEl.value) fd.append('description', descEl.value);
        if (weightEl && weightEl.value) fd.append('weight', weightEl.value);
        if (lengthEl && lengthEl.value) fd.append('length', lengthEl.value);
        if (widthEl && widthEl.value) fd.append('width', widthEl.value);
        if (heightEl && heightEl.value) fd.append('height', heightEl.value);
        if (metaTitleEl && metaTitleEl.value) fd.append('meta_title', metaTitleEl.value);
        if (metaDescEl && metaDescEl.value) fd.append('meta_description', metaDescEl.value);
        if (urlEl && urlEl.value) fd.append('thumbnail_url', urlEl.value);
        if (resizeEl && resizeEl.value) fd.append('image_resize', resizeEl.value);
        if (galleryUrlsEl && galleryUrlsEl.value) fd.append('gallery_urls', galleryUrlsEl.value);

        if (fileEl && fileEl.files && fileEl.files[0]) {
            fd.append('image_file', fileEl.files[0]);
        }
        if (galleryFilesEl && galleryFilesEl.files) {
            for (let i = 0; i < galleryFilesEl.files.length; i++) {
                fd.append('gallery_file_' + i, galleryFilesEl.files[i]);
            }
        }

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (btn) setBtnLoading(btn, false);
                if (res.success) {
                    let productUrl = res.data ? res.data.url : '';
                    let successMsg = res.data ? res.data.message : 'محصول با موفقیت در ووکامرس منتشر شد!';
                    if (typeof showToast === 'function') showToast(successMsg, 'success');

                    if (resultEl) {
                        resultEl.style.display = 'block';
                        resultEl.style.background = 'var(--success-soft, #ecfdf5)';
                        resultEl.style.border = '1px solid var(--success, #10b981)';
                        resultEl.innerHTML = `
                            <div style="color:var(--success, #065f46); font-weight:700; margin-bottom:8px;">✅ ${escapeHtml(successMsg)}</div>
                            ${productUrl ? `<a href="${encodeURI(productUrl)}" target="_blank" style="color:var(--accent); text-decoration:underline; font-size:0.9rem;">مشاهده محصول در فروشگاه ↗</a>` : ''}
                        `;
                    }
                } else {
                    let errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در انتشار محصول';
                    if (typeof showToast === 'function') showToast(errMsg, 'error');
                    if (resultEl) {
                        resultEl.style.display = 'block';
                        resultEl.style.background = 'var(--error-soft, #fef2f2)';
                        resultEl.style.border = '1px solid var(--error, #ef4444)';
                        resultEl.innerHTML = `<div style="color:var(--error, #991b1b); font-weight:600;">❌ ${escapeHtml(errMsg)}</div>`;
                    }
                }
            })
            .catch(() => {
                if (btn) setBtnLoading(btn, false);
                if (typeof showToast === 'function') showToast('خطای شبکه در ایجاد محصول ووکامرس', 'error');
            });
    };

    window.pgPreview = function() {
        let nameEl = document.getElementById('pg_product_name');
        let shortDescEl = document.getElementById('pg_short_desc');
        let descEl = document.getElementById('pg_description');
        let priceEl = document.getElementById('pg_regular_price');
        let salePriceEl = document.getElementById('pg_sale_price');

        let name = nameEl ? nameEl.value : 'پیش‌نمایش محصول';
        let shortDesc = shortDescEl ? shortDescEl.value : '';
        let desc = descEl ? descEl.value : '';
        let price = priceEl ? priceEl.value : '';
        let salePrice = salePriceEl ? salePriceEl.value : '';

        if (!name && !desc) {
            if (typeof showToast === 'function') showToast('اطلاعاتی برای پیش‌نمایش وجود ندارد', 'error');
            return;
        }

        let modal = getOrCreateModal('ssp_preview_modal');
        modal.title.textContent = name;
        modal.body.innerHTML = `
            <div style="font-family: inherit; line-height: 1.7; color: var(--text);">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid var(--border); padding-bottom: 12px; margin-bottom: 14px;">
                    <h2 style="font-size: 1.3rem; margin:0;">${escapeHtml(name)}</h2>
                    <div style="text-align:left;">
                        ${price ? `<span style="${salePrice ? 'text-decoration:line-through; color:var(--text-muted); font-size:0.9rem;' : 'font-weight:700; color:var(--accent); font-size:1.1rem;'}">${Number(price).toLocaleString()} تومان</span>` : ''}
                        ${salePrice ? `<div style="font-weight:700; color:var(--error); font-size:1.2rem;">${Number(salePrice).toLocaleString()} تومان</div>` : ''}
                    </div>
                </div>
                ${shortDesc ? `<div style="padding:10px 14px; background:var(--bg-alt); border-radius:8px; margin-bottom:14px; font-size:0.9rem; color:var(--text);">${shortDesc}</div>` : ''}
                <div style="font-size: 0.95rem; margin-top:10px;">${desc}</div>
            </div>
        `;
        modal.show();
    };

    /* =========================================================================
       5. BATCH CONTENT ENGINE & FAST GENERATOR
       ========================================================================= */
    window.batchGenerate = function() {
        let btn = document.getElementById('batch_gen_btn');
        let countEl = document.getElementById('batch_count');
        let styleEl = document.getElementById('batch_style');
        let toneEl = document.getElementById('batch_tone');
        let topicEl = document.getElementById('batch_topic');
        let lengthEl = document.getElementById('batch_length');
        let resultsWrap = document.getElementById('batch_results');

        let topic = topicEl ? topicEl.value.trim() : '';
        if (!topic) {
            if (typeof showToast === 'function') showToast('لطفاً موضوع محتوا را برای تولید دسته‌ای مشخص کنید', 'error');
            if (topicEl) topicEl.focus();
            return;
        }

        let count = countEl ? countEl.value : '3';
        let style = styleEl ? styleEl.value : 'general';
        let tone = toneEl ? toneEl.value : 'natural';
        let length = lengthEl ? lengthEl.value : '800';

        if (btn) setBtnLoading(btn, true);

        let fd = new FormData();
        fd.append('action', 'ssp_batch_generate');
        fd.append('security', window.nonce || '');
        fd.append('topic', topic);
        fd.append('count', count);
        fd.append('style', style);
        fd.append('tone', tone);
        fd.append('length', length);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    if (res.data.mode === 'browser') {
                        if (typeof showToast === 'function') showToast('درخواست به چت‌بات ارسال شد. در حال پردازش...', 'info');
                        window._bridgeCurrentTool = 'batchgen';
                        if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                        if (typeof window.startBridgePolling === 'function') {
                            window.startBridgePolling(res.data.task_id, function(bridgeData) {
                                if (btn) setBtnLoading(btn, false);
                                let items = [];
                                let parsed = bridgeData.parsed || extractJsonFromText(bridgeData.raw);
                                if (parsed) {
                                    if (Array.isArray(parsed)) items = parsed;
                                    else if (parsed.items && Array.isArray(parsed.items)) items = parsed.items;
                                    else if (parsed.posts && Array.isArray(parsed.posts)) items = parsed.posts;
                                    else if (parsed.ideas && Array.isArray(parsed.ideas)) items = parsed.ideas;
                                }
                                if (items.length === 0 && bridgeData.raw) {
                                    items = [{ title: topic, message: cleanAiRawText(bridgeData.raw) }];
                                }
                                renderBatchResults(items, resultsWrap);
                                if (typeof showToast === 'function') showToast(`${items.length} محتوا از چت‌بات دریافت شد!`, 'success');
                            }, function() { if (btn) setBtnLoading(btn, false); });
                        }
                        return;
                    }

                    if (btn) setBtnLoading(btn, false);
                    let items = res.data.items || [];
                    renderBatchResults(items, resultsWrap);
                    if (typeof showToast === 'function') showToast(`${items.length} محتوا به صورت دسته‌ای تولید شد!`, 'success');
                } else {
                    if (btn) setBtnLoading(btn, false);
                    let errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در تولید دسته‌ای';
                    if (typeof showToast === 'function') showToast(errMsg, 'error');
                }
            })
            .catch(() => {
                if (btn) setBtnLoading(btn, false);
                if (typeof showToast === 'function') showToast('خطای سرور در تولید دسته‌ای', 'error');
            });
    };

    window.cgBatchGenerate = function() {
        let btn = document.getElementById('cg_batch_gen_btn');
        let countEl = document.getElementById('cg_batch_count');
        let styleEl = document.getElementById('cg_batch_style');
        let topicEl = document.getElementById('cg_batch_topic');
        let resultsWrap = document.getElementById('cg_batch_results');

        let topic = topicEl ? topicEl.value.trim() : '';
        if (!topic) {
            if (typeof showToast === 'function') showToast('لطفاً موضوع را وارد کنید', 'error');
            if (topicEl) topicEl.focus();
            return;
        }

        let count = countEl ? countEl.value : '3';
        let style = styleEl ? styleEl.value : 'general';

        if (btn) setBtnLoading(btn, true);

        let fd = new FormData();
        fd.append('action', 'ssp_batch_generate');
        fd.append('security', window.nonce || '');
        fd.append('topic', topic);
        fd.append('count', count);
        fd.append('style', style);
        fd.append('length', 1200);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    if (res.data.mode === 'browser') {
                        if (typeof showToast === 'function') showToast('درخواست به چت‌بات ارسال شد. در حال پردازش...', 'info');
                        window._bridgeCurrentTool = 'batchgen';
                        if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                        if (typeof window.startBridgePolling === 'function') {
                            window.startBridgePolling(res.data.task_id, function(bridgeData) {
                                if (btn) setBtnLoading(btn, false);
                                let items = [];
                                let parsed = bridgeData.parsed || extractJsonFromText(bridgeData.raw);
                                if (parsed) {
                                    if (Array.isArray(parsed)) items = parsed;
                                    else if (parsed.items && Array.isArray(parsed.items)) items = parsed.items;
                                    else if (parsed.posts && Array.isArray(parsed.posts)) items = parsed.posts;
                                    else if (parsed.ideas && Array.isArray(parsed.ideas)) items = parsed.ideas;
                                }
                                if (items.length === 0 && bridgeData.raw) {
                                    items = [{ title: topic, message: cleanAiRawText(bridgeData.raw) }];
                                }
                                renderBatchResults(items, resultsWrap);
                                if (typeof showToast === 'function') showToast(`${items.length} محتوا از چت‌بات دریافت شد!`, 'success');
                            }, function() { if (btn) setBtnLoading(btn, false); });
                        }
                        return;
                    }

                    if (btn) setBtnLoading(btn, false);
                    let items = res.data.items || [];
                    renderBatchResults(items, resultsWrap);
                    if (typeof showToast === 'function') showToast(`${items.length} محتوا تولید شد`, 'success');
                } else {
                    if (btn) setBtnLoading(btn, false);
                    if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در تولید', 'error');
                }
            })
            .catch(() => {
                if (btn) setBtnLoading(btn, false);
                if (typeof showToast === 'function') showToast('خطای شبکه', 'error');
            });
    };

    function renderBatchResults(items, container) {
        if (!container) return;
        container.innerHTML = '';

        if (!items || items.length === 0) {
            container.innerHTML = '<div class="ssp-empty"><p>هیچ محتوایی تولید نشد.</p></div>';
            return;
        }

        let headerDiv = document.createElement('div');
        headerDiv.style.display = 'flex';
        headerDiv.style.justifyContent = 'space-between';
        headerDiv.style.alignItems = 'center';
        headerDiv.style.marginBottom = '14px';
        headerDiv.innerHTML = `
            <h4 style="margin:0;">نتایج تولید دسته‌ای (${items.length} مورد)</h4>
            <button type="button" class="ssp-btn-secondary" onclick="saveAllBatchDrafts()" style="font-size:0.8rem; padding:6px 12px;">💾 ذخیره همه در پیش‌نویس‌ها</button>
        `;
        container.appendChild(headerDiv);

        window._currentBatchItems = items;

        let grid = document.createElement('div');
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(320px, 1fr))';
        grid.style.gap = '14px';

        items.forEach((item, idx) => {
            let title = item.title || `محتوای شماره ${idx + 1}`;
            let message = item.message || item.content || '';

            let card = document.createElement('div');
            card.className = 'ssp-card';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.justifyContent = 'space-between';
            card.style.padding = '14px';
            card.style.borderRadius = '10px';
            card.style.border = '1px solid var(--border)';

            let safeTitleJs = escapeJsArg(title);
            let safeMsgJs = escapeJsArg(message);

            card.innerHTML = `
                <div>
                    <h4 style="margin:0 0 8px; font-size:0.9rem; font-weight:700; color:var(--text);">${escapeHtml(title)}</h4>
                    <p style="font-size:0.82rem; color:var(--text-muted); line-height:1.6; margin:0 0 12px; max-height:120px; overflow-y:auto;">${formatPostText(message)}</p>
                </div>
                <div style="border-top:1px solid var(--border); padding-top:10px; display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="ssp-btn-primary" onclick="transferIdeaToPost('${safeTitleJs}', '${safeMsgJs}')" style="font-size:0.75rem; padding:4px 8px; flex:1;">✍️ ارسال به پست‌ساز</button>
                    <button type="button" class="ssp-btn-secondary" onclick="saveSingleBatchDraft('${safeTitleJs}', '${safeMsgJs}')" style="font-size:0.75rem; padding:4px 8px;">💾 پیش‌نویس</button>
                    <button type="button" class="ssp-btn-secondary" onclick="copyIdeaText('${safeTitleJs}\\n\\n${safeMsgJs}')" style="font-size:0.75rem; padding:4px 8px;">📋 کپی</button>
                </div>
            `;
            grid.appendChild(card);
        });

        container.appendChild(grid);
    }

    window.saveSingleBatchDraft = function(title, content) {
        let fd = new FormData();
        fd.append('action', 'ssp_save_draft');
        fd.append('security', window.nonce || '');
        fd.append('title', title || 'پیش‌نویس جدید');
        fd.append('content', content || '');

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (typeof showToast === 'function') showToast('در پیش‌نویس‌ها ذخیره شد', 'success');
                } else {
                    if (typeof showToast === 'function') showToast('خطا در ذخیره', 'error');
                }
            });
    };

    window.saveAllBatchDrafts = function() {
        if (!window._currentBatchItems || window._currentBatchItems.length === 0) return;
        let count = window._currentBatchItems.length;
        let saved = 0;

        window._currentBatchItems.forEach(item => {
            let fd = new FormData();
            fd.append('action', 'ssp_save_draft');
            fd.append('security', window.nonce || '');
            fd.append('title', item.title || 'پیش‌نویس گروهی');
            fd.append('content', item.message || item.content || '');

            fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
                .then(() => {
                    saved++;
                    if (saved === count && typeof showToast === 'function') {
                        showToast(`همه ${count} مورد در پیش‌نویس‌ها ذخیره شدند!`, 'success');
                    }
                });
        });
    };

    /* =========================================================================
       6. SEO & UTILITY BRIDGES
       ========================================================================= */
    window.sendCgToSeo = function() {
        let contentEl = document.getElementById('cg_post_content') || document.getElementById('cg_content_editor');
        let titleEl = document.getElementById('cg_post_title');
        let content = contentEl ? contentEl.value : '';
        let title = titleEl ? titleEl.value : '';

        if (!content && !title) {
            if (typeof showToast === 'function') showToast('محتوایی برای تحلیل سئو وجود ندارد', 'error');
            return;
        }

        let seoContent = document.getElementById('seo_content');
        let seoKeyword = document.getElementById('seo_keyword');
        if (seoContent) seoContent.value = content;
        if (seoKeyword && title) seoKeyword.value = title;

        window.sspSwitchToAiTab('seo');
        if (typeof showToast === 'function') showToast('محتوا به ابزار سئو منتقل شد', 'success');
    };

    window.sendPgToSeo = function() {
        let nameEl = document.getElementById('pg_product_name');
        let descEl = document.getElementById('pg_description');
        let content = descEl ? descEl.value : '';
        let name = nameEl ? nameEl.value : '';

        if (!content && !name) {
            if (typeof showToast === 'function') showToast('محتوایی برای تحلیل سئو وجود ندارد', 'error');
            return;
        }

        let seoContent = document.getElementById('seo_content');
        let seoKeyword = document.getElementById('seo_keyword');
        if (seoContent) seoContent.value = content;
        if (seoKeyword && name) seoKeyword.value = name;

        window.sspSwitchToAiTab('seo');
        if (typeof showToast === 'function') showToast('اطلاعات محصول به ابزار سئو منتقل شد', 'success');
    };

    // Modal Helper
    function getOrCreateModal(id) {
        let existing = document.getElementById(id);
        if (existing) {
            return {
                title: existing.querySelector('.ssp-modal-title'),
                body: existing.querySelector('.ssp-modal-body'),
                show: function() { existing.style.display = 'flex'; },
                hide: function() { existing.style.display = 'none'; }
            };
        }

        let overlay = document.createElement('div');
        overlay.id = id;
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100%';
        overlay.style.height = '100%';
        overlay.style.background = 'rgba(0,0,0,0.5)';
        overlay.style.zIndex = '99999';
        overlay.style.display = 'none';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.padding = '20px';

        let box = document.createElement('div');
        box.style.background = 'var(--card, #ffffff)';
        box.style.borderRadius = '14px';
        box.style.width = '100%';
        box.style.maxWidth = '700px';
        box.style.maxHeight = '85vh';
        box.style.display = 'flex';
        box.style.flexDirection = 'column';
        box.style.boxShadow = '0 20px 25px -5px rgba(0, 0, 0, 0.2)';
        box.style.overflow = 'hidden';

        let header = document.createElement('div');
        header.style.padding = '16px 20px';
        header.style.borderBottom = '1px solid var(--border)';
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';

        let title = document.createElement('h3');
        title.className = 'ssp-modal-title';
        title.style.margin = '0';
        title.style.fontSize = '1.1rem';

        let closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.innerHTML = '&times;';
        closeBtn.style.background = 'none';
        closeBtn.style.border = 'none';
        closeBtn.style.fontSize = '1.6rem';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.color = 'var(--text-muted)';
        closeBtn.onclick = () => overlay.style.display = 'none';

        header.appendChild(title);
        header.appendChild(closeBtn);

        let body = document.createElement('div');
        body.className = 'ssp-modal-body';
        body.style.padding = '20px';
        body.style.overflowY = 'auto';
        body.style.flex = '1';

        let footer = document.createElement('div');
        footer.style.padding = '12px 20px';
        footer.style.borderTop = '1px solid var(--border)';
        footer.style.display = 'flex';
        footer.style.justifyContent = 'flex-end';

        let okBtn = document.createElement('button');
        okBtn.type = 'button';
        okBtn.className = 'ssp-btn-primary';
        okBtn.textContent = 'بستن';
        okBtn.onclick = () => overlay.style.display = 'none';
        footer.appendChild(okBtn);

        box.appendChild(header);
        box.appendChild(body);
        box.appendChild(footer);
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        overlay.onclick = (e) => { if (e.target === overlay) overlay.style.display = 'none'; };

        return {
            title: title,
            body: body,
            show: function() { overlay.style.display = 'flex'; },
            hide: function() { overlay.style.display = 'none'; }
        };
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeJsArg(str) {
        if (!str) return '';
        return String(str)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/"/g, '\\"')
            .replace(/\n/g, '\\n')
            .replace(/\r/g, '');
    }

})(window);
