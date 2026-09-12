const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const classNames = [
    'btn-test-wpsite',
    'btn-delete-wpsite',
    'btn-delete-rss',
    'btn-fetch-rss',
    'btn-delete-messenger'
];

for(const cls of classNames) {
    let regex = new RegExp(`if\\(e\\.target && e\\.target\\.classList\\.contains\\('${cls}'\\)\\) \\{\\s*let id = e\\.target\\.getAttribute\\('data-id'\\);`, "m");
    let replacement = `let target_${cls.replace(/-/g, '_')} = e.target.closest ? e.target.closest('.${cls}') : (e.target.classList.contains('${cls}') ? e.target : null);
    if(target_${cls.replace(/-/g, '_')}) {
        let id = target_${cls.replace(/-/g, '_')}.getAttribute('data-id');`;
    
    // Also we need to replace `e.target` inside with the target variable if there is a `let btn = e.target;`
    let match = js.match(regex);
    if(match) {
        let blockRegex = new RegExp(`if\\(e\\.target && e\\.target\\.classList\\.contains\\('${cls}'\\)\\) \\{\\s*let id = e\\.target\\.getAttribute\\('data-id'\\);\\s*let btn = e\\.target;`, "m");
        if(js.match(blockRegex)) {
            let replacement2 = `let target_${cls.replace(/-/g, '_')} = e.target.closest ? e.target.closest('.${cls}') : (e.target.classList.contains('${cls}') ? e.target : null);
    if(target_${cls.replace(/-/g, '_')}) {
        let id = target_${cls.replace(/-/g, '_')}.getAttribute('data-id');
        let btn = target_${cls.replace(/-/g, '_')};`;
            js = js.replace(blockRegex, replacement2);
        } else {
            js = js.replace(regex, replacement);
        }
    }
}

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched all clicks');
