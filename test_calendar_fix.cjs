const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// Also checking gregorianToJalali which is included in the portal-core.js file. 
let hasGregorian = code.includes('window.gregorianToJalali = function');
console.log('Has gregorianToJalali:', hasGregorian);

