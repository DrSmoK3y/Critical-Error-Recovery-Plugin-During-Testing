<?php
/**
 * Plugin Name: Emergency Plugin Manager
 * Plugin URI:  https://github.com/DrSmoK3y
 * Description: Emergency plugin deactivation via secret URL - works even when WordPress crashes, ⚠ This plugin is only for test sites, don't use it on live sites.
 * Version:     1.0.0
 * Author:      DrSmoK3y
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Allow direct access ONLY for emergency mode
    define('ABSPATH', dirname(__FILE__) . '/../../');
    define('EMERGENCY_DIRECT_ACCESS', true);
}

// ─────────────────────────────────────────────
//  SETTINGS
// ─────────────────────────────────────────────
define('EPM_SECRET_KEY',   'change-this-secret-key-123');  // <<< CHANGE THIS!
define('EPM_OPTION_KEY',   'epm_settings');
define('EPM_VERSION',      '1.0.0');

// ─────────────────────────────────────────────
//  HOOK: Admin Menu
// ─────────────────────────────────────────────
add_action('admin_menu', function() {
    add_management_page(
        'Emergency Plugin Manager',
        'Emergency Plugin Manager',
        'manage_options',
        'emergency-plugin-manager',
        'epm_admin_page'
    );
});

// ─────────────────────────────────────────────
//  HOOK: Handle emergency URL (runs very early)
// ─────────────────────────────────────────────
add_action('init', 'epm_handle_emergency_request', 1);

function epm_handle_emergency_request() {
    if ( ! isset($_GET['epm_emergency']) ) return;
    if ( $_GET['epm_emergency'] !== EPM_SECRET_KEY ) {
        wp_die('<h2>❌ Invalid emergency key.</h2>', 'Access Denied', ['response' => 403]);
    }

    // Action handling
    if ( isset($_POST['epm_action']) && check_admin_referer('epm_emergency_action') ) {
        $action  = sanitize_text_field($_POST['epm_action']);
        $plugins = isset($_POST['plugins']) ? (array) $_POST['plugins'] : [];
        $plugins = array_map('sanitize_text_field', $plugins);

        $active = get_option('active_plugins', []);

        if ($action === 'deactivate' && !empty($plugins)) {
            $active = array_diff($active, $plugins);
            update_option('active_plugins', array_values($active));
            epm_show_emergency_ui('✅ Selected plugins deactivated successfully!', 'success');
            return;
        }
        if ($action === 'deactivate_all') {
            update_option('active_plugins', []);
            epm_show_emergency_ui('✅ All plugins deactivated!', 'success');
            return;
        }
        if ($action === 'activate' && !empty($plugins)) {
            foreach ($plugins as $plugin) {
                if (!in_array($plugin, $active)) {
                    $active[] = $plugin;
                }
            }
            update_option('active_plugins', array_values($active));
            epm_show_emergency_ui('✅ Selected plugins activated!', 'success');
            return;
        }
    }

    epm_show_emergency_ui();
    exit;
}

// ─────────────────────────────────────────────
//  EMERGENCY UI (standalone page)
// ─────────────────────────────────────────────
function epm_show_emergency_ui($message = '', $msg_type = 'info') {
    $active_plugins  = get_option('active_plugins', []);
    $all_plugins     = epm_get_all_plugins();
    $inactive        = array_diff(array_keys($all_plugins), $active_plugins);
    $emergency_url   = home_url('/?epm_emergency=' . EPM_SECRET_KEY);
    $nonce           = wp_create_nonce('epm_emergency_action');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🚨 Emergency Plugin Manager</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                   background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 20px; }
            .container { max-width: 900px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #dc2626, #991b1b);
                      border-radius: 12px; padding: 24px; margin-bottom: 24px;
                      display: flex; align-items: center; gap: 16px; }
            .header-icon { font-size: 48px; }
            .header h1 { font-size: 24px; font-weight: 700; }
            .header p  { font-size: 14px; opacity: 0.85; margin-top: 4px; }
            .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;
                     font-weight: 600; display: flex; align-items: center; gap: 10px; }
            .alert.success { background: #065f46; border: 1px solid #10b981; color: #6ee7b7; }
            .alert.info    { background: #1e3a5f; border: 1px solid #3b82f6; color: #93c5fd; }
            .card { background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 20px;
                    border: 1px solid #334155; }
            .card h2 { font-size: 16px; font-weight: 600; margin-bottom: 16px;
                       display: flex; align-items: center; gap: 8px; }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 999px;
                     font-size: 12px; font-weight: 600; margin-left: 8px; }
            .badge-active   { background: #065f46; color: #6ee7b7; }
            .badge-inactive { background: #1e3a5f; color: #93c5fd; }
            .plugin-list { display: flex; flex-direction: column; gap: 8px; }
            .plugin-item { background: #0f172a; border: 1px solid #334155; border-radius: 8px;
                           padding: 12px 16px; display: flex; align-items: center; gap: 12px;
                           transition: border-color 0.2s; cursor: pointer; }
            .plugin-item:hover { border-color: #64748b; }
            .plugin-item input[type=checkbox] { width: 18px; height: 18px; accent-color: #ef4444;
                                                 cursor: pointer; flex-shrink: 0; }
            .plugin-name { font-weight: 600; font-size: 14px; flex: 1; }
            .plugin-path { font-size: 11px; color: #64748b; margin-top: 2px; }
            .btn-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
            .btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
                   border: none; cursor: pointer; transition: opacity 0.2s; }
            .btn:hover { opacity: 0.85; }
            .btn-danger  { background: #dc2626; color: white; }
            .btn-danger2 { background: #7f1d1d; color: #fca5a5; border: 1px solid #dc2626; }
            .btn-success { background: #059669; color: white; }
            .btn-select  { background: #334155; color: #cbd5e1; font-size: 12px; padding: 6px 12px; }
            .separator { border: none; border-top: 1px solid #334155; margin: 20px 0; }
            .url-box { background: #0f172a; border: 1px solid #334155; border-radius: 8px;
                       padding: 12px 16px; font-family: monospace; font-size: 12px;
                       color: #94a3b8; word-break: break-all; }
            .tab-bar { display: flex; gap: 4px; margin-bottom: 16px; }
            .tab { padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px;
                   font-weight: 600; background: #334155; color: #94a3b8; border: none; }
            .tab.active { background: #dc2626; color: white; }
            .tab-content { display: none; }
            .tab-content.active { display: block; }
            @media (max-width: 600px) { .btn-row { flex-direction: column; } }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="header-icon">🚨</div>
                <div>
                    <h1>Emergency Plugin Manager</h1>
                    <p>WordPress crash recovery — Manage plugins without cPanel or File Manager</p>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert <?php echo $msg_type; ?>">
                <span><?php echo esc_html($message); ?></span>
            </div>
            <?php endif; ?>

            <!-- TABS -->
            <div class="tab-bar">
                <button class="tab active" onclick="showTab('deactivate', this)">🔴 Deactivate</button>
                <button class="tab" onclick="showTab('activate', this)">🟢 Activate</button>
                <button class="tab" onclick="showTab('info', this)">ℹ️ Info</button>
            </div>

            <!-- TAB: DEACTIVATE -->
            <div id="tab-deactivate" class="tab-content active">
                <div class="card">
                    <h2>🔴 Active Plugins
                        <span class="badge badge-active"><?php echo count($active_plugins); ?></span>
                    </h2>
                    <?php if (empty($active_plugins)): ?>
                        <p style="color:#64748b;">No active plugins found.</p>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">
                        <input type="hidden" name="epm_action" value="deactivate">
                        <div style="margin-bottom:10px; display:flex; gap:8px;">
                            <button type="button" class="btn btn-select" onclick="selectAll('deact-list', true)">✅ Select All</button>
                            <button type="button" class="btn btn-select" onclick="selectAll('deact-list', false)">☐ Deselect All</button>
                        </div>
                        <div class="plugin-list" id="deact-list">
                        <?php foreach ($active_plugins as $plugin_file):
                            $info = $all_plugins[$plugin_file] ?? ['name' => $plugin_file];
                        ?>
                            <label class="plugin-item">
                                <input type="checkbox" name="plugins[]" value="<?php echo esc_attr($plugin_file); ?>">
                                <div>
                                    <div class="plugin-name"><?php echo esc_html($info['name']); ?></div>
                                    <div class="plugin-path"><?php echo esc_html($plugin_file); ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        </div>
                        <div class="btn-row">
                            <button type="submit" class="btn btn-danger">🔴 Deactivate Selected</button>
                        </div>
                    </form>
                    <hr class="separator">
                    <form method="POST">
                        <input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">
                        <input type="hidden" name="epm_action" value="deactivate_all">
                        <button type="submit" class="btn btn-danger2"
                            onclick="return confirm('Sare active plugins deactivate ho jaenge. Confirm?')">
                            ⚠️ Deactivate ALL Plugins (Emergency)
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB: ACTIVATE -->
            <div id="tab-activate" class="tab-content">
                <div class="card">
                    <h2>🟢 Inactive Plugins
                        <span class="badge badge-inactive"><?php echo count($inactive); ?></span>
                    </h2>
                    <?php if (empty($inactive)): ?>
                        <p style="color:#64748b;">Koi inactive plugin nahi mili.</p>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">
                        <input type="hidden" name="epm_action" value="activate">
                        <div style="margin-bottom:10px; display:flex; gap:8px;">
                            <button type="button" class="btn btn-select" onclick="selectAll('act-list', true)">✅ Select All</button>
                            <button type="button" class="btn btn-select" onclick="selectAll('act-list', false)">☐ Deselect All</button>
                        </div>
                        <div class="plugin-list" id="act-list">
                        <?php foreach ($inactive as $plugin_file):
                            $info = $all_plugins[$plugin_file] ?? ['name' => $plugin_file];
                        ?>
                            <label class="plugin-item">
                                <input type="checkbox" name="plugins[]" value="<?php echo esc_attr($plugin_file); ?>">
                                <div>
                                    <div class="plugin-name"><?php echo esc_html($info['name']); ?></div>
                                    <div class="plugin-path"><?php echo esc_html($plugin_file); ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        </div>
                        <div class="btn-row">
                            <button type="submit" class="btn btn-success">🟢 Activate Selected</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB: INFO -->
            <div id="tab-info" class="tab-content">
                <div class="card">
                    <h2>🔗 Emergency URL</h2>
                    <p style="font-size:13px; color:#94a3b8; margin-bottom:12px;">
                        Yeh URL bookmark kar lein ya kisi safe jagah save kar lein. Site crash hone par bhi kaam karega:
                    </p>
                    <div class="url-box"><?php echo esc_html($emergency_url); ?></div>
                    <br>
                    <p style="font-size:12px; color:#ef4444;">
                        ⚠️ Yeh URL secret rakhein. Isse koi bhi plugins manage kar sakta hai.
                    </p>
                </div>
                <div class="card">
                    <h2>📋 Plugin Summary</h2>
                    <p style="font-size:14px; color:#94a3b8; line-height:1.7;">
                        Total plugins installed: <strong style="color:#e2e8f0;"><?php echo count($all_plugins); ?></strong><br>
                        Active: <strong style="color:#6ee7b7;"><?php echo count($active_plugins); ?></strong><br>
                        Inactive: <strong style="color:#93c5fd;"><?php echo count($inactive); ?></strong>
                    </p>
                </div>
                <div class="card">
                    <h2>🔑 Secret Key Change Karna</h2>
                    <p style="font-size:13px; color:#94a3b8; line-height:1.7;">
                        Plugin file main yeh line change karein:<br>
                        <code style="background:#0f172a; padding:4px 8px; border-radius:4px; color:#fbbf24;">
                            define('EPM_SECRET_KEY', 'your-new-secret-key');
                        </code>
                    </p>
                </div>
            </div>

        </div>

        <script>
        function showTab(name, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            btn.classList.add('active');
        }
        function selectAll(listId, checked) {
            document.querySelectorAll('#' + listId + ' input[type=checkbox]')
                .forEach(cb => cb.checked = checked);
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ─────────────────────────────────────────────
//  HELPER: Get all installed plugins
// ─────────────────────────────────────────────
function epm_get_all_plugins() {
    if ( ! function_exists('get_plugins') ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugins = get_plugins();
    $result  = [];
    foreach ($plugins as $file => $data) {
        $result[$file] = ['name' => $data['Name'], 'version' => $data['Version']];
    }
    return $result;
}

// ─────────────────────────────────────────────
//  ADMIN PAGE (normal WP admin access)
// ─────────────────────────────────────────────
function epm_admin_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Access denied');
    }
    $secret_key  = EPM_SECRET_KEY;
    $emergency_url = home_url('/?epm_emergency=' . $secret_key);
    ?>
    <div class="wrap">
        <h1>🚨 Emergency Plugin Manager</h1>
        <div style="background:#1e1e1e; color:#e2e8f0; padding:20px; border-radius:8px; max-width:700px; margin-top:20px;">
            <h2 style="color:#ef4444; margin-bottom:12px;">Emergency URL</h2>
            <p style="margin-bottom:10px; color:#94a3b8;">
                Yeh URL site crash hone par use karein. Bookmark kar lein abhi:
            </p>
            <code style="background:#0f172a; display:block; padding:12px; border-radius:6px;
                         word-break:break-all; color:#fbbf24; font-size:13px;">
                <?php echo esc_html($emergency_url); ?>
            </code>
            <br>
            <p style="color:#ef4444; font-size:13px;">
                ⚠️ Secret key change karna na bhulen: plugin file line 12 per <code>EPM_SECRET_KEY</code>
            </p>
            <br>
            <a href="<?php echo esc_url($emergency_url); ?>" target="_blank"
               style="background:#dc2626; color:white; padding:10px 20px; border-radius:6px;
                      text-decoration:none; font-weight:600;">
                🚨 Emergency Panel Kholein
            </a>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────
//  ACTIVATION: Show notice with emergency URL
// ─────────────────────────────────────────────
register_activation_hook(__FILE__, function() {
    add_option('epm_activation_notice', true);
});

add_action('admin_notices', function() {
    if ( get_option('epm_activation_notice') ) {
        delete_option('epm_activation_notice');
        $url = home_url('/?epm_emergency=' . EPM_SECRET_KEY);
        echo '<div class="notice notice-warning is-dismissible">
            <p><strong>🚨 Emergency Plugin Manager:</strong> Apna emergency URL save kar lein: 
            <code>' . esc_html($url) . '</code></p>
        </div>';
    }
});
