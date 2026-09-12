const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldSend = `            send: function(input) {
                var sendBtn = document.querySelector('#chat-input-send-button') ||
                              document.querySelector('div[role="button"][aria-label*="Send"]') ||
                              document.querySelector('button[aria-label*="Send"]') ||
                              document.querySelector('div.ds-icon-button') ||
                              document.querySelector('div[class*="send-btn"]') ||
                              document.querySelector('button[type="submit"]') ||
                              document.querySelector('button[class*="send"]');
                if (sendBtn && !sendBtn.disabled && sendBtn.getAttribute('aria-disabled') !== 'true') {
                    sendBtn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
                    sendBtn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
                    sendBtn.click();
                    return true;
                }
                if (input) {
                    input.focus();
                    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true }));
                    input.dispatchEvent(new KeyboardEvent('keypress', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true }));
                    input.dispatchEvent(new KeyboardEvent('keyup', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true }));
                    return true;
                }
                return false;
            },`;

const newSend = `            send: function(input) {
                var sendBtn = document.querySelector('div.ds-icon-button') ||
                              document.querySelector('#chat-input-send-button') ||
                              document.querySelector('div[role="button"][aria-label*="Send"]') ||
                              document.querySelector('button[aria-label*="Send"]') ||
                              document.querySelector('div[class*="send-btn"]') ||
                              document.querySelector('button[type="submit"]') ||
                              document.querySelector('button[class*="send"]');

                if (sendBtn && !sendBtn.disabled && sendBtn.getAttribute('aria-disabled') !== 'true') {
                    // DeepSeek often uses an SVG icon wrapped in a div for the send button
                    sendBtn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
                    sendBtn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
                    sendBtn.click();
                    return true;
                }
                
                // Fallback to enter key on the input
                if (input) {
                    input.focus();
                    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true, composed: true }));
                    input.dispatchEvent(new KeyboardEvent('keypress', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true, composed: true }));
                    input.dispatchEvent(new KeyboardEvent('keyup', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true, composed: true }));
                    return true;
                }
                return false;
            },`;

if (js.includes('send: function(input) {')) {
    js = js.replace(oldSend, newSend);
    fs.writeFileSync('assets/js/ai-bridge.user.js', js);
    console.log('Patched send for DeepSeek');
} else {
    console.log('Could not find send function');
}
