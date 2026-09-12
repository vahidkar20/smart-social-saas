const fs = require('fs');
let content = fs.readFileSync('includes/views/portal.php', 'utf8');

content = content.replace(/🔴 |🚀 |🎨 |🎤 /g, '');
content = content.replace(/<div style="font-size:3rem;">💬<\/div>/g, '<div style="font-size:3rem;"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>');
content = content.replace(/<div style="font-size:3rem;">🚀<\/div>/g, '<div style="font-size:3rem;"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2l.5-.5c2.14-2.14 2.14-5.61 0-7.75l-1.5-1.5c-2.14-2.14-5.61-2.14-7.75 0l-.5.5z"/><path d="M12 15l2 2"/><path d="M15 12l2 2"/></svg></div>');

fs.writeFileSync('includes/views/portal.php', content);
