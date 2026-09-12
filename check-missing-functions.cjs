const fs = require('fs');

const phpFile = fs.readFileSync('includes/views/portal.php', 'utf8');
const onclickMatches = phpFile.match(/onclick="([^"]*)"/g) || [];
const functionNames = onclickMatches.map(m => m.replace('onclick="', '').replace('"', '').split('(')[0].trim()).filter(n => n.length > 0 && n.indexOf('.') === -1 && n.indexOf(' ') === -1);
const uniqueFunctions = [...new Set(functionNames)];

let missingFunctions = [];
for (let fn of uniqueFunctions) {
    if (!phpFile.includes(`function ${fn}`) && !phpFile.includes(`${fn} = function`)) {
        missingFunctions.push(fn);
    }
}
console.log("Missing functions:");
console.log(missingFunctions.join('\n'));
