const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

code = code.replace(
    /let msg = 'تعداد کاربران: ' \+ res.data.users_count \+ '\\\\nپیام‌های امروز: ' \+ res.data.messages_today;/g,
    "let msg = 'نام: ' + (res.data.bot_info.name || '') + '\\nیوزرنیم: @' + (res.data.bot_info.username || '') + '\\nدستورات: ' + (res.data.commands_count || 0) + '\\nدکمه‌ها: ' + (res.data.buttons_count || 0);"
);

fs.writeFileSync('assets/js/portal-core.js', code);
console.log('Fixed Bot Stats popup');
