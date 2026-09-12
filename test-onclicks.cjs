const fs = require('fs');
let portal = fs.readFileSync('includes/views/portal.php', 'utf8');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');
let ai = fs.readFileSync('assets/js/portal-ai.js', 'utf8');
let extra = fs.readFileSync('scripts-extra.js', 'utf8');
let allJs = core + ai + extra;

let matches = portal.match(/onclick="([a-zA-Z0-9_]+)/g);
let onclicks = [...new Set(matches.map(m => m.replace('onclick="', '')))];

let missing = [];
onclicks.forEach(fn => {
    if (
        !allJs.includes('window.' + fn) && 
        !allJs.includes('function ' + fn) &&
        fn !== 'document' && fn !== 'event' && fn !== 'navigator'
    ) {
        missing.push(fn);
    }
});
console.log(missing.join(', '));
