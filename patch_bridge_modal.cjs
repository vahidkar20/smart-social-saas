const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

const bridgeModalHTML = `
            <!-- Bridge AI Modal -->
            <div class="ssp-modal-overlay" id="modal_ai_bridge">
                <div class="ssp-modal" style="max-width: 450px;">
                    <div class="ssp-modal-header" style="border-bottom:none; padding-bottom:0;">
                        <h3 style="display:flex; align-items:center; gap:8px;">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10H12V2Z"/><path d="M12 12 21.1 2.9"/></svg>
                            ارتباط با چت‌بات (حالت مرورگر)
                        </h3>
                        <button type="button" class="ssp-modal-close" onclick="closeModal('modal_ai_bridge'); window._bridgeActive = false;">&times;</button>
                    </div>
                    <div class="ssp-modal-body" style="padding-top:10px;">
                        <div style="background:var(--bg-alt); padding:20px; border-radius:12px; border:1px solid var(--border); margin-bottom:16px;">
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;" id="bridge_step_1">
                                <div class="bridge-step-icon" style="width:24px; height:24px; border-radius:50%; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px;">1</div>
                                <div style="font-weight:600; color:var(--text);" id="bridge_step_1_text">ارسال درخواست به تب چت‌بات...</div>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;" id="bridge_step_2">
                                <div class="bridge-step-icon" style="width:24px; height:24px; border-radius:50%; border:2px solid var(--border); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:12px;">2</div>
                                <div style="font-weight:600; color:var(--text-muted);" id="bridge_step_2_text">در حال پردازش توسط هوش مصنوعی...</div>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;" id="bridge_step_3">
                                <div class="bridge-step-icon" style="width:24px; height:24px; border-radius:50%; border:2px solid var(--border); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:12px;">3</div>
                                <div style="font-weight:600; color:var(--text-muted);" id="bridge_step_3_text">دریافت و جایگذاری پاسخ</div>
                            </div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <div style="height:6px; background:var(--bg-alt); border-radius:3px; overflow:hidden;">
                                <div id="bridge_progress_bar" style="height:100%; width:10%; background:var(--accent); transition:width 0.3s ease;"></div>
                            </div>
                        </div>

                        <div id="bridge_error_box" style="display:none; background:var(--error-soft); color:var(--error); padding:12px; border-radius:8px; font-size:0.85rem; margin-bottom:16px; border:1px solid var(--error);">
                            <!-- Error message here -->
                        </div>

                        <div style="font-size:0.8rem; color:var(--text-muted); line-height:1.6;">
                            <strong>نکته:</strong> لطفاً تب باز شده (DeepSeek یا ChatGPT) را نبندید تا پاسخ کامل دریافت شود. 
                        </div>
                    </div>
                    <div class="ssp-modal-actions" style="border-top:1px solid var(--border); padding-top:16px; margin-top:16px;">
                        <button type="button" class="ssp-btn-secondary" onclick="closeModal('modal_ai_bridge'); window._bridgeActive = false;">بستن</button>
                        <button type="button" class="ssp-btn-primary" id="bridge_retry_btn" onclick="retryBridgeTask()" style="display:none;">تلاش مجدد</button>
                    </div>
                </div>
            </div>
`;

if (!php.includes('id="modal_ai_bridge"')) {
    php = php.replace('<!-- RSS Preview Modal -->', bridgeModalHTML + '\n            <!-- RSS Preview Modal -->');
    fs.writeFileSync('includes/views/portal.php', php);
    console.log('Added modal_ai_bridge HTML');
}
