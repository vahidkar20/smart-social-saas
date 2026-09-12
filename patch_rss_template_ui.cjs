const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

// For Add Feed
let addFeedTarget = `<div class="ssp-form-group" style="margin:0;">
                                            <label class="ssp-label">حالت محتوا</label>`;
let addFeedReplacement = `<div class="ssp-form-group" style="margin:0;">
                                            <label class="ssp-label">حالت محتوا (پیش‌فرض)</label>`;
php = php.replace(addFeedTarget, addFeedReplacement);

let addFeedTarget2 = `                                    </div>
                                </div>
                                <!-- Destination Options -->`;
let addFeedReplacement2 = `                                    </div>
                                    <div class="ssp-form-group" style="margin-top:12px;">
                                        <label class="ssp-label">قالب پیام سفارشی (جایگزین حالت محتوا)</label>
                                        <textarea id="new_feed_message_template" class="ssp-textarea" rows="3" placeholder="{title}\n\n{content}\n\nمنبع: {url}"></textarea>
                                        <small class="ssp-hint">متغیرها: {title}, {content}, {url}, {excerpt} - اگر پر شود، تنظیمات "حالت محتوا" نادیده گرفته می‌شود.</small>
                                    </div>
                                </div>
                                <!-- Destination Options -->`;
php = php.replace(addFeedTarget2, addFeedReplacement2);

// For Edit Feed
let editFeedTarget = `<div class="ssp-form-group" style="margin:0;">
                                    <label class="ssp-label">حالت محتوا</label>
                                    <select id="edit_rss_content_mode" class="ssp-select">`;
let editFeedReplacement = `<div class="ssp-form-group" style="margin:0;">
                                    <label class="ssp-label">حالت محتوا</label>
                                    <select id="edit_rss_content_mode" class="ssp-select">`;
// Just locate the end of the div for content mode.
let editFeedTarget2 = `                                        <option value="title_link">عنوان+لینک</option>
                                        <option value="full">متن کامل</option>
                                    </select>
                                </div>
                            </div>
                        </div>`;
let editFeedReplacement2 = `                                        <option value="title_link">عنوان+لینک</option>
                                        <option value="full">متن کامل</option>
                                    </select>
                                </div>
                            </div>
                            <div class="ssp-form-group" style="margin-top:12px;">
                                <label class="ssp-label">قالب پیام سفارشی (اختیاری)</label>
                                <textarea id="edit_rss_message_template" class="ssp-textarea" rows="3" placeholder="{title}\n\n{content}\n\nمنبع: {url}"></textarea>
                                <small class="ssp-hint">متغیرها: {title}, {content}, {url}, {excerpt}</small>
                            </div>
                        </div>`;
php = php.replace(editFeedTarget2, editFeedReplacement2);

fs.writeFileSync('includes/views/portal.php', php);
console.log('patched RSS forms UI in portal.php');
