<?php
/**
 * Plugin Name: Soft AI Chat
 * Plugin URI:  https://soft.io.vn/soft-ai-chat
 * Description: An AI Chat Widget (Groq, OpenAI, Gemini) that answers questions based on your website's content.
 * Version:     1.0.0
 * Author:      Tung Pham
 * License:     GPL-2.0+
 * Text Domain: soft-ai-chat
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// ---------------------------------------------------------
// 1. SETTINGS & ADMIN MENU
// ---------------------------------------------------------

add_action('admin_menu', 'soft_ai_chat_add_admin_menu');
add_action('admin_init', 'soft_ai_chat_settings_init');
add_action('admin_enqueue_scripts', 'soft_ai_chat_admin_enqueue');

function soft_ai_chat_add_admin_menu() {
    add_options_page(
        'Soft AI Chat Settings',
        'Soft AI Chat',
        'manage_options',
        'soft-ai-chat',
        'soft_ai_chat_options_page'
    );
}

function soft_ai_chat_admin_enqueue($hook_suffix) {
    if ($hook_suffix === 'settings_page_soft-ai-chat') {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Inline script to toggle API key fields based on provider selection
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($){ 
                function toggleFields() {
                    var provider = $('#soft_ai_provider_select').val();
                    $('.api-key-row').closest('tr').hide();
                    $('.row-' + provider).closest('tr').show();
                }
                $('#soft_ai_provider_select').change(toggleFields);
                toggleFields();
            });
        ");
    }
}

function soft_ai_chat_settings_init() {
    register_setting('softAiChat', 'soft_ai_chat_settings');

    add_settings_section('soft_ai_chat_main', __('General Configuration', 'soft-ai-chat'), null, 'softAiChat');

    add_settings_field('provider', __('Select AI Provider', 'soft-ai-chat'), 'soft_ai_chat_provider_render', 'softAiChat', 'soft_ai_chat_main');
    
    // API Keys
    add_settings_field('groq_api_key', __('Groq API Key', 'soft-ai-chat'), 'soft_ai_chat_groq_key_render', 'softAiChat', 'soft_ai_chat_main');
    add_settings_field('openai_api_key', __('OpenAI API Key', 'soft-ai-chat'), 'soft_ai_chat_openai_key_render', 'softAiChat', 'soft_ai_chat_main');
    add_settings_field('gemini_api_key', __('Google Gemini API Key', 'soft-ai-chat'), 'soft_ai_chat_gemini_key_render', 'softAiChat', 'soft_ai_chat_main');

    add_settings_field('model', __('AI Model Name', 'soft-ai-chat'), 'soft_ai_chat_model_render', 'softAiChat', 'soft_ai_chat_main');
    add_settings_field('temperature', __('Creativity (Temperature)', 'soft-ai-chat'), 'soft_ai_chat_temperature_render', 'softAiChat', 'soft_ai_chat_main');
    add_settings_field('max_tokens', __('Max Tokens', 'soft-ai-chat'), 'soft_ai_chat_maxtokens_render', 'softAiChat', 'soft_ai_chat_main');
    add_settings_field('theme_color', __('Widget Color', 'soft-ai-chat'), 'soft_ai_chat_themecolor_render', 'softAiChat', 'soft_ai_chat_main');
}

// --- Render Functions ---

function soft_ai_chat_provider_render() {
    $options = get_option('soft_ai_chat_settings');
    $val = isset($options['provider']) ? $options['provider'] : 'groq';
    ?>
    <select name="soft_ai_chat_settings[provider]" id="soft_ai_provider_select">
        <option value="groq" <?php selected($val, 'groq'); ?>>Groq (Fastest/Free Tier)</option>
        <option value="openai" <?php selected($val, 'openai'); ?>>OpenAI (GPT-4o/Turbo)</option>
        <option value="gemini" <?php selected($val, 'gemini'); ?>>Google Gemini</option>
    </select>
    <?php
}

function soft_ai_chat_groq_key_render() {
    $options = get_option('soft_ai_chat_settings');
    $val = $options['groq_api_key'] ?? '';
    echo "<div class='api-key-row row-groq'><input type='password' name='soft_ai_chat_settings[groq_api_key]' value='" . esc_attr($val) . "' style='width:400px;'>";
    echo "<p class='description'>Get key at <a href='https://console.groq.com/keys' target='_blank'>console.groq.com</a></p></div>";
}

function soft_ai_chat_openai_key_render() {
    $options = get_option('soft_ai_chat_settings');
    $val = $options['openai_api_key'] ?? '';
    echo "<div class='api-key-row row-openai'><input type='password' name='soft_ai_chat_settings[openai_api_key]' value='" . esc_attr($val) . "' style='width:400px;'>";
    echo "<p class='description'>Get key at <a href='https://platform.openai.com/api-keys' target='_blank'>platform.openai.com</a></p></div>";
}

function soft_ai_chat_gemini_key_render() {
    $options = get_option('soft_ai_chat_settings');
    $val = $options['gemini_api_key'] ?? '';
    echo "<div class='api-key-row row-gemini'><input type='password' name='soft_ai_chat_settings[gemini_api_key]' value='" . esc_attr($val) . "' style='width:400px;'>";
    echo "<p class='description'>Get key at <a href='https://aistudio.google.com/app/apikey' target='_blank'>aistudio.google.com</a></p></div>";
}

function soft_ai_chat_model_render() {
    $options = get_option('soft_ai_chat_settings');
    $value = isset($options['model']) && !empty($options['model']) ? $options['model'] : 'llama-3.3-70b-versatile';
    ?>
    <input type='text' name='soft_ai_chat_settings[model]' value='<?php echo esc_attr($value); ?>' style="width: 400px;">
    <p class="description">
        <strong>Common Models:</strong><br>
        Groq: <code>llama-3.3-70b-versatile</code>, <code>openai/gpt-oss-120b</code><br>
        OpenAI: <code>gpt-4o</code>, <code>gpt-4o-mini</code>, <code>gpt-3.5-turbo</code><br>
        Gemini: <code>gemini-2.5-flash</code>, <code>gemini-2.0-flash-lite</code>, <code>gemini-2.0-flash</code>, <code>gemini-2.5-pro</code>, <code>gemini-2.5-flash-lite</code>, <code>gemini-2.5-flash</code>, <code>gemini-3-flash-preview</code>, <code>gemini-3-pro-preview</code>
    </p>
    <?php
}

function soft_ai_chat_temperature_render() {
    $options = get_option('soft_ai_chat_settings');
    $current_val = isset($options['temperature']) ? floatval($options['temperature']) : 0.5;

    // Define the labels for specific ranges
    $get_label = function($val) {
        if ($val <= 0.2) return 'Very Precise';
        if ($val <= 0.4) return 'Precise (Factual)';
        if ($val <= 0.7) return 'Balanced';
        return 'Creative';
    };

    echo "<select name='soft_ai_chat_settings[temperature]'>";
    
    // Generate options from 0.1 to 1.0
    for ($i = 1; $i <= 10; $i++) {
        $val = $i / 10; // 0.1, 0.2, ... 1
        $label = "$val - " . $get_label($val);
        
        // Use the WordPress helper function selected() for the active state
        echo "<option value='" . esc_attr($val) . "' " . selected($current_val, $val, false) . ">" . esc_html($label) . "</option>";
    }

    echo "</select>";
}

function soft_ai_chat_maxtokens_render() {
    $options = get_option('soft_ai_chat_settings');
    $value = isset($options['max_tokens']) ? intval($options['max_tokens']) : 4096;
    echo "<input type='number' min='1024' max='8192' step='512' name='soft_ai_chat_settings[max_tokens]' value='" . esc_attr($value) . "' style='width:100px;'>";
}

function soft_ai_chat_themecolor_render() {
    $options = get_option('soft_ai_chat_settings');
    $value = isset($options['theme_color']) ? $options['theme_color'] : '#027DDD';
    ?>
    <input type="text" name="soft_ai_chat_settings[theme_color]" value="<?php echo esc_attr($value); ?>" class="soft-ai-color-field" data-default-color="#027DDD" />
    <script>jQuery(document).ready(function($){ $('.soft-ai-color-field').wpColorPicker(); });</script>
    <?php
}

function soft_ai_chat_options_page() {
    ?>
    <div class="wrap">
        <h1>Soft AI Chat Settings</h1>
        <form action='options.php' method='post'>
            <?php
            settings_fields('softAiChat');
            do_settings_sections('softAiChat');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// ---------------------------------------------------------
// 2. REST API & LOGIC
// ---------------------------------------------------------

add_action('rest_api_init', function () {
    register_rest_route('soft-ai-chat/v1', '/ask', [
        'methods' => 'POST',
        'callback' => 'soft_ai_chat_handle_request',
        'permission_callback' => '__return_true',
    ]);
});

function soft_ai_clean_utf8($content) {
    if (!is_string($content)) return '';
    if (function_exists('iconv')) $content = @iconv('UTF-8', 'UTF-8//IGNORE', $content);
    if (function_exists('mb_convert_encoding')) $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
}

function soft_ai_clean_divi_content($content) {
    $content = preg_replace('/\[\/?et_pb_[^\]]+\]/', '', $content);
    $content = wp_strip_all_tags($content);
    return preg_replace('/\s+/', ' ', $content);
}

function soft_ai_chat_get_context($question) {
    // Define post types to include Posts, Pages, and WooCommerce Products
    $post_types = ['post', 'page', 'product'];

    $args = [
        'post_type' => $post_types, 
        'post_status' => 'publish',
        'posts_per_page' => 5, 
        's' => $question, 
        'orderby' => 'relevance',
    ];
    $query = new WP_Query($args);
    $posts = $query->posts;

    // Fallback if no search results
    if (empty($posts)) {
        $posts = get_posts([
            'post_type' => $post_types, 
            'posts_per_page' => 3, 
            'orderby' => 'date', 
            'order' => 'DESC'
        ]);
    }

    $context = "";
    foreach ($posts as $post) {
        $title = soft_ai_clean_utf8($post->post_title);
        $link = get_permalink($post->ID);
        $raw = $post->post_content;
        
        // If it's a product, you might want to append the price or short description
        if ($post->post_type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $price = $product->get_price_html();
                $short_desc = $product->get_short_description();
                $raw .= " \nPrice: " . strip_tags($price) . "\nShort Description: " . $short_desc;
            }
        }
        
        if (strpos($raw, '[et_pb_') !== false) {
            $clean = soft_ai_clean_divi_content($raw);
        } else {
            $clean = wp_strip_all_tags($raw);
        }
        
        $clean = soft_ai_clean_utf8(preg_replace('/\s+/', ' ', $clean));
        if (mb_strlen($clean) > 2000) $clean = mb_substr($clean, 0, 2000) . "...";

        $context .= "--- ARTICLE ---\nTitle: $title\nLink: $link\nContent: $clean\n\n";
    }
    return empty($context) ? "No content found on website." : $context;
}

function soft_ai_chat_handle_request($request) {
    $options = get_option('soft_ai_chat_settings');
    $provider = $options['provider'] ?? 'groq';
    $model = $options['model'] ?? 'llama-3.3-70b-versatile';
    $temp = floatval($options['temperature'] ?? 0.5);
    $max_tokens = intval($options['max_tokens'] ?? 4096);

    $params = $request->get_json_params();
    $question = sanitize_text_field($params['question'] ?? '');
    
    if (empty($question)) return new WP_Error('missing_params', 'Please enter a question', ['status' => 400]);

    // 1. Get Context
    $context_data = soft_ai_chat_get_context($question);
    
    // 2. Prepare System Prompt
    $system_prompt = "You are a helpful AI assistant for this website.\n" .
                     "Answer strictly based on the 'Context Data' below.\n" .
                     "If you don't know, say you don't know.\n" .
                     "Include links when citing information.\n" .
                     "DO NOT use Markdown tables. Use bullet points or numbered lists for structured data.\n" . // <--- Dòng thêm mới
                     "Reply in Vietnamese.\n\n" .
                     "Context Data:\n" . $context_data;

    // 3. Route to Provider
    $answer = "";
    $error = null;

    if ($provider === 'groq') {
        $key = $options['groq_api_key'] ?? '';
        $res = soft_ai_chat_call_openai_compatible('https://api.groq.com/openai/v1/chat/completions', $key, $model, $system_prompt, $question, $temp, $max_tokens);
    } elseif ($provider === 'openai') {
        $key = $options['openai_api_key'] ?? '';
        $res = soft_ai_chat_call_openai_compatible('https://api.openai.com/v1/chat/completions', $key, $model, $system_prompt, $question, $temp, $max_tokens);
    } elseif ($provider === 'gemini') {
        $key = $options['gemini_api_key'] ?? '';
        $res = soft_ai_chat_call_gemini($key, $model, $system_prompt, $question, $temp, $max_tokens);
    } else {
        return new WP_Error('config_error', 'Invalid Provider', ['status' => 500]);
    }

    if (is_wp_error($res)) return $res;
    return rest_ensure_response(['answer' => $res]);
}

/**
 * Handler for OpenAI and Groq
 */
function soft_ai_chat_call_openai_compatible($endpoint, $api_key, $model, $system_prompt, $user_msg, $temp, $max_tokens) {
    if (empty($api_key)) return new WP_Error('missing_key', 'API Key is missing.', ['status' => 500]);

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => soft_ai_clean_utf8($user_msg)]
        ],
        'temperature' => $temp,
        'max_tokens' => $max_tokens,
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($payload),
        'timeout' => 45
    ]);

    if (is_wp_error($response)) return $response;
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['error'])) return new WP_Error('api_error', $body['error']['message'], ['status' => 500]);

    return $body['choices'][0]['message']['content'] ?? 'No response from AI.';
}

/**
 * Handler for Google Gemini
 */
function soft_ai_chat_call_gemini($api_key, $model, $system_prompt, $user_msg, $temp, $max_tokens) {
    if (empty($api_key)) return new WP_Error('missing_key', 'Gemini API Key is missing.', ['status' => 500]);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

    $payload = [
        'system_instruction' => [
            'parts' => [ ['text' => $system_prompt] ]
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [ ['text' => soft_ai_clean_utf8($user_msg)] ]
            ]
        ],
        'generationConfig' => [
            'temperature' => $temp,
            'maxOutputTokens' => $max_tokens
        ]
    ];

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($payload),
        'timeout' => 45
    ]);

    if (is_wp_error($response)) return $response;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) return new WP_Error('gemini_error', $body['error']['message'], ['status' => 500]);

    if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        return $body['candidates'][0]['content']['parts'][0]['text'];
    }

    return 'No response from Gemini.';
}

// ---------------------------------------------------------
// 3. FRONTEND WIDGET
// ---------------------------------------------------------

add_action('wp_footer', 'soft_ai_chat_inject_widget');

function soft_ai_chat_inject_widget() {
    $options = get_option('soft_ai_chat_settings');
    $provider = $options['provider'] ?? 'groq';
    $key_field = $provider . '_api_key';
    
    if (is_admin() || empty($options[$key_field])) return;

    $theme_color = $options['theme_color'] ?? '#027DDD';
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/11.1.1/marked.min.js"></script>
    <style>
        #soft-ai-chat-trigger {
            position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px;
            background-color: <?php echo esc_attr($theme_color); ?>; 
            color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 999999; transition: transform 0.2s; font-size: 24px;
        }
        #soft-ai-chat-trigger:hover { transform: scale(1.05); }

        #soft-ai-chat-window {
            position: fixed; bottom: 90px; right: 20px; width: 350px; height: 350px; max-height: 80vh;
            background: white; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            display: none; flex-direction: column; z-index: 9999; overflow: hidden; font-family: sans-serif;
            border: 1px solid #eee;
        }
        .sac-header { background: <?php echo esc_attr($theme_color); ?>; color: white; padding: 15px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .sac-close { cursor: pointer; }
        #sac-messages { flex: 1; padding: 15px; overflow-y: auto; background: #f9f9f9; display: flex; flex-direction: column; gap: 10px; font-size: 14px; }
        .sac-msg { padding: 10px 14px; border-radius: 10px; line-height: 1.5; max-width: 85%; word-wrap: break-word; }
        .sac-msg.user { align-self: flex-end; background: #333; color: white; border-bottom-right-radius: 2px; }
        .sac-msg.bot { align-self: flex-start; background: #fff; border: 1px solid #ddd; border-bottom-left-radius: 2px; color: #333; }
        .sac-msg.bot p { margin: 0 0 10px 0; } .sac-msg.bot p:last-child { margin: 0; }
        .sac-msg.bot ul { margin: 0 0 10px 20px; padding:0; }
        .sac-msg.bot a { color: <?php echo esc_attr($theme_color); ?>; }
        .sac-input-area { padding: 10px; border-top: 1px solid #eee; background: white; display: flex; gap: 5px; }
        #sac-input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; outline: none; }
        #sac-send { padding: 0 15px; background: <?php echo esc_attr($theme_color); ?>; color: white; border: none; border-radius: 6px; cursor: pointer; }
        #sac-send:disabled { background: #ccc; }
    </style>

    <div id="soft-ai-chat-trigger" onclick="toggleSoftAiChat()">💬</div>
    <div id="soft-ai-chat-window">
        <div class="sac-header"><span>Trợ lý thông minh</span><span class="sac-close" onclick="toggleSoftAiChat()">✕</span></div>
        <div id="sac-messages"><div class="sac-msg bot">Xin chào! Tôi có thể giúp gì cho bạn?</div></div>
        <div class="sac-input-area">
            <input type="text" id="sac-input" placeholder="Nhập câu hỏi..." onkeypress="handleEnter(event)">
            <button id="sac-send" onclick="askSoftAiChat()">Gửi</button>
        </div>
    </div>

    <script>
        const apiUrl = '<?php echo esc_url(rest_url('soft-ai-chat/v1/ask')); ?>';
        function toggleSoftAiChat() {
            const win = document.getElementById('soft-ai-chat-window');
            win.style.display = win.style.display === 'flex' ? 'none' : 'flex';
            if (win.style.display === 'flex') document.getElementById('sac-input').focus();
        }
        function handleEnter(e) { if (e.key === 'Enter') askSoftAiChat(); }
        async function askSoftAiChat() {
            const input = document.getElementById('sac-input');
            const msgs = document.getElementById('sac-messages');
            const btn = document.getElementById('sac-send');
            const q = input.value.trim();
            if (!q) return;

            msgs.innerHTML += `<div class="sac-msg user">${q.replace(/</g, "&lt;")}</div>`;
            msgs.innerHTML += `<div class="sac-msg loading" id="sac-loading">...</div>`;
            msgs.scrollTop = msgs.scrollHeight;
            input.value = ''; input.disabled = true; btn.disabled = true;

            try {
                const res = await fetch(apiUrl, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ question: q })
                });
                const data = await res.json();
                document.getElementById('sac-loading').remove();
                
                if (data.answer) {
                    msgs.innerHTML += `<div class="sac-msg bot">${marked.parse(data.answer)}</div>`;
                } else {
                    msgs.innerHTML += `<div class="sac-msg bot" style="color:red">${data.message || 'Error'}</div>`;
                }
            } catch (err) {
                document.getElementById('sac-loading')?.remove();
                msgs.innerHTML += `<div class="sac-msg bot" style="color:red">Connection Failed</div>`;
            }
            input.disabled = false; btn.disabled = false; input.focus(); msgs.scrollTop = msgs.scrollHeight;
        }
    </script>
    <?php
}