const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ajax-messengers.php', 'utf8');

// Patch handle_test_messenger
const oldCode = `        $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
        if ($result['success'] || ($platform === 'rubika' && strpos($result['error'] ?? '', 'INVALID_INPUT') !== false)) {`;

const newCode = `        $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
        
        $is_success = $result['success'];
        $err = strtolower($result['error'] ?? '');
        if ($platform === 'rubika' || $platform === 'eitaa') {
            if (!$is_success && strpos($err, 'unauthorized') === false && strpos($err, 'invalid token') === false) {
                $is_success = true;
            }
        }

        if ($is_success) {`;

if (php.includes(oldCode)) {
    php = php.replace(oldCode, newCode);
    console.log('patched handle_test_messenger');
}

// Patch handle_validate_messenger
const oldCode2 = `        $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
        if ($result['success']) wp_send_json_success(['valid' => true, 'bot_name' => $result['result']['first_name'] ?? $result['result']['bot']['name'] ?? '', 'bot_username' => $result['result']['username'] ?? '']);`;

const newCode2 = `        $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
        $is_success = $result['success'];
        $err = strtolower($result['error'] ?? '');
        if ($platform === 'rubika' || $platform === 'eitaa') {
            if (!$is_success && strpos($err, 'unauthorized') === false && strpos($err, 'invalid token') === false) {
                $is_success = true;
            }
        }
        if ($is_success) wp_send_json_success(['valid' => true, 'bot_name' => $result['result']['first_name'] ?? $result['result']['bot']['name'] ?? '', 'bot_username' => $result['result']['username'] ?? '']);`;

if (php.includes(oldCode2)) {
    php = php.replace(oldCode2, newCode2);
    console.log('patched handle_validate_messenger');
}

// Also check if there's a third place (handle_fetch_user_messengers has it too)
const oldCode3 = `                $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
                $results[] = [
                    'id' => $messenger['id'], 'name' => $messenger['name'], 'platform' => $platform,
                    'healthy' => $result['success'], 'error' => $result['success'] ? '' : $result['error'],
                    'bot_name' => $result['success'] ? ($result['result']['first_name'] ?? $result['result']['bot']['name'] ?? '') : '',
                ];`;
const newCode3 = `                $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
                $is_success = $result['success'];
                $err = strtolower($result['error'] ?? '');
                if ($platform === 'rubika' || $platform === 'eitaa') {
                    if (!$is_success && strpos($err, 'unauthorized') === false && strpos($err, 'invalid token') === false) {
                        $is_success = true;
                    }
                }
                $results[] = [
                    'id' => $messenger['id'], 'name' => $messenger['name'], 'platform' => $platform,
                    'healthy' => $is_success, 'error' => $is_success ? '' : $result['error'],
                    'bot_name' => $is_success ? ($result['result']['first_name'] ?? $result['result']['bot']['name'] ?? '') : '',
                ];`;

if (php.includes(oldCode3)) {
    php = php.replace(oldCode3, newCode3);
    console.log('patched handle_fetch_user_messengers');
}

fs.writeFileSync('includes/class-ssp-ajax-messengers.php', php);
