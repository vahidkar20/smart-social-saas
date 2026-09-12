const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-messenger.php', 'utf8');

const oldCode = `        $tmp = sys_get_temp_dir() . '/ssp_' . wp_generate_password(8, false) . $ext;
        $body = wp_remote_retrieve_body($response);
        file_put_contents($tmp, $body);

        $size = filesize($tmp);`;

const newCode = `        $tmp = sys_get_temp_dir() . '/ssp_' . wp_generate_password(8, false) . $ext;
        $body = wp_remote_retrieve_body($response);
        file_put_contents($tmp, $body);

        // Telegram/Eitaa/Bale treat .webp as stickers and fail if caption is present.
        // Convert .webp to .jpg
        if ($mime === 'image/webp' || $ext === '.webp') {
            if (function_exists('imagecreatefromwebp') && function_exists('imagejpeg')) {
                $img = @imagecreatefromwebp($tmp);
                if ($img !== false) {
                    $new_tmp = sys_get_temp_dir() . '/ssp_' . wp_generate_password(8, false) . '.jpg';
                    // Fill background with white for transparent webp
                    $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
                    $white = imagecolorallocate($bg, 255, 255, 255);
                    imagefill($bg, 0, 0, $white);
                    imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                    
                    if (@imagejpeg($bg, $new_tmp, 90)) {
                        @unlink($tmp);
                        $tmp = $new_tmp;
                        $mime = 'image/jpeg';
                        $name = preg_replace('/\.webp$/i', '.jpg', $name);
                    }
                    @imagedestroy($img);
                    @imagedestroy($bg);
                }
            }
        }

        $size = filesize($tmp);`;

if (php.includes('file_put_contents($tmp, $body);')) {
    php = php.replace(oldCode, newCode);
    fs.writeFileSync('includes/class-ssp-messenger.php', php);
    console.log('Patched download_file_for_upload');
} else {
    console.log('Could not find file_put_contents code');
}
