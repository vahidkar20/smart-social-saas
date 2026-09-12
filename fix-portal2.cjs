const fs = require('fs');
let content = fs.readFileSync('includes/views/portal.php', 'utf8');

const replacements = [
    { match: /el\.classList\.remove\('show'\); \}, 2500\);\s+window\.selectProvider = function/g, replace: "el.classList.remove('show'); }, 2500);\n            }\n            window.selectProvider = function" },
    { match: /return '⚪';\s+\/\/\s+=====\s+Manual Send\s+=====/g, replace: "return '⚪';\n            }\n            // ===== Manual Send =====" }
];

for (let r of replacements) {
    content = content.replace(r.match, r.replace);
}

fs.writeFileSync('includes/views/portal.php', content);
