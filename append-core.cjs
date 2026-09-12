const fs = require('fs');
let portal = fs.readFileSync('includes/views/portal.php', 'utf8');
let coreJs = fs.readFileSync('assets/js/portal-core.js', 'utf8');

portal = portal.replace('})();\n        </script>', '})();\n' + coreJs + '\n        </script>');

fs.writeFileSync('includes/views/portal.php', portal);
