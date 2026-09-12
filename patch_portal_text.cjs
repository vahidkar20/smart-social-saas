const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

// Replace standard hints
php = php.replace(/نیاز به SnapMonkey/g, 'نیاز به افزونه مرورگر');
php = php.replace(/SnapMonkey \(یا Tampermonkey\)/g, 'ScriptCat یا Tampermonkey');

// Update the list of instructions
const oldInstructions = `                                        <ol class="ssp-guide-steps" style="margin:12px 0;">
                                            <li>افزونه <strong>SnapMonkey</strong> (یا Tampermonkey) را روی مرورگر خود نصب کنید <a href="https://chromewebstore.google.com/detail/snapmonkey/ajpopphbpfgbaacomdckngkahibkkccj" target="_blank" style="display:inline-block; background:#4f46e5; color:white; padding:2px 10px; border-radius:12px; font-size:0.75rem; text-decoration:none; margin-right:6px; vertical-align:middle;">دانلود از Chrome Web Store</a></li>
                                            <li>به صفحه افزونه‌ها (<code>chrome://extensions</code>) بروید، روی <strong>Details</strong> اکستنشن کلیک کنید و اطمینان یابید گزینه <strong>Developer mode</strong> و در صورت وجود <strong>User Scripts</strong> فعال باشد.</li>
                                            <li>روی دکمه <strong>«دریافت کد و لینک اسکریپت»</strong> در زیر کلیک کنید.</li>
                                            <li><strong>روش ۱ (سریع و مطمئن):</strong> در SnapMonkey روی دکمه <strong>+ New Script</strong> کلیک کنید، متن قبلی را پاک کرده و کد اسکریپت کپی‌شده را پیست کنید و <strong>Save</strong> را بزنید.</li>
                                            <li><strong>روش ۲:</strong> لینک اسکریپت را در یک تب جدید مرورگر باز کنید تا دیالوگ نصب خودکار نمایان شود و <strong>Install</strong> را بزنید.</li>
                                            <li>به سایت چت‌بات (<a href="https://chat.deepseek.com" target="_blank">DeepSeek</a> یا <a href="https://chatgpt.com" target="_blank">ChatGPT</a>) بروید؛ ویجت سبز رنگ در گوشه صفحه به معنای اتصال فعال است.</li>
                                        </ol>`;

const newInstructions = `                                        <ol class="ssp-guide-steps" style="margin:12px 0;">
                                            <li>یکی از افزونه‌های <strong>ScriptCat</strong> یا <strong>Tampermonkey</strong> را روی مرورگر خود نصب کنید: 
                                                <a href="https://chromewebstore.google.com/detail/scriptcat/ndcooeababalnlpkfedmmbbbgkljhpjf" target="_blank" style="display:inline-block; background:#4f46e5; color:white; padding:2px 10px; border-radius:12px; font-size:0.75rem; text-decoration:none; margin:0 4px; vertical-align:middle;">دانلود ScriptCat</a>
                                                <a href="https://chromewebstore.google.com/detail/tampermonkey/dhdgffkkebhmkfjojejmpbldmpobfkfo" target="_blank" style="display:inline-block; background:#10b981; color:white; padding:2px 10px; border-radius:12px; font-size:0.75rem; text-decoration:none; margin:0 4px; vertical-align:middle;">دانلود Tampermonkey</a>
                                            </li>
                                            <li>اگر از ScriptCat استفاده می‌کنید، به صفحه افزونه‌ها (<code>chrome://extensions</code>) بروید، وارد <strong>Details</strong> (جزئیات) اکستنشن شوید و گزینه <strong>Developer mode</strong> را فعال کنید (<a href="https://docs.scriptcat.org/en/docs/use/open-dev/?userscript_enabled=false&userscript_permission=true&userscript_guard=allowScript&browser=chrome#allow-user-scripts" target="_blank">راهنمای فعال‌سازی</a>).</li>
                                            <li>روی دکمه <strong>«دریافت کد و لینک اسکریپت»</strong> در زیر کلیک کنید.</li>
                                            <li><strong>نصب دستی (سریع و مطمئن):</strong> در افزونه ScriptCat یا Tampermonkey روی دکمه <strong>افزودن اسکریپت جدید (+ New Script)</strong> کلیک کنید، کدهای قبلی را پاک کرده و کد اسکریپت کپی‌شده را Paste کنید، سپس ذخیره (Save) را بزنید.</li>
                                            <li><strong>نصب با یک کلیک:</strong> لینک اسکریپت را در یک تب جدید مرورگر باز کنید تا دیالوگ نصب خودکار افزونه نمایان شود و دکمه <strong>Install</strong> را بزنید.</li>
                                            <li>به سایت چت‌بات (<a href="https://chat.deepseek.com" target="_blank">DeepSeek</a> یا <a href="https://chatgpt.com" target="_blank">ChatGPT</a>) بروید؛ ویجت سبز رنگ در گوشه صفحه به معنای اتصال موفقیت‌آمیز است.</li>
                                        </ol>`;

php = php.replace(oldInstructions, newInstructions);

php = php.replace(/این روش نیازی به SnapMonkey ندارد/g, 'این روش نیازی به افزونه مرورگر (مانند ScriptCat) ندارد');
php = php.replace(/در SnapMonkey روی \+ New Script بزنید/g, 'در ScriptCat یا Tampermonkey روی + New Script بزنید');
php = php.replace(/نصب در SnapMonkey/g, 'نصب در مرورگر');
php = php.replace(/جایگذاری مستقیم کد در SnapMonkey/g, 'جایگذاری مستقیم کد در افزونه مرورگر');
php = php.replace(/افزونه <strong>SnapMonkey<\/strong> را باز کرده و روی دکمه مشکی <strong>\+ New Script<\/strong> کلیک کنید./g, 'افزونه <strong>ScriptCat</strong> یا <strong>Tampermonkey</strong> را باز کرده و روی دکمه افزودن اسکریپت جدید کلیک کنید.');
php = php.replace(/SnapMonkey به صورت خودکار فایل اسکریپت را شناخته/g, 'افزونه مرورگر به صورت خودکار فایل اسکریپت را شناخته');

fs.writeFileSync('includes/views/portal.php', php);
console.log('Patched portal.php texts');

let phpApi = fs.readFileSync('includes/class-ssp-ai-browser.php', 'utf8');
phpApi = phpApi.replace(/SnapMonkey/g, 'ScriptCat/Tampermonkey');
fs.writeFileSync('includes/class-ssp-ai-browser.php', phpApi);
console.log('Patched class-ssp-ai-browser.php texts');

