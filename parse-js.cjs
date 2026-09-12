const fs = require('fs');
let html = fs.readFileSync('includes/views/portal.php', 'utf8');
let scripts = html.match(/<script>([\s\S]*?)<\/script>/gs);
if (scripts && scripts[3]) {
    let code = scripts[3].replace(/<\/?script>/g, '');
    code = code.replace(/<\?php[\s\S]*?\?>/g, '""');
    fs.writeFileSync('script3.js', code);
}
