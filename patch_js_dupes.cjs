const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// The block to remove:
// // === DISTRIBUTION PREVIEW & MODAL ===
// window.closeDistributionForm = function() { ... }
// window.previewDistribution = function() { ... }

let startStr = "// === DISTRIBUTION PREVIEW & MODAL ===";
let endStr = "// === ADVANCED SEO SUITE IMPLEMENTATION ===";

let startIndex = js.indexOf(startStr);
let endIndex = js.indexOf(endStr);

if (startIndex !== -1 && endIndex !== -1) {
    let before = js.substring(0, startIndex);
    let after = js.substring(endIndex);
    js = before + after;
    fs.writeFileSync('assets/js/portal-core.js', js);
    console.log('Removed duplicate distribution functions');
} else {
    console.log('Could not find the duplicate block');
}
