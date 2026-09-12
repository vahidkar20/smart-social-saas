const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldInputSelector = `        deepseek: {
            input: function() {
                return document.querySelector('#chat-input') ||
                       document.querySelector('textarea#chat-input') ||
                       document.querySelector('form textarea') ||
                       document.querySelector('textarea[placeholder*="DeepSeek"]') ||
                       document.querySelector('textarea[placeholder*="Message"]') ||
                       document.querySelector('textarea[placeholder*="پیام"]') ||
                       document.querySelector('div[contenteditable="true"]') ||
                       document.querySelector('textarea');
            },`;

const newInputSelector = `        deepseek: {
            input: function() {
                // Return the textarea directly, prioritize chat-input id
                return document.querySelector('#chat-input') ||
                       document.querySelector('textarea') ||
                       document.querySelector('div[contenteditable="true"]');
            },`;

if (js.includes('deepseek: {')) {
    js = js.replace(oldInputSelector, newInputSelector);
    fs.writeFileSync('assets/js/ai-bridge.user.js', js);
    console.log('Patched input selector');
} else {
    console.log('Could not find input selector');
}
