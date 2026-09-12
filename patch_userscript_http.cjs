const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const regexGet = /function httpGet\(url, headers\) \{[\s\S]*?return fetch/g;
const regexPost = /function httpPost\(url, body, headers\) \{[\s\S]*?return fetch/g;

const newHttpGet = `function httpGet(url, headers) {
        // ALWAYS use fetch directly if the site supports CORS
        return fetch(url, {
            method: 'GET',
            headers: headers || {}
        }).then(function(r) {
            return r.json();
        }).catch(function(err) {
            console.error('[SSP Bridge] GET error:', err);
            throw err;
        });
    }

    // fallback return fetch`; // This last part to match the replace

const newHttpPost = `function httpPost(url, body, headers) {
        var h = headers || {};
        h['Content-Type'] = 'application/json';
        return fetch(url, {
            method: 'POST',
            headers: h,
            body: JSON.stringify(body)
        }).then(function(r) {
            return r.json();
        }).catch(function(err) {
            console.error('[SSP Bridge] POST error:', err);
            throw err;
        });
    }

    // fallback return fetch`;

// We will just rewrite the entire httpGet and httpPost functions
