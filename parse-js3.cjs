const fs = require('fs');
let html = fs.readFileSync('includes/views/portal.php', 'utf8');
let scripts = html.match(/<script[\s\S]*?>([\s\S]*?)<\/script>/gi);
if (scripts) {
    scripts.forEach((s, i) => {
        let code = s.replace(/<script[\s\S]*?>|<\/script>/gi, '');
        code = code.replace(/<\?php[\s\S]*?\?>/g, '""'); // Mock PHP tags
        fs.writeFileSync(`test-script-${i}.js`, code);
        try {
            require('child_process').execSync(`node -c test-script-${i}.js`);
            console.log(`Script ${i} is OK.`);
        } catch (e) {
            console.log(`Script ${i} ERROR:`, e.stderr.toString());
        }
    });
}
