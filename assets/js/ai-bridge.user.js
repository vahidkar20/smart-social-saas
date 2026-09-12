// ==UserScript==
// @name         Smart Automation Pro - AI Browser Bridge
// @namespace    https://smart-automation-pro/
// @version      4.7.0
// @description  Universal, ultra-resilient browser bridge connecting WordPress with AI Chatbots (DeepSeek, ChatGPT, Claude) with adaptive UI self-healing.
// @match        https://chat.deepseek.com/*
// @match        https://chatgpt.com/*
// @match        https://chat.openai.com/*
// @match        https://claude.ai/*
// @grant        GM_xmlhttpRequest
// @grant        GM.xmlHttpRequest
// @grant        GM_notification
// @grant        GM_setValue
// @grant        GM_getValue
// @grant        GM.setValue
// @grant        GM.getValue
// @connect      *
// @run-at       document-idle
// ==/UserScript==

(function() {
    'use strict';

    // Injectable config (Replaced by WordPress PHP if served dynamically, otherwise fallback to storage)
    var INJECTED_TOKEN = '{{SSP_BRIDGE_TOKEN}}';
    var INJECTED_SITE  = '{{SSP_WP_SITE_URL}}';
    
    var BRIDGE_TOKEN = INJECTED_TOKEN !== '{{SS' + 'P_BRIDGE_TOKEN}}' ? INJECTED_TOKEN : '';
    var WP_SITE_URL  = INJECTED_SITE !== '{{SS' + 'P_WP_SITE_URL}}' ? INJECTED_SITE : '';

    var POLL_INTERVAL = 2500;
    var isProcessing = false;
    var isPolling = false;
    var currentActiveTaskId = null;
    
    // Detect platform
    var host = window.location.hostname;
    var platform = 'deepseek';
    if (host.includes('chatgpt.com') || host.includes('openai.com')) {
        platform = 'chatgpt';
    } else if (host.includes('claude.ai')) {
        platform = 'claude';
    }

    // ===== CONSOLE LOGGING =====
    function log(level, message, ...args) {
        var prefix = '[SSP Bridge v4.7.0] ' + message;
        if (level === 'error') {
            console.error(prefix, ...args);
        } else if (level === 'warn') {
            console.warn(prefix, ...args);
        } else {
            console.log(prefix, ...args);
        }
    }

    // ===== UI STATUS WIDGET =====
    function createStatusWidget() {
        if (document.getElementById('ssp-bridge-widget')) return;
        var widget = document.createElement('div');
        widget.id = 'ssp-bridge-widget';
        widget.style.cssText = 'position:fixed;bottom:24px;left:24px;z-index:999999;background:rgba(24,24,27,0.94);backdrop-filter:blur(10px);color:#f4f4f5;padding:8px 16px;border-radius:24px;font-family:system-ui,-apple-system,sans-serif;font-size:12px;font-weight:500;display:flex;align-items:center;gap:10px;box-shadow:0 8px 24px rgba(0,0,0,0.4);border:1px solid rgba(255,255,255,0.15);transition:all 0.3s ease;user-select:none;';
        
        var indicator = document.createElement('div');
        indicator.id = 'ssp-bridge-indicator';
        indicator.style.cssText = 'width:9px;height:9px;border-radius:50%;background:#fbbf24;box-shadow:0 0 8px rgba(251,191,36,0.6);transition:background 0.3s ease;flex-shrink:0;';
        
        var text = document.createElement('span');
        text.id = 'ssp-bridge-text';
        text.textContent = 'Bridge: Initializing...';
        text.style.cssText = 'white-space:nowrap;';

        var sendNowBtn = document.createElement('button');
        sendNowBtn.id = 'ssp-bridge-send-btn';
        sendNowBtn.textContent = 'ارسال دستی به وردپرس';
        sendNowBtn.title = 'کلیک برای ارسال فوری متن/JSON تولیدشده به وردپرس';
        sendNowBtn.style.cssText = 'display:none;margin-right:4px;padding:4px 10px;border-radius:14px;background:#3b82f6;color:#ffffff;border:none;cursor:pointer;font-size:11px;font-weight:600;transition:all 0.2s;box-shadow:0 2px 6px rgba(59,130,246,0.4);';
        sendNowBtn.onmouseover = function() { sendNowBtn.style.background = '#2563eb'; };
        sendNowBtn.onmouseout = function() { sendNowBtn.style.background = '#3b82f6'; };
        sendNowBtn.onclick = function(e) {
            e.stopPropagation();
            var content = getLatestResponse();
            if (content && content.length > 10) {
                var tid = currentActiveTaskId;
                if (!tid) {
                    try {
                        var m = document.title || '';
                        tid = 'manual_' + Date.now();
                    } catch(e){}
                }
                updateWidget('processing', 'Sending manually...');
                sendResponseToPlugin(tid, content, 'completed').then(function() {
                    updateWidget('active', 'Sent to WordPress!');
                    setTimeout(function() { updateWidget('active', 'Bridge: Active'); }, 3000);
                });
            } else {
                alert('هنوز پاسخی در صفحه یافت نشد!');
            }
        };
        
        widget.appendChild(indicator);
        widget.appendChild(text);
        widget.appendChild(sendNowBtn);
        document.body.appendChild(widget);
    }

    function updateWidget(state, message) {
        var text = document.getElementById('ssp-bridge-text');
        var ind = document.getElementById('ssp-bridge-indicator');
        var sendBtn = document.getElementById('ssp-bridge-send-btn');
        if (!text || !ind) return;
        text.textContent = message;
        
        if (state === 'active') {
            ind.style.background = '#10b981'; // Emerald Green
            ind.style.boxShadow = '0 0 10px rgba(16,185,129,0.7)';
            if (sendBtn) sendBtn.style.display = 'none';
        } else if (state === 'processing') {
            ind.style.background = '#3b82f6'; // Blue
            ind.style.boxShadow = '0 0 10px rgba(59,130,246,0.7)';
            if (sendBtn) sendBtn.style.display = 'inline-block';
        } else if (state === 'error') {
            ind.style.background = '#ef4444'; // Red
            ind.style.boxShadow = '0 0 10px rgba(239,68,68,0.7)';
            if (sendBtn) sendBtn.style.display = 'none';
        } else {
            ind.style.background = '#fbbf24'; // Yellow
            ind.style.boxShadow = '0 0 8px rgba(251,191,36,0.5)';
            if (sendBtn) sendBtn.style.display = 'none';
        }
    }

    // ===== HTTP HELPERS =====
    function httpPost(url, data, headers) {
        return new Promise((resolve, reject) => {
            let fallbackUsed = false;
            let timeoutId = setTimeout(() => {
                if (fallbackUsed) return;
                log('warn', 'GM_xmlhttpRequest POST timeout, executing native fetch');
                fallbackUsed = true;
                doFetch();
            }, 6000);

            const doFetch = () => {
                fetch(url, {
                    method: 'POST',
                    headers: Object.assign({}, headers || {}, { 'Content-Type': 'application/json' }),
                    body: JSON.stringify(data)
                }).then(r => r.json()).then(res => {
                    clearTimeout(timeoutId);
                    resolve(res);
                }).catch(err => {
                    clearTimeout(timeoutId);
                    log('error', 'fetch POST error:', err);
                    reject(err);
                });
            };

            const requestFunc = (typeof GM !== 'undefined' && GM.xmlHttpRequest) ? GM.xmlHttpRequest : (typeof GM_xmlhttpRequest !== 'undefined' ? GM_xmlhttpRequest : null);

            if (requestFunc) {
                try {
                    requestFunc({
                        method: 'POST',
                        url: url,
                        headers: Object.assign({}, headers || {}, { 'Content-Type': 'application/json' }),
                        data: JSON.stringify(data),
                        timeout: 8000,
                        onload: function(res) {
                            if (fallbackUsed) return;
                            clearTimeout(timeoutId);
                            try {
                                resolve(JSON.parse(res.responseText));
                            } catch (e) {
                                resolve({ success: res.status >= 200 && res.status < 300, raw: res.responseText });
                            }
                        },
                        onerror: function(err) {
                            if (fallbackUsed) return;
                            clearTimeout(timeoutId);
                            log('warn', 'GM_xmlhttpRequest POST error, falling back to fetch', err);
                            fallbackUsed = true;
                            doFetch();
                        },
                        ontimeout: function() {
                            if (fallbackUsed) return;
                            clearTimeout(timeoutId);
                            log('warn', 'GM_xmlhttpRequest POST timed out, falling back to fetch');
                            fallbackUsed = true;
                            doFetch();
                        }
                    });
                } catch(e) {
                    if (!fallbackUsed) {
                        clearTimeout(timeoutId);
                        fallbackUsed = true;
                        doFetch();
                    }
                }
            } else {
                clearTimeout(timeoutId);
                doFetch();
            }
        });
    }

    function httpGet(url, headers) {
        return new Promise((resolve, reject) => {
            let fallbackUsed = false;
            let timeoutId = setTimeout(() => {
                if (fallbackUsed) return;
                log('warn', 'GM_xmlhttpRequest GET timeout, executing native fetch');
                fallbackUsed = true;
                doFetch();
            }, 6000);

            const doFetch = () => {
                fetch(url, {
                    method: 'GET',
                    headers: headers || {}
                }).then(r => r.json()).then(res => {
                    clearTimeout(timeoutId);
                    resolve(res);
                }).catch(err => {
                    clearTimeout(timeoutId);
                    log('error', 'fetch GET error:', err);
                    reject(err);
                });
            };

            const requestFunc = (typeof GM !== 'undefined' && GM.xmlHttpRequest) ? GM.xmlHttpRequest : (typeof GM_xmlhttpRequest !== 'undefined' ? GM_xmlhttpRequest : null);

            if (requestFunc) {
                try {
                    requestFunc({
                        method: 'GET',
                        url: url,
                        headers: headers || {},
                        timeout: 8000,
                        onload: function(res) {
                            if (fallbackUsed) return;
                            clearTimeout(timeoutId);
                            try {
                                resolve(JSON.parse(res.responseText));
                            } catch (e) {
                                resolve({ success: res.status >= 200 && res.status < 300, raw: res.responseText });
                            }
                        },
                        onerror: function(err) {
                            if (fallbackUsed) return;
                            clearTimeout(timeoutId);
                            log('warn', 'GM_xmlhttpRequest GET error, falling back to fetch', err);
                            fallbackUsed = true;
                            doFetch();
                        },
                        ontimeout: function() {
                            if (fallbackUsed) return;
                            clearTimeout(timeoutId);
                            log('warn', 'GM_xmlhttpRequest GET timed out, falling back to fetch');
                            fallbackUsed = true;
                            doFetch();
                        }
                    });
                } catch(e) {
                    if (!fallbackUsed) {
                        clearTimeout(timeoutId);
                        fallbackUsed = true;
                        doFetch();
                    }
                }
            } else {
                clearTimeout(timeoutId);
                doFetch();
            }
        });
    }

    function sendResponseToPlugin(taskId, responseText, status) {
        if (typeof responseText !== 'string') {
            try { responseText = JSON.stringify(responseText); } catch(e) { responseText = String(responseText || ''); }
        }
        var clean = responseText.replace(/\\n/g, '\n').replace(/\\r/g, '\r').replace(/\\t/g, '\t');
        clean = clean.replace(/nn(?=[a-zA-Z؀-ۿ])/g, '\n\n');

        log('info', 'Sending response for task ' + taskId + ' [status=' + status + ', length=' + clean.length + ' chars]');

        return httpPost(WP_SITE_URL + '/wp-json/ssp/v1/ai-bridge/response', {
            task_id: taskId,
            response: clean,
            response_text: clean,
            content: clean,
            status: status
        }, {
            'X-SSP-Bridge-Token': BRIDGE_TOKEN
        }).then(function(res) {
            log('info', 'WordPress acknowledged receipt of status: ' + status, res);
            if (status === 'completed' || status === 'error') {
                isProcessing = false;
                updateWidget('active', 'Bridge: Active');
            }
        }).catch(function(err) {
            log('error', 'Failed to send response to WordPress:', err);
            if (status === 'completed' || status === 'error') {
                isProcessing = false;
                updateWidget('active', 'Bridge: Active');
            }
        });
    }

    function reportError(taskId, error) {
        var msg = (error && error.message) ? error.message : String(error);
        sendResponseToPlugin(taskId, msg, 'error');
    }

    // ===== SAFE DOM DISPATCHERS =====
    function simulateClick(element) {
        if (!element) return false;
        try {
            element.focus();
            
            const eventOpts = {
                bubbles: true,
                cancelable: true,
                composed: true,
                button: 0,
                buttons: 1
            };
            
            try { element.dispatchEvent(new PointerEvent('pointerdown', eventOpts)); } catch(e){}
            try { element.dispatchEvent(new MouseEvent('mousedown', eventOpts)); } catch(e){}
            try { element.dispatchEvent(new PointerEvent('pointerup', eventOpts)); } catch(e){}
            try { element.dispatchEvent(new MouseEvent('mouseup', eventOpts)); } catch(e){}
            try { element.dispatchEvent(new MouseEvent('click', eventOpts)); } catch(e){}
            
            if (typeof element.click === 'function') {
                element.click();
            }
            return true;
        } catch(e) {
            log('warn', 'simulateClick fallback to .click():', e);
            try {
                if (typeof element.click === 'function') {
                    element.click();
                    return true;
                }
            } catch(e2){}
            return false;
        }
    }

    function simulateEnter(element) {
        if (!element) return false;
        try {
            element.focus();
            const eventOpts = {
                key: 'Enter',
                code: 'Enter',
                keyCode: 13,
                which: 13,
                charCode: 13,
                bubbles: true,
                cancelable: true,
                composed: true
            };
            element.dispatchEvent(new KeyboardEvent('keydown', eventOpts));
            element.dispatchEvent(new KeyboardEvent('keypress', eventOpts));
            element.dispatchEvent(new KeyboardEvent('keyup', eventOpts));
            return true;
        } catch(e) {
            log('warn', 'simulateEnter error:', e);
            return false;
        }
    }

    // ===== ADAPTIVE DOM SELECTORS =====
    function findAdaptiveChatInput() {
        const exactSelectors = [
            'textarea#chat-input',
            'div#prompt-textarea.ProseMirror[contenteditable="true"]',
            'div.ProseMirror[contenteditable="true"][role="textbox"]',
            'textarea#prompt-textarea',
            'div#prompt-textarea',
            'div[contenteditable="true"][role="textbox"]',
            'textarea[placeholder*="DeepSeek"]',
            'textarea[placeholder*="Message"]',
            'textarea[placeholder*="Ask"]',
            'textarea[placeholder*="ChatGPT"]',
            'div[contenteditable="true"][aria-label*="Prompt"]',
            'div[contenteditable="true"][aria-label*="Message"]',
            'div[contenteditable="true"][data-placeholder]',
            'fieldset textarea',
            'form textarea'
        ];

        for (let sel of exactSelectors) {
            try {
                const el = document.querySelector(sel);
                if (el && el.offsetParent !== null) return el;
            } catch(e){}
        }

        const candidates = document.querySelectorAll('div[contenteditable="true"], textarea, [role="textbox"]');
        let bestCandidate = null;
        let maxBottom = -1;

        for (let i = 0; i < candidates.length; i++) {
            const el = candidates[i];
            if (el.offsetParent === null) continue;
            if (el.getAttribute('aria-hidden') === 'true') continue;
            
            const rect = el.getBoundingClientRect();
            if (rect.top > window.innerHeight * 0.25 && rect.bottom > maxBottom && rect.height > 20) {
                maxBottom = rect.bottom;
                bestCandidate = el;
            }
        }

        return bestCandidate;
    }

    function findAdaptiveSendButton(inputElement) {
        const exactSelectors = [
            'button#chat-input-send-button',
            'div#chat-input-send-button',
            'div.ds-icon-button',
            'button[data-testid="send-button"]',
            'button[data-testid="fruitjuice-send-button"]',
            'button[aria-label*="Send"]',
            'button[aria-label*="ارسال"]',
            'div[role="button"][aria-label*="Send"]',
            'div[role="button"][aria-label*="ارسال"]',
            'div[class*="send-btn"]',
            'button[class*="send"]'
        ];

        for (let sel of exactSelectors) {
            try {
                const els = document.querySelectorAll(sel);
                for (let i = 0; i < els.length; i++) {
                    const el = els[i];
                    if (el && el.offsetParent !== null) return el;
                }
            } catch(e){}
        }

        if (inputElement) {
            let container = inputElement.closest('form, fieldset, [class*="input"], [class*="box"], [class*="prompt"], [class*="container"]');
            if (!container) container = inputElement.parentElement ? inputElement.parentElement.parentElement : null;

            if (container) {
                const clickables = container.querySelectorAll('button, div[role="button"], [class*="button"], [class*="btn"], div[tabindex="0"]');
                for (let i = clickables.length - 1; i >= 0; i--) {
                    const el = clickables[i];
                    if (el.offsetParent === null) continue;
                    
                    const label = (el.getAttribute('aria-label') || '').toLowerCase();
                    const testId = (el.getAttribute('data-testid') || '').toLowerCase();
                    const svg = el.querySelector('svg');

                    if (label.includes('send') || label.includes('ارسال') || testId.includes('send')) {
                        return el;
                    }
                    
                    if (svg && el.offsetWidth < 80 && el.offsetHeight < 80) {
                        if (!label.includes('attach') && !label.includes('file') && !label.includes('search') && !label.includes('think')) {
                            return el;
                        }
                    }
                }
            }
        }

        return null;
    }

    // ===== ULTRA-RESILIENT INPUT INJECTION =====
    function setInputValue(element, text) {
        if (!element) return false;
        
        if (typeof text !== 'string') {
            try { text = JSON.stringify(text); } catch(e) { text = String(text); }
        }

        element.focus();
        
        // Strategy A: Standard HTML TextArea / Input (e.g. DeepSeek textarea#chat-input)
        if (element.tagName === 'TEXTAREA' || element.tagName === 'INPUT') {
            try {
                element.select();
            } catch(e){}

            // 1. Try execCommand insertText first (native & trusted by frameworks)
            let execSuccess = false;
            try {
                execSuccess = document.execCommand('insertText', false, text);
            } catch(e){}

            // 2. If execCommand didn't update value, apply native setter with React tracker reset
            if (!execSuccess || element.value !== text) {
                try {
                    const proto = element.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
                    const nativeSetter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                    if (nativeSetter) {
                        nativeSetter.call(element, text);
                    } else {
                        element.value = text;
                    }
                } catch(e) {
                    element.value = text;
                }

                // CRITICAL: Reset React 16+ internal tracker to empty string so React notices the change!
                if (element._valueTracker) {
                    element._valueTracker.setValue('');
                }

                try {
                    element.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text }));
                } catch(e){}
                element.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
                element.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
            }
            return true;
        }
        
        // Strategy B: ContentEditable / ProseMirror (e.g. ChatGPT / Claude)
        if (element.isContentEditable || element.tagName === 'DIV' || element.getAttribute('role') === 'textbox') {
            element.focus();
            
            try {
                document.execCommand('selectAll', false, null);
                document.execCommand('delete', false, null);
                document.execCommand('insertText', false, text);
            } catch(e){}
            
            if (!element.innerText || element.innerText.trim().length === 0) {
                element.innerHTML = '';
                const p = document.createElement('p');
                const lines = text.split('\n');
                lines.forEach((line, i) => {
                    p.appendChild(document.createTextNode(line));
                    if (i < lines.length - 1) p.appendChild(document.createElement('br'));
                });
                element.appendChild(p);
            }

            element.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text, composed: true }));
            element.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
            element.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
            return true;
        }
        
        return false;
    }

    // ===== STREAM & GENERATION DETECTION =====
    function isStillStreaming() {
        // 1. Direct active stop button selectors across popular chatbots (DeepSeek, ChatGPT, Claude, Gemini, etc.)
        const stopSelectors = [
            'button[data-testid="stop-button"]',
            'button[aria-label*="Stop" i]',
            'div[role="button"][aria-label*="Stop" i]',
            'button[aria-label*="توقف" i]',
            'div[role="button"][aria-label*="توقف" i]',
            'button[aria-label*="Stop Generating" i]',
            'div[aria-label*="Stop Generating" i]',
            'button[aria-label*="Stop response" i]',
            'div[aria-label*="Stop response" i]',
            'button[aria-label*="Cancel" i]',
            'div[role="button"][aria-label*="Cancel" i]',
            '.ds-loading--anim',
            '.ds-loading',
            'span.streaming-cursor',
            '.result-streaming',
            '[data-is-streaming="true"]',
            'span.ds-cursor',
            'div.ds-cursor',
            'div[class*="result-thinking"]',
            'mat-spinner',
            'mat-progress-spinner'
        ];

        for (let sel of stopSelectors) {
            try {
                const el = document.querySelector(sel);
                if (el && el.offsetParent !== null && !el.disabled) return true;
            } catch(e){}
        }

        // 2. Generic check for any visible active button containing a stop square icon (<rect>)
        try {
            const stopIcons = document.querySelectorAll('button:not([disabled]) svg rect, div[role="button"]:not([aria-disabled="true"]) svg rect');
            for (let icon of stopIcons) {
                const btn = icon.closest('button, [role="button"]');
                if (btn && btn.offsetParent !== null) {
                    const ariaLabel = (btn.getAttribute('aria-label') || '').toLowerCase();
                    // Exclude copy, regenerate, thumbs up/down, or edit buttons that might have rects in SVG
                    if (ariaLabel.includes('copy') || ariaLabel.includes('edit') || ariaLabel.includes('like') || ariaLabel.includes('share')) {
                        continue;
                    }
                    return true;
                }
            }
        } catch(e){}

        return false;
    }

    function extractCleanText(node) {
        if (!node) return '';
        var clone = node.cloneNode(true);

        // 1. Remove all thought / reasoning containers
        var unwanted = clone.querySelectorAll('.ds-think, .ds-think-header, .ds-think-content, [class*="think"], [class*="thought"], [data-testid*="thought"], [data-testid*="reasoning"], [class*="reasoning"], details, summary, .ds-thought, .thought-container, [aria-label*="thought" i], [aria-label*="thinking" i]');
        for (var i = 0; i < unwanted.length; i++) {
            try { unwanted[i].remove(); } catch(e){}
        }

        // 2. Remove any remaining buttons / elements that just say "Thinking" or "Thought"
        var allEls = clone.querySelectorAll('*');
        for (var j = 0; j < allEls.length; j++) {
            var txt = (allEls[j].innerText || '').trim();
            if (/^(thinking|thought|reasoning|در حال تفکر|تفکر)(\.{1,3}|(\s+for\s+\d+\s+seconds))?$/i.test(txt)) {
                try { allEls[j].remove(); } catch(e){}
            }
        }

        var result = clone.innerText || '';

        // 3. Strip XML thought tags <think>...</think> and <thought>...</thought>
        result = result.replace(/<think>[\s\S]*?<\/think>/gi, '');
        result = result.replace(/<thought>[\s\S]*?<\/thought>/gi, '');

        // 4. Strip leading thought headers
        result = result.replace(/^(thinking|thought(\s+for\s+\d+\s+seconds)?|reasoning)\b[\s\S]*?\n\n/i, '');
        result = result.replace(/^(thinking|thought|reasoning)[\s\S]*?$/i, function(match) {
            if (/^(thinking|thought|reasoning)+$/i.test(match.trim())) return '';
            return match;
        });

        result = result.trim();

        // If after cleaning, only thinking artifacts remain, return empty
        if (/^(thinking|thought|reasoning)+$/i.test(result.replace(/\s+/g, ''))) {
            return '';
        }

        return result;
    }

    function findJsonInString(str) {
        if (!str || typeof str !== 'string') return null;
        
        // 1. Extract from Markdown code block
        var match = str.match(/```(?:json)?\s*([\s\S]*?)```/i);
        if (match && match[1]) {
            var trimmed = match[1].trim();
            if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
                return trimmed;
            }
        }
        
        // 2. Extract from outermost JSON object { ... }
        var firstBrace = str.indexOf('{');
        var lastBrace = str.lastIndexOf('}');
        if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
            var candidate = str.substring(firstBrace, lastBrace + 1).trim();
            if (candidate.length > 15 && (candidate.includes(':') || candidate.includes('"'))) {
                return candidate;
            }
        }
        
        // 3. Extract from outermost JSON array [ ... ]
        var firstBracket = str.indexOf('[');
        var lastBracket = str.lastIndexOf(']');
        if (firstBracket !== -1 && lastBracket !== -1 && lastBracket > firstBracket) {
            var candidateArr = str.substring(firstBracket, lastBracket + 1).trim();
            if (candidateArr.length > 15) {
                return candidateArr;
            }
        }
        
        return null;
    }

    function isReasoningContainer(el) {
        if (!el) return false;
        return !!(
            el.closest('.ds-think') ||
            el.closest('.ds-think-content') ||
            el.closest('.ds-think-header') ||
            el.closest('[class*="think"]') ||
            el.closest('[class*="thought"]') ||
            el.closest('[class*="reasoning"]') ||
            el.closest('details')
        );
    }

    function getLatestResponse() {
        // 1. Look for code blocks with JSON
        var codeBlocks = document.querySelectorAll('pre code, code');
        for (var k = codeBlocks.length - 1; k >= 0; k--) {
            var cEl = codeBlocks[k];
            if (isReasoningContainer(cEl)) continue;
            var cText = (cEl.innerText || '').trim();
            if ((cText.startsWith('{') && cText.endsWith('}')) || (cText.startsWith('[') && cText.endsWith(']'))) {
                if (cText.length > 20) return cText;
            }
        }

        // 2. Look for markdown content blocks
        var markdowns = document.querySelectorAll('.markdown, .prose, .ds-markdown--block, .ds-markdown, .markdown-body, [class*="markdown"]');
        var validCandidates = [];
        for (var i = 0; i < markdowns.length; i++) {
            var el = markdowns[i];
            if (isReasoningContainer(el)) continue;
            validCandidates.push(el);
        }
        
        if (validCandidates.length > 0) {
            var lastEl = validCandidates[validCandidates.length - 1];
            var cleanText = extractCleanText(lastEl);
            if (cleanText.length > 15) {
                var jsonExtracted = findJsonInString(cleanText);
                return jsonExtracted || cleanText;
            }
        }

        // 3. Look for assistant message wrappers
        var assistantMsgs = document.querySelectorAll('[data-message-author-role="assistant"], div[class*="assistant"], .ds-message, [class*="message-bubble"]');
        if (assistantMsgs.length > 0) {
            var lastContainer = assistantMsgs[assistantMsgs.length - 1];
            var rawClean = extractCleanText(lastContainer);
            if (rawClean.length > 15) {
                var jsonExt = findJsonInString(rawClean);
                return jsonExt || rawClean;
            }
        }
        
        return '';
    }

    function waitForResponse(initialContent, timeout = 240000) {
        return new Promise((resolve) => {
            const startTime = Date.now();
            let lastContent = initialContent || '';
            let stableCount = 0;
            let hasSeenChangeOrStream = false;
            
            log('info', 'Listening for chatbot output...');
            
            const checkInterval = setInterval(() => {
                try {
                    const currentContent = getLatestResponse();
                    const isStreaming = isStillStreaming();
                    
                    if (isStreaming) {
                        hasSeenChangeOrStream = true;
                        updateWidget('processing', 'AI Generating... (' + currentContent.length + ' chars)');
                    }
                    
                    // Filter out thinking-only strings from triggering change
                    const isThinkingText = /^(thinking|thought|reasoning)+$/i.test((currentContent || '').replace(/\s+/g, ''));
                    
                    if (currentContent && currentContent !== initialContent && currentContent.length > 20 && !isThinkingText) {
                        hasSeenChangeOrStream = true;
                    }
                    
                    if (hasSeenChangeOrStream && currentContent && currentContent.length > 20 && !isThinkingText) {
                        const hasJsonOpening = currentContent.includes('{') || currentContent.includes('[');
                        const hasJsonClosing = currentContent.includes('}') || currentContent.includes(']');
                        const isCompleteJson = (currentContent.includes('{') && currentContent.includes('}')) || (currentContent.includes('[') && currentContent.includes(']'));
                        
                        // If JSON opened but didn't close, it is definitely still generating!
                        if (hasJsonOpening && !hasJsonClosing) {
                            updateWidget('processing', 'Receiving JSON stream (' + currentContent.length + ' chars)...');
                            lastContent = currentContent;
                            stableCount = 0;
                            return;
                        }

                        // Check JSON parseability
                        let parsedSuccessfully = false;
                        if (isCompleteJson) {
                            try {
                                const jsonStr = findJsonInString(currentContent) || currentContent;
                                JSON.parse(jsonStr);
                                parsedSuccessfully = true;
                            } catch(e){}
                        }

                        if (currentContent === lastContent) {
                            stableCount++;
                            updateWidget('processing', 'Verifying response (' + currentContent.length + ' chars, ' + stableCount + '/2)...');

                            // Fast path 1: Valid parsed JSON + not streaming -> complete immediately!
                            if (parsedSuccessfully && !isStreaming) {
                                clearInterval(checkInterval);
                                log('info', 'Valid JSON response completed and verified! Length: ' + currentContent.length + ' chars');
                                resolve(currentContent);
                                return;
                            }

                            // Fast path 2: Valid parsed JSON and content stable for 2 ticks even if isStreaming was true (stale indicator)
                            if (parsedSuccessfully && stableCount >= 2) {
                                clearInterval(checkInterval);
                                log('info', 'Valid JSON response stabilized! Length: ' + currentContent.length + ' chars');
                                resolve(currentContent);
                                return;
                            }

                            // Path 3: General text not streaming and stable for 2 ticks
                            if (!isStreaming && stableCount >= 2) {
                                clearInterval(checkInterval);
                                log('info', 'Generation completed successfully! Length: ' + currentContent.length + ' chars');
                                resolve(currentContent);
                                return;
                            }

                            // Path 4: Fallback auto-resolve if content didn't change for 4 consecutive seconds
                            if (stableCount >= 4) {
                                clearInterval(checkInterval);
                                log('info', 'Self-healing auto-resolved stable output! Length: ' + currentContent.length + ' chars');
                                resolve(currentContent);
                                return;
                            }
                        } else {
                            stableCount = 0;
                            lastContent = currentContent;
                        }
                    }
                    
                    if (Date.now() - startTime > timeout) {
                        clearInterval(checkInterval);
                        log('error', 'Timeout waiting for chatbot response');
                        resolve(currentContent || 'TIMEOUT');
                    }
                } catch(err) {
                    log('error', 'Error inside waitForResponse cycle:', err);
                }
            }, 1000);
        });
    }

    function waitForAdaptiveInput(timeout = 15000) {
        return new Promise((resolve, reject) => {
            var el = findAdaptiveChatInput();
            if (el) return resolve(el);

            var observer = new MutationObserver(() => {
                var found = findAdaptiveChatInput();
                if (found) {
                    observer.disconnect();
                    resolve(found);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            setTimeout(() => {
                observer.disconnect();
                var fallback = findAdaptiveChatInput();
                if (fallback) resolve(fallback);
                else reject(new Error('Timeout waiting for chat input field'));
            }, timeout);
        });
    }

    // ===== TASK EXECUTION FLOW =====
    function handleTask(taskId, prompt) {
        currentActiveTaskId = taskId;
        isProcessing = true;
        updateWidget('processing', 'Locating input area...');
        sendResponseToPlugin(taskId, '', 'processing');
        
        const initialResponse = getLatestResponse();

        waitForAdaptiveInput(15000).then(input => {
            updateWidget('processing', 'Injecting prompt...');
            setInputValue(input, prompt);
            
            return new Promise(r => setTimeout(() => r(input), 300));
        }).then((input) => {
            updateWidget('processing', 'Submitting prompt...');
            
            return new Promise((resolve) => {
                let attempts = 0;
                const trySubmit = () => {
                    attempts++;
                    
                    // Verify if text was cleared by React. If cleared, re-inject!
                    const currentVal = input.tagName === 'TEXTAREA' || input.tagName === 'INPUT' ? input.value : (input.innerText || '');
                    if (!currentVal || currentVal.trim().length === 0) {
                        log('warn', 'Prompt value was emptied by framework, re-injecting...');
                        setInputValue(input, prompt);
                    }

                    // 1. Try finding and clicking send button
                    const sendBtn = findAdaptiveSendButton(input);
                    if (sendBtn) {
                        log('info', 'Found send button. Simulating click.');
                        simulateClick(sendBtn);
                    }

                    // 2. Dispatch Enter key
                    simulateEnter(input);

                    setTimeout(() => {
                        const isStreaming = isStillStreaming();
                        if (isStreaming || attempts >= 5) {
                            log('info', 'Prompt submission confirmed (streaming=' + isStreaming + ', attempts=' + attempts + ')');
                            resolve();
                        } else {
                            trySubmit();
                        }
                    }, 500);
                };
                
                trySubmit();
            });
        }).then(() => {
            updateWidget('processing', 'Waiting for AI output...');
            return waitForResponse(initialResponse, 240000);
        }).then(response => {
            isProcessing = false;
            currentActiveTaskId = null;
            if (response && response.length > 10 && response !== 'TIMEOUT') {
                log('info', 'Dispatching COMPLETED response to WordPress [Task ' + taskId + ']');
                updateWidget('active', 'Sent to WordPress!');
                sendResponseToPlugin(taskId, response, 'completed');
            } else {
                sendResponseToPlugin(taskId, response || 'No response generated', 'error');
            }
        }).catch(err => {
            isProcessing = false;
            currentActiveTaskId = null;
            log('error', 'Task execution failure:', err);
            reportError(taskId, err);
        });
    }

    // ===== TASK POLLING =====
    function pollForTasks() {
        if (isProcessing || isPolling || !BRIDGE_TOKEN || !WP_SITE_URL) return;
        isPolling = true;

        httpGet(WP_SITE_URL + '/wp-json/ssp/v1/ai-bridge/pending', {
            'X-SSP-Bridge-Token': BRIDGE_TOKEN
        }).then(res => {
            if (res && res.task_id && res.prompt) {
                log('info', 'New task received from WordPress: ' + res.task_id);
                isProcessing = true;
                updateWidget('processing', 'Task in progress...');
                handleTask(res.task_id, res.prompt);
            }
            isPolling = false;
        }).catch(err => {
            log('error', 'Polling check failed:', err);
            isPolling = false;
        });
    }

    // ===== INITIALIZATION =====
    async function init() {
        if (!BRIDGE_TOKEN || BRIDGE_TOKEN.startsWith('{{')) {
            try {
                const getVal = typeof GM !== 'undefined' && GM.getValue ? GM.getValue : GM_getValue;
                const setVal = typeof GM !== 'undefined' && GM.setValue ? GM.setValue : GM_setValue;
                
                BRIDGE_TOKEN = await getVal('bridge_token', '');
                WP_SITE_URL = await getVal('wp_site_url', '');

                if (!BRIDGE_TOKEN || !WP_SITE_URL) {
                    BRIDGE_TOKEN = prompt('لطفاً توکن پل ارتباطی را وارد کنید (Token):');
                    WP_SITE_URL = prompt('لطفاً آدرس سایت وردپرسی خود را وارد کنید (مثلا https://site.com):');
                    if (BRIDGE_TOKEN && WP_SITE_URL) {
                        try {
                            await setVal('bridge_token', BRIDGE_TOKEN);
                            await setVal('wp_site_url', WP_SITE_URL);
                        } catch(e){}
                    } else {
                        return;
                    }
                }
            } catch(e){}
        }

        if (document.body) {
            createStatusWidget();
            updateWidget('active', 'Bridge: Active');
            setInterval(pollForTasks, POLL_INTERVAL);
            log('info', 'Initialized Universal AI Browser Bridge (v4.6.0) on ' + host);
            log('info', 'Connected site: ' + WP_SITE_URL);
            
            httpGet(WP_SITE_URL + '/wp-json/ssp/v1/ai-bridge/pending', {
                'X-SSP-Bridge-Token': BRIDGE_TOKEN
            }).then(() => {
                log('info', 'Plugin connectivity test passed successfully');
                updateWidget('active', 'Bridge: Active');
            }).catch(err => {
                log('error', 'Plugin connectivity test failed:', err);
                updateWidget('error', 'Connection failed');
            });
        } else {
            setTimeout(init, 500);
        }
    }

    init();

})();
