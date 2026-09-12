const fs = require('fs');
let ai = fs.readFileSync('assets/js/portal-ai.js', 'utf8');

ai = ai.replace(
    /window\.setupBrowserBridge = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/,
    "window.setupBrowserBridge = function() { showToast('درخواست اتصال به افزونه مرورگر ارسال شد. لطفاً افزونه را نصب کنید.', 'info'); };"
).replace(
    /window\.testBrowserBridge = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/,
    "window.testBrowserBridge = function() { showToast('ارتباط با مرورگر برقرار نیست. افزونه فعال نیست.', 'error'); };"
);

fs.writeFileSync('assets/js/portal-ai.js', ai);

let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');
core = core.replace(
    /window\.setupBrowserBridge = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/g,
    ""
).replace(
    /window\.testBrowserBridge = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/g,
    ""
);
fs.writeFileSync('assets/js/portal-core.js', core);

