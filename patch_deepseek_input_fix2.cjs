const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

js = js.replace(/        \/\/ Method 1: execCommand \(Works best for React\/Vue rich textareas\)\n        var execSuccess = false;\n        try {\n            execSuccess = document.execCommand\('insertText', false, text\);\n        } catch \(e\) \{\}/, 
`        // Method 1: execCommand (Works best for React/Vue rich textareas)
        var execSuccess = false;
        try {
            execSuccess = document.execCommand('insertText', false, text);
        } catch (e) {}`);

// Actually Deepseek's textarea uses native value property with a React onChange handler.
const oldSetTextarea = `    function setTextareaValue(textarea, text) {
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

const newSetTextarea = `    function setTextareaValue(textarea, text) {
        textarea.focus();
        textarea.value = text;

        let tracker = textarea._valueTracker;
        if (tracker) {
            tracker.setValue(textarea.value === text ? '' : text);
        }

        var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
        if (nativeSetter) {
            nativeSetter.call(textarea, text);
        }

        // Trigger standard React events
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));

        // Try the simulated paste or insertText event
        try {
            textarea.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text }));
        } catch (e) {}
    }`;

js = js.replace(oldSetTextarea, newSetTextarea);
fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Patched setTextareaValue again');
