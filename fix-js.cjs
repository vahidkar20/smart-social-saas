const fs = require('fs');
let code = fs.readFileSync('script3.js', 'utf8');
let lines = code.split('\n');

let stack = [];
for (let i = 0; i < lines.length; i++) {
    for (let char of lines[i]) {
        if (char === '{') stack.push(i + 1);
        else if (char === '}') stack.pop();
    }
}
console.log("Unclosed braces opened at lines:", stack);
