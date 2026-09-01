<?php
/**
 * SSP WP Publish Trait - WordPress/WooCommerce publishing, product generation, image handling
 */
trait SSP_WpPublish {

    private function publish_to_wp_site($site, $title, $message, $url, $hashtags, $featured_image_url = '') {
        $post_type = $site['post_type'] ?? 'post';
        $rest_type = $this->get_wp_rest_type($post_type);
        $endpoint = rtrim($site['site_url'], '/') . '/wp-json/wp/v2/' . $rest_type;
        $auth = base64_encode($site['username'] . ':' . $site['app_password']);

        $full_content = $message;
        if (!empty($url)) $full_content .= "\n\n<p><a href=\"$url\" target=\"_blank\">ادامه مطلب</a></p>";
        if (!empty($hashtags)) $full_content .= "\n\n<p>" . esc_html($hashtags) . "</p>";

        $payload = [
            'title' => $title,
            'content' => $full_content,
            'status' => 'publish',
        ];

        $categories = $site['categories'] ?? '';
        if (!empty($categories)) {
            $cat_ids = array_filter(array_map('intval', explode(',', $categories)));
            if (!empty($cat_ids)) $payload['categories'] = $cat_ids;
        }

        if (!empty($featured_image_url)) {
            $media_id = $this->upload_featured_image($site, $featured_image_url);
            if ($media_id) $payload['featured_media'] = $media_id;
        }

        $args = [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Basic ' . $auth, 'Content-Type' => 'application/json'],
            'body' => json_encode($payload),
        ];

        $args = array_merge($args, $this->get_proxy_args());
        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) return ['success' => false, 'response' => $response->get_error_message()];
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 201) return ['success' => true, 'response' => 'منتشر شد: ' . ($body['link'] ?? '')];
        return ['success' => false, 'response' => $body['message'] ?? 'خطای ' . $code];
    }

    private function upload_featured_image($site, $image_url) {
        $auth = base64_encode($site['username'] . ':' . $site['app_password']);
        $upload_endpoint = rtrim($site['site_url'], '/') . '/wp-json/wp/v2/media';

        $image_response = wp_remote_get($image_url, array_merge(['timeout' => 30], $this->get_proxy_args()));
        if (is_wp_error($image_response)) return false;

        $content_type = wp_remote_retrieve_header($image_response, 'content-type');
        $ext = $this->get_extension_from_mime($content_type);
        $filename = 'featured-' . md5($image_url) . '.' . $ext;

        $upload_args = [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Type' => $content_type,
            ],
            'body' => wp_remote_retrieve_body($image_response),
        ];
        $upload_args = array_merge($upload_args, $this->get_proxy_args());

        $upload_response = wp_remote_post($upload_endpoint, $upload_args);

        if (is_wp_error($upload_response)) return false;
        $code = wp_remote_retrieve_response_code($upload_response);
        if ($code === 201) {
            $body = json_decode(wp_remote_retrieve_body($upload_response), true);
            return $body['id'] ?? false;
        }
        return false;
    }

    private function generate_ai_image($user_id, $prompt, $size = '1024x1024', $quality = 'standard') {
        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        if (empty($ai_api_key)) throw new Exception('API Key تنظیم نشده');

        $endpoint = 'https://api.openai.com/v1/images/generations';
        $args = [
            'timeout' => 120,
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $ai_api_key],
            'body' => json_encode(['model' => 'dall-e-3', 'prompt' => $prompt, 'n' => 1, 'size' => $size, 'quality' => $quality]),
        ];
        $args = array_merge($args, $this->get_proxy_args());

        $response = wp_remote_post($endpoint, $args);
        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200) throw new Exception($body['error']['message'] ?? 'خطای ' . $code);

        return [
            'url' => $body['data'][0]['url'] ?? '',
            'revised_prompt' => $body['data'][0]['revised_prompt'] ?? $prompt,
        ];
    }

    private function build_product_payload($data) {
        // Fix short_description: ensure proper HTML structure before sanitization
        $short_desc = $data['short_description'] ?? '';
        if (!empty($short_desc)) {
            // Decode HTML entities multiple times to handle nested encoding
            // AI often returns &lt; instead of <, sometimes double-encoded
            $short_desc = html_entity_decode($short_desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $short_desc = html_entity_decode($short_desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $short_desc = preg_replace('/<br(?!\s*\/?)>/i', '<br />', $short_desc);
            
            // Wrap standalone <strong>text</strong> or <strong>text</strong><br /> in <p> tags
            // This regex finds <strong>...</strong> optionally followed by <br /> that are NOT inside <p> tags
            $short_desc = preg_replace_callback(
                '/(?<!<p>)\s*(<strong[^>]*>.*?<\/strong>\s*(?:<br\s*\/?>\s*)?)(?!<\/p>)/is',
                function($matches) {
                    return '<p>' . trim($matches[1]) . '</p>';
                },
                $short_desc
            );
            
            // Ensure all tags are balanced
            $short_desc = force_balance_tags($short_desc);
            
            // Allow specific HTML tags for product short description
            $allowed_tags = [
                'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
                'ul' => [], 'ol' => [], 'li' => [], 'a' => ['href' => true, 'target' => true],
            ];
            $short_desc = wp_kses($short_desc, $allowed_tags);
        }
        
        // Fix description: ensure all tags are balanced and clean
        $description = $data['description'] ?? '';
        if (!empty($description)) {
            // Decode HTML entities multiple times to handle nested encoding
            // AI often returns &lt; instead of <, sometimes double-encoded
            $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $description = preg_replace('/<br(?!\s*\/?)>/i', '<br />', $description);
            // Ensure all tags are balanced
            $description = force_balance_tags($description);
            // Allow comprehensive HTML tags for product description
            $allowed_tags = [
                'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
                'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
                'ul' => [], 'ol' => [], 'li' => [], 'a' => ['href' => true, 'target' => true],
                'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
                'div' => [], 'span' => [], 'blockquote' => [], 'code' => [], 'pre' => [],
            ];
            $description = wp_kses($description, $allowed_tags);
        }
        
        $payload = [
            'name' => sanitize_text_field($data['product_name'] ?? ''),
            'status' => sanitize_text_field($data['product_status'] ?? 'draft'),
            'short_description' => $short_desc,
            'description' => $description,
            'regular_price' => sanitize_text_field($data['regular_price'] ?? ''),
            'sku' => sanitize_text_field($data['sku'] ?? ''),
            'stock_status' => sanitize_text_field($data['stock_status'] ?? 'instock'),
            'catalog_visibility' => sanitize_text_field($data['catalog_visibility'] ?? 'visible'),
        ];

        // Only add manage_stock and stock_quantity if manage_stock is explicitly enabled
        if (!empty($data['manage_stock']) && ($data['manage_stock'] === 'yes' || $data['manage_stock'] === '1')) {
            $payload['manage_stock'] = true;
            if (isset($data['stock_quantity'])) {
                $payload['stock_quantity'] = intval($data['stock_quantity']);
            }
        } else {
            $payload['manage_stock'] = false;
        }

        if (!empty($data['sale_price'])) $payload['sale_price'] = sanitize_text_field($data['sale_price']);
        if (!empty($data['date_on_sale_from'])) $payload['date_on_sale_from'] = sanitize_text_field($data['date_on_sale_from']);
        if (!empty($data['date_on_sale_to'])) $payload['date_on_sale_to'] = sanitize_text_field($data['date_on_sale_to']);
        if (!empty($data['purchase_limit'])) $payload['purchase_limit'] = intval($data['purchase_limit']);

        if (!empty($data['categories'])) {
            $cat_ids = array_filter(array_map('intval', (array)$data['categories']));
            if (!empty($cat_ids)) $payload['categories'] = array_map(function($id) { return ['id' => $id]; }, $cat_ids);
        }
        if (!empty($data['tags'])) {
            $tags_raw = (array)$data['tags'];
            $tag_ids = array_filter(array_map('intval', $tags_raw));
            if (!empty($tag_ids)) {
                $payload['tags'] = array_map(function($id) { return ['id' => $id]; }, $tag_ids);
            } else {
                $tag_names = array_filter(array_map('trim', (array)$data['tags']));
                if (!empty($tag_names)) {
                    $payload['tags'] = array_map(function($name) { return ['name' => $name]; }, $tag_names);
                }
            }
        }
        if (!empty($data['brand_id'])) {
            $payload['meta_data'] = $payload['meta_data'] ?? [];
            $payload['meta_data'][] = ['key' => 'product_brand', 'value' => intval($data['brand_id'])];
        }
        if (!empty($data['shipping_class_id'])) $payload['shipping_class_id'] = intval($data['shipping_class_id']);

        $attr_names = (array)($data['attribute_names'] ?? []);
        $attr_values = (array)($data['attribute_values'] ?? []);
        $attr_variation = (array)($data['attribute_variation'] ?? []);
        $attributes = [];
        foreach ($attr_names as $i => $attr_name) {
            if (!empty($attr_name) && isset($attr_values[$i])) {
                $attributes[] = [
                    'name' => sanitize_text_field($attr_name),
                    'options' => array_map('trim', explode(',', $attr_values[$i])),
                    'visible' => true,
                    'variation' => !empty($attr_variation[$i]),
                ];
            }
        }
        if (!empty($attributes)) $payload['attributes'] = $attributes;

        if (!empty($data['weight'])) $payload['weight'] = sanitize_text_field($data['weight']);
        if (!empty($data['length']) || !empty($data['width']) || !empty($data['height'])) {
            $payload['dimensions'] = [
                'length' => sanitize_text_field($data['length'] ?? ''),
                'width' => sanitize_text_field($data['width'] ?? ''),
                'height' => sanitize_text_field($data['height'] ?? ''),
            ];
        }

        if (!empty($data['cross_sell_ids'])) {
            $ids = array_filter(array_map('intval', (array)$data['cross_sell_ids']));
            if (!empty($ids)) $payload['cross_sell_ids'] = $ids;
        }
        if (!empty($data['upsell_ids'])) {
            $ids = array_filter(array_map('intval', (array)$data['upsell_ids']));
            if (!empty($ids)) $payload['upsell_ids'] = $ids;
        }
        if (!empty($data['grouped_products'])) {
            $ids = array_filter(array_map('intval', (array)$data['grouped_products']));
            if (!empty($ids)) $payload['grouped_products'] = $ids;
        }
        if (!empty($data['menu_order'])) $payload['menu_order'] = intval($data['menu_order']);
        if (!empty($data['virtual'])) $payload['virtual'] = true;
        if (!empty($data['downloadable'])) {
            $payload['downloadable'] = true;
            if (!empty($data['download_limit'])) $payload['download_limit'] = intval($data['download_limit']);
            if (!empty($data['download_expiry'])) $payload['download_expiry'] = intval($data['download_expiry']);
        }

        if (!empty($data['meta_title']) || !empty($data['meta_description'])) {
            $payload['meta_data'] = $payload['meta_data'] ?? [];
            if (!empty($data['meta_title'])) {
                $payload['meta_data'][] = ['key' => '_yoast_wpseo_title', 'value' => sanitize_text_field($data['meta_title'])];
                $payload['meta_data'][] = ['key' => 'rank_math_title', 'value' => sanitize_text_field($data['meta_title'])];
            }
            if (!empty($data['meta_description'])) {
                $payload['meta_data'][] = ['key' => '_yoast_wpseo_metadesc', 'value' => sanitize_textarea_field($data['meta_description'])];
                $payload['meta_data'][] = ['key' => 'rank_math_description', 'value' => sanitize_textarea_field($data['meta_description'])];
            }
        }

        $meta_keys = (array)($data['custom_meta_keys'] ?? []);
        $meta_values = (array)($data['custom_meta_values'] ?? []);
        if (!empty($meta_keys)) {
            $payload['meta_data'] = $payload['meta_data'] ?? [];
            foreach ($meta_keys as $i => $key) {
                if (!empty($key) && isset($meta_values[$i])) {
                    $payload['meta_data'][] = ['key' => sanitize_key($key), 'value' => sanitize_text_field($meta_values[$i])];
                }
            }
        }

        return $payload;
    }

    private function build_post_payload($data, $content_type) {
        // Fix content: ensure all tags are balanced and clean
        $content = $data['post_content'] ?? '';
        if (!empty($content)) {
            // Normalize <br> tags
            $content = preg_replace('/<br(?!\\s*\\/?)>/i', '<br />', $content);
            // Ensure all tags are balanced
            $content = force_balance_tags($content);
            // Final cleanup
            $content = wp_kses_post($content);
        }
        
        // Fix excerpt: ensure proper HTML structure
        $excerpt = $data['post_excerpt'] ?? '';
        if (!empty($excerpt)) {
            // Normalize <br> tags
            $excerpt = preg_replace('/<br(?!\\s*\\/?)>/i', '<br />', $excerpt);
            // Ensure all tags are balanced
            $excerpt = force_balance_tags($excerpt);
            // Final cleanup
            $excerpt = wp_kses_post($excerpt);
        }
        
        $payload = [
            'title' => sanitize_text_field($data['post_title'] ?? ''),
            'content' => $content,
            'status' => sanitize_text_field($data['post_status'] ?? 'publish'),
            'excerpt' => $excerpt,
        ];
        if (!empty($data['post_categories'])) {
            $cat_ids = array_filter(array_map('intval', (array)$data['post_categories']));
            if (!empty($cat_ids)) $payload['categories'] = $cat_ids;
        }
        if (!empty($data['post_tags'])) {
            $tag_names = array_filter(array_map('trim', explode(',', $data['post_tags'])));
            if (!empty($tag_names)) $payload['tags'] = $tag_names;
        }
        return $payload;
    }

    private function process_product_images($site, $data, $files) {
        $result = ['thumbnail' => 0, 'gallery' => [], 'all' => []];

        if (!empty($data['thumbnail_url'])) {
            $media_id = $this->upload_image_to_remote($site, $data['thumbnail_url'], $data['image_resize'] ?? '');
            if ($media_id) { $result['thumbnail'] = $media_id; $result['all'][] = $media_id; }
        }
        if (!empty($files['thumbnail_file']) && $files['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
            $media_id = $this->upload_file_to_remote($site, $files['thumbnail_file'], $data['image_resize'] ?? '');
            if ($media_id) { $result['thumbnail'] = $media_id; $result['all'][] = $media_id; }
        }
        $gallery_urls = (array)($data['gallery_urls'] ?? []);
        foreach ($gallery_urls as $url) {
            if (!empty($url)) {
                $media_id = $this->upload_image_to_remote($site, $url, $data['image_resize'] ?? '');
                if ($media_id) { $result['gallery'][] = $media_id; $result['all'][] = $media_id; }
            }
        }
        if (!empty($files['gallery_files'])) {
            $gallery_files = $files['gallery_files'];
            for ($i = 0; $i < count($gallery_files['name']); $i++) {
                if ($gallery_files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = ['name' => $gallery_files['name'][$i], 'type' => $gallery_files['type'][$i], 'tmp_name' => $gallery_files['tmp_name'][$i], 'error' => $gallery_files['error'][$i], 'size' => $gallery_files['size'][$i]];
                    $media_id = $this->upload_file_to_remote($site, $file, $data['image_resize'] ?? '');
                    if ($media_id) { $result['gallery'][] = $media_id; $result['all'][] = $media_id; }
                }
            }
        }
        return $result;
    }

    private function upload_image_to_remote($site, $image_url, $resize = '') {
        $temp_file = false;
        try {
            $temp_file = wp_tempnam('ssp_img_');
            $response = wp_remote_get($image_url, ['timeout' => 30, 'stream' => true, 'filename' => $temp_file]);
            if (is_wp_error($response) || !file_exists($temp_file)) {
                if ($temp_file && file_exists($temp_file)) @unlink($temp_file);
                return false;
            }
            $mime = wp_check_filetype(basename($image_url))['type'] ?? '';
            if (empty($mime)) { $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($temp_file); }
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowed)) { @unlink($temp_file); return false; }
            if (!empty($resize)) {
                $resized = $this->resize_image_file($temp_file, $resize, $mime);
                if ($resized) { @unlink($temp_file); $temp_file = $resized; }
            }
            $ext = $this->get_extension_from_mime($mime);
            $filename = 'product-' . md5($image_url . time()) . '.' . $ext;
            $auth = base64_encode($site['username'] . ':' . $site['app_password']);
            $upload_endpoint = rtrim($site['site_url'], '/') . '/wp-json/wp/v2/media';
            $args = [
                'timeout' => 60,
                'headers' => ['Authorization' => 'Basic ' . $auth, 'Content-Disposition' => 'attachment; filename="' . $filename . '"', 'Content-Type' => $mime],
                'body' => file_get_contents($temp_file),
            ];
            $args = array_merge($args, $this->get_proxy_args());
            $upload_response = wp_remote_post($upload_endpoint, $args);
            if ($temp_file && file_exists($temp_file)) @unlink($temp_file);
            if (is_wp_error($upload_response)) return false;
            $code = wp_remote_retrieve_response_code($upload_response);
            if ($code === 201) { $body = json_decode(wp_remote_retrieve_body($upload_response), true); return $body['id'] ?? false; }
            return false;
        } catch (Exception $e) {
            if ($temp_file && file_exists($temp_file)) @unlink($temp_file);
            return false;
        }
    }

    private function upload_file_to_remote($site, $file, $resize = '') {
        $temp_file = false;
        try {
            $temp_file = $file['tmp_name'];
            $mime = $file['type'] ?? '';
            if (empty($mime)) { $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($temp_file); }
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowed)) return false;
            $use_file = $temp_file;
            if (!empty($resize)) {
                $resized = $this->resize_image_file($temp_file, $resize, $mime);
                if ($resized) $use_file = $resized;
            }
            $ext = $this->get_extension_from_mime($mime);
            $filename = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $ext;
            $auth = base64_encode($site['username'] . ':' . $site['app_password']);
            $upload_endpoint = rtrim($site['site_url'], '/') . '/wp-json/wp/v2/media';
            $args = [
                'timeout' => 60,
                'headers' => ['Authorization' => 'Basic ' . $auth, 'Content-Disposition' => 'attachment; filename="' . $filename . '"', 'Content-Type' => $mime],
                'body' => file_get_contents($use_file),
            ];
            $args = array_merge($args, $this->get_proxy_args());
            $upload_response = wp_remote_post($upload_endpoint, $args);
            if ($use_file !== $temp_file && file_exists($use_file)) @unlink($use_file);
            if (is_wp_error($upload_response)) return false;
            $code = wp_remote_retrieve_response_code($upload_response);
            if ($code === 201) { $body = json_decode(wp_remote_retrieve_body($upload_response), true); return $body['id'] ?? false; }
            return false;
        } catch (Exception $e) { return false; }
    }

    private function resize_image_file($source, $target_size, $mime = 'image/jpeg') {
        $sizes = ['thumbnail' => [300, 300], 'medium' => [300, 300], 'woocommerce_thumbnail' => [600, 600], 'woocommerce_single' => [600, 600], 'woocommerce_gallery_thumbnail' => [100, 100]];
        if (!isset($sizes[$target_size])) return false;
        if (!function_exists('imagecreatefromjpeg') && $mime === 'image/jpeg') return false;
        $dims = $sizes[$target_size];
        switch ($mime) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($source); break;
            case 'image/png': $img = @imagecreatefrompng($source); break;
            case 'image/gif': $img = @imagecreatefromgif($source); break;
            case 'image/webp': $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false; break;
            default: return false;
        }
        if (!$img) return false;
        $orig_w = imagesx($img); $orig_h = imagesy($img);
        $ratio = min($dims[0] / $orig_w, $dims[1] / $orig_h);
        $new_w = (int)($orig_w * $ratio); $new_h = (int)($orig_h * $ratio);
        $resized = imagecreatetruecolor($new_w, $new_h);
        if ($mime === 'image/png' || $mime === 'image/gif') { imagealphablending($resized, false); imagesavealpha($resized, true); }
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        imagedestroy($img);
        $temp = wp_tempnam('ssp_resize_');
        switch ($mime) {
            case 'image/jpeg': imagejpeg($resized, $temp, 85); break;
            case 'image/png': imagepng($resized, $temp, 6); break;
            case 'image/gif': imagegif($resized, $temp); break;
            case 'image/webp': function_exists('imagewebp') ? imagewebp($resized, $temp, 85) : (imagedestroy($resized) && @unlink($temp)); break;
        }
        imagedestroy($resized);
        return $temp;
    }

    private function publish_woo_product($site, $payload) {
        $auth = base64_encode($site['username'] . ':' . $site['app_password']);
        $endpoint = rtrim($site['site_url'], '/') . '/wp-json/wc/v3/products';
        $args = [
            'timeout' => 60,
            'headers' => ['Authorization' => 'Basic ' . $auth, 'Content-Type' => 'application/json'],
            'body' => json_encode($payload),
        ];
        $args = array_merge($args, $this->get_proxy_args());
        $response = wp_remote_post($endpoint, $args);
        if (is_wp_error($response)) return ['success' => false, 'error' => $response->get_error_message()];
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code === 200 || $code === 201) return ['success' => true, 'url' => $body['permalink'] ?? '', 'id' => $body['id'] ?? 0];
        return ['success' => false, 'error' => $body['message'] ?? 'خطای ' . $code];
    }

    private function publish_wp_content($site, $payload, $content_type) {
        $rest_type = $this->get_wp_rest_type($content_type);
        $auth = base64_encode($site['username'] . ':' . $site['app_password']);
        $endpoint = rtrim($site['site_url'], '/') . '/wp-json/wp/v2/' . $rest_type;
        $args = [
            'timeout' => 60,
            'headers' => ['Authorization' => 'Basic ' . $auth, 'Content-Type' => 'application/json'],
            'body' => json_encode($payload),
        ];
        $args = array_merge($args, $this->get_proxy_args());
        $response = wp_remote_post($endpoint, $args);
        if (is_wp_error($response)) return ['success' => false, 'error' => $response->get_error_message()];
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code === 200 || $code === 201) return ['success' => true, 'url' => $body['link'] ?? '', 'id' => $body['id'] ?? 0];
        return ['success' => false, 'error' => $body['message'] ?? 'خطای ' . $code];
    }

    private function get_user_product_templates($user_id) {
        return get_user_meta($user_id, 'ssp_product_templates', true) ?: [];
    }

    private function set_user_product_templates($user_id, $templates) {
        update_user_meta($user_id, 'ssp_product_templates', $templates);
    }

    private function get_user_publish_history($user_id) {
        return get_user_meta($user_id, 'ssp_publish_history', true) ?: [];
    }

    private function add_publish_history($user_id, $entry) {
        $history = $this->get_user_publish_history($user_id);
        array_unshift($history, $entry);
        if (count($history) > 100) $history = array_slice($history, 0, 100);
        update_user_meta($user_id, 'ssp_publish_history', $history);
    }

    private function get_product_ai_prompt($content_type, $name, $brief, $prompt_mode, $custom_prompt) {
        if (($prompt_mode === 'custom' || $prompt_mode === 'template') && !empty($custom_prompt)) {
            return str_replace(['{name}', '{brief}', '{type}'], [$name, $brief, $content_type], $custom_prompt);
        }
        // Check for saved prompt templates
        if (strpos($prompt_mode, 'saved_') === 0) {
            $template_id = intval(substr($prompt_mode, 6));
            $user_id = get_current_user_id();
            $saved_templates = get_user_meta($user_id, 'ssp_prompt_templates', true) ?: [];
            foreach ($saved_templates as $t) {
                if ((int)$t['id'] === $template_id) {
                    return $this->build_prompt_from_saved_template($t, $name, $brief);
                }
            }
        }
        $templates = $this->get_product_prompt_templates();
        if ($content_type === 'product') {
            $key = isset($templates[$prompt_mode]) ? $prompt_mode : 'default_product';
        } else {
            $key = 'default_post';
        }
        return str_replace(['{name}', '{brief}'], [$name, $brief], $templates[$key]);
    }

    private function build_prompt_from_saved_template($template, $product_name, $product_brief = '') {
        $is_article = ($template['type'] ?? 'product') === 'article';
        $parts = [$is_article ? "موضوع: {$product_name}" : "محصول: {$product_name}"];
        if (!empty($product_brief)) $parts[] = "توضیح: {$product_brief}";
        if (!empty($template['industry'])) $parts[] = "\nحوزه: {$template['industry']}";
        if (!empty($template['tone'])) $parts[] = "لحن: {$template['tone']}";
        if (!empty($template['general_rules'])) $parts[] = "\nقوانین کلی:\n{$template['general_rules']}";
        if ($is_article) {
            if (!empty($template['short_rules'])) $parts[] = "\n--- ساختار مقاله ---\n{$template['short_rules']}";
            if (!empty($template['long_rules'])) $parts[] = "\n--- قوانین محتوا ---\n{$template['long_rules']}";
            if (!empty($template['seo_rules'])) $parts[] = "\n--- اطلاعات سئو ---\n{$template['seo_rules']}";
            $json_format = <<<'JSON'
فقط JSON معتبر با فیلدهای زیر تولید کن:
{
  "title": "عنوان ≤70 کاراکتر فارسی",
  "content": "محتوای HTML کامل مقاله با h2,h3,p,ul/li,strong. حداقل 800 کلمه",
  "excerpt": "خلاصه 2-3 جمله‌ای",
  "categories": ["دسته مرتبط"],
  "tags": ["تگ ۱", "تگ ۲"],
  "meta_title": "تیتر سئو ≤60 کاراکتر",
  "meta_description": "متا ≤170 کاراکتر"
}
JSON;
        } else {
            if (!empty($template['short_rules'])) $parts[] = "\n--- توضیحات کوتاه (short_description) ---\n{$template['short_rules']}";
            if (!empty($template['long_rules'])) $parts[] = "\n--- توضیحات بلند (description) ---\n{$template['long_rules']}";
            if (!empty($template['seo_rules'])) $parts[] = "\n--- اطلاعات سئو ---\n{$template['seo_rules']}";
            $json_format = <<<'JSON'
فقط JSON معتبر با فیلدهای زیر تولید کن:
{
  "name": "نام محصول ≤60 کاراکتر فارسی",
  "short_description": "توضیحات کوتاه HTML",
  "description": "توضیحات بلند HTML با h2,h3,p,ul/li,strong",
  "regular_price": "قیمت (خالی بگذار)",
  "sale_price": "",
  "categories": ["دسته مرتبط"],
  "tags": ["تگ ۱", "تگ ۲"],
  "meta_title": "تیتر سئو ≤60 کاراکتر",
  "meta_description": "متا ≤170 کاراکتر",
  "slug": "انگلیسی-خط-تیره"
}
JSON;
        }
        if (!empty($template['focus'])) $parts[] = "\nتمرکز: {$template['focus']}";
        if (!empty($template['forbidden'])) $parts[] = "ممنوعیات: {$template['forbidden']}";
        if (!empty($template['extra'])) $parts[] = "زمینه: {$template['extra']}";
        $parts[] = "\n{$json_format}";
        return implode("\n", $parts);
    }

    private function get_product_prompt_templates() {
        $product_fields = <<<'JSON'
{
  "name": "نام محصول ≤60 کاراکتر فارسی",
  "short_description": "2-3 جمله جذاب فارسی",
  "description": "HTML: <h2>,<p>,<ul><li>. 200-400 کلمه فارسی",
  "regular_price": "قیمت اصلی (تومان عددی)",
  "sale_price": "قیمت تخفیف (خالی اگر ندارد)",
  "categories": ["دسته 1"],
  "tags": ["تگ 1", "تگ 2"],
  "attributes": [{"name": "ویژگی", "options": ["مقدار"]}],
  "sku": "کد-محصول",
  "weight": "کیلوگرم",
  "meta_title": "SEO ≤60 کاراکتر",
  "meta_description": "SEO ≤155 کاراکتر"
}
JSON;
        $post_fields = <<<'JSON'
{
  "title": "عنوان ≤70 کاراکتر فارسی",
  "content": "HTML: <h2>,<p>,<ul><li>,<strong>. 400-800 کلمه فارسی",
  "excerpt": "2-3 جمله خلاصه",
  "categories": ["دسته 1"],
  "tags": ["تگ 1", "تگ 2"],
  "meta_title": "SEO ≤60 کاراکتر",
  "meta_description": "SEO ≤155 کاراکتر"
}
JSON;

        return [
            'default_product' => "تولید محصول WooCommerce فارسی.\nنام: {name}\nتوضیح: {brief}\n\n" .
                "توضیحات باید:\n- واقعی و تخصصی باشه، نه مصنوعی\n- مزایای عملی و کاربردی ذکر بشه\n- لحن حرفه‌ای ولی قابل فهم\n- HTML با تگ‌های <h2>,<p>,<ul>\n- فقط اطلاعات واقعی و تایید شده\n\n" .
                "فقط JSON معتبر:\n{$product_fields}",
            'default_post' => "تولید پست وردپرس فارسی.\nموضوع: {name}\nتوضیح: {brief}\n\n" .
                "محتوا باید:\n- آموزشی و کاربردی باشه\n- نکات عملی و واقعی داشته باشه\n- لحن طبیعی و انسانی\n- HTML با تگ‌های <h2>,<p>,<ul>,<strong>\n- فقط اطلاعات واقعی و تایید شده\n\n" .
                "فقط JSON معتبر:\n{$post_fields}",
            'fashion' => "محصول مد و پوشاک فارسی.\nنام: {name}\nتوضیح: {brief}\n\n" .
                "تمرکز: استایل واقعی، جنس پارچه، فیت مناسب، مناسب چه موقعیتی\nویژگی: سایز، رنگ، جنس\ndescription با HTML. فقط اطلاعات واقعی.\nفقط JSON:\n{$product_fields}",
            'electronics' => "محصول الکترونیکی فارسی.\nنام: {name}\nتوضیح: {brief}\n\n" .
                "تمرکز: مشخصات فنی واقعی، سازگاری، گارانتی\nویژگی: برند، مدل، رنگ\ndescription با HTML. فقط اطلاعات واقعی.\nفقط JSON:\n{$product_fields}",
            'food' => "محصول غذایی فارسی.\nنام: {name}\nتوضیح: {brief}\n\n" .
                "تمرکز: مواد اولیه واقعی، طعم، نحوه نگهداری\nویژگی: وزن، طعم، مواد تشکیل‌دهنده\ndescription با HTML. فقط اطلاعات واقعی.\nفقط JSON:\n{$product_fields}",
            'health_beauty' => "محصول بهداشتی فارسی.\nنام: {name}\nتوضیح: {brief}\n\n" .
                "تمرکز: مواد تشکیل‌دهنده واقعی، مزایا، نحوه استفاده\nویژگی: برند، حجم\ndescription با HTML. فقط اطلاعات واقعی.\nفقط JSON:\n{$product_fields}",
            'home_garden' => "محصول خانه و باغ فارسی.\nنام: {name}\nتوضیح: {brief}\n\n" .
                "تمرکز: ابعاد واقعی، جنس، نحوه نگهداری\nویژگی: جنس، رنگ\ndescription با HTML. فقط اطلاعات واقعی.\nفقط JSON:\n{$product_fields}",
            'sports' => "محصول ورزشی فارسی.\nنام: {name}\nتوضیح: {brief}\n\n" .
                "تمرکز: عملکرد واقعی، دوام، مناسب چه ورزشی\nویژگی: برند، سایز\ndescription با HTML. فقط اطلاعات واقعی.\nفقط JSON:\n{$product_fields}",
        ];
    }
}
