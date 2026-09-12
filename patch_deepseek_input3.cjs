const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const newSetInputValue = `
    function setInputValue(element, text) {
        if (!element) return false;
        
        // Ensure text is clean string
        if (typeof text !== 'string') {
            try { text = JSON.stringify(text); } catch(e) { text = String(text); }
        }

        element.focus();
        
        if (element.tagName === 'TEXTAREA') {
            // For textarea
            element.value = text;
            
            // React 16+ hack
            let tracker = element._valueTracker;
            if (tracker) {
                tracker.setValue(element.value === text ? '' : text);
            }

            // Native setter
            var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value');
            if (nativeSetter && nativeSetter.set) {
                nativeSetter.set.call(element, text);
            }
            
            element.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
            element.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
            return true;
        }
        
        if (element.isContentEditable || element.tagName === 'DIV') {
            // ContentEditable (React ProseMirror or DeepSeek's new div)
            element.innerHTML = '';
            const p = document.createElement('p');
            // Split by newlines and append <br> for proper formatting
            const lines = text.split('\\n');
            lines.forEach((line, i) => {
                p.appendChild(document.createTextNode(line));
                if (i < lines.length - 1) p.appendChild(document.createElement('br'));
            });
            element.appendChild(p);
            
            const range = document.createRange();
            range.selectNodeContents(element);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            
            element.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text, composed: true }));
            element.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
            return true;
        }
        
        return false;
    }
`;

js = js.replace(/    function setInputValue\(element, text\) \{([\s\S]*?)return false;\n    \}/, newSetInputValue);
fs.writeFileSync('assets/js/ai-bridge.user.js', js);
console.log('Patched setInputValue to handle complex strings and formats');
