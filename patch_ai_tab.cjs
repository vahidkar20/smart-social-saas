const fs = require('fs');
let code = fs.readFileSync('includes/views/portal.php', 'utf8');

const targetStr = `<h3 style="margin:32px 0 12px;">3. حالت پرامپت</h3>`;
const replaceStr = `<div style="margin:40px 0 20px; padding:24px; background:rgba(79, 70, 229, 0.03); border:1px solid rgba(79, 70, 229, 0.15); border-radius:12px;">
                                    <h3 style="margin:0 0 8px; color:var(--text); display:flex; align-items:center; gap:8px;">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                        تنظیمات پردازش اتوماسیون هوش مصنوعی
                                    </h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:24px; line-height:1.6;"><strong>توجه:</strong> قابلیت‌های زیر صرفاً برای پردازش محتواهای اتوماتیک (مثل فید RSS یا ربات‌های خودکار وردپرس) کاربرد دارند. این تنظیمات روی <strong>«استودیو هوش مصنوعی»</strong> یا تولید مقالات دستی تاثیری ندارند.</p>
                                    
                                    <h4 style="margin:0 0 12px; font-size:1rem;">نحوه پردازش و بازنویسی (Prompt Mode)</h4>`;

code = code.replace(targetStr, replaceStr);

const targetStr2 = `<h3 style="margin:32px 0 12px;">4. قابلیت‌های پردازش</h3>`;
const replaceStr2 = `<h4 style="margin:32px 0 12px; font-size:1rem;">عملیات پردازش روی محتواهای ورودی</h4>`;
code = code.replace(targetStr2, replaceStr2);

const targetStr3 = `</div><!-- end api_mode_settings -->`;
const replaceStr3 = `</div>
                                </div><!-- end api_mode_settings -->`;
code = code.replace(targetStr3, replaceStr3);

fs.writeFileSync('includes/views/portal.php', code);
console.log('AI Tab patched');
