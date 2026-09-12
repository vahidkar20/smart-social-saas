const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
let target = `fd.append('new_delay_value', delayVal);`;
let replacement = `fd.append('new_delay_value', delayVal);
    fd.append('daily_limit', timesArr.length || 1);`;
js = js.replace(target, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched daily limit');
