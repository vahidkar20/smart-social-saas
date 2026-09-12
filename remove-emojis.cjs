const fs = require('fs');
let content = fs.readFileSync('includes/views/portal.php', 'utf8');

// 1. Remove emojis in <option> tags and text inputs
content = content.replace(/🧠 |🏛️ |🔍 |🔮 |🔄 |👶 |🔀 |🔘 |📱 |📍 |👤 |👥 |💳 |📢 |📷 /g, '');
content = content.replace(/✈️ |📥 |⬇ |✨ |🚀 |📝 |📄 |🔗 |👋/g, '');

// 2. Replace Toast and Status emojis with SVGs
// ✅ 
const iconSuccess = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>`;
// ❌
const iconError = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
// ℹ️
const iconInfo = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;

content = content.replace(
    `toast.innerHTML = (type === 'success' ? '✅ ' : (type === 'error' ? '❌ ' : 'ℹ️ ')) + msg;`,
    `toast.innerHTML = (type === 'success' ? '${iconSuccess}' : (type === 'error' ? '${iconError}' : '${iconInfo}')) + msg;`
);

// 3. Settings gear emoji in portal.php (line 825)
const iconGear = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>`;
content = content.replace(/>⚙<\/button>/g, `>${iconGear}</button>`);

// 4. Bar chart emoji
const iconChart = `<svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>`;
content = content.replace(/>📊<\/div>/g, `>${iconChart}</div>`);

// 5. Whale icon (DeepSeek)
const iconWhale = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><path d="M22 12c0 6-4.39 10-9.8 10C6.6 22 2 17.6 2 12 2 6.6 6.6 2 12 2c5.4 0 10 4.4 10 10z"/><path d="M16 12a4 4 0 0 0-8 0"/></svg>`;
content = content.replace(/'🐋',/g, `'${iconWhale}',`);

// 6. getStepIcon function emojis
content = content.replace(/return '✅';/g, `return '${iconSuccess}';`);
content = content.replace(/return '⏳';/g, `return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><circle cx="12" cy="12" r="3"/></svg>';`);
content = content.replace(/return '❌';/g, `return '${iconError}';`);
content = content.replace(/return '⚪';/g, `return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';`);

// 7. Emojis in placeholders
content = content.replace(/سلام! (👋&#10;|&#10;)/g, 'سلام!&#10;');
content = content.replace(/مثال:&#10;\[آیکون\]/g, 'مثال:&#10;');

// Write back
fs.writeFileSync('includes/views/portal.php', content);
