<?php
/**
 * Plugin Name: Soft AI Chat (All-in-One) - Enhanced Payment & Social Widgets
 * Plugin URI:  https://soft.io.vn/soft-ai-chat
 * Description: AI Chat Widget & Sales Bot. Supports RAG + WooCommerce + VietQR/PayPal + Facebook/Zalo Integration.
 * Version:     2.5.2
 * Author:      Tung Pham
 * License:     GPL-2.0+
 * Text Domain: soft-ai-chat
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// ---------------------------------------------------------
// 0. ACTIVATION: CREATE DATABASE TABLE
// ---------------------------------------------------------

register_activation_hook(__FILE__, 'soft_ai_chat_activate');

function soft_ai_chat_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'soft_ai_chat_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        user_ip varchar(100) DEFAULT '' NOT NULL,
        provider varchar(50) DEFAULT '' NOT NULL,
        model varchar(100) DEFAULT '' NOT NULL,
        question text NOT NULL,
        answer longtext NOT NULL,
        source varchar(50) DEFAULT 'widget' NOT NULL, 
        PRIMARY KEY  (id),
        KEY time (time)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// ---------------------------------------------------------
// 1. SETTINGS & ADMIN MENU
// ---------------------------------------------------------

add_action('admin_menu', 'soft_ai_chat_add_admin_menu');
add_action('admin_init', 'soft_ai_chat_settings_init');
add_action('admin_enqueue_scripts', 'soft_ai_chat_admin_enqueue');

function soft_ai_chat_add_admin_menu() {
    add_menu_page('Soft AI Chat', 'Soft AI Chat', 'manage_options', 'soft-ai-chat', 'soft_ai_chat_options_page', 'dashicons-format-chat', 80);
    add_submenu_page('soft-ai-chat', 'Settings', 'Settings', 'manage_options', 'soft-ai-chat', 'soft_ai_chat_options_page');
    add_submenu_page('soft-ai-chat', 'Chat History', 'Chat History', 'manage_options', 'soft-ai-chat-history', 'soft_ai_chat_history_page');
}

function soft_ai_chat_admin_enqueue($hook_suffix) {
    if ($hook_suffix === 'toplevel_page_soft-ai-chat') {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($){ 
                function toggleFields() {
                    var provider = $('#soft_ai_provider_select').val();
                    $('.api-key-row').closest('tr').hide();
                    $('.row-' + provider).closest('tr').show();
                }
                $('#soft_ai_provider_select').change(toggleFields);
                toggleFields();
                $('.soft-ai-color-field').wpColorPicker();
            });
        ");
    }
}

function soft_ai_chat_settings_init() {
    register_setting('softAiChat', 'soft_ai_chat_settings');

    // Section 1: AI Configuration
    add_settings_section('soft_ai_chat_main', __('General & AI Configuration', 'soft-ai-chat'), null, 'softAiChat');
    add_settings_field('save_history', __('Save Chat History', 'soft-ai-chat'), 'soft_ai_render_checkbox', 'softAiChat', 'soft_ai_chat_main', ['field' => 'save_history']);
    add_settings_field('provider', __('Select AI Provider', 'soft-ai-chat'), 'soft_ai_chat_provider_render', 'softAiChat', 'soft_ai_chat_main');
    add_settings_field('groq_api_key', __('Groq API Key', 'soft-ai-chat'), 'soft_ai_render_password', 'softAiChat', 'soft_ai_chat_main', ['field' => 'groq_api_key', 'class' => 'row-groq']);
    add_settings_field('openai_api_key', __('OpenAI API Key', 'soft-ai-chat'), 'soft_ai_render_password', 'softAiChat', 'soft_ai_chat_main', ['field' => 'openai_api_key', 'class' => 'row-openai']);
    add_settings_field('gemini_api_key', __('Google Gemini API Key', 'soft-ai-chat'), 'soft_ai_render_password', 'softAiChat', 'soft_ai_chat_main', ['field' => 'gemini_api_key', 'class' => 'row-gemini']);
    add_settings_field('model', __('AI Model Name', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_main', ['field' => 'model', 'default' => 'llama-3.3-70b-versatile','desc' => 'Recommended: <code>llama-3.3-70b-versatile</code> (Groq), <code>gpt-4o-mini</code> (OpenAI)']);
    add_settings_field('temperature', __('Creativity', 'soft-ai-chat'), 'soft_ai_render_number', 'softAiChat', 'soft_ai_chat_main', ['field' => 'temperature', 'default' => 0.5, 'step' => 0.1, 'max' => 1]);
    add_settings_field('max_tokens', __('Max Tokens', 'soft-ai-chat'), 'soft_ai_render_number', 'softAiChat', 'soft_ai_chat_main', ['field' => 'max_tokens', 'default' => 4096]);
    add_settings_field('system_prompt', __('Custom Persona', 'soft-ai-chat'), 'soft_ai_render_textarea', 'softAiChat', 'soft_ai_chat_main', ['field' => 'system_prompt']);
    
    // Section 2: Payment Integration
    add_settings_section('soft_ai_chat_payment', __('Payment Integration (Chat Only)', 'soft-ai-chat'), null, 'softAiChat');
    add_settings_field('vietqr_bank', __('VietQR Bank Code', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_payment', ['field' => 'vietqr_bank']);
    add_settings_field('vietqr_acc', __('VietQR Account No', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_payment', ['field' => 'vietqr_acc']);
    add_settings_field('vietqr_name', __('Account Name (Optional)', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_payment', ['field' => 'vietqr_name']);
    add_settings_field('paypal_me', __('PayPal.me Username', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_payment', ['field' => 'paypal_me']);

    // Section 3: UI
    add_settings_section('soft_ai_chat_ui', __('User Interface', 'soft-ai-chat'), null, 'softAiChat');
    // --- NEW FIELD: Chat Title ---
    add_settings_field('chat_title', __('Chat Window Title', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_ui', ['field' => 'chat_title', 'default' => 'Trợ lý AI']);
    add_settings_field('welcome_msg', __('Welcome Message', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_ui', ['field' => 'welcome_msg', 'default' => 'Xin chào! Bạn cần tìm gì ạ?', 'width' => '100%']);
    add_settings_field('theme_color', __('Widget Color', 'soft-ai-chat'), 'soft_ai_chat_themecolor_render', 'softAiChat', 'soft_ai_chat_ui');

    // Section 4: Social Integration
    add_settings_section('soft_ai_chat_social', __('Social Media Integration', 'soft-ai-chat'), 'soft_ai_chat_social_desc', 'softAiChat');
    
    // Facebook
    add_settings_field('fb_sep', '<strong>--- Facebook Messenger ---</strong>', 'soft_ai_render_sep', 'softAiChat', 'soft_ai_chat_social');
    add_settings_field('enable_fb_widget', __('Show FB Chat Bubble', 'soft-ai-chat'), 'soft_ai_render_checkbox', 'softAiChat', 'soft_ai_chat_social', ['field' => 'enable_fb_widget']);
    add_settings_field('fb_page_id', __('Facebook Page ID', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_social', ['field' => 'fb_page_id', 'desc' => 'Required for Chatbox Widget (Find in Page > About).']);
    add_settings_field('fb_app_access_token', __('Facebook App Access Token', 'soft-ai-chat'), 'soft_ai_render_token', 'softAiChat', 'soft_ai_chat_social', ['field' => 'fb_app_access_token', 'desc' => 'Required for extended API features (Optional).']);
    add_settings_field('fb_page_token', __('Facebook Page Access Token', 'soft-ai-chat'), 'soft_ai_render_token', 'softAiChat', 'soft_ai_chat_social', ['field' => 'fb_page_token', 'desc' => 'Required for AI Auto-Reply.']);
    add_settings_field('fb_verify_token', __('Facebook Verify Token', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_social', ['field' => 'fb_verify_token', 'default' => 'soft_ai_verify']);

    // Zalo
    add_settings_field('zalo_sep', '<strong>--- Zalo OA ---</strong>', 'soft_ai_render_sep', 'softAiChat', 'soft_ai_chat_social');
    add_settings_field('enable_zalo_widget', __('Show Zalo Widget', 'soft-ai-chat'), 'soft_ai_render_checkbox', 'softAiChat', 'soft_ai_chat_social', ['field' => 'enable_zalo_widget']);
    add_settings_field('zalo_oa_id', __('Zalo OA ID', 'soft-ai-chat'), 'soft_ai_render_text', 'softAiChat', 'soft_ai_chat_social', ['field' => 'zalo_oa_id', 'desc' => 'Required for Chat Widget.']);
    add_settings_field('zalo_access_token', __('Zalo OA Access Token', 'soft-ai-chat'), 'soft_ai_render_token', 'softAiChat', 'soft_ai_chat_social', ['field' => 'zalo_access_token', 'desc' => 'Required for AI Auto-Reply.']);
}

// --- Generic Render Helpers ---
function soft_ai_render_sep() { echo ''; }
function soft_ai_render_text($args) {
    $options = get_option('soft_ai_chat_settings');
    $val = $options[$args['field']] ?? ($args['default'] ?? '');
    $width = $args['width'] ?? '400px';
    echo "<input type='text' name='soft_ai_chat_settings[{$args['field']}]' value='" . esc_attr($val) . "' style='width: {$width};'>";
    if(isset($args['desc'])) echo "<p class='description'>{$args['desc']}</p>";
}
function soft_ai_render_textarea($args) {
    $options = get_option('soft_ai_chat_settings');
    $val = $options[$args['field']] ?? '';
    echo "<textarea name='soft_ai_chat_settings[{$args['field']}]' rows='5' style='width: 100%;'>" . esc_textarea($val) . "</textarea>";
    if(isset($args['desc'])) echo "<p class='description'>{$args['desc']}</p>";
}
function soft_ai_render_password($args) {
    $options = get_option('soft_ai_chat_settings');
    $val = $options[$args['field']] ?? '';
    $cls = $args['class'] ?? '';
    echo "<div class='api-key-row {$cls}'><input type='password' name='soft_ai_chat_settings[{$args['field']}]' value='" . esc_attr($val) . "' style='width:400px;'>";
    if(isset($args['desc'])) echo "<p class='description'>{$args['desc']}</p>";
    echo "</div>";
}
function soft_ai_render_token($args) {
    $options = get_option('soft_ai_chat_settings');
    $val = $options[$args['field']] ?? '';
    $cls = $args['class'] ?? '';
    echo "<div class='api-key-token-row {$cls}'><input type='password' name='soft_ai_chat_settings[{$args['field']}]' value='" . esc_attr($val) . "' style='width:400px;'>";
    if(isset($args['desc'])) echo "<p class='description'>{$args['desc']}</p>";
    echo "</div>";
}
function soft_ai_render_number($args) {
    $options = get_option('soft_ai_chat_settings');
    $val = $options[$args['field']] ?? ($args['default'] ?? 0);
    $step = $args['step'] ?? 1;
    $max = $args['max'] ?? 99999;
    echo "<input type='number' step='{$step}' max='{$max}' name='soft_ai_chat_settings[{$args['field']}]' value='" . esc_attr($val) . "' style='width:100px;'>";
}
function soft_ai_render_checkbox($args) {
    $options = get_option('soft_ai_chat_settings');
    $val = isset($options[$args['field']]) ? $options[$args['field']] : '0';
    echo '<label><input type="checkbox" name="soft_ai_chat_settings['.$args['field'].']" value="1" ' . checked($val, '1', false) . ' /> Enable</label>';
}

function soft_ai_chat_provider_render() {
    $options = get_option('soft_ai_chat_settings');
    $val = $options['provider'] ?? 'groq';
    ?>
    <select name="soft_ai_chat_settings[provider]" id="soft_ai_provider_select">
        <option value="groq" <?php selected($val, 'groq'); ?>>Groq (Llama 3/Mixtral)</option>
        <option value="openai" <?php selected($val, 'openai'); ?>>OpenAI (GPT-4o/Turbo)</option>
        <option value="gemini" <?php selected($val, 'gemini'); ?>>Google Gemini</option>
    </select>
    <?php
}

function soft_ai_chat_themecolor_render() {
    $options = get_option('soft_ai_chat_settings');
    $val = $options['theme_color'] ?? '#027DDD';
    echo '<input type="text" name="soft_ai_chat_settings[theme_color]" value="' . esc_attr($val) . '" class="soft-ai-color-field" />';
}

function soft_ai_chat_social_desc() {
    echo '<p>Configure webhooks to connect AI to social platforms. Use the "Page ID" and "OA ID" to display chat bubbles on your site.</p>';
    echo '<p><strong>Webhooks URL:</strong><br>FB: <code>' . rest_url('soft-ai-chat/v1/webhook/facebook') . '</code><br>Zalo: <code>' . rest_url('soft-ai-chat/v1/webhook/zalo') . '</code></p>';
}

function soft_ai_chat_options_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>Soft AI Chat Configuration</h1>
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
// 1.5. HISTORY PAGE
// ---------------------------------------------------------

function soft_ai_chat_history_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'soft_ai_chat_logs';

    if (isset($_POST['delete_log']) && check_admin_referer('delete_log_' . $_POST['log_id'])) {
        $wpdb->delete($table_name, ['id' => intval($_POST['log_id'])]);
        echo '<div class="updated"><p>Log deleted.</p></div>';
    }
    if (isset($_POST['clear_all_logs']) && check_admin_referer('clear_all_logs')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="updated"><p>All logs cleared.</p></div>';
    }

    $per_page = 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pages = ceil($total_items / $per_page);
    $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY time DESC LIMIT %d OFFSET %d", $per_page, $offset));
    ?>
    <div class="wrap">
        <h1>Chat History</h1>
        <form method="post" style="margin-bottom: 20px; text-align:right;">
            <?php wp_nonce_field('clear_all_logs'); ?>
            <input type="hidden" name="clear_all_logs" value="1">
            <button type="submit" class="button button-link-delete" onclick="return confirm('Delete ALL logs?')">Clear All History</button>
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="120">Time</th>
                    <th width="80">Source</th>
                    <th width="100">Provider</th>
                    <th width="20%">Question</th>
                    <th>Answer</th>
                    <th width="60">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->time); ?></td>
                    <td><span class="badge"><?php echo esc_html($log->source); ?></span></td>
                    <td><?php echo esc_html($log->provider); ?></td>
                    <td><?php echo esc_html($log->question); ?></td>
                    <td><div style="max-height:80px;overflow-y:auto;"><?php echo esc_html($log->answer); ?></div></td>
                    <td>
                        <form method="post">
                            <?php wp_nonce_field('delete_log_' . $log->id); ?>
                            <input type="hidden" name="delete_log" value="1"><input type="hidden" name="log_id" value="<?php echo $log->id; ?>">
                            <button class="button button-small">Del</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: echo '<tr><td colspan="6">No history found.</td></tr>'; endif; ?>
            </tbody>
        </table>
        <?php if ($total_pages > 1): echo paginate_links(['base' => add_query_arg('paged', '%#%'), 'total' => $total_pages, 'current' => $paged]); endif; ?>
    </div>
    <?php
}

// ---------------------------------------------------------
// 2. CONTEXT & STATE MANAGER
// ---------------------------------------------------------

class Soft_AI_Context {
    public $user_id;
    public $source;
    
    public function __construct($user_id, $source) {
        $this->user_id = $user_id;
        $this->source = $source;
    }

    public function get($key) {
        if ($this->source === 'widget') {
            return (function_exists('WC') && WC()->session) ? WC()->session->get('soft_ai_' . $key) : null;
        } else {
            $data = get_transient('soft_ai_sess_' . $this->user_id);
            return isset($data[$key]) ? $data[$key] : null;
        }
    }
    
    public function set($key, $val) {
        if ($this->source === 'widget') {
            if (function_exists('WC') && WC()->session) WC()->session->set('soft_ai_' . $key, $val);
        } else {
            $data = get_transient('soft_ai_sess_' . $this->user_id) ?: [];
            $data[$key] = $val;
            set_transient('soft_ai_sess_' . $this->user_id, $data, 24 * HOUR_IN_SECONDS);
        }
    }

    public function add_to_cart($product_id, $qty = 1) {
        if ($this->source === 'widget' && function_exists('WC')) {
            WC()->cart->add_to_cart($product_id, $qty);
        } else {
            $cart = $this->get('cart') ?: [];
            if (isset($cart[$product_id])) $cart[$product_id]['qty'] += $qty;
            else $cart[$product_id] = ['qty' => $qty];
            $this->set('cart', $cart);
        }
    }

    public function empty_cart() {
        if ($this->source === 'widget' && function_exists('WC')) WC()->cart->empty_cart();
        else $this->set('cart', []);
    }

    public function get_cart_count() {
        if ($this->source === 'widget' && function_exists('WC')) return WC()->cart->get_cart_contents_count();
        else {
            $c = 0; $cart = $this->get('cart') ?: [];
            foreach($cart as $i) $c += $i['qty'];
            return $c;
        }
    }

    public function get_cart_total_string() {
        if ($this->source === 'widget' && function_exists('WC')) return WC()->cart->get_cart_total();
        else {
            $total = 0; $cart = $this->get('cart') ?: [];
            foreach($cart as $pid => $item) {
                $p = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                if($p) $total += ($p->get_price() * $item['qty']);
            }
            return function_exists('wc_price') ? wc_price($total) : number_format($total) . 'đ';
        }
    }
}

// ---------------------------------------------------------
// 3. CORE LOGIC (HYBRID: RAG + ORDERING + PAYMENTS)
// ---------------------------------------------------------

function soft_ai_clean_content($content) {
    if (!is_string($content)) return '';
    $content = strip_shortcodes($content);
    $content = preg_replace('/\[\/?et_pb_[^\]]+\]/', '', $content);
    $content = wp_strip_all_tags($content);
    $content = preg_replace('/\s+/', ' ', $content);
    return mb_substr(trim($content), 0, 1500);
}

function soft_ai_chat_get_context($question) {
    $args = ['post_type' => ['post', 'page', 'product'], 'post_status' => 'publish', 'posts_per_page' => 4, 's' => $question, 'orderby' => 'relevance'];
    $posts = get_posts($args);

    $context = "";
    if ($posts) {
        foreach ($posts as $post) {
            $info = "";
            if ($post->post_type === 'product' && function_exists('wc_get_product')) {
                $p = wc_get_product($post->ID);
                if ($p) $info = " | Price: " . $p->get_price_html() . " | Status: " . $p->get_stock_status();
            }
            $clean_body = soft_ai_clean_content($post->post_content);
            $context .= "--- Source: {$post->post_title} ---\nLink: " . get_permalink($post->ID) . $info . "\nContent: $clean_body\n\n";
        }
    }
    return $context ?: "No specific website content found for this query.";
}

function soft_ai_log_chat($question, $answer, $source = 'widget') {
    global $wpdb;
    $opt = get_option('soft_ai_chat_settings');
    if (empty($opt['save_history'])) return;
    $wpdb->insert($wpdb->prefix . 'soft_ai_chat_logs', [
        'time' => current_time('mysql'),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'provider' => $opt['provider'] ?? 'unknown',
        'model' => $opt['model'] ?? 'unknown',
        'question' => $question,
        'answer' => $answer,
        'source' => $source
    ]);
}

/**
 * Helper: Clean Markdown/HTML for Social Platforms (Facebook/Zalo)
 */
function soft_ai_clean_text_for_social($content) {
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = preg_replace('/^\|?\s*[-:]+\s*(\|\s*[-:]+\s*)+\|?\s*$/m', '', $content);
    $content = preg_replace('/^\|\s*/m', '', $content);
    $content = preg_replace('/\s*\|$/m', '', $content);
    $content = str_replace('|', ' - ', $content);
    $content = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '$2', $content);
    $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1: $2', $content);
    $content = str_replace(['**', '__', '`'], '', $content);
    $content = preg_replace('/^#+\s*/m', '', $content);
    return trim(wp_strip_all_tags($content));
}

/**
 * Main AI Engine + State Machine
 */
function soft_ai_generate_answer($question, $platform = 'widget', $user_id = '') {
    if (empty($user_id)) $user_id = get_current_user_id() ?: md5($_SERVER['REMOTE_ADDR']);
    $context = new Soft_AI_Context($user_id, $platform);
    
    // 1. Flow Interruption
    $current_step = $context->get('bot_collecting_info_step');
    $cancel_keywords = ['huỷ', 'hủy', 'cancel', 'thôi', 'stop', 'thoát'];
    if (in_array(mb_strtolower(trim($question)), $cancel_keywords)) {
        $context->set('bot_collecting_info_step', null);
        return "Đã hủy thao tác hiện tại. Mình có thể giúp gì khác không?";
    }

    if ($current_step && class_exists('WooCommerce')) {
        $response = soft_ai_handle_ordering_steps($question, $current_step, $context);
        if ($platform === 'facebook' || $platform === 'zalo') {
            return soft_ai_clean_text_for_social($response);
        }
        return $response;
    }

    // 2. Setup AI
    $options = get_option('soft_ai_chat_settings');
    $provider = $options['provider'] ?? 'groq';
    $model = $options['model'] ?? 'llama-3.3-70b-versatile';
    
    // 3. Prompt Engineering
    $site_context = soft_ai_chat_get_context($question);
    $user_instruction = $options['system_prompt'] ?? '';
    
    $system_prompt = "You are a helpful AI assistant for this website.\n" .
                     ($user_instruction ? "Additional Persona: $user_instruction\n" : "") .
                     "Website Content Context:\n" . $site_context . "\n\n" .
                     "CRITICAL INSTRUCTIONS:\n" . 
                     "1. If user wants to BUY/ORDER/FIND products, return STRICT JSON only (no markdown):\n" .
                     "   {\"action\": \"find_product\", \"query\": \"product name\"}\n" .
                     "   {\"action\": \"check_cart\"}\n" .
                     "   {\"action\": \"checkout\"}\n" .
                     "2. For general chat, answer normally in Vietnamese.\n" .
                     "3. If unknown, admit it politely.";

    // 4. Call API
    $ai_response = soft_ai_chat_call_api($provider, $model, $system_prompt, $question, $options);
    if (is_wp_error($ai_response)) return "Lỗi hệ thống: " . $ai_response->get_error_message();

    // 5. Clean & Parse JSON
    $clean_response = trim($ai_response);
    if (preg_match('/```json\s*(.*?)\s*```/s', $clean_response, $matches)) {
        $clean_response = $matches[1];
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $clean_response, $matches)) {
        $clean_response = $matches[1];
    }

    $intent = json_decode($clean_response, true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($intent['action']) && class_exists('WooCommerce')) {
        $response = soft_ai_process_order_logic($intent, $context);
        if ($platform === 'facebook' || $platform === 'zalo') {
            return soft_ai_clean_text_for_social($response);
        }
        return $response;
    }

    // 6. Return Text
    if ($platform === 'facebook' || $platform === 'zalo') {
        return soft_ai_clean_text_for_social($clean_response);
    }
    return $clean_response;
}

// Logic Dispatcher
function soft_ai_process_order_logic($intent, $context) {
    $action = $intent['action'];
    $source = $context->source;

    switch ($action) {
        case 'find_product':
            $query = sanitize_text_field($intent['query'] ?? '');
            $products = wc_get_products(['status' => 'publish', 'limit' => 1, 's' => $query]);
            
            if (!empty($products)) {
                $p = $products[0];
                if (!$p->is_in_stock()) return "Sản phẩm " . $p->get_name() . " hiện đang hết hàng ạ.";

                $context->set('pending_product_id', $p->get_id());
                
                $attributes = $p->get_attributes();
                $attr_keys = array_keys($attributes); 
                
                if (!empty($attr_keys) && $p->is_type('variable')) {
                    $context->set('attr_queue', $attr_keys);
                    $context->set('attr_answers', []); 
                    $context->set('bot_collecting_info_step', 'process_attribute_loop'); 
                    $question = soft_ai_ask_next_attribute($context, $p);
                    return ($source == 'widget') 
                        ? "Tìm thấy: <b>" . $p->get_name() . "</b>.<br>" . $question 
                        : "Tìm thấy: " . $p->get_name() . ".\n" . $question;
                } else {
                    $context->set('bot_collecting_info_step', 'ask_quantity');
                    return "Đã tìm thấy " . $p->get_name() . ". Bạn muốn lấy số lượng bao nhiêu?";
                }
            }
            return "Xin lỗi, mình không tìm thấy sản phẩm nào khớp với '$query'.";

        case 'check_cart':
            $count = $context->get_cart_count();
            return $count > 0 
                ? "Giỏ hàng có $count sản phẩm (" . $context->get_cart_total_string() . "). Gõ 'Thanh toán' để đặt hàng nhé." 
                : "Giỏ hàng của bạn đang trống.";

        case 'checkout':
            if ($context->get_cart_count() == 0) return "Giỏ hàng trống. Hãy chọn sản phẩm trước nhé!";
            
            $has_info = false;
            $name = '';
            
            if ($source === 'widget' && WC()->customer->get_billing_first_name() && WC()->customer->get_billing_email()) {
                $has_info = true; 
                $name = WC()->customer->get_billing_first_name();
            } else {
                $saved = $context->get('user_info');
                if (!empty($saved['name']) && !empty($saved['email'])) { 
                    $has_info = true; 
                    $name = $saved['name']; 
                }
            }

            if ($has_info) {
                return soft_ai_present_payment_gateways($context, "Chào $name! Bạn muốn thanh toán qua đâu?");
            } else {
                $context->set('bot_collecting_info_step', 'fullname');
                return "Để đặt hàng, cho em xin Họ và Tên của bạn ạ?";
            }
            break;
    }
    return "Tôi chưa hiểu yêu cầu này. Bạn có thể nói rõ hơn không?";
}

// State Machine Handlers
function soft_ai_handle_ordering_steps($message, $step, $context) {
    $clean_message = trim($message);
    $source = $context->source;

    switch ($step) {
        case 'process_attribute_loop':
            $current_slug = $context->get('current_asking_attr');
            $answers = $context->get('attr_answers') ?: [];
            $answers[$current_slug] = $clean_message;
            $context->set('attr_answers', $answers);
            
            $p = wc_get_product($context->get('pending_product_id'));
            return soft_ai_ask_next_attribute($context, $p);

        case 'ask_quantity':
            $qty = intval($clean_message);
            if ($qty <= 0) return "Số lượng phải lớn hơn 0. Vui lòng nhập lại:";
            
            $pid = $context->get('pending_product_id');
            if ($pid) {
                $context->add_to_cart($pid, $qty);
                $context->set('bot_collecting_info_step', null);
                $total = $context->get_cart_total_string();
                return "✅ Đã thêm vào giỏ. Tổng đơn: $total.\nGõ 'Thanh toán' để chốt đơn hoặc hỏi mua tiếp.";
            }
            return "Có lỗi xảy ra với sản phẩm. Vui lòng tìm lại.";

        case 'fullname':
            $context->set('temp_name', $clean_message);
            if ($source === 'widget') WC()->customer->set_billing_first_name($clean_message);
            $context->set('bot_collecting_info_step', 'phone');
            return "Chào $clean_message, cho em xin Số điện thoại liên hệ?";

        case 'phone':
            if (!preg_match('/^[0-9]{9,12}$/', $clean_message)) return "Số điện thoại không hợp lệ. Vui lòng nhập lại:";
            $context->set('temp_phone', $clean_message);
            if ($source === 'widget') WC()->customer->set_billing_phone($clean_message);
            $context->set('bot_collecting_info_step', 'email');
            return "Dạ, cho em xin địa chỉ Email để gửi thông tin đơn hàng và thanh toán ạ?";

        case 'email':
            if (!is_email($clean_message)) return "Email không hợp lệ. Vui lòng nhập lại (ví dụ: ten@gmail.com):";
            $context->set('temp_email', $clean_message);
            if ($source === 'widget') WC()->customer->set_billing_email($clean_message);
            $context->set('bot_collecting_info_step', 'address');
            return "Cuối cùng, cho em xin Địa chỉ giao hàng cụ thể ạ?";

        case 'address':
            $context->set('temp_address', $clean_message);
            if ($source === 'widget') {
                WC()->customer->set_billing_address_1($clean_message);
                WC()->customer->save();
            } else {
                $context->set('user_info', [
                    'name' => $context->get('temp_name'),
                    'phone' => $context->get('temp_phone'),
                    'email' => $context->get('temp_email'),
                    'address' => $clean_message
                ]);
            }
            return soft_ai_present_payment_gateways($context, "Đã lưu địa chỉ. Bạn chọn hình thức thanh toán nào?");

        case 'payment_method':
            // 1. Check Custom Methods (VietQR / PayPal)
            $method_key = mb_strtolower($clean_message);
            if (strpos($method_key, 'vietqr') !== false || strpos($method_key, 'chuyển khoản') !== false || strpos($method_key, 'qr') !== false) {
                return soft_ai_finalize_order($context, 'vietqr_custom');
            }
            if (strpos($method_key, 'paypal') !== false) {
                return soft_ai_finalize_order($context, 'paypal_custom');
            }

            // 2. Check Standard WC Gateways
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            $selected = null;
            foreach ($gateways as $g) {
                if (stripos($g->title, $clean_message) !== false || stripos($g->id, $clean_message) !== false) { 
                    $selected = $g; break; 
                }
            }
            // Default Fallback
            if (!$selected && (stripos($clean_message, 'cod') !== false || stripos($clean_message, 'mặt') !== false)) {
                $selected = $gateways['cod'] ?? null;
            }

            if (!$selected) return "Phương thức chưa đúng. Vui lòng nhập lại (ví dụ: VietQR, PayPal, COD).";

            return soft_ai_finalize_order($context, $selected);
    }
    return "";
}

function soft_ai_ask_next_attribute($context, $product) {
    $queue = $context->get('attr_queue');
    if (empty($queue)) {
        $context->set('bot_collecting_info_step', 'ask_quantity');
        return "Bạn muốn lấy số lượng bao nhiêu?";
    }
    $current = array_shift($queue);
    $context->set('attr_queue', $queue);
    $context->set('current_asking_attr', $current);
    
    return "Bạn chọn " . wc_attribute_label($current) . " nào?";
}

function soft_ai_present_payment_gateways($context, $msg) {
    $gateways = WC()->payment_gateways->get_available_payment_gateways();
    $opts = get_option('soft_ai_chat_settings');
    
    $list = "";
    $prefix = ($context->source == 'widget') ? "<br>• " : "\n- ";

    // Add Standard Gateways
    foreach ($gateways as $g) {
        $list .= $prefix . $g->get_title();
    }
    
    // Add Custom Chat Integrations
    if (!empty($opts['vietqr_bank']) && !empty($opts['vietqr_acc'])) {
        $list .= $prefix . "VietQR (Chuyển khoản nhanh)";
    }
    if (!empty($opts['paypal_me'])) {
        $list .= $prefix . "PayPal";
    }

    $context->set('bot_collecting_info_step', 'payment_method');
    return $msg . $list;
}

function soft_ai_finalize_order($context, $gateway_or_code) {
    try {
        $order = wc_create_order();
        $opts = get_option('soft_ai_chat_settings');

        // Add Products
        if ($context->source === 'widget') {
            foreach (WC()->cart->get_cart() as $values) $order->add_product($values['data'], $values['quantity']);
            $billing = [
                'first_name' => WC()->customer->get_billing_first_name(),
                'phone'      => WC()->customer->get_billing_phone(),
                'address_1'  => WC()->customer->get_billing_address_1(),
                'email'      => WC()->customer->get_billing_email() ?: $context->get('temp_email')
            ];
        } else {
            $cart = $context->get('cart') ?: [];
            foreach ($cart as $pid => $item) {
                $p = wc_get_product($pid);
                if($p) $order->add_product($p, $item['qty']);
            }
            $billing = [
                'first_name' => $context->get('temp_name'),
                'phone'      => $context->get('temp_phone'),
                'address_1'  => $context->get('temp_address'),
                'email'      => $context->get('temp_email') ?: 'social-guest@example.com'
            ];
        }
        
        if (empty($billing['email'])) $billing['email'] = 'no-email@example.com';
        $order->set_address($billing, 'billing');

        $extra_msg = "";
        
        // Handle Payment Methods
        if ($gateway_or_code === 'vietqr_custom') {
            $order->set_payment_method('bacs');
            $order->set_payment_method_title('VietQR (Chat)');
            $order->calculate_totals();
            
            $bacs_accounts = get_option('woocommerce_bacs_accounts');
            $bank = ''; $acc = ''; $name = '';

            if (!empty($bacs_accounts) && is_array($bacs_accounts)) {
                $account = $bacs_accounts[0];
                $bank = str_replace(' ', '', $account['bank_name']); 
                $acc  = str_replace(' ', '', $account['account_number']);
                $raw_name = $account['account_name'];
                $name = str_replace(' ', '%20', $raw_name);
            } else {
                 $bank = str_replace(' ', '', $opts['vietqr_bank'] ?? '');
                 $acc  = str_replace(' ', '', $opts['vietqr_acc'] ?? '');
                 $name = str_replace(' ', '%20', $opts['vietqr_name'] ?? '');
            }

            $amt = intval($order->get_total()); 
            $desc = "DH" . $order->get_id(); 
            
            if ($bank && $acc) {
                $qr_url = "https://img.vietqr.io/image/{$bank}-{$acc}-compact.jpg?amount={$amt}&addInfo={$desc}&accountName={$name}";
                $extra_msg = "\n\n⬇️ **Quét mã để thanh toán:**\n![VietQR]($qr_url)";
                if ($context->source == 'widget') $extra_msg = "<br><br><b>Quét mã để thanh toán:</b><br><img src='$qr_url' style='max-width:100%; border-radius:8px;'>";
            } else {
                $extra_msg = "\n\n(Vui lòng cập nhật thông tin ngân hàng trong cài đặt WooCommerce)";
            }
            
        } elseif ($gateway_or_code === 'paypal_custom') {
            $order->set_payment_method('paypal');
            $order->set_payment_method_title('PayPal (Chat Link)');
            $order->calculate_totals();
            
            $raw_user = $opts['paypal_me'] ?? '';
            $raw_user = str_replace(['https://', 'http://', 'paypal.me/', '/'], '', $raw_user);
            $currency = get_woocommerce_currency(); 
            $amt = $order->get_total();
            
            $pp_link = "https://paypal.me/{$raw_user}/{$amt}{$currency}";
            
            $extra_msg = "\n\n👉 [Nhấn để thanh toán PayPal]($pp_link)";
            if ($context->source == 'widget') $extra_msg = "<br><br><a href='$pp_link' target='_blank' style='background:#0070ba;color:white;padding:10px 15px;border-radius:5px;text-decoration:none;font-weight:bold;'>Thanh toán ngay với PayPal</a>";
            
        } else {
            $order->set_payment_method($gateway_or_code);
            $order->calculate_totals();
        }

        $order->update_status('on-hold', "Order via Soft AI Chat ({$context->source})");
        
        $context->empty_cart();
        $context->set('bot_collecting_info_step', null);
        
        $base_msg = "🎉 ĐẶT HÀNG THÀNH CÔNG!\nMã đơn: #" . $order->get_id() . "\nEmail xác nhận đã gửi tới " . $billing['email'] . ".";
        
        return $base_msg . $extra_msg;

    } catch (Exception $e) {
        return "Lỗi khi tạo đơn: " . $e->getMessage() . ". Vui lòng liên hệ Hotline.";
    }
}

// ---------------------------------------------------------
// 4. API CALLER (Unified)
// ---------------------------------------------------------

function soft_ai_chat_call_api($provider, $model, $sys, $user, $opts) {
    $api_key = $opts[$provider . '_api_key'] ?? '';
    if (!$api_key) return new WP_Error('missing_key', 'API Key Missing');

    $url = '';
    $headers = ['Content-Type' => 'application/json'];
    $body = [];

    switch ($provider) {
        case 'groq':
            $url = 'https://api.groq.com/openai/v1/chat/completions';
            $headers['Authorization'] = 'Bearer ' . $api_key;
            $body = [
                'model' => $model,
                'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                'temperature' => (float)$opts['temperature'],
                'max_tokens' => (int)$opts['max_tokens']
            ];
            break;
        case 'openai':
            $url = 'https://api.openai.com/v1/chat/completions';
            $headers['Authorization'] = 'Bearer ' . $api_key;
            $body = [
                'model' => $model,
                'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                'temperature' => (float)$opts['temperature'],
                'max_tokens' => (int)$opts['max_tokens']
            ];
            break;
        case 'gemini':
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
            $body = [
                'system_instruction' => ['parts' => [['text' => $sys]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig' => ['temperature' => (float)$opts['temperature'], 'maxOutputTokens' => (int)$opts['max_tokens']]
            ];
            break;
    }

    $response = wp_remote_post($url, [
        'headers' => $headers, 
        'body' => json_encode($body), 
        'timeout' => 60
    ]);

    if (is_wp_error($response)) return $response;
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        return $data['choices'][0]['message']['content'];
    }
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    return "API Error: " . wp_remote_retrieve_body($response);
}

// ---------------------------------------------------------
// 5. REST API & WEBHOOKS
// ---------------------------------------------------------

add_action('rest_api_init', function () {
    register_rest_route('soft-ai-chat/v1', '/ask', [
        'methods' => 'POST',
        'callback' => 'soft_ai_chat_handle_widget_request',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('soft-ai-chat/v1', '/webhook/facebook', [
        'methods' => ['GET', 'POST'],
        'callback' => 'soft_ai_chat_webhook_facebook',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('soft-ai-chat/v1', '/webhook/zalo', [
        'methods' => 'POST',
        'callback' => 'soft_ai_chat_webhook_zalo',
        'permission_callback' => '__return_true',
    ]);
});

function soft_ai_chat_handle_widget_request($request) {
    if (function_exists('WC') && !WC()->session) {
        $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
        WC()->session = new $session_class();
        WC()->session->init();
        if (!WC()->cart) { WC()->cart = new WC_Cart(); WC()->cart->get_cart(); }
        if (!WC()->customer) { WC()->customer = new WC_Customer(get_current_user_id()); }
    }

    $params = $request->get_json_params();
    $question = sanitize_text_field($params['question'] ?? '');
    
    if (!$question) return new WP_Error('no_input', 'Empty Question', ['status' => 400]);

    $answer = soft_ai_generate_answer($question, 'widget');
    soft_ai_log_chat($question, $answer, 'widget');
    
    return rest_ensure_response(['answer' => $answer]);
}

function soft_ai_chat_webhook_facebook($request) {
    $options = get_option('soft_ai_chat_settings');
    $verify_token = $options['fb_verify_token'] ?? 'soft_ai_verify';

    if ($request->get_method() === 'GET') {
        $params = $request->get_query_params();
        if (isset($params['hub_verify_token']) && $params['hub_verify_token'] === $verify_token) {
            echo $params['hub_challenge']; exit;
        }
        return new WP_Error('forbidden', 'Invalid Token', ['status' => 403]);
    }

    $body = $request->get_json_params();
    if (isset($body['object']) && $body['object'] === 'page') {
        foreach ($body['entry'] as $entry) {
            foreach ($entry['messaging'] as $event) {
                if (isset($event['message']['text']) && !isset($event['message']['is_echo'])) {
                    $sender = $event['sender']['id'];
                    $reply = soft_ai_generate_answer($event['message']['text'], 'facebook', $sender);
                    soft_ai_send_fb_message($sender, $reply, $options['fb_page_token']);
                    soft_ai_log_chat($event['message']['text'], $reply, 'facebook');
                }
            }
        }
        return rest_ensure_response(['status' => 'EVENT_RECEIVED']);
    }
    return new WP_Error('bad_req', 'Invalid FB Data', ['status' => 404]);
}

function soft_ai_send_fb_message($recipient, $text, $token) {
    if (!$token) return;
    $chunks = str_split($text, 1900);
    foreach ($chunks as $chunk) {
        wp_remote_post("https://graph.facebook.com/v21.0/me/messages?access_token=$token", [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['recipient' => ['id' => $recipient], 'message' => ['text' => $chunk]])
        ]);
    }
}

function soft_ai_chat_webhook_zalo($request) {
    $body = $request->get_json_params();
    if (isset($body['event_name']) && $body['event_name'] === 'user_send_text') {
        $sender = $body['sender']['id'];
        $reply = soft_ai_generate_answer($body['message']['text'], 'zalo', $sender);
        
        $token = get_option('soft_ai_chat_settings')['zalo_access_token'] ?? '';
        if ($token) {
            wp_remote_post("https://openapi.zalo.me/v3.0/oa/message/cs", [
                'headers' => ['access_token' => $token, 'Content-Type' => 'application/json'],
                'body' => json_encode(['recipient' => ['user_id' => $sender], 'message' => ['text' => $reply]])
            ]);
        }
        soft_ai_log_chat($body['message']['text'], $reply, 'zalo');
        return rest_ensure_response(['status' => 'success']);
    }
    return rest_ensure_response(['status' => 'ignored']);
}

// ---------------------------------------------------------
// 6. FRONTEND WIDGETS
// ---------------------------------------------------------

add_action('wp_footer', 'soft_ai_chat_inject_widget');
add_action('wp_footer', 'soft_ai_social_widgets_render');

function soft_ai_social_widgets_render() {
    $options = get_option('soft_ai_chat_settings');

    // 1. Render Zalo Chat Widget
    if (!empty($options['enable_zalo_widget']) && !empty($options['zalo_oa_id'])) {
        $zalo_id = esc_attr($options['zalo_oa_id']);
        $welcome = esc_attr($options['welcome_msg'] ?? 'Xin chào!');
        echo <<<HTML
        <div class="zalo-chat-widget" data-oaid="{$zalo_id}" data-welcome-message="{$welcome}" data-autopopup="0" data-width="350" data-height="420"></div>
        <script src="https://sp.zalo.me/plugins/sdk.js"></script>
HTML;
    }

    // 2. Render Facebook Customer Chat
    if (!empty($options['enable_fb_widget']) && !empty($options['fb_page_id'])) {
        $fb_id = esc_attr($options['fb_page_id']);
        echo <<<HTML
        <div id="fb-root"></div>
        <div id="fb-customer-chat" class="fb-customerchat"></div>
        <script>
        var chatbox = document.getElementById('fb-customer-chat');
        chatbox.setAttribute("page_id", "{$fb_id}");
        chatbox.setAttribute("attribution", "biz_inbox");

        window.fbAsyncInit = function() {
            FB.init({
                xfbml            : true,
                version          : 'v18.0'
            });
        };

        (function(d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s); js.id = id;
            js.src = 'https://connect.facebook.net/vi_VN/sdk/xfbml.customerchat.js';
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));
        </script>
HTML;
    }
}

function soft_ai_chat_inject_widget() {
    $options = get_option('soft_ai_chat_settings');
    if (is_admin() || empty($options['provider'])) return;

    $color = $options['theme_color'] ?? '#027DDD';
    $welcome = $options['welcome_msg'] ?? 'Xin chào! Bạn cần tìm gì ạ?';
    // --- Get the Title Option ---
    $chat_title = $options['chat_title'] ?? 'Trợ lý AI';

    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/11.1.1/marked.min.js"></script>
    <style>
        #sac-trigger {
            position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px;
            background: <?php echo esc_attr($color); ?>; color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 999999; transition: all 0.3s;
            font-size: 28px;
        }
        
        /* Tránh đè lên widget Zalo (thường nằm góc phải dưới) */
        .zalo-chat-widget + #sac-trigger { bottom: 90px; }

        #sac-trigger:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        #sac-window {
            position: fixed; bottom: 90px; right: 20px; width: 360px; height: 500px;
            max-height: calc(100vh - 120px); background: #fff; border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); display: none; flex-direction: column;
            z-index: 999999; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            border: 1px solid #f0f0f0;
        }
        .sac-header { background: <?php echo esc_attr($color); ?>; color: white; padding: 15px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .sac-close { cursor: pointer; font-size: 18px; opacity: 0.8; }
        .sac-close:hover { opacity: 1; }
        #sac-messages { flex: 1; padding: 15px; overflow-y: auto; background: #f8f9fa; display: flex; flex-direction: column; gap: 12px; font-size: 14px; }
        .sac-msg { padding: 10px 14px; border-radius: 12px; line-height: 1.5; max-width: 85%; word-wrap: break-word; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .sac-msg.user { align-self: flex-end; background: #222; color: white; border-bottom-right-radius: 2px; }
        .sac-msg.bot { align-self: flex-start; background: #fff; border: 1px solid #e5e5e5; color: #333; border-bottom-left-radius: 2px; }
        .sac-msg.bot p { margin: 0 0 8px 0; } .sac-msg.bot p:last-child { margin: 0; }
        .sac-msg.bot img { max-width: 100%; border-radius: 8px; margin-top: 5px; }
        .sac-msg.bot strong { color: <?php echo esc_attr($color); ?>; }
        .sac-input-area { padding: 12px; border-top: 1px solid #eee; background: white; display: flex; gap: 8px; }
        #sac-input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; outline: none; transition: border 0.2s; }
        #sac-input:focus { border-color: <?php echo esc_attr($color); ?>; }
        #sac-send { width: 40px; height: 40px; background: <?php echo esc_attr($color); ?>; color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0 !important;}
        #sac-send:disabled { background: #ccc; cursor: not-allowed; }
        /* Typing indicator */
        .typing-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #888; margin-right: 3px; animation: typing 1.4s infinite ease-in-out both; }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; } .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
    </style>

    <div id="sac-trigger" onclick="toggleSac()">💬</div>
    <div id="sac-window">
        <div class="sac-header">
            <span><?php echo esc_html($chat_title); ?></span>
            <span class="sac-close" onclick="toggleSac()">✕</span>
        </div>
        <div id="sac-messages">
            <div class="sac-msg bot"><?php echo esc_html($welcome); ?></div>
        </div>
        <div class="sac-input-area">
            <input type="text" id="sac-input" placeholder="Hỏi gì đó..." onkeypress="handleEnter(event)">
            <button id="sac-send" onclick="sendSac()"><span style="font-size:16px;">➤</span></button>
        </div>
    </div>

    <script>
        const apiUrl = '<?php echo esc_url(rest_url('soft-ai-chat/v1/ask')); ?>';
        function toggleSac() {
            const win = document.getElementById('sac-window');
            const isHidden = win.style.display === '' || win.style.display === 'none';
            win.style.display = isHidden ? 'flex' : 'none';
            if (isHidden) setTimeout(() => document.getElementById('sac-input').focus(), 100);
        }
        
        function handleEnter(e) { if (e.key === 'Enter') sendSac(); }

        async function sendSac() {
            const input = document.getElementById('sac-input');
            const msgs = document.getElementById('sac-messages');
            const btn = document.getElementById('sac-send');
            const txt = input.value.trim();
            if (!txt) return;

            msgs.innerHTML += `<div class="sac-msg user">${txt.replace(/</g, "&lt;")}</div>`;
            const loadingId = 'sac-load-' + Date.now();
            msgs.innerHTML += `<div class="sac-msg bot" id="${loadingId}"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>`;
            msgs.scrollTop = msgs.scrollHeight;
            input.value = ''; input.disabled = true; btn.disabled = true;

            try {
                const res = await fetch(apiUrl, {
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' },
                    body: JSON.stringify({ question: txt })
                });
                const data = await res.json();
                document.getElementById(loadingId).remove();
                
                if (data.answer) {
                    msgs.innerHTML += `<div class="sac-msg bot">${marked.parse(data.answer)}</div>`;
                } else {
                    msgs.innerHTML += `<div class="sac-msg bot" style="color:red">Lỗi: ${data.message || 'Unknown'}</div>`;
                }
            } catch (err) {
                document.getElementById(loadingId)?.remove();
                msgs.innerHTML += `<div class="sac-msg bot" style="color:red">Mất kết nối server.</div>`;
            }
            
            input.disabled = false; btn.disabled = false; input.focus();
            msgs.scrollTop = msgs.scrollHeight;
        }
    </script>
    <?php
}