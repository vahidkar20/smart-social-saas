const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

const oldCode1 = `<button class="ssp-btn-primary" onclick="saveDistribution()">ذخیره توزیع</button>
                                        <button class="ssp-btn-secondary" onclick="closeDistributionForm()">انصراف</button>
                                        <button class="ssp-btn-secondary" onclick="previewDistribution()">پیش‌نمایش</button>`;
const newCode1 = `<button type="button" class="ssp-btn-primary" onclick="saveDistribution()">ذخیره توزیع</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="closeDistributionForm()">انصراف</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="previewDistribution()">پیش‌نمایش</button>`;

if (php.includes(oldCode1)) {
    php = php.replace(oldCode1, newCode1);
    console.log('patched distribution form buttons');
} else {
    console.log('not found distribution form buttons');
}

fs.writeFileSync('includes/views/portal.php', php);
