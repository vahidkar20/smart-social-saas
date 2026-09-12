const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const headerAdditions = `// @grant        GM_xmlhttpRequest
// @grant        GM.xmlHttpRequest
// @grant        GM_notification
// @grant        GM_setValue
// @grant        GM_getValue
// @grant        GM.setValue
// @grant        GM.getValue`;

js = js.replace(/\/\/ @grant\s+GM_xmlhttpRequest\n\/\/ @grant\s+GM_notification\n\/\/ @grant\s+GM_setValue\n\/\/ @grant\s+GM_getValue/, headerAdditions);

const initReplacement = `    // ===== INITIALIZATION =====
    async function init() {
        // If not injected via PHP, fallback to GM_getValue storage
        if (!BRIDGE_TOKEN || BRIDGE_TOKEN.startsWith('{{')) {
            try {
                // Support both GM.* and GM_* APIs (ScriptCat prefers GM.*)
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
                        return; // Abort
                    }
                }
            } catch(e){}
        }`;

js = js.replace(/    \/\/ ===== INITIALIZATION =====\n    async function init\(\) {[\s\S]*?\} catch\(e\)\{\}\n                \} else \{\n                    return; \/\/ Abort\n                \}\n            \}\n        \}/, initReplacement);

const httpReplacement = `    // ===== HTTP WRAPPERS =====
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

            const requestFunc = (typeof GM !== 'undefined' && GM.xmlHttpRequest) ? GM.xmlHttpRequest : (typeof GM_xmlhttpRequest !== 'undefined' ? GM_xmlhttpRequest : null);

            if (requestFunc) {
                try {
                    requestFunc({
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

            const requestFunc = (typeof GM !== 'undefined' && GM.xmlHttpRequest) ? GM.xmlHttpRequest : (typeof GM_xmlhttpRequest !== 'undefined' ? GM_xmlhttpRequest : null);

            if (requestFunc) {
                try {
                    requestFunc({
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
    }`;

js = js.replace(/    \/\/ ===== HTTP WRAPPERS =====[\s\S]*?function sendResponseToPlugin/, httpReplacement + '\n\n    function sendResponseToPlugin');

fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Added ScriptCat support');
