const fs = require('fs');
let code = fs.readFileSync('includes/views/portal.php', 'utf8');

const startTag = `<div id="tab-generate" class="tab-content">`;
const endTag = `<!-- ============ MESSENGERS ============ -->`;

const startIndex = code.indexOf(startTag);
const endIndex = code.indexOf(endTag);

if (startIndex === -1 || endIndex === -1) {
    console.error('Could not find tags');
    process.exit(1);
}

const replacement = `<div id="tab-generate" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">مرکز تولید محتوا ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <div style="text-align:center; margin-bottom:40px; margin-top:10px;">
                                <div style="width:64px; height:64px; background:linear-gradient(135deg, #4f46e5, #ec4899); border-radius:20px; display:inline-flex; align-items:center; justify-content:center; color:#fff; margin-bottom:20px; box-shadow:0 10px 25px rgba(79, 70, 229, 0.25);">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                                <h2 style="margin:0 0 12px; font-weight:800; font-size:1.8rem; color:var(--text);">استودیو هوش مصنوعی</h2>
                                <p style="color:var(--text-muted); font-size:1rem; max-width:550px; margin:0 auto; line-height:1.6;">مجموعه یکپارچه ابزارهای هوش مصنوعی برای تولید انواع محتوا. ابزار مورد نظر خود را برای شروع انتخاب کنید.</p>
                            </div>

                            <?php if (!$ai_configured) : ?>
                            <div class="ssp-upgrade-banner" style="background:var(--warning-soft); border-color:var(--warning);">
                                <div>
                                    <h3 style="color:var(--warning);">هوش مصنوعی تنظیم نشده است</h3>
                                    <p>برای استفاده از امکانات استودیو هوش مصنوعی، ابتدا API Key را تنظیم کنید یا حالت مرورگر را فعال نمایید.</p>
                                </div>
                                <button class="ssp-btn-primary" onclick="switchTab('ai', document.querySelector('[data-tab=ai]'))">رفتن به تنظیمات AI</button>
                            </div>
                            <?php else : ?>

                            <div class="ssp-grid-2" style="gap:24px;">
                                <!-- Article Gen -->
                                <div class="ssp-card" style="cursor:pointer; transition:all 0.3s ease; border:1px solid var(--border); padding:24px;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(79,70,229,0.1)';" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onclick="switchTab('contentgen', document.querySelector('[data-tab=contentgen]'))">
                                    <div style="font-size:2.5rem; margin-bottom:16px;">📝</div>
                                    <h3 style="margin:0 0 8px; font-size:1.2rem; font-weight:800; color:var(--text);">تولید مقاله سئو شده</h3>
                                    <p style="margin:0; font-size:0.9rem; color:var(--text-muted); line-height:1.6;">نگارش مقالات طولانی و ساختاریافته وردپرس با تگ‌ها و هدینگ‌های استاندارد و کاملاً سئو شده.</p>
                                </div>

                                <!-- Post Gen -->
                                <div class="ssp-card" style="cursor:pointer; transition:all 0.3s ease; border:1px solid var(--border); padding:24px;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(79,70,229,0.1)';" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onclick="switchTab('postgen', document.querySelector('[data-tab=postgen]'))">
                                    <div style="font-size:2.5rem; margin-bottom:16px;">📱</div>
                                    <h3 style="margin:0 0 8px; font-size:1.2rem; font-weight:800; color:var(--text);">تولید پست شبکه‌های اجتماعی</h3>
                                    <p style="margin:0; font-size:0.9rem; color:var(--text-muted); line-height:1.6;">نگارش جذاب و خلاقانه کپشن و پست برای تلگرام، اینستاگرام، بله، ایتا و سایر پیام‌رسان‌ها.</p>
                                </div>

                                <!-- Product Gen -->
                                <div class="ssp-card" style="cursor:pointer; transition:all 0.3s ease; border:1px solid var(--border); padding:24px;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(79,70,229,0.1)';" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onclick="switchTab('productgen', document.querySelector('[data-tab=productgen]'))">
                                    <div style="font-size:2.5rem; margin-bottom:16px;">🛍️</div>
                                    <h3 style="margin:0 0 8px; font-size:1.2rem; font-weight:800; color:var(--text);">تولید محصول فروشگاهی</h3>
                                    <p style="margin:0; font-size:0.9rem; color:var(--text-muted); line-height:1.6;">معرفی جذاب و قانع‌کننده محصولات برای فروشگاه ووکامرس شما جهت افزایش نرخ تبدیل فروش.</p>
                                </div>

                                <!-- Brainstorm -->
                                <div class="ssp-card" style="cursor:pointer; transition:all 0.3s ease; border:1px solid var(--border); padding:24px;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(79,70,229,0.1)';" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onclick="switchTab('brainstorm', document.querySelector('[data-tab=brainstorm]'))">
                                    <div style="font-size:2.5rem; margin-bottom:16px;">💡</div>
                                    <h3 style="margin:0 0 8px; font-size:1.2rem; font-weight:800; color:var(--text);">ایده‌یابی هوشمند</h3>
                                    <p style="margin:0; font-size:0.9rem; color:var(--text-muted); line-height:1.6;">طوفان فکری و پیدا کردن سوژه‌ها و ایده‌های ناب برای تقویم محتوایی در روزها و هفته‌های آینده.</p>
                                </div>
                            </div>
                            
                            <div style="margin-top:40px; padding-top:24px; border-top:1px solid var(--border); display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
                                <button class="ssp-btn-secondary" onclick="switchTab('promptbuilder', document.querySelector('[data-tab=promptbuilder]'))" style="font-size:0.9rem; padding:8px 16px;">🛠️ پرامپت ساز پیشرفته</button>
                                <button class="ssp-btn-secondary" onclick="switchTab('drafts', document.querySelector('[data-tab=drafts]'))" style="font-size:0.9rem; padding:8px 16px;">📂 صندوق پیش‌نویس‌ها</button>
                                <button class="ssp-btn-secondary" onclick="switchTab('template', document.querySelector('[data-tab=template]'))" style="font-size:0.9rem; padding:8px 16px;">📋 قالب‌های پیام</button>
                            </div>

                            <?php endif; ?>
                            <?php endif; // end Pro guard for generate ?>
                        </div>
                        
                        `;

code = code.substring(0, startIndex) + replacement + code.substring(endIndex);

fs.writeFileSync('includes/views/portal.php', code);
console.log('Replaced wizard with Hub dashboard');
