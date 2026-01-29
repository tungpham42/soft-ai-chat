<?php
/**
 * Plugin Name: Soft AI Chat (All-in-One) - Enhanced Payment & Social & Live Chat
 * Plugin URI:  https://soft.io.vn/soft-ai-chat
 * Description: AI Chat Widget & Sales Bot. Supports RAG + WooCommerce + VietQR/PayPal + Facebook/Zalo + Live Chat (Human Handover).
 * Version:     3.3.0
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
        is_read tinyint(1) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        KEY time (time),
        KEY user_ip (user_ip)
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
    add_submenu_page('soft-ai-chat', 'Live Chat (Support)', '🔴 Live Chat', 'manage_options', 'soft-ai-live-chat', 'soft_ai_live_chat_page');
    add_submenu_page('soft-ai-chat', 'Settings', 'Settings', 'manage_options', 'soft-ai-chat', 'soft_ai_chat_options_page');
    add_submenu_page('soft-ai-chat', 'Chat History', 'Chat Logs', 'manage_options', 'soft-ai-chat-history', 'soft_ai_chat_history_page');
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
// 1.5. LIVE CHAT PAGE (UPDATED WITH DELETE)
// ---------------------------------------------------------

function soft_ai_live_chat_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <style>
        /* Toggle Switch CSS */
        .sac-switch { position: relative; display: inline-block; width: 50px; height: 24px; vertical-align: middle; margin-left: 10px; }
        .sac-switch input { opacity: 0; width: 0; height: 0; }
        .sac-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; -webkit-transition: .4s; transition: .4s; border-radius: 24px; }
        .sac-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; -webkit-transition: .4s; transition: .4s; border-radius: 50%; }
        input:checked + .sac-slider { background-color: #0073aa; }
        input:focus + .sac-slider { box-shadow: 0 0 1px #0073aa; }
        input:checked + .sac-slider:before { -webkit-transform: translateX(26px); -ms-transform: translateX(26px); transform: translateX(26px); }
        .sac-mode-label { font-size: 13px; font-weight: normal; margin-left: 5px; color: #555; }
    </style>

    <div class="wrap" style="height: auto; display: flex; flex-direction: column;">
        <h1 style="margin-bottom: 20px;">🔴 Live Chat (Human Support)</h1>
        
        <div style="display: flex; flex: 1; gap: 20px; height: 100%; overflow: hidden;">
            <div style="height: 100vh; width: 250px; background: #fff; border: 1px solid #ccd0d4; overflow-y: auto;" id="sac-admin-sessions">
                <div style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; background: #f8f9fa;">Recent Users</div>
                <div id="sac-session-list">
                    <div style="padding:20px; text-align:center; color:#999;">Loading...</div>
                </div>
            </div>

            <div style="height: 100vh; flex: 1; display: flex; flex-direction: column; background: #fff; border: 1px solid #ccd0d4; position: relative;">
                <div style="padding: 10px 20px; border-bottom: 1px solid #eee; background: #f0f0f1; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span id="sac-current-user-title">Select a user to chat</span>
                        <span id="sac-mode-control" style="display:none; margin-left: 20px; border-left: 1px solid #ccc; padding-left: 15px;">
                            <label class="sac-switch">
                                <input type="checkbox" id="sac-mode-toggle" onchange="toggleAiMode()">
                                <span class="sac-slider"></span>
                            </label>
                            <span class="sac-mode-label" id="sac-mode-text">AI Auto-Bot</span>
                        </span>
                    </div>
                    <div style="display:flex; gap: 10px;">
                        <button class="button button-small" style="color: #a00; border-color: #a00;" id="sac-delete-btn" onclick="deleteConversation()" style="display:none;">🗑️ Delete History</button>
                        <button class="button button-small" onclick="loadLiveSessions()">Refresh Users</button>
                    </div>
                </div>
                
                <div id="sac-admin-messages" style="flex: 1; padding: 20px; overflow-y: auto; background: #f6f7f7;">
                    <div style="text-align:center; color:#aaa; margin-top: 50px;">Select a user from the left to start chatting.</div>
                </div>

                <div style="padding: 15px; background: #fff; border-top: 1px solid #ddd; display: flex; gap: 10px;">
                    <input type="text" id="sac-admin-input" placeholder="Type your reply..." style="flex: 1;" onkeypress="if(event.key==='Enter') sendAdminReply()">
                    <button class="button button-primary" onclick="sendAdminReply()">Send</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    var currentChatIp = null;
    var adminPollInterval = null;

    function loadLiveSessions() {
        jQuery.get(ajaxurl, { action: 'sac_get_sessions' }, function(response) {
            if(response.success) {
                var html = '';
                response.data.forEach(function(sess) {
                    var activeClass = (currentChatIp === sess.ip) ? 'background:#e6f7ff;' : '';
                    var badge = sess.unread > 0 ? '<span style="background:red; color:white; border-radius:50%; padding:2px 6px; font-size:10px; margin-left:5px;">'+sess.unread+'</span>' : '';
                    html += '<div onclick="openAdminChat(\''+sess.ip+'\')" style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; '+activeClass+'">';
                    html += '<strong>' + sess.ip + '</strong>'+badge+'<br><small style="color:#666">' + sess.time + '</small>';
                    html += '</div>';
                });
                jQuery('#sac-session-list').html(html);
            }
        });
    }

    function openAdminChat(ip) {
        currentChatIp = ip;
        jQuery('#sac-current-user-title').text('Chatting with: ' + ip);
        jQuery('#sac-mode-control').show(); 
        jQuery('#sac-delete-btn').show(); // Show delete button
        
        loadLiveMessages();
        loadLiveSessions(); 
        
        if(adminPollInterval) clearInterval(adminPollInterval);
        adminPollInterval = setInterval(loadLiveMessages, 3000);
    }

    function loadLiveMessages() {
        if(!currentChatIp) return;
        jQuery.get(ajaxurl, { action: 'sac_get_messages', ip: currentChatIp }, function(response) {
            if(response.success) {
                // Update Messages
                var html = '';
                response.data.messages.forEach(function(msg) {
                    var align = msg.is_admin ? 'text-align:right;' : 'text-align:left;';
                    var bg = msg.is_admin ? 'background:#0073aa; color:white;' : 'background:#e5e5e5; color:#333;';
                    html += '<div style="margin-bottom: 10px; ' + align + '">';
                    html += '<div style="display:inline-block; padding: 8px 12px; border-radius: 15px; max-width: 70%; ' + bg + '">' + msg.content + '</div>';
                    html += '<div style="font-size:10px; color:#999; margin-top:2px;">' + msg.time + '</div>';
                    html += '</div>';
                });
                var container = document.getElementById('sac-admin-messages');
                container.innerHTML = html;
                
                var isLive = response.data.is_live; 
                var toggle = document.getElementById('sac-mode-toggle');
                var label = document.getElementById('sac-mode-text');
                
                toggle.checked = isLive;
                label.innerText = isLive ? "🔴 Live Chat Mode (AI OFF)" : "🤖 AI Auto-Bot (AI ON)";
                label.style.color = isLive ? "#d63031" : "#555";
                label.style.fontWeight = isLive ? "bold" : "normal";
            }
        });
    }

    function toggleAiMode() {
        if(!currentChatIp) return;
        var isChecked = document.getElementById('sac-mode-toggle').checked;
        var newMode = isChecked ? 'live' : 'ai';
        
        jQuery.post(ajaxurl, {
            action: 'sac_toggle_mode',
            ip: currentChatIp,
            mode: newMode
        }, function(response) {
            loadLiveMessages(); 
        });
    }

    function deleteConversation() {
        if(!currentChatIp) return;
        if(!confirm('Are you sure you want to delete ALL history with ' + currentChatIp + '? This cannot be undone.')) return;

        jQuery.post(ajaxurl, {
            action: 'sac_delete_conversation',
            ip: currentChatIp
        }, function(response) {
            if(response.success) {
                // Reset UI
                currentChatIp = null;
                jQuery('#sac-admin-messages').html('<div style="text-align:center; color:#aaa; margin-top: 50px;">Conversation deleted. Select another user.</div>');
                jQuery('#sac-current-user-title').text('Select a user to chat');
                jQuery('#sac-mode-control').hide();
                jQuery('#sac-delete-btn').hide();
                if(adminPollInterval) clearInterval(adminPollInterval);
                loadLiveSessions();
            } else {
                alert('Error deleting conversation.');
            }
        });
    }

    function sendAdminReply() {
        var txt = jQuery('#sac-admin-input').val().trim();
        if(!txt || !currentChatIp) return;
        jQuery('#sac-admin-input').val(''); 
        
        jQuery.post(ajaxurl, { 
            action: 'sac_send_reply', 
            ip: currentChatIp, 
            message: txt 
        }, function(response) {
            loadLiveMessages();
        });
    }

    jQuery(document).ready(function(){
        jQuery('#sac-delete-btn').hide(); // Hide by default
        loadLiveSessions();
        setInterval(loadLiveSessions, 10000); 
    });
    </script>
    <?php
}

// ---------------------------------------------------------
// 1.6. ADMIN AJAX HANDLERS
// ---------------------------------------------------------

add_action('wp_ajax_sac_get_sessions', 'soft_ai_ajax_get_sessions');
add_action('wp_ajax_sac_get_messages', 'soft_ai_ajax_get_messages');
add_action('wp_ajax_sac_send_reply', 'soft_ai_ajax_send_reply');
add_action('wp_ajax_sac_toggle_mode', 'soft_ai_ajax_toggle_mode');
add_action('wp_ajax_sac_delete_conversation', 'soft_ai_ajax_delete_conversation');

function soft_ai_ajax_get_sessions() {
    global $wpdb;
    $table = $wpdb->prefix . 'soft_ai_chat_logs';
    
    // Get distinct IPs from last 24h
    $results = $wpdb->get_results("
        SELECT user_ip as ip, MAX(time) as latest_time, 
        SUM(CASE WHEN is_read = 0 AND provider != 'live_admin' THEN 1 ELSE 0 END) as unread
        FROM $table 
        WHERE time > NOW() - INTERVAL 24 HOUR 
        GROUP BY user_ip 
        ORDER BY latest_time DESC
    ");
    
    $data = [];
    foreach($results as $r) {
        $data[] = [
            'ip' => $r->ip,
            'time' => date('H:i', strtotime($r->latest_time)),
            'unread' => $r->unread
        ];
    }
    wp_send_json_success($data);
}

function soft_ai_ajax_get_messages() {
    global $wpdb;
    $ip = sanitize_text_field($_GET['ip']);
    $table = $wpdb->prefix . 'soft_ai_chat_logs';
    
    // Mark as read
    $wpdb->update($table, ['is_read' => 1], ['user_ip' => $ip]);

    // Check Current Mode
    $context = new Soft_AI_Context($ip, 'widget'); // Using IP as ID for admin context
    $is_live = $context->get('live_chat_mode');

    $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_ip = %s ORDER BY time ASC LIMIT 100", $ip));
    
    $messages = [];
    foreach($logs as $log) {
        $is_admin = ($log->provider === 'live_admin');
        $content = $is_admin ? $log->answer : $log->question;
        
        if ($log->provider == 'live_user') {
             $content = $log->question;
             $is_admin = false;
        } elseif ($log->provider == 'live_admin') {
             $content = $log->answer;
             $is_admin = true;
        } elseif (!empty($log->answer) && !empty($log->question)) {
             // Normal AI Log
             $messages[] = ['content' => $log->question, 'is_admin' => false, 'time' => date('H:i', strtotime($log->time))];
             $messages[] = ['content' => $log->answer, 'is_admin' => true, 'time' => date('H:i', strtotime($log->time))];
             continue;
        }

        $messages[] = [
            'content' => $content,
            'is_admin' => $is_admin,
            'time' => date('H:i', strtotime($log->time))
        ];
    }
    
    wp_send_json_success([
        'messages' => $messages,
        'is_live' => (bool)$is_live
    ]);
}

function soft_ai_ajax_toggle_mode() {
    $ip = sanitize_text_field($_POST['ip']);
    $mode = sanitize_text_field($_POST['mode']); // 'live' or 'ai'
    
    if (!$ip) wp_send_json_error();

    $context = new Soft_AI_Context($ip, 'widget');
    if ($mode === 'live') {
        $context->set('live_chat_mode', true);
    } else {
        $context->set('live_chat_mode', false);
    }
    wp_send_json_success();
}

function soft_ai_ajax_delete_conversation() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    
    global $wpdb;
    $ip = sanitize_text_field($_POST['ip']);
    $table = $wpdb->prefix . 'soft_ai_chat_logs';

    if (!$ip) wp_send_json_error('Missing IP');

    $deleted = $wpdb->delete($table, ['user_ip' => $ip]);
    
    if ($deleted === false) {
        wp_send_json_error('Database error');
    } else {
        wp_send_json_success();
    }
}

function soft_ai_ajax_send_reply() {
    global $wpdb;
    $ip = sanitize_text_field($_POST['ip']);
    $msg = sanitize_text_field($_POST['message']);
    
    if (!$ip || !$msg) wp_send_json_error();

    $wpdb->insert($wpdb->prefix . 'soft_ai_chat_logs', [
        'time' => current_time('mysql'),
        'user_ip' => $ip,
        'provider' => 'live_admin', // Marker for admin reply
        'model' => 'human',
        'question' => '', 
        'answer' => $msg, 
        'source' => 'widget',
        'is_read' => 1
    ]);
    
    // Auto enable Live Mode if admin replies (optional, but good UX)
    $context = new Soft_AI_Context($ip, 'widget');
    $context->set('live_chat_mode', true);
    
    wp_send_json_success();
}


// ---------------------------------------------------------
// 1.7. HISTORY PAGE (UPDATED HIGHLIGHT & PAGINATION)
// ---------------------------------------------------------

function soft_ai_chat_history_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'soft_ai_chat_logs';

    // Xử lý xóa log
    if (isset($_POST['delete_log']) && check_admin_referer('delete_log_' . $_POST['log_id'])) {
        $wpdb->delete($table_name, ['id' => intval($_POST['log_id'])]);
        echo '<div class="updated"><p>Log deleted.</p></div>';
    }
    if (isset($_POST['clear_all_logs']) && check_admin_referer('clear_all_logs')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="updated"><p>All logs cleared.</p></div>';
    }

    // Phân trang
    $per_page = 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pages = ceil($total_items / $per_page);
    $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY time DESC LIMIT %d OFFSET %d", $per_page, $offset));
    
    ?>
    <div class="wrap">
        <h1>Chat Logs (Archive)</h1>
        
        <form method="post" style="margin-bottom: 20px; text-align:right;">
            <?php wp_nonce_field('clear_all_logs'); ?>
            <input type="hidden" name="clear_all_logs" value="1">
            <button type="submit" class="button button-link-delete" onclick="return confirm('Delete ALL logs?')">Clear All History</button>
        </form>

        <style>
            .sac-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100000; justify-content: center; align-items: center; }
            .sac-modal-box { background: #fff; width: 800px; max-width: 90%; max-height: 90vh; border-radius: 8px; box-shadow: 0 4px 25px rgba(0,0,0,0.2); display: flex; flex-direction: column; overflow: hidden; }
            .sac-modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; }
            .sac-modal-title { font-size: 18px; font-weight: 600; margin: 0; }
            .sac-modal-close { cursor: pointer; font-size: 24px; color: #999; }
            .sac-modal-close:hover { color: #d63031; }
            .sac-modal-body { padding: 20px; overflow-y: auto; font-size: 14px; line-height: 1.6; background: #fff; }
            .sac-modal-row { margin-bottom: 20px; }
            .sac-modal-label { font-weight: bold; display: block; margin-bottom: 6px; color: #555; text-transform: uppercase; font-size: 11px; }
            .sac-modal-content-box { background: #f9f9f9; padding: 15px; border-radius: 4px; border: 1px solid #e0e0e0; min-height: 50px; }
            .sac-modal-content-box img { max-width: 100%; height: auto; border-radius: 4px; margin-top: 5px; }
            .sac-modal-content-box ul { list-style: disc; margin-left: 20px; }
            .sac-modal-content-box a { color: #0073aa; text-decoration: underline; }
            
            /* Highlighting Admin Rows */
            tr.sac-row-admin { background-color: #e6f7ff !important; }

            /* Modern Pagination Styling */
            .sac-pagination-wrapper { margin-top: 30px; display: flex; justify-content: center; align-items: center; width: 100%; }
            .sac-pagination .page-numbers {
                display: inline-flex; align-items: center; justify-content: center;
                min-width: 34px; height: 34px; padding: 0 10px; margin: 0 3px;
                border-radius: 6px; 
                background: #fff; border: 1px solid #ddd;
                color: #444; text-decoration: none; 
                font-weight: 600; font-size: 13px;
                transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
                box-shadow: 0 2px 2px rgba(0,0,0,0.02);
            }
            .sac-pagination .page-numbers:hover:not(.current):not(.dots) {
                border-color: #0073aa; color: #0073aa; background: #f0f7fd;
                transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,115,170,0.15);
            }
            .sac-pagination .page-numbers.current {
                background: #0073aa; color: #fff; border-color: #0073aa;
                box-shadow: 0 2px 5px rgba(0,115,170,0.4);
            }
            .sac-pagination .dots { border: none; background: transparent; box-shadow: none; color: #999; }
            .sac-pagination .next, .sac-pagination .prev { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; font-weight: bold; }
        </style>

        <script>
            function openSacLogModal(id) {
                var time = document.getElementById('data-time-' + id).value;
                var source = document.getElementById('data-source-' + id).value;
                var provider = document.getElementById('data-provider-' + id).value;
                var ip = document.getElementById('data-ip-' + id).value;
                var question = document.getElementById('data-question-' + id).value;
                var answer = document.getElementById('data-answer-' + id).value;

                document.getElementById('sac-modal-meta').innerHTML = time + ' | ' + source + ' | ' + provider + ' | IP: ' + ip;
                document.getElementById('sac-modal-question-box').textContent = question;
                document.getElementById('sac-modal-answer-box').innerHTML = answer;
                document.getElementById('sac-log-modal').style.display = 'flex';
            }
            function closeSacLogModal() { document.getElementById('sac-log-modal').style.display = 'none'; }
            window.onclick = function(event) { if (event.target == document.getElementById('sac-log-modal')) closeSacLogModal(); }
        </script>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="120">Time</th>
                    <th width="80">Source</th>
                    <th width="100">Provider</th>
                    <th width="20%">Question</th>
                    <th>Answer (Preview)</th>
                    <th width="120">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): foreach ($logs as $log): 
                    // Highlight Admin Rows
                    $row_class = ($log->provider === 'live_admin') ? 'sac-row-admin' : '';
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><?php echo esc_html($log->time); ?></td>
                    <td><span class="badge"><?php echo esc_html($log->source); ?></span></td>
                    <td><?php echo esc_html($log->provider); ?></td>
                    <td><?php echo esc_html(mb_strimwidth($log->question, 0, 50, '...')); ?></td>
                    <td><?php echo wp_strip_all_tags(mb_strimwidth($log->answer, 0, 80, '...')); ?></td>
                    <td>
                        <div style="display:flex; gap: 5px; align-items: center;">
                            <button type="button" class="button button-secondary button-small" onclick="openSacLogModal(<?php echo $log->id; ?>)">View</button>
                            <form method="post" style="display:inline-block; margin:0;">
                                <?php wp_nonce_field('delete_log_' . $log->id); ?>
                                <input type="hidden" name="delete_log" value="1"><input type="hidden" name="log_id" value="<?php echo $log->id; ?>">
                                <button class="button button-link-delete" style="color: #a00;" onclick="return confirm('Delete?')">Del</button>
                            </form>
                        </div>
                        <textarea id="data-time-<?php echo $log->id; ?>" style="display:none"><?php echo esc_html($log->time); ?></textarea>
                        <textarea id="data-source-<?php echo $log->id; ?>" style="display:none"><?php echo esc_html($log->source); ?></textarea>
                        <textarea id="data-provider-<?php echo $log->id; ?>" style="display:none"><?php echo esc_html($log->provider); ?></textarea>
                        <textarea id="data-ip-<?php echo $log->id; ?>" style="display:none"><?php echo esc_html($log->user_ip); ?></textarea>
                        <textarea id="data-question-<?php echo $log->id; ?>" style="display:none"><?php echo esc_textarea($log->question); ?></textarea>
                        <textarea id="data-answer-<?php echo $log->id; ?>" style="display:none"><?php echo esc_textarea($log->answer); ?></textarea>
                    </td>
                </tr>
                <?php endforeach; else: echo '<tr><td colspan="6">No history found.</td></tr>'; endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
        <div class="sac-pagination-wrapper">
            <div class="sac-pagination">
                <?php 
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'total' => $total_pages,
                    'current' => $paged,
                    'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2" style="margin-top:2px;"></span>',
                    'next_text' => '<span class="dashicons dashicons-arrow-right-alt2" style="margin-top:2px;"></span>',
                    'mid_size' => 2
                ]); 
                ?>
            </div>
        </div>
        <?php endif; ?>

        <div id="sac-log-modal" class="sac-modal-overlay">
            <div class="sac-modal-box">
                <div class="sac-modal-header">
                    <h3 class="sac-modal-title">Chi tiết Log <span id="sac-modal-meta" style="font-weight:normal; font-size:12px; color:#666; margin-left:10px;"></span></h3>
                    <div class="sac-modal-close" onclick="closeSacLogModal()">×</div>
                </div>
                <div class="sac-modal-body">
                    <div class="sac-modal-row">
                        <span class="sac-modal-label">User Input:</span>
                        <div class="sac-modal-content-box" id="sac-modal-question-box" style="white-space: pre-wrap;"></div>
                    </div>
                    <div class="sac-modal-row">
                        <span class="sac-modal-label">System Output:</span>
                        <div class="sac-modal-content-box" id="sac-modal-answer-box"></div>
                    </div>
                </div>
            </div>
        </div>

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

    public function add_to_cart($product_id, $qty = 1, $variation_id = 0, $variation = []) {
        if ($this->source === 'widget' && function_exists('WC')) {
            WC()->cart->add_to_cart($product_id, $qty, $variation_id, $variation);
        } else {
            $cart = $this->get('cart') ?: [];
            $cart_key = $variation_id ? $variation_id : $product_id;
            
            if (isset($cart[$cart_key])) {
                $cart[$cart_key]['qty'] += $qty;
            } else {
                $cart[$cart_key] = [
                    'qty' => $qty, 
                    'product_id' => $product_id, 
                    'variation_id' => $variation_id,
                    'variation' => $variation 
                ];
            }
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
// 3. CORE LOGIC
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

function soft_ai_log_chat($question, $answer, $source = 'widget', $provider_override = '', $model_override = '') {
    global $wpdb;
    $opt = get_option('soft_ai_chat_settings');
    
    // Nếu là Live Chat messages, luôn lưu. Nếu AI chat thường, check setting.
    $is_live = (strpos($provider_override, 'live_') !== false);
    if (empty($opt['save_history']) && !$is_live) return;

    $wpdb->insert($wpdb->prefix . 'soft_ai_chat_logs', [
        'time' => current_time('mysql'),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'provider' => $provider_override ?: ($opt['provider'] ?? 'unknown'),
        'model' => $model_override ?: ($opt['model'] ?? 'unknown'),
        'question' => $question,
        'answer' => $answer,
        'source' => $source
    ]);
}

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
    
    // --- 0. CHECK LIVE CHAT STATUS ---
    // Keywords to enter Live Mode
    $human_keywords = ['human', 'người thật', 'nhân viên', 'tư vấn viên', 'gặp người', 'chat với người', 'support', 'live chat'];
    $exit_keywords = ['thoát', 'exit', 'bye', 'bot', 'gặp bot'];
    
    $clean_q = mb_strtolower(trim($question));

    if (in_array($clean_q, $human_keywords)) {
        $context->set('live_chat_mode', true);
        $msg = "Đã chuyển sang chế độ Chat với Nhân Viên 🔴.\nVui lòng nhắn tin, nhân viên sẽ trả lời bạn sớm nhất có thể.";
        // Log this switching event
        soft_ai_log_chat($question, $msg, $platform, 'system_switch');
        return $msg;
    }

    if (in_array($clean_q, $exit_keywords)) {
        $context->set('live_chat_mode', false);
        return "Đã quay lại chế độ AI Bot 🤖. Bạn cần giúp gì không?";
    }

    // If in Live Mode, DO NOT call AI
    if ($context->get('live_chat_mode')) {
        // Save user message to DB so Admin can see it
        soft_ai_log_chat($question, '', $platform, 'live_user', 'human');
        // Return null/empty tells the controller to NOT reply with AI, just wait.
        // But for UX, we might want to return nothing to widget, just "sent".
        return "[WAIT_FOR_HUMAN]"; 
    }
    // ----------------------------------

    // 1. Flow Interruption (Huỷ bỏ)
    $current_step = $context->get('bot_collecting_info_step');
    $cancel_keywords = ['huỷ', 'hủy', 'cancel', 'thôi', 'stop', 'thoát'];
    if (in_array($clean_q, $cancel_keywords)) {
        $context->set('bot_collecting_info_step', null);
        return "Đã hủy thao tác hiện tại. Mình có thể giúp gì khác không?";
    }

    // 2. Handle Ongoing Steps
    if ($current_step && class_exists('WooCommerce')) {
        $response = soft_ai_handle_ordering_steps($question, $current_step, $context);
        if ($platform === 'facebook' || $platform === 'zalo') {
            return soft_ai_clean_text_for_social($response);
        }
        return $response;
    }

    // 3. Fast-Track Checkout
    $checkout_triggers = ['thanh toán', 'thanh toan', 'xác nhận', 'xac nhan', 'chốt đơn', 'chot don', 'đặt hàng', 'dat hang', 'mua ngay', 'pay'];
    $is_checkout_intent = false;
    foreach ($checkout_triggers as $trigger) {
        if (strpos($clean_q, $trigger) !== false) {
            $is_checkout_intent = true;
            break;
        }
    }

    if ($is_checkout_intent && class_exists('WooCommerce')) {
        $response = soft_ai_process_order_logic(['action' => 'checkout'], $context);
        if ($platform === 'facebook' || $platform === 'zalo') return soft_ai_clean_text_for_social($response);
        return $response;
    }

    // 4. Setup AI
    $options = get_option('soft_ai_chat_settings');
    $provider = $options['provider'] ?? 'groq';
    $model = $options['model'] ?? 'llama-3.3-70b-versatile';
    
    // 5. Prompt Engineering & RAG
    $site_context = soft_ai_chat_get_context($question);
    $user_instruction = $options['system_prompt'] ?? '';
    
    $system_prompt = "You are a helpful AI assistant for this website.\n" .
                     ($user_instruction ? "Additional Persona: $user_instruction\n" : "") .
                     "Website Content Context:\n" . $site_context . "\n\n" .
                     "CRITICAL INSTRUCTIONS:\n" . 
                     "1. If user wants to BUY/ORDER/FIND products, return STRICT JSON only (no markdown):\n" .
                     "   {\"action\": \"find_product\", \"query\": \"product name\"}\n" .
                     "   {\"action\": \"list_products\"}\n" .
                     "   {\"action\": \"check_cart\"}\n" .
                     "   {\"action\": \"checkout\"}\n" .
                     "2. If user asks general discovery questions like 'bán gì', 'có gì', 'sản phẩm gì', 'menu', use action 'list_products'.\n" .
                     "3. For general chat, answer normally in Vietnamese.\n" .
                     "4. If unknown, admit it politely.";

    // 6. Call API
    $ai_response = soft_ai_chat_call_api($provider, $model, $system_prompt, $question, $options);
    if (is_wp_error($ai_response)) return "Lỗi hệ thống: " . $ai_response->get_error_message();

    // 7. Clean & Parse JSON
    $clean_response = trim($ai_response);
    if (preg_match('/```json\s*(.*?)\s*```/s', $clean_response, $matches)) {
        $clean_response = $matches[1];
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $clean_response, $matches)) {
        $clean_response = $matches[1];
    }

    $intent = json_decode($clean_response, true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($intent['action']) && class_exists('WooCommerce')) {
        $response = soft_ai_process_order_logic($intent, $context);
        if ($platform === 'facebook' || $platform === 'zalo') return soft_ai_clean_text_for_social($response);
        return $response;
    }

    // 8. Return Text
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
        case 'list_products':
            $args = ['limit' => 12, 'status' => 'publish', 'orderby' => 'date', 'order' => 'DESC'];
            $products = wc_get_products($args);

            if (empty($products)) return "Dạ hiện tại shop chưa cập nhật sản phẩm lên web ạ.";

            $msg = "Dạ, bên em đang có những sản phẩm nổi bật này ạ:<br>";
            if ($source !== 'widget') $msg = "Dạ, bên em đang có những sản phẩm nổi bật này ạ:\n";

            foreach ($products as $p) {
                $price = $p->get_price_html();
                $name = $p->get_name();
                
                if ($source === 'widget') {
                    $img_id = $p->get_image_id();
                    $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : wc_placeholder_img_src();
                    $msg .= "
                    <div style='display:flex; align-items:center; gap:10px; margin-top:10px; border:1px solid #f0f0f0; padding:8px; border-radius:8px; background:#fff;'>
                        <img src='{$img_url}' style='width:50px; height:50px; object-fit:cover; border-radius:6px; flex-shrink:0;'>
                        <div style='font-size:13px; line-height:1.4;'>
                            <div style='font-weight:bold; color:#333;'>{$name}</div>
                            <div style='color:#d63031; font-weight:600;'>{$price}</div>
                        </div>
                    </div>";
                } else {
                    $plain_price = strip_tags(wc_price($p->get_price()));
                    $msg .= "- {$name} ({$plain_price})\n";
                }
            }
            $suffix = ($source === 'widget') ? "<br>Bạn quan tâm món nào nhắn tên để em tư vấn nhé!" : "\nBạn quan tâm món nào nhắn tên để em tư vấn nhé!";
            return $msg . $suffix;

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
                    return ($source == 'widget') ? "Tìm thấy: <b>" . $p->get_name() . "</b>.<br>" . $question : "Tìm thấy: " . $p->get_name() . ".\n" . $question;
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
            $clean_message = trim($message);
            $valid_options = $context->get('valid_options_for_' . $current_slug);
            $is_valid = false;
            
            if (empty($valid_options)) {
                $is_valid = true; 
            } else {
                foreach ($valid_options as $opt) {
                    if (mb_strtolower(trim($opt)) === mb_strtolower($clean_message)) {
                        $is_valid = true;
                        $clean_message = $opt; 
                        break;
                    }
                }
            }

            if (!$is_valid) {
                $label = wc_attribute_label($current_slug);
                $list_str = implode(', ', $valid_options);
                return "⚠️ Dạ shop không có $label '{$message}' ạ.\nVui lòng chỉ chọn một trong các loại sau: **$list_str**";
            }

            $answers = $context->get('attr_answers') ?: [];
            $answers[$current_slug] = $clean_message;
            $context->set('attr_answers', $answers);
            $context->set('valid_options_for_' . $current_slug, null); 

            $p = wc_get_product($context->get('pending_product_id'));
            return soft_ai_ask_next_attribute($context, $p);

        case 'ask_quantity':
            $qty = intval($clean_message);
            if ($qty <= 0) return "Số lượng phải lớn hơn 0. Vui lòng nhập lại:";
            
            $pid = $context->get('pending_product_id');
            $p = wc_get_product($pid);
            
            if ($p) {
                $var_id = 0; $var_data = [];
                
                if ($p->is_type('variable')) {
                    $collected = $context->get('attr_answers') ?: [];
                    $var_data = [];
                    foreach ($collected as $attr_key => $user_val_name) {
                        $slug_val = $user_val_name; 
                        if (taxonomy_exists($attr_key)) {
                            $term = get_term_by('name', $user_val_name, $attr_key);
                            if ($term) $slug_val = $term->slug;
                        } else {
                            $slug_val = sanitize_title($user_val_name);
                        }
                        $var_data['attribute_' . $attr_key] = $slug_val;
                    }
                    $data_store = new WC_Product_Data_Store_CPT();
                    $var_id = $data_store->find_matching_product_variation($p, $var_data);
                    if (!$var_id) return "Xin lỗi, phiên bản bạn chọn hiện không tồn tại hoặc đã hết hàng. Vui lòng chọn lại.";
                }

                $context->add_to_cart($pid, $qty, $var_id, $var_data);
                $context->set('bot_collecting_info_step', null);
                $total = $context->get_cart_total_string();
                return "✅ Đã thêm vào giỏ ($qty cái). Tổng tạm tính: $total.\nGõ 'Thanh toán' để chốt đơn hoặc hỏi mua tiếp.";
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
            $method_key = mb_strtolower($clean_message);
            if (strpos($method_key, 'vietqr') !== false || strpos($method_key, 'chuyển khoản') !== false || strpos($method_key, 'qr') !== false) {
                return soft_ai_finalize_order($context, 'vietqr_custom');
            }
            if (strpos($method_key, 'paypal') !== false) {
                return soft_ai_finalize_order($context, 'paypal_custom');
            }

            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            $selected = null;
            foreach ($gateways as $g) {
                if (stripos($g->title, $clean_message) !== false || stripos($g->id, $clean_message) !== false) { 
                    $selected = $g; break; 
                }
            }
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
        return "Dạ bạn đã chọn đủ thông tin. Bạn muốn lấy số lượng bao nhiêu ạ?";
    }
    $current_slug = array_shift($queue);
    $context->set('attr_queue', $queue); 
    $context->set('current_asking_attr', $current_slug); 
    
    $terms = wc_get_product_terms($product->get_id(), $current_slug, array('fields' => 'names'));
    $options_text = "";
    if (!empty($terms) && !is_wp_error($terms)) {
        $context->set('valid_options_for_' . $current_slug, $terms);
        $options_text = "\n(" . implode(', ', $terms) . ")";
    } else {
        $context->set('valid_options_for_' . $current_slug, []);
    }
    $label = wc_attribute_label($current_slug);
    return "Bạn chọn **$label** loại nào?$options_text";
}

function soft_ai_present_payment_gateways($context, $msg) {
    $gateways = WC()->payment_gateways->get_available_payment_gateways();
    $opts = get_option('soft_ai_chat_settings');
    $list = "";
    $prefix = ($context->source == 'widget') ? "<br>• " : "\n- ";

    foreach ($gateways as $g) $list .= $prefix . $g->get_title();
    
    if (!empty($opts['vietqr_bank']) && !empty($opts['vietqr_acc'])) $list .= $prefix . "VietQR (Chuyển khoản nhanh)";
    if (!empty($opts['paypal_me'])) $list .= $prefix . "PayPal";

    $context->set('bot_collecting_info_step', 'payment_method');
    return $msg . $list;
}

function soft_ai_finalize_order($context, $gateway_or_code) {
    try {
        $order = wc_create_order();
        $opts = get_option('soft_ai_chat_settings');

        if ($context->source === 'widget' && function_exists('WC')) {
            foreach (WC()->cart->get_cart() as $values) $order->add_product($values['data'], $values['quantity']);
        } else {
            $cart = $context->get('cart') ?: [];
            foreach ($cart as $key => $item) {
                $pid = isset($item['product_id']) ? $item['product_id'] : $key;
                $vid = isset($item['variation_id']) ? $item['variation_id'] : 0;
                $p = wc_get_product($vid ? $vid : $pid);
                if ($p) $order->add_product($p, $item['qty']);
            }
        }

        $name    = $context->get('temp_name');
        $phone   = $context->get('temp_phone');
        $email   = $context->get('temp_email');
        $address = $context->get('temp_address');

        if ($context->source === 'widget' && function_exists('WC') && WC()->customer) {
            if (empty($name))    $name = WC()->customer->get_billing_first_name();
            if (empty($phone))   $phone = WC()->customer->get_billing_phone();
            if (empty($email))   $email = WC()->customer->get_billing_email();
            if (empty($address)) $address = WC()->customer->get_billing_address_1();
        }

        $parts = explode(' ', trim($name));
        $last_name  = (count($parts) > 1) ? array_pop($parts) : '';
        $first_name = implode(' ', $parts);
        if (empty($first_name)) $first_name = $name;

        $billing_info = [
            'first_name' => $first_name, 'last_name'  => $last_name, 'phone' => $phone,
            'email' => $email ?: 'no-email@example.com', 'address_1'  => $address, 'country' => 'VN',
        ];
        $order->set_address($billing_info, 'billing');
        $order->set_address($billing_info, 'shipping');

        $extra_msg = "";
        
        if ($gateway_or_code === 'vietqr_custom') {
            $order->set_payment_method('bacs');
            $order->set_payment_method_title('VietQR (Chat)');
            $order->calculate_totals();
            
            $bacs_accounts = get_option('woocommerce_bacs_accounts');
            $bank = ''; $acc = ''; $name_acc = '';
            if (!empty($bacs_accounts) && is_array($bacs_accounts)) {
                $account = $bacs_accounts[0];
                $bank = str_replace(' ', '', $account['bank_name']); 
                $acc  = str_replace(' ', '', $account['account_number']);
                $name_acc = str_replace(' ', '%20', $account['account_name']);
            } else {
                 $bank = str_replace(' ', '', $opts['vietqr_bank'] ?? '');
                 $acc  = str_replace(' ', '', $opts['vietqr_acc'] ?? '');
                 $name_acc = str_replace(' ', '%20', $opts['vietqr_name'] ?? '');
            }
            $amt = intval($order->get_total()); 
            $desc = "DH" . $order->get_id(); 
            if ($bank && $acc) {
                $qr_url = "https://img.vietqr.io/image/{$bank}-{$acc}-compact.jpg?amount={$amt}&addInfo={$desc}&accountName={$name_acc}";
                $extra_msg = "\n\n⬇️ **Quét mã để thanh toán:**\n![VietQR]($qr_url)";
                if ($context->source == 'widget') $extra_msg = "<br><br><b>Quét mã để thanh toán:</b><br><img src='$qr_url' style='max-width:100%; border-radius:8px;'>";
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

        $order->update_status('on-hold', "Order created via Soft AI Chat. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
        $context->empty_cart();
        $context->set('bot_collecting_info_step', null);
        return "🎉 ĐẶT HÀNG THÀNH CÔNG!\nMã đơn: #" . $order->get_id() . "\nEmail xác nhận đã gửi tới " . $billing_info['email'] . "." . $extra_msg;

    } catch (Exception $e) {
        return "Lỗi khi tạo đơn: " . $e->getMessage();
    }
}

// ---------------------------------------------------------
// 4. API CALLER
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
    
    if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) return $data['candidates'][0]['content']['parts'][0]['text'];

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
    register_rest_route('soft-ai-chat/v1', '/poll', [
        'methods' => 'POST',
        'callback' => 'soft_ai_chat_poll_messages',
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
    
    // If answer is the special wait flag, return empty to frontend so it just waits
    if ($answer === '[WAIT_FOR_HUMAN]') {
        return rest_ensure_response(['answer' => '', 'live_mode' => true]);
    }

    soft_ai_log_chat($question, $answer, 'widget');
    return rest_ensure_response(['answer' => $answer]);
}

function soft_ai_chat_poll_messages($request) {
    global $wpdb;
    // Client polls for new admin messages
    $ip = $_SERVER['REMOTE_ADDR'];
    $table = $wpdb->prefix . 'soft_ai_chat_logs';
    $last_id = (int) ($request->get_json_params()['last_id'] ?? 0);
    
    // Fetch only admin replies newer than last_id
    $new_msgs = $wpdb->get_results($wpdb->prepare("SELECT id, answer, time FROM $table WHERE user_ip = %s AND provider = 'live_admin' AND id > %d ORDER BY time ASC", $ip, $last_id));

    $data = [];
    foreach($new_msgs as $m) {
        $data[] = ['id' => $m->id, 'text' => $m->answer];
    }

    return rest_ensure_response(['messages' => $data]);
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
                    if ($reply !== '[WAIT_FOR_HUMAN]') {
                         soft_ai_send_fb_message($sender, $reply, $options['fb_page_token']);
                         soft_ai_log_chat($event['message']['text'], $reply, 'facebook');
                    }
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
        
        if ($reply !== '[WAIT_FOR_HUMAN]') {
            $token = get_option('soft_ai_chat_settings')['zalo_access_token'] ?? '';
            if ($token) {
                wp_remote_post("https://openapi.zalo.me/v3.0/oa/message/cs", [
                    'headers' => ['access_token' => $token, 'Content-Type' => 'application/json'],
                    'body' => json_encode(['recipient' => ['user_id' => $sender], 'message' => ['text' => $reply]])
                ]);
            }
            soft_ai_log_chat($body['message']['text'], $reply, 'zalo');
        }
        return rest_ensure_response(['status' => 'success']);
    }
    return rest_ensure_response(['status' => 'ignored']);
}

// ---------------------------------------------------------
// 6. FRONTEND WIDGETS (UPDATED HIGHLIGHT)
// ---------------------------------------------------------

add_action('wp_footer', 'soft_ai_chat_inject_widget');
add_action('wp_footer', 'soft_ai_social_widgets_render');

function soft_ai_social_widgets_render() {
    $options = get_option('soft_ai_chat_settings');
    // Zalo
    if (!empty($options['enable_zalo_widget']) && !empty($options['zalo_oa_id'])) {
        $zalo_id = esc_attr($options['zalo_oa_id']);
        $welcome = esc_attr($options['welcome_msg'] ?? 'Xin chào!');
        echo <<<HTML
        <div class="zalo-chat-widget" data-oaid="{$zalo_id}" data-welcome-message="{$welcome}" data-autopopup="0" data-width="350" data-height="420"></div>
        <script src="https://sp.zalo.me/plugins/sdk.js"></script>
HTML;
    }
    // FB
    if (!empty($options['enable_fb_widget']) && !empty($options['fb_page_id'])) {
        $fb_id = esc_attr($options['fb_page_id']);
        echo <<<HTML
        <div id="fb-root"></div>
        <div id="fb-customer-chat" class="fb-customerchat"></div>
        <script>
        var chatbox = document.getElementById('fb-customer-chat');
        chatbox.setAttribute("page_id", "{$fb_id}");
        chatbox.setAttribute("attribution", "biz_inbox");
        window.fbAsyncInit = function() { FB.init({ xfbml : true, version : 'v18.0' }); };
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
        
        /* Bot Message Style */
        .sac-msg.bot { align-self: flex-start; background: #fff; border: 1px solid #e5e5e5; color: #333; border-bottom-left-radius: 2px; }
        .sac-msg.bot p { margin: 0 0 8px 0; } .sac-msg.bot p:last-child { margin: 0; }
        .sac-msg.bot img { max-width: 100%; border-radius: 8px; margin-top: 5px; }
        .sac-msg.bot strong { color: <?php echo esc_attr($color); ?>; }

        /* Admin Highlight Style */
        .sac-msg.admin { 
            align-self: flex-start; 
            background: #e6f7ff; 
            border: 1px solid #91d5ff; 
            color: #0050b3; 
            border-bottom-left-radius: 2px; 
            position: relative;
            padding-top: 20px; /* Space for label */
        }

        .sac-input-area { padding: 12px; border-top: 1px solid #eee; background: white; display: flex; gap: 8px; }
        #sac-input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; outline: none; transition: border 0.2s; }
        #sac-input:focus { border-color: <?php echo esc_attr($color); ?>; }
        #sac-send { width: 40px; height: 40px; background: <?php echo esc_attr($color); ?>; color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0 !important;}
        #sac-send:disabled { background: #ccc; cursor: not-allowed; }
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
        const pollUrl = '<?php echo esc_url(rest_url('soft-ai-chat/v1/poll')); ?>';
        let lastMsgId = 0;
        let pollInterval = null;

        function toggleSac() {
            const win = document.getElementById('sac-window');
            const isHidden = win.style.display === '' || win.style.display === 'none';
            win.style.display = isHidden ? 'flex' : 'none';
            if (isHidden) {
                setTimeout(() => document.getElementById('sac-input').focus(), 100);
                startPolling();
            } else {
                stopPolling();
            }
        }
        
        function handleEnter(e) { if (e.key === 'Enter') sendSac(); }

        function startPolling() {
            if(pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(async () => {
                try {
                    const res = await fetch(pollUrl, {
                         method: 'POST',
                         headers: { 'Content-Type': 'application/json' },
                         body: JSON.stringify({ last_id: lastMsgId })
                    });
                    const data = await res.json();
                    if(data.messages && data.messages.length > 0) {
                        const msgs = document.getElementById('sac-messages');
                        data.messages.forEach(m => {
                            lastMsgId = Math.max(lastMsgId, parseInt(m.id));
                            // Use 'admin' class for highlighted messages
                            msgs.innerHTML += `<div class="sac-msg admin">${marked.parse(m.text)}</div>`;
                        });
                        msgs.scrollTop = msgs.scrollHeight;
                    }
                } catch(e) {}
            }, 5000); // Poll every 5s
        }

        function stopPolling() {
            if(pollInterval) clearInterval(pollInterval);
        }

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
                } else if(data.live_mode) {
                    // Do nothing, just sent. 
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