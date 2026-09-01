// ==UserScript==
// @name         Smart Automation Pro - AI Browser Bridge
// @namespace    https://smart-automation-pro/
// @version      4.0.0
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

    // ===== CONFIGURATION (hardcoded by PHP) =====
    var BRIDGE_TOKEN = '{{SSP_BRIDGE_TOKEN}}';
    var WP_SITE_URL = '{{SSP_WP_SITE_URL}}';

    console.log('[SSP Bridge] Script loaded. Token: ' + (BRIDGE_TOKEN ? BRIDGE_TOKEN.substring(0, 12) + '...' : 'MISSING'));

    if (!BRIDGE_TOKEN || BRIDGE_TOKEN.indexOf('{{') === 0 || !WP_SITE_URL || WP_SITE_URL.indexOf('{{') === 0) {
        console.error('[SSP Bridge] Token or Site not configured! Script halted.');
        var errDiv = document.createElement('div');
        errDiv.style.cssText = 'position:fixed;bottom:20px;left:20px;z-index:99999;background:#ef4444;color:white;padding:10px 16px;border-radius:8px;font-size:13px;font-family:system-ui,sans-serif;';
        errDiv.textContent = 'SSP Bridge: Token/Site not configured!';
        document.body.appendChild(errDiv);
        return;
    }

    var POLL_INTERVAL = 3000;
    var isProcessing = false;

    // ===== PLATFORM DETECTION =====
    function detectPlatform() {
        var host = window.location.hostname;
        if (host.indexOf('deepseek.com') !== -1) return 'deepseek';
        if (host.indexOf('chatgpt.com') !== -1 || host.indexOf('chat.openai.com') !== -1) return 'chatgpt';
        return null;
    }

    var platform = detectPlatform();
    if (!platform) return;

    // ===== DOM SELECTORS =====
    var SELECTORS = {
        deepseek: {
            input: function() {
                return document.querySelector('form textarea') ||
                       document.querySelector('textarea[placeholder*="Message"]') ||
                       document.querySelector('textarea');
            },
            sendViaEnter: function(input) {
                input.focus();
                input.dispatchEvent(new KeyboardEvent('keydown', {
                    key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true
                }));
            },
            isStreaming: function() {
                // Check multiple indicators of streaming
                var sendBtn = document.querySelector('button[type="submit"]');
                if (sendBtn && sendBtn.disabled) return true;
                if (document.querySelector('button[aria-label*="Stop"]')) return true;
                if (document.querySelector('button[aria-label*="stop"]')) return true;
                if (document.querySelector('div[class*="loading"]')) return true;
                if (document.querySelector('span[class*="cursor"]')) return true;
                if (document.querySelector('div[class*="generating"]')) return true;
                if (document.querySelector('div[class*="typing"]')) return true;
                // Check if the send button text changed to "Stop" or similar
                var allBtns = document.querySelectorAll('button');
                for (var i = 0; i < allBtns.length; i++) {
                    var txt = allBtns[i].textContent.toLowerCase();
                    if (txt.indexOf('stop') !== -1 || txt.indexOf('توقف') !== -1) return true;
                }
                return false;
            },
            getLatestResponse: function() {
                // Priority selectors for DeepSeek (ds- namespace)
                var selectors = [
                    '.ds-markdown',
                    '.markdown-body',
                    '[data-message-author-role="assistant"] .ds-markdown',
                    '[data-message-author-role="assistant"] .markdown-body',
                    '[class*="markdown"]',
                    '[data-message-author-role="assistant"]'
                ];
                for (var i = 0; i < selectors.length; i++) {
                    var elements = document.querySelectorAll(selectors[i]);
                    if (elements.length > 0) {
                        var el = elements[elements.length - 1];
                        // If matched parent container, try to find markdown child
                        if (selectors[i] === '[data-message-author-role="assistant"]') {
                            var childMarkdown = el.querySelector('.ds-markdown, .markdown-body, [class*="markdown"]');
                            if (childMarkdown) el = childMarkdown;
                        }
                        var text = el.innerText.trim();
                        if (text.length > 10) {
                            console.log('[SSP Bridge] Response via: ' + selectors[i] + ', length: ' + text.length);
                            return text;
                        }
                    }
                }
                return '';
            }
        },
        chatgpt: {
            input: function() {
                return document.querySelector('div.ProseMirror[contenteditable="true"]') ||
                       document.querySelector('#prompt-textarea') ||
                       document.querySelector('div[contenteditable="true"][role="textbox"]');
            },
            sendViaEnter: function(input) {
                input.focus();
                input.dispatchEvent(new KeyboardEvent('keydown', {
                    key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true
                }));
            },
            isStreaming: function() {
                var sendBtn = document.querySelector('button[data-testid="send-button"]');
                if (sendBtn && sendBtn.disabled) return true;
                if (document.querySelector('button[data-testid="stop-button"]')) return true;
                return false;
            },
            getLatestResponse: function() {
                var selectors = [
                    '[data-message-author-role="assistant"] .markdown',
                    '[data-message-author-role="assistant"] .whitespace-pre-wrap',
                    '[data-message-author-role="assistant"]'
                ];
                for (var i = 0; i < selectors.length; i++) {
                    var msgs = document.querySelectorAll(selectors[i]);
                    if (msgs.length > 0) {
                        var text = msgs[msgs.length - 1].innerText.trim();
                        if (text.length > 10) return text;
                    }
                }
                return '';
            }
        }
    };

    // ===== INPUT METHODS =====
    function setProseMirrorValue(element, text) {
        element.focus();
        element.innerHTML = '';
        var textNode = document.createTextNode(text);
        element.appendChild(textNode);
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
        var range = document.createRange();
        var sel = window.getSelection();
        range.selectNodeContents(element);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function setTextareaValue(textarea, text) {
        textarea.focus();
        var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
        nativeSetter.call(textarea, text);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setInputValue(element, text) {
        if (!element) return false;
        if (element.tagName === 'TEXTAREA') {
            setTextareaValue(element, text);
            return true;
        }
        if (element.isContentEditable) {
            setProseMirrorValue(element, text);
            return true;
        }
        return false;
    }

    // ===== WAIT FOR ELEMENT =====
    function waitForElement(getter, timeout) {
        timeout = timeout || 15000;
        return new Promise(function(resolve, reject) {
            var el = getter();
            if (el) return resolve(el);

            var observer = new MutationObserver(function() {
                var found = getter();
                if (found) {
                    observer.disconnect();
                    resolve(found);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            setTimeout(function() {
                observer.disconnect();
                var finalCheck = getter();
                finalCheck ? resolve(finalCheck) : reject(new Error('Element not found'));
            }, timeout);
        });
    }

    // ===== WAIT FOR RESPONSE =====
    function waitForResponse(platform, timeout) {
        timeout = timeout || 300000;
        var sels = SELECTORS[platform];
        return new Promise(function(resolve) {
            var startTime = Date.now();
            var lastResponseLength = 0;
            var stableCount = 0;
            var stableText = '';

            console.log('[SSP Bridge] Waiting for response...');

            var checkInterval = setInterval(function() {
                var currentResponse = sels.getLatestResponse();
                var currentLength = currentResponse ? currentResponse.length : 0;

                // Content stability detection
                // Response must be >100 chars and unchanged for 8 consecutive seconds
                if (currentLength > 100) {
                    // Use text comparison with tolerance: allow ±10 chars difference
                    // (DeepSeek sometimes adds/removes cursor artifacts)
                    var isStable = Math.abs(currentLength - lastResponseLength) <= 10;
                    if (isStable) {
                        stableCount++;
                        // On first stable detection, save the text as baseline
                        if (stableCount === 1) stableText = currentResponse;
                        if (stableCount >= 8) {
                            console.log('[SSP Bridge] Response stable for 8s. Length: ' + currentLength);
                            clearInterval(checkInterval);
                            setTimeout(function() {
                                // Get fresh response one more time for accuracy
                                var final = sels.getLatestResponse() || stableText;
                                resolve(final);
                            }, 1500);
                            return;
                        }
                    } else {
                        stableCount = 0;
                        lastResponseLength = currentLength;
                        stableText = currentResponse;
                    }
                } else if (currentLength > 0) {
                    // Response exists but too short - just track it
                    lastResponseLength = currentLength;
                    stableCount = 0;
                }

                // Timeout
                if (Date.now() - startTime > timeout) {
                    console.log('[SSP Bridge] Timeout. Last response length: ' + currentLength);
                    clearInterval(checkInterval);
                    resolve(currentResponse || 'TIMEOUT');
                }
            }, 1000);
        });
    }

    // ===== PLUGIN COMMUNICATION =====
    function httpPost(url, data, headers) {
        return fetch(url, {
            method: 'POST',
            headers: headers || { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(function(r) {
            console.log('[SSP Bridge] POST ' + url + ' -> ' + r.status);
            return r.json();
        }).catch(function(err) {
            console.error('[SSP Bridge] POST error:', err);
            throw err;
        });
    }

    function httpGet(url, headers) {
        return fetch(url, {
            method: 'GET',
            headers: headers || {}
        }).then(function(r) {
            console.log('[SSP Bridge] GET ' + url + ' -> ' + r.status);
            return r.json();
        }).catch(function(err) {
            console.error('[SSP Bridge] GET error:', err);
            throw err;
        });
    }

    function sendResponseToPlugin(taskId, responseText, status) {
        // Convert literal \n sequences (from DeepSeek's rendered text) to actual newlines
        var clean = responseText.replace(/\\n/g, '\n').replace(/\\r/g, '\r').replace(/\\t/g, '\t');
        // Also convert literal nn to actual double newlines (AI sometimes outputs nn instead of \n\n)
        clean = clean.replace(/nn(?=[a-zA-Z\u0600-\u06FF])/g, '\n\n');

        console.log('[SSP Bridge] Sending response...');
        console.log('[SSP Bridge] Task ID:', taskId);
        console.log('[SSP Bridge] Response length:', clean.length);
        console.log('[SSP Bridge] Status:', status);

        var payload = {
            task_id: taskId,
            response_text: clean,
            status: status
        };

        httpPost(WP_SITE_URL + '/wp-json/ssp/v1/ai-bridge/response', payload, {
            'X-SSP-Bridge-Token': BRIDGE_TOKEN
        }).then(function(res) {
            console.log('[SSP Bridge] Server response:', res);
            updateWidget('active', 'Bridge: Active');
            if (typeof GM_notification !== 'undefined') {
                GM_notification({ text: 'Task completed!', title: 'SSP Bridge', timeout: 3000 });
            }
            isProcessing = false;
        }).catch(function(err) {
            console.error('[SSP Bridge] Send error:', err);
            updateWidget('error', 'Send failed');
            isProcessing = false;
        });
    }

    // ===== TASK HANDLING =====
    function handleTask(taskId, prompt) {
        var sels = SELECTORS[platform];

        updateWidget('processing', 'Waiting for input...');
        waitForElement(sels.input, 15000).then(function(input) {
            updateWidget('processing', 'Setting prompt...');
            var success = setInputValue(input, prompt);
            if (!success) throw new Error('Failed to set input value');

            return new Promise(function(r) { setTimeout(r, 800); });
        }).then(function() {
            updateWidget('processing', 'Sending...');
            var input = sels.input();
            if (input) sels.sendViaEnter(input);

            updateWidget('processing', 'Waiting for AI...');
            return waitForResponse(platform, 300000);
        }).then(function(response) {
            if (response && response.length > 10 && response !== 'TIMEOUT') {
                sendResponseToPlugin(taskId, response, 'completed');
            } else {
                sendResponseToPlugin(taskId, response || 'No response generated', 'error');
            }
        }).catch(function(err) {
            console.error('[SSP Bridge] Task error:', err);
            sendResponseToPlugin(taskId, err.message, 'error');
        });
    }

    // ===== STATUS WIDGET =====
    function createStatusWidget() {
        if (document.getElementById('ssp-bridge-widget')) return;
        var widget = document.createElement('div');
        widget.id = 'ssp-bridge-widget';
        widget.style.cssText = 'position:fixed;bottom:20px;left:20px;z-index:99999;background:#1e293b;color:white;padding:10px 16px;border-radius:8px;font-size:13px;font-family:system-ui,sans-serif;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(0,0,0,0.4);cursor:default;';
        widget.innerHTML = '<span id="ssp-bridge-dot" style="width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 8px #22c55e;"></span><span id="ssp-bridge-text">Bridge: Active</span>';
        document.body.appendChild(widget);
    }

    function updateWidget(status, text) {
        var dot = document.getElementById('ssp-bridge-dot');
        var txt = document.getElementById('ssp-bridge-text');
        if (!dot || !txt) return;

        var colors = { active: '#22c55e', processing: '#f59e0b', error: '#ef4444' };
        dot.style.background = colors[status] || colors.active;
        dot.style.boxShadow = status === 'processing' ? '0 0 8px ' + colors[status] : 'none';
        txt.textContent = text;
    }

    // ===== POLLING =====
    function pollForTasks() {
        if (isProcessing) return;

        console.log('[SSP Bridge] Polling for tasks... Token: ' + BRIDGE_TOKEN.substring(0, 12) + '... Site: ' + WP_SITE_URL);
        httpGet(WP_SITE_URL + '/wp-json/ssp/v1/ai-bridge/pending', {
            'X-SSP-Bridge-Token': BRIDGE_TOKEN
        }).then(function(res) {
            console.log('[SSP Bridge] Poll result:', JSON.stringify(res));
            if (res && res.task_id && res.prompt) {
                console.log('[SSP Bridge] Task received:', res.task_id);
                isProcessing = true;
                updateWidget('processing', 'Processing task...');
                handleTask(res.task_id, res.prompt);
            }
        }).catch(function(err) {
            console.error('[SSP Bridge] Poll error:', err);
        });
    }

    // ===== INIT =====
    function init() {
        if (document.body) {
            createStatusWidget();
            updateWidget('active', 'Bridge: Active');
            setInterval(pollForTasks, POLL_INTERVAL);
            console.log('[SSP Bridge] ===== INITIALIZED =====');
            console.log('[SSP Bridge] Platform: ' + platform);
            console.log('[SSP Bridge] Token: ' + BRIDGE_TOKEN.substring(0, 15) + '...');
            console.log('[SSP Bridge] Site: ' + WP_SITE_URL);
            console.log('[SSP Bridge] GM_xmlhttpRequest: ' + (typeof GM_xmlhttpRequest !== 'undefined' ? 'available' : 'NOT AVAILABLE - using fetch'));
            console.log('[SSP Bridge] Poll interval: ' + POLL_INTERVAL + 'ms');

            // Test connectivity
            console.log('[SSP Bridge] Testing connection to: ' + WP_SITE_URL);
            fetch(WP_SITE_URL, { method: 'HEAD' }).then(function(r) {
                console.log('[SSP Bridge] CONNECTIVITY TEST PASSED - Status: ' + r.status);
                updateWidget('active', 'Bridge: Active');
            }).catch(function(err) {
                console.error('[SSP Bridge] CONNECTIVITY TEST FAILED:', err);
                updateWidget('error', 'Connection failed!');
            });
        } else {
            setTimeout(init, 500);
        }
    }

    init();
})();
