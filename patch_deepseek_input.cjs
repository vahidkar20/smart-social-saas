const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldInputFunction = `    function setTextareaValue(textarea, text) {
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

const newInputFunction = `    function setTextareaValue(textarea, text) {
        textarea.focus();
        textarea.select();

        // Method 1: execCommand (Works best for React/Vue rich textareas)
        var execSuccess = false;
        try {
            execSuccess = document.execCommand('insertText', false, text);
        } catch (e) {}

        if (!execSuccess || textarea.value !== text) {
            // Method 2: Native setter (Bypasses React's event pooling)
            try {
                var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
                nativeSetter.call(textarea, text);
            } catch (e) {
                textarea.value = text;
            }
        }
        
        // Method 3: React 16+ _valueTracker hack
        let tracker = textarea._valueTracker;
        if (tracker) {
            tracker.setValue('');
        }

        // Dispatch events so the framework registers the change
        try {
            textarea.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text }));
        } catch(e) {}
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
        
        // DeepSeek specific: dispatch a composed input event and keyboard events
        textarea.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
        textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true }));
        textarea.dispatchEvent(new KeyboardEvent('keyup', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true }));
    }`;

if (js.includes('function setTextareaValue(textarea, text) {')) {
    js = js.replace(oldInputFunction, newInputFunction);
    fs.writeFileSync('assets/js/ai-bridge.user.js', js);
    console.log('Patched setTextareaValue');
} else {
    console.log('Could not find setTextareaValue');
}
