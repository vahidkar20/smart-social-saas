const fs = require('fs');
let js = fs.readFileSync('assets/js/ai-bridge.user.js', 'utf8');

const regex = /setTextareaValue\(textarea, text\) \{([\s\S]*?)\}/g;
const match = regex.exec(js);
console.log("setTextareaValue body:", match[1]);

