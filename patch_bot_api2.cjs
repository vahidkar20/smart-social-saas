const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-bot-builder.php', 'utf8');

let target = `$endpoints = [
            'telegram' => "https://api.telegram.org/bot$token/$method",
            'bale' => "https://tapi.bale.ai/bot$token/$method",
            'eitaa' => "https://eitaayar.ir/api/bot$token/$method",
            'rubika' => "https://messengerg2c42.iranlms.ir/v3/$token/$method",
        ];`;
let replacement = `$endpoints = [
            'telegram' => "https://api.telegram.org/bot$token/$method",
            'bale' => "https://tapi.bale.ai/bot$token/$method",
            'eitaa' => "https://eitaayar.ir/api/$token/$method",
            'rubika' => "https://botapi.rubika.ir/v3/$token/$method",
        ];`;

php = php.replace(target, replacement);

fs.writeFileSync('includes/class-ssp-bot-builder.php', php);
console.log('patched eitaa and rubika urls');
