const fs = require('fs');
let portal = fs.readFileSync('includes/views/portal.php', 'utf8');
let extra = fs.readFileSync('scripts-extra.js', 'utf8');

portal = portal.replace('})();\n        </script>', '})();\n' + extra + '\n        </script>');

fs.writeFileSync('includes/views/portal.php', portal);
