const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const oldCode2 = `                if (sendBtn && !sendBtn.disabled && sendBtn.getAttribute('aria-disabled') !== 'true') {
                    sendBtn.click();
                    return true;
                }`;

const newCode2 = `                if (sendBtn && !sendBtn.disabled && sendBtn.getAttribute('aria-disabled') !== 'true') {
                    sendBtn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
                    sendBtn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
                    sendBtn.click();
                    return true;
                }`;

if (js.includes(oldCode2)) {
    js = js.replace(oldCode2, newCode2);
    fs.writeFileSync('assets/js/ai-bridge.user.js', js);
    console.log('Patched sendBtn click');
} else {
    console.log('Could not find sendBtn click code');
}
