const fs = require('fs');

const fullScript = `// ==UserScript==
// @name         Smart Automation Pro - AI Browser Bridge
// @namespace    https://smart-automation-pro/
// @version      4.1.0
// @description  Bridges WordPress plugin with chatbot web interfaces (SnapMonkey compatible)
// @match        https://chat.deepseek.com/*
// @match        https://chatgpt.com/*
// @match        https://chat.openai.com/*
// @grant        GM_xmlhttpRequest
// @grant        GM_notification
// @grant        GM_setValue
// @grant        GM_getValue
// @connect      *
// @run-at       document-idle
// ==/UserScript==

(function() {
    'use strict';

    // Injectable config (These will be replaced by the PHP script if injected, otherwise we fallback to GM_getValue)
    var INJECTED_TOKEN = '{{SSP_BRIDGE_TOKEN}}';
    var INJECTED_SITE  = '{{SSP_WP_SITE_URL}}';
    
    var BRIDGE_TOKEN = INJECTED_TOKEN !== '{{SS' + 'P_BRIDGE_TOKEN}}' ? INJECTED_TOKEN : '';
    var WP_SITE_URL  = INJECTED_SITE !== '{{SS' + 'P_WP_SITE_URL}}' ? INJECTED_SITE : '';

    var POLL_INTERVAL = 3000;
    var isProcessing = false;
    var isPolling = false;
    var platform = window.location.hostname.includes('chatgpt.com') || window.location.hostname.includes('openai.com') ? 'chatgpt' : 'deepseek';

    // ===== LOGGING =====
    const LOG_LEVEL = 'debug';
    function log(level, message, ...args) {
        const levels = { debug: 0, info: 1, error: 2 };
        if (levels[level] >= levels[LOG_LEVEL]) {
            console[level]('[SSP Bridge] ' + message, ...args);
        }
    }

    // ===== UI WIDGET =====
    function createStatusWidget() {
        if (document.getElementById('ssp-bridge-widget')) return;
        var widget = document.createElement('div');
        widget.id = 'ssp-bridge-widget';
        widget.style.cssText = 'position:fixed;bottom:20px;left:20px;z-index:999999;background:#1e1e1e;color:#fff;padding:8px 16px;border-radius:20px;font-family:sans-serif;font-size:12px;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);border:1px solid #333;transition:all 0.3s ease;';
        
        var indicator = document.createElement('div');
        indicator.id = 'ssp-bridge-indicator';
        indicator.style.cssText = 'width:8px;height:8px;border-radius:50%;background:#fbbf24;';
        
        var text = document.createElement('span');
        text.id = 'ssp-bridge-text';
        text.textContent = 'Bridge: Initializing...';
        
        widget.appendChild(indicator);
        widget.appendChild(text);
        document.body.appendChild(widget);
    }

    function updateWidget(state, message) {
        var text = document.getElementById('ssp-bridge-text');
        var ind = document.getElementById('ssp-bridge-indicator');
        if (!text || !ind) return;
        text.textContent = message;
        
        if (state === 'active') {
            ind.style.background = '#10b981'; // Green
        } else if (state === 'processing') {
            ind.style.background = '#3b82f6'; // Blue
        } else if (state === 'error') {
            ind.style.background = '#ef4444'; // Red
        } else {
            ind.style.background = '#fbbf24'; // Yellow
        }
    }

    // ===== HTTP WRAPPERS =====
    function httpPost(url, data, headers) {
        return new Promise((resolve, reject) => {
            let fallbackUsed = false;
            let timeoutId = setTimeout(() => {
                if (fallbackUsed) return;
                log('warn', 'GM_xmlhttpRequest POST hung, falling back to fetch');
                fallbackUsed = true;
                doFetch();
            }, 5000);

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

            if (typeof GM_xmlhttpRequest !== 'undefined') {
                try {
                    GM_xmlhttpRequest({
                        method: 'POST',
                        url: url,
                        headers: Object.assign({}, headers || {}, { 'Content-Type': 'application/json' }),
                        data: JSON.stringify(data),
                        timeout: 5000,
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
                            log('warn', 'GM_xmlhttpRequest POST timeout, falling back to fetch');
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
                log('warn', 'GM_xmlhttpRequest GET hung, falling back to fetch');
                fallbackUsed = true;
                doFetch();
            }, 5000);

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

            if (typeof GM_xmlhttpRequest !== 'undefined') {
                try {
                    GM_xmlhttpRequest({
                        method: 'GET',
                        url: url,
                        headers: headers || {},
                        timeout: 5000,
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
                            log('warn', 'GM_xmlhttpRequest GET timeout, falling back to fetch');
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
        var clean = responseText.replace(/\\\\n/g, '\\n').replace(/\\\\r/g, '\\r').replace(/\\\\t/g, '\\t');
        clean = clean.replace(/nn(?=[a-zA-Z\u0600-\u06FF])/g, '\\n\\n');

        return httpPost(WP_SITE_URL + '/wp-json/ssp/v1/ai-bridge/response', {
            task_id: taskId,
            response: clean,
            status: status
        }, {
            'X-SSP-Bridge-Token': BRIDGE_TOKEN
        }).then(function() {
            log('info', 'Status ' + status + ' sent to plugin');
            if (status === 'completed' || status === 'error') {
                isProcessing = false;
                updateWidget('active', 'Bridge: Active');
            }
        }).catch(function(err) {
            log('error', 'Failed to send status to plugin:', err);
            if (status === 'completed' || status === 'error') {
                isProcessing = false;
            }
        });
    }

    function reportError(taskId, error) {
        sendResponseToPlugin(taskId, (error.message || String(error)), 'error');
    }

    // ===== DOM SELECTORS =====
    function getDeepSeekInput() {
        const selectors = [
            'textarea#chat-input',
            'textarea[placeholder*="DeepSeek"]',
            'textarea[placeholder*="Message"]',
            'div[contenteditable="true"][role="textbox"]',
            'form textarea',
            'textarea'
        ];
        for (let sel of selectors) {
            const el = document.querySelector(sel);
            if (el) return el;
        }
        return null;
    }

    function getDeepSeekSendButton() {
        const selectors = [
            'div.ds-icon-button',
            'button#chat-input-send-button:not([disabled])',
            'button[aria-label*="Send"]:not([disabled])',
            'div[role="button"][aria-label*="Send"]:not([aria-disabled="true"])',
            'div[class*="send-btn"]',
            'button[class*="send"]:not([disabled])'
        ];
        for (let sel of selectors) {
            const btn = document.querySelector(sel);
            if (btn && !btn.disabled && btn.getAttribute('aria-disabled') !== 'true') return btn;
        }
        return null;
    }

    function getChatGPTInput() {
        const selectors = [
            'div#prompt-textarea.ProseMirror[contenteditable="true"]',
            'div.ProseMirror[contenteditable="true"][role="textbox"]',
            'div[contenteditable="true"]:not([role="presentation"])',
            'textarea#prompt-textarea',
            'div#prompt-textarea'
        ];
        for (let sel of selectors) {
            const el = document.querySelector(sel);
            if (el) return el;
        }
        return null;
    }

    function getChatGPTSendButton() {
        const selectors = [
            'button[data-testid="send-button"]:not([disabled])',
            'button[data-testid="fruitjuice-send-button"]:not([disabled])',
            'button[aria-label*="Send"][type="submit"]'
        ];
        for (let sel of selectors) {
            const btn = document.querySelector(sel);
            if (btn && !btn.disabled && btn.getAttribute('aria-disabled') !== 'true') return btn;
        }
        return null;
    }

    // ===== INPUT MANIPULATION =====
    function setContentEditableValue(element, text) {
        element.focus();
        element.innerHTML = '';
        const p = document.createElement('p');
        p.textContent = text;
        element.appendChild(p);
        
        const range = document.createRange();
        range.selectNodeContents(element);
        range.collapse(false);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        
        element.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text, composed: true }));
        element.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
        
        if (element._valueTracker) {
            element._valueTracker.setValue('');
        }
    }

    function setTextareaValue(textarea, text) {
        textarea.focus();
        textarea.select();
        
        var execSuccess = false;
        try { execSuccess = document.execCommand('insertText', false, text); } catch(e){}

        if (!execSuccess || textarea.value !== text) {
            try {
                var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
                nativeSetter.call(textarea, text);
            } catch (e) {
                textarea.value = text;
            }
        }

        if (textarea._valueTracker) {
            textarea._valueTracker.setValue('');
        }

        textarea.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text, composed: true }));
        textarea.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
    }

    function setInputValue(element, text) {
        if (!element) return false;
        if (element.tagName === 'TEXTAREA') {
            setTextareaValue(element, text);
            return true;
        }
        if (element.isContentEditable) {
            setContentEditableValue(element, text);
            return true;
        }
        return false;
    }

    // ===== STREAM DETECTION =====
    function isStillStreaming(plat) {
        if (plat === 'chatgpt') {
            const stopBtn = document.querySelector('button[data-testid="stop-button"]');
            if (stopBtn) return true;
            const sendBtn = document.querySelector('button[data-testid="send-button"]');
            if (sendBtn && (sendBtn.disabled || sendBtn.getAttribute('aria-disabled') === 'true')) return true;
            if (document.querySelector('.result-streaming')) return true;
        } else if (plat === 'deepseek') {
            const sendBtn = getDeepSeekSendButton();
            if (sendBtn && (sendBtn.disabled || sendBtn.getAttribute('aria-disabled') === 'true')) return true;
            if (document.querySelector('button[aria-label*="Stop"]')) return true;
            if (document.querySelector('div[class*="loading"]')) return true;
            if (document.querySelector('div[class*="generating"]')) return true;
            if (document.querySelector('.ds-think-content, .typing-indicator')) return true;
            var allBtns = document.querySelectorAll('button');
            for (var i = 0; i < allBtns.length; i++) {
                var txt = allBtns[i].textContent.toLowerCase();
                if (txt.indexOf('stop') !== -1 || txt.indexOf('توقف') !== -1) return true;
            }
        }
        return false;
    }

    function getLatestResponse(plat) {
        var assistantMsgs = document.querySelectorAll('[data-message-author-role="assistant"], div[class*="assistant"]');
        var container = assistantMsgs.length > 0 ? assistantMsgs[assistantMsgs.length - 1] : document.body;
        
        var selectors = ['.ds-markdown--block', '.ds-markdown', '.markdown-body', '[class*="markdown"]'];
        for (var i = 0; i < selectors.length; i++) {
            var elements = container.querySelectorAll(selectors[i]);
            if (elements.length > 0) {
                var targetEl = elements[elements.length - 1];
                for (var j = elements.length - 1; j >= 0; j--) {
                    var elItem = elements[j];
                    if (!elItem.closest('.ds-think-content') && !elItem.classList.contains('ds-think-content') && !elItem.closest('[class*="think"]')) {
                        targetEl = elItem;
                        break;
                    }
                }
                var text = targetEl.innerText.trim();
                if (text.length > 10) return text;
            }
        }
        return '';
    }

    function waitForResponse(plat, timeout = 300000) {
        return new Promise((resolve) => {
            const startTime = Date.now();
            let lastContent = '';
            let stableCount = 0;
            
            const checkInterval = setInterval(() => {
                const currentContent = getLatestResponse(plat);
                const isStreaming = isStillStreaming(plat);
                
                if (!isStreaming && currentContent === lastContent && currentContent.length > 20) {
                    stableCount++;
                    if (stableCount >= 3) { // 3 seconds stable
                        clearInterval(checkInterval);
                        resolve(currentContent);
                        return;
                    }
                } else {
                    stableCount = 0;
                    lastContent = currentContent;
                }
                
                if (Date.now() - startTime > timeout) {
                    clearInterval(checkInterval);
                    resolve(currentContent || 'TIMEOUT');
                }
            }, 1000);
        });
    }

    function waitForElement(getter, timeout = 15000) {
        return new Promise((resolve, reject) => {
            var el = getter();
            if (el) return resolve(el);

            var observer = new MutationObserver(() => {
                var found = getter();
                if (found) {
                    observer.disconnect();
                    resolve(found);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            setTimeout(() => {
                observer.disconnect();
                reject(new Error('Timeout waiting for element'));
            }, timeout);
        });
    }

    // ===== TASK HANDLING =====
    function handleTask(taskId, prompt) {
        var inputGetter = platform === 'chatgpt' ? getChatGPTInput : getDeepSeekInput;
        var btnGetter = platform === 'chatgpt' ? getChatGPTSendButton : getDeepSeekSendButton;

        updateWidget('processing', 'Waiting for input...');
        sendResponseToPlugin(taskId, '', 'processing');
        
        waitForElement(inputGetter, 15000).then(input => {
            updateWidget('processing', 'Setting prompt...');
            var success = setInputValue(input, prompt);
            if (!success) throw new Error('Failed to set input value');
            
            return new Promise(r => setTimeout(r, 1200));
        }).then(() => {
            updateWidget('processing', 'Sending...');
            
            var input = inputGetter();
            if (input) {
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            
            var sendBtn = btnGetter();
            if (sendBtn) {
                sendBtn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
                sendBtn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
                sendBtn.click();
            } else if (input) {
                // Fallback to enter key
                input.focus();
                input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true, composed: true }));
                input.dispatchEvent(new KeyboardEvent('keyup', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true, composed: true }));
            }
            
            updateWidget('processing', 'Waiting for AI...');
            return waitForResponse(platform, 300000);
        }).then(response => {
            if (response && response.length > 10 && response !== 'TIMEOUT') {
                sendResponseToPlugin(taskId, response, 'completed');
            } else {
                sendResponseToPlugin(taskId, response || 'No response generated', 'error');
            }
        }).catch(err => {
            log('error', 'Task error:', err);
            reportError(taskId, err);
        });
    }

    // ===== POLLING =====
    function pollForTasks() {
        if (isProcessing || isPolling || !BRIDGE_TOKEN || !WP_SITE_URL) return;
        isPolling = true;

        httpGet(WP_SITE_URL + '/wp-json/ssp/v1/ai-bridge/pending', {
            'X-SSP-Bridge-Token': BRIDGE_TOKEN
        }).then(res => {
            if (res && res.task_id && res.prompt) {
                log('info', 'Task received: ' + res.task_id);
                isProcessing = true;
                updateWidget('processing', 'Processing task...');
                handleTask(res.task_id, res.prompt);
            }
            isPolling = false;
        }).catch(err => {
            log('error', 'Poll error:', err);
            isPolling = false;
        });
    }

    // ===== INITIALIZATION =====
    async function init() {
        // If not injected via PHP, fallback to GM_getValue storage
        if (!BRIDGE_TOKEN || BRIDGE_TOKEN.startsWith('{{')) {
            try {
                BRIDGE_TOKEN = await GM_getValue('bridge_token', '');
                WP_SITE_URL = await GM_getValue('wp_site_url', '');
            } catch(e){}
            
            if (!BRIDGE_TOKEN || !WP_SITE_URL) {
                BRIDGE_TOKEN = prompt('لطفاً توکن پل ارتباطی را وارد کنید (Token):');
                WP_SITE_URL = prompt('لطفاً آدرس سایت وردپرسی خود را وارد کنید (مثلا https://site.com):');
                if (BRIDGE_TOKEN && WP_SITE_URL) {
                    try {
                        await GM_setValue('bridge_token', BRIDGE_TOKEN);
                        await GM_setValue('wp_site_url', WP_SITE_URL);
                    } catch(e){}
                } else {
                    return; // Abort
                }
            }
        }

        if (document.body) {
            createStatusWidget();
            updateWidget('active', 'Bridge: Active');
            setInterval(pollForTasks, POLL_INTERVAL);
            log('info', '===== INITIALIZED =====');
            log('info', 'Platform: ' + platform);
            log('info', 'Site: ' + WP_SITE_URL);
            
            // Initial connection test
            httpGet(WP_SITE_URL + '/wp-json/ssp/v1/ai-bridge/pending', {
                'X-SSP-Bridge-Token': BRIDGE_TOKEN
            }).then(() => {
                log('info', 'CONNECTIVITY TEST PASSED');
                updateWidget('active', 'Bridge: Active');
            }).catch(err => {
                log('error', 'CONNECTIVITY TEST FAILED', err);
                updateWidget('error', 'Connection failed!');
            });
        } else {
            setTimeout(init, 500);
        }
    }

    init();

})();
`;
fs.writeFileSync('assets/js/ai-bridge.user.js', fullScript);
console.log('Script fully rewritten.');
