const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldCode1 = `    function setTextareaValue(textarea, text) {
        textarea.focus();
        try {
            var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
            nativeSetter.call(textarea, text);
        } catch (e) {
            textarea.value = text;
        }
        try {
            textarea.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text }));
        } catch(e) {}
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }`;

const newCode1 = `    function setTextareaValue(textarea, text) {
        textarea.focus();
        try {
            var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
            nativeSetter.call(textarea, text);
        } catch (e) {
            textarea.value = text;
        }
        
        // React 16+ specific hack
        let tracker = textarea._valueTracker;
        if (tracker) {
            tracker.setValue('');
        }

        try {
            textarea.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text }));
        } catch(e) {}
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
        
        // DeepSeek specific: try simulated keyboard events
        textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'a', bubbles: true }));
        textarea.dispatchEvent(new KeyboardEvent('keyup', { key: 'a', bubbles: true }));
    }`;

if (js.includes(oldCode1)) {
    js = js.replace(oldCode1, newCode1);
    fs.writeFileSync('assets/js/ai-bridge.user.js', js);
    console.log('Patched setTextareaValue');
} else {
    console.log('Could not find setTextareaValue code');
}
