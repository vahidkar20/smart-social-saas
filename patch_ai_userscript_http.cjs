const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldHttpPostGet = `    // ===== PLUGIN COMMUNICATION =====
    function httpPost(url, data, headers) {
        if (typeof GM_xmlhttpRequest !== 'undefined') {
            return new Promise(function(resolve, reject) {
                GM_xmlhttpRequest({
                    method: 'POST',
                    url: url,
                    headers: headers || { 'Content-Type': 'application/json' },
                    data: JSON.stringify(data),
                    timeout: 25000,
                    onload: function(res) {
                        try {
                            resolve(JSON.parse(res.responseText));
                        } catch (e) {
                            resolve({ success: res.status >= 200 && res.status < 300, raw: res.responseText });
                        }
                    },
                    onerror: function(err) {
                        console.error('[SSP Bridge] GM_xmlhttpRequest POST error:', err);
                        reject(err);
                    },
                    ontimeout: function() {
                        reject(new Error('GM_xmlhttpRequest POST timeout'));
                    }
                });
            });
        }
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
        if (typeof GM_xmlhttpRequest !== 'undefined') {
            return new Promise(function(resolve, reject) {
                GM_xmlhttpRequest({
                    method: 'GET',
                    url: url,
                    headers: headers || {},
                    timeout: 15000,
                    onload: function(res) {
                        try {
                            resolve(JSON.parse(res.responseText));
                        } catch (e) {
                            resolve({ success: res.status >= 200 && res.status < 300, raw: res.responseText });
                        }
                    },
                    onerror: function(err) {
                        reject(err);
                    },
                    ontimeout: function() {
                        reject(new Error('GM_xmlhttpRequest GET timeout'));
                    }
                });
            });
        }
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
    }`;

const newHttpPostGet = `    // ===== PLUGIN COMMUNICATION =====
    function httpPost(url, data, headers) {
        return new Promise(function(resolve, reject) {
            var timeoutId = setTimeout(function() {
                reject(new Error('Manual timeout'));
            }, 10000);

            var fallbackToFetch = function() {
                fetch(url, {
                    method: 'POST',
                    headers: headers || { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                }).then(function(r) {
                    return r.json();
                }).then(function(res) {
                    clearTimeout(timeoutId);
                    resolve(res);
                }).catch(function(err) {
                    console.error('[SSP Bridge] fetch POST error:', err);
                    clearTimeout(timeoutId);
                    reject(err);
                });
            };

            if (typeof GM_xmlhttpRequest !== 'undefined') {
                try {
                    GM_xmlhttpRequest({
                        method: 'POST',
                        url: url,
                        headers: headers || { 'Content-Type': 'application/json' },
                        data: JSON.stringify(data),
                        timeout: 8000,
                        onload: function(res) {
                            clearTimeout(timeoutId);
                            try {
                                resolve(JSON.parse(res.responseText));
                            } catch (e) {
                                resolve({ success: res.status >= 200 && res.status < 300, raw: res.responseText });
                            }
                        },
                        onerror: function(err) {
                            console.warn('[SSP Bridge] GM_xmlhttpRequest POST error, falling back to fetch', err);
                            fallbackToFetch();
                        },
                        ontimeout: function() {
                            console.warn('[SSP Bridge] GM_xmlhttpRequest POST timeout, falling back to fetch');
                            fallbackToFetch();
                        }
                    });
                } catch(e) {
                    fallbackToFetch();
                }
            } else {
                fallbackToFetch();
            }
        });
    }

    function httpGet(url, headers) {
        return new Promise(function(resolve, reject) {
            var timeoutId = setTimeout(function() {
                reject(new Error('Manual timeout'));
            }, 10000);

            var fallbackToFetch = function() {
                fetch(url, {
                    method: 'GET',
                    headers: headers || {}
                }).then(function(r) {
                    return r.json();
                }).then(function(res) {
                    clearTimeout(timeoutId);
                    resolve(res);
                }).catch(function(err) {
                    console.error('[SSP Bridge] fetch GET error:', err);
                    clearTimeout(timeoutId);
                    reject(err);
                });
            };

            if (typeof GM_xmlhttpRequest !== 'undefined') {
                try {
                    GM_xmlhttpRequest({
                        method: 'GET',
                        url: url,
                        headers: headers || {},
                        timeout: 8000,
                        onload: function(res) {
                            clearTimeout(timeoutId);
                            try {
                                resolve(JSON.parse(res.responseText));
                            } catch (e) {
                                resolve({ success: res.status >= 200 && res.status < 300, raw: res.responseText });
                            }
                        },
                        onerror: function(err) {
                            console.warn('[SSP Bridge] GM_xmlhttpRequest GET error, falling back to fetch', err);
                            fallbackToFetch();
                        },
                        ontimeout: function() {
                            console.warn('[SSP Bridge] GM_xmlhttpRequest GET timeout, falling back to fetch');
                            fallbackToFetch();
                        }
                    });
                } catch(e) {
                    fallbackToFetch();
                }
            } else {
                fallbackToFetch();
            }
        });
    }`;

if (js.includes('function httpGet(url, headers) {')) {
    js = js.replace(oldHttpPostGet, newHttpPostGet);
    fs.writeFileSync('assets/js/ai-bridge.user.js', js);
    console.log('Patched http methods');
} else {
    console.log('Could not find httpGet code');
}
