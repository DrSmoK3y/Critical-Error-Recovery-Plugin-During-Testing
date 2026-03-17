<?php
/**
 * Plugin Name: Emergency Plugin Manager
 * Plugin URI:  https://github.com/DrSmoK3y/
 * Description: Emergency plugin deactivation via secret URL - works even when WordPress crashes. ⚠ This plugin is only for test sites, don't use it on live sites.
 * Version:     1.0.0
 * Author:      DrSmoK3y
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EPM_FILE',    __FILE__ );
define( 'EPM_OPT',     'epm_config' );

// ─────────────────────────────────────────────────────────────
//  EARLY INTERCEPT — URL open hone par deactivate (init se pehle)
// ─────────────────────────────────────────────────────────────
add_action( 'muplugins_loaded', 'epm_intercept', 1 );
add_action( 'plugins_loaded',   'epm_intercept', 1 );

function epm_intercept() {
    static $ran = false;
    if ( $ran ) return;
    $ran = true;

    if ( ! isset( $_GET['epm'] ) ) return;

    $cfg = get_option( EPM_OPT, [] );
    if ( empty( $cfg['secret'] ) ) return;
    if ( $_GET['epm'] !== $cfg['secret'] ) {
        epm_die( '❌ Invalid key.' );
    }

    // Deactivate the stored plugins
    $to_deact = $cfg['selected_plugins'] ?? [];

    if ( empty( $to_deact ) ) {
        epm_die( '⚠️ Koi plugin selected nahi. Pehle backend se plugins choose karein.' );
    }

    $active  = get_option( 'active_plugins', [] );
    $removed = [];

    foreach ( $to_deact as $p ) {
        if ( in_array( $p, $active ) ) {
            $removed[] = $p;
        }
    }

    $active = array_values( array_diff( $active, $to_deact ) );
    update_option( 'active_plugins', $active );

    epm_success_screen( $removed, $to_deact );
    exit;
}

// ─────────────────────────────────────────────────────────────
//  SUCCESS SCREEN
// ─────────────────────────────────────────────────────────────
function epm_success_screen( $removed, $attempted ) {
    $not_found = array_diff( $attempted, $removed );
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>✅ Plugins Deactivated</title>
    <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
         background:#0f0f1a;color:#e2e8f0;min-height:100vh;
         display:flex;align-items:center;justify-content:center;padding:20px;}
    .box{background:#1a1a2e;border:1px solid #2d2d44;border-radius:16px;
         padding:36px;max-width:560px;width:100%;text-align:center;}
    .icon{font-size:56px;margin-bottom:16px;}
    h1{font-size:22px;font-weight:700;color:#4ade80;margin-bottom:8px;}
    .sub{font-size:14px;color:#64748b;margin-bottom:24px;}
    .list{background:#0f0f1a;border-radius:10px;padding:16px;text-align:left;margin-bottom:20px;}
    .list-item{padding:8px 0;border-bottom:1px solid #1e1e30;font-size:13px;display:flex;align-items:center;gap:10px;}
    .list-item:last-child{border-bottom:none;}
    .dot{width:8px;height:8px;border-radius:50%;background:#4ade80;flex-shrink:0;}
    .dot.warn{background:#fbbf24;}
    .warn-text{color:#fbbf24;font-size:12px;margin-top:12px;}
    .admin-btn{display:inline-block;margin-top:20px;background:#7c3aed;color:#fff;
               padding:12px 28px;border-radius:8px;text-decoration:none;
               font-weight:600;font-size:14px;}
    </style>
    </head>
    <body>
    <div class="box">
        <div class="icon">✅</div>
        <h1><?php echo count($removed); ?> Plugin(s) Deactivate Ho Gaye!</h1>
        <p class="sub">Aap ab WP Admin mein ja kar issue fix kar sakte hain.</p>

        <?php if( !empty($removed) ): ?>
        <div class="list">
            <?php foreach($removed as $p): ?>
            <div class="list-item"><div class="dot"></div><?php echo esc_html($p); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if( !empty($not_found) ): ?>
        <p class="warn-text">⚠️ Yeh plugins pehle se inactive thay:</p>
        <div class="list">
            <?php foreach($not_found as $p): ?>
            <div class="list-item"><div class="dot warn"></div><?php echo esc_html($p); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <a class="admin-btn" href="<?php echo esc_url( admin_url('plugins.php') ); ?>">
            → WP Admin Plugins
        </a>
    </div>
    </body></html>
    <?php
}

// ─────────────────────────────────────────────────────────────
//  ERROR SCREEN
// ─────────────────────────────────────────────────────────────
function epm_die( $msg ) {
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>EPM Error</title>
    <style>
    body{font-family:sans-serif;background:#0f0f1a;color:#e2e8f0;display:flex;
         align-items:center;justify-content:center;min-height:100vh;}
    .box{background:#1a1a2e;border:1px solid #7f1d1d;border-radius:12px;padding:32px;
         max-width:480px;text-align:center;}
    h2{color:#f87171;margin-bottom:10px;}p{color:#64748b;font-size:14px;}
    </style></head><body>
    <div class="box"><h2>🚨 Emergency Plugin Manager</h2>
    <p><?php echo esc_html($msg); ?></p></div>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
//  ADMIN MENU
// ─────────────────────────────────────────────────────────────
add_action( 'admin_menu', function() {
    add_management_page(
        'Emergency Plugin Manager',
        '🚨 Emergency Manager',
        'manage_options',
        'epm-settings',
        'epm_admin_page'
    );
});

// ─────────────────────────────────────────────────────────────
//  SAVE SETTINGS
// ─────────────────────────────────────────────────────────────
add_action( 'admin_post_epm_save', function() {
    if ( ! current_user_can('manage_options') ) wp_die('No access');
    check_admin_referer('epm_save');

    $key     = sanitize_text_field( $_POST['secret'] ?? '' );
    $plugins = array_map( 'sanitize_text_field', $_POST['selected_plugins'] ?? [] );

    if ( empty($key) ) {
        wp_redirect( admin_url('tools.php?page=epm-settings&err=nokey') );
        exit;
    }
    if ( empty($plugins) ) {
        wp_redirect( admin_url('tools.php?page=epm-settings&err=noplugins') );
        exit;
    }

    update_option( EPM_OPT, [
        'secret'           => $key,
        'selected_plugins' => $plugins,
    ]);

    wp_redirect( admin_url('tools.php?page=epm-settings&saved=1') );
    exit;
});

// ─────────────────────────────────────────────────────────────
//  ADMIN PAGE
// ─────────────────────────────────────────────────────────────
function epm_admin_page() {
    if ( ! current_user_can('manage_options') ) return;

    if ( ! function_exists('get_plugins') )
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $cfg      = get_option( EPM_OPT, [] );
    $secret   = $cfg['secret'] ?? '';
    $selected = $cfg['selected_plugins'] ?? [];
    $all      = get_plugins();
    $active   = get_option( 'active_plugins', [] );
    $this_p   = plugin_basename( EPM_FILE );
    $saved    = isset( $_GET['saved'] );
    $err      = $_GET['err'] ?? '';
    $gen_url  = $secret ? home_url( '/?epm=' . $secret ) : '';
    ?>
    <style>
    #epm-wrap *{box-sizing:border-box;}
    #epm-wrap{max-width:800px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;}
    #epm-wrap h1{font-size:22px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
    .epm-card{background:#1e1e2e;border:1px solid #2d2d44;border-radius:12px;
              padding:24px;margin-bottom:20px;color:#e2e8f0;}
    .epm-card h2{font-size:15px;font-weight:700;color:#a78bfa;margin-bottom:6px;}
    .epm-card p.hint{font-size:12px;color:#64748b;margin-bottom:16px;}
    .epm-input{width:100%;max-width:380px;padding:10px 14px;border-radius:8px;
               background:#0f0f1a;border:1px solid #3d3d5c;color:#e2e8f0;
               font-size:14px;outline:none;}
    .epm-input:focus{border-color:#7c3aed;}
    .plugin-grid{display:flex;flex-direction:column;gap:6px;max-height:400px;
                 overflow-y:auto;padding-right:6px;margin-bottom:16px;}
    .plugin-grid::-webkit-scrollbar{width:4px;}
    .plugin-grid::-webkit-scrollbar-thumb{background:#2d2d44;border-radius:4px;}
    .pi-row{background:#0f0f1a;border:1px solid #2d2d44;border-radius:8px;
            padding:11px 14px;display:flex;align-items:center;gap:12px;
            cursor:pointer;transition:border-color .15s;}
    .pi-row:hover{border-color:#7c3aed;}
    .pi-row.checked{border-color:#7c3aed;background:#1a0f2e;}
    .pi-row input{width:17px;height:17px;accent-color:#a78bfa;cursor:pointer;flex-shrink:0;}
    .pi-name{font-size:13px;font-weight:600;color:#e2e8f0;}
    .pi-slug{font-size:11px;color:#64748b;margin-top:2px;}
    .sel-bar{display:flex;gap:8px;margin-bottom:10px;}
    .btn-sm{padding:6px 12px;border-radius:6px;border:1px solid #3d3d5c;
            background:#1a1a2e;color:#94a3b8;font-size:12px;cursor:pointer;}
    .btn-sm:hover{color:#e2e8f0;border-color:#7c3aed;}
    .btn-save{background:#7c3aed;color:#fff;border:none;padding:11px 28px;
              border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;}
    .btn-save:hover{background:#6d28d9;}
    .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;
           font-size:13px;font-weight:600;}
    .alert.ok {background:#052e16;border:1px solid #166534;color:#4ade80;}
    .alert.err{background:#2d0000;border:1px solid #7f1d1d;color:#f87171;}
    .url-box{background:#0f0f1a;border:1px solid #2d2d44;border-radius:8px;
             padding:14px;font-family:monospace;font-size:13px;color:#fbbf24;
             word-break:break-all;margin-bottom:10px;}
    .copy-btn{background:#1a1a2e;border:1px solid #3d3d5c;color:#94a3b8;
              padding:7px 16px;border-radius:6px;font-size:12px;cursor:pointer;}
    .copy-btn:hover{color:#e2e8f0;}
    .badge{display:inline-block;background:#1a0f2e;color:#a78bfa;
           border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;margin-left:6px;}
    .selected-preview{background:#0f0f1a;border-radius:8px;padding:12px 14px;
                      font-size:12px;color:#64748b;margin-top:10px;}
    .selected-preview span{color:#a78bfa;}
    .st-badge{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;
              white-space:nowrap;flex-shrink:0;}
    .st-on {background:#052e16;color:#4ade80;border:1px solid #166534;}
    .st-off{background:#1e1e30;color:#64748b;border:1px solid #2d2d44;}
    </style>

    <div id="epm-wrap">
    <h1>🚨 Emergency Plugin Manager</h1>

    <?php if ($saved): ?>
    <div class="alert ok">✅ Save ho gaya! Emergency URL ready hai — bookmark kar lein.</div>
    <?php endif; ?>
    <?php if ($err === 'nokey'): ?>
    <div class="alert err">❌ Secret key likhna zarori hai.</div>
    <?php elseif ($err === 'noplugins'): ?>
    <div class="alert err">❌ Kam az kam ek plugin select karein.</div>
    <?php endif; ?>

    <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php wp_nonce_field('epm_save'); ?>
    <input type="hidden" name="action" value="epm_save">

    <!-- SECRET KEY -->
    <div class="epm-card">
        <h2>🔑 Secret Key</h2>
        <p class="hint">Apni marzi ki key likhein — letters ya numbers. Koi default nahi.</p>
        <input type="text" name="secret" class="epm-input"
               value="<?php echo esc_attr($secret); ?>"
               placeholder="e.g. ali2024 ya mysite99"
               autocomplete="off">
    </div>

    <!-- PLUGIN SELECTION — ALL PLUGINS -->
    <div class="epm-card">
        <h2>📋 Plugins Select Karein
            <span class="badge" id="sel-count"><?php echo count($selected); ?> selected</span>
        </h2>
        <p class="hint">
            Sare installed plugins — active aur inactive dono dikh rahe hain.
            Jo select karein ge wo URL kholne par <strong style="color:#f87171;">deactivate</strong> ho jaenge.
        </p>

        <div class="sel-bar">
            <button type="button" class="btn-sm" onclick="selAll(true)">✅ Sab Select</button>
            <button type="button" class="btn-sm" onclick="selAll(false)">☐ Sab Hatao</button>
        </div>

        <div class="plugin-grid" id="pgrid">
        <?php
        // Build list: active upar, inactive neeche
        $all_sorted = [];
        foreach ( $all as $pfile => $info ) {
            if ( $pfile === $this_p ) continue;
            $all_sorted[] = [
                'file'   => $pfile,
                'info'   => $info,
                'active' => in_array( $pfile, $active ),
            ];
        }
        usort( $all_sorted, fn($a,$b) => $b['active'] - $a['active'] );

        foreach ( $all_sorted as $row ):
            $pfile      = $row['file'];
            $info       = $row['info'];
            $is_active  = $row['active'];
            $is_checked = in_array( $pfile, $selected );
        ?>
        <label class="pi-row <?php echo $is_checked ? 'checked' : ''; ?>" onclick="updateRow(this)">
            <input type="checkbox" name="selected_plugins[]"
                   value="<?php echo esc_attr($pfile); ?>"
                   <?php checked($is_checked); ?>
                   onchange="updateCount()">
            <div style="flex:1;">
                <div class="pi-name">
                    <?php echo esc_html($info['Name']); ?>
                    <?php if(!empty($info['Version'])): ?>
                    <span style="color:#64748b;font-weight:400;font-size:11px;">
                        v<?php echo esc_html($info['Version']); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="pi-slug"><?php echo esc_html($pfile); ?></div>
            </div>
            <span class="st-badge <?php echo $is_active ? 'st-on' : 'st-off'; ?>">
                <?php echo $is_active ? '● Active' : '○ Inactive'; ?>
            </span>
        </label>
        <?php endforeach; ?>
        </div>

        <button type="submit" class="btn-save">💾 Save Karein &amp; URL Banao</button>
    </div>

    </form>

    <!-- GENERATED URL -->
    <?php if ($gen_url && !empty($selected)): ?>
    <div class="epm-card">
        <h2>🔗 Emergency URL</h2>
        <p class="hint">
            Yeh URL kholne se <strong style="color:#f87171;"><?php echo count($selected); ?> plugin(s)</strong>
            automatically deactivate ho jaenge — bina login ke.
        </p>
        <div class="url-box" id="epm-url"><?php echo esc_html($gen_url); ?></div>
        <button class="copy-btn" onclick="copyUrl()">📋 Copy URL</button>
        <a href="<?php echo esc_url($gen_url); ?>" target="_blank"
           style="margin-left:8px;background:#7f1d1d;color:#fca5a5;border:1px solid #dc2626;
                  padding:7px 16px;border-radius:6px;font-size:12px;text-decoration:none;
                  font-weight:600;">
            🧪 Test Karein →
        </a>

        <div class="selected-preview">
            <strong style="color:#e2e8f0;">Deactivate honge:</strong><br>
            <?php foreach($selected as $p):
                $n = $all[$p]['Name'] ?? $p;
            ?>
            <span>• <?php echo esc_html($n); ?></span><br>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($secret && empty($selected)): ?>
    <div class="epm-card">
        <p style="color:#64748b;font-size:13px;">
            ⚠️ Koi plugin selected nahi — URL generate nahi hoga.
        </p>
    </div>
    <?php endif; ?>

    </div><!-- /epm-wrap -->

    <script>
    function updateRow(label) {
        setTimeout(() => {
            var cb = label.querySelector('input[type=checkbox]');
            label.classList.toggle('checked', cb.checked);
            updateCount();
        }, 0);
    }
    function updateCount() {
        var n = document.querySelectorAll('#pgrid input:checked').length;
        document.getElementById('sel-count').textContent = n + ' selected';
    }
    function selAll(chk) {
        document.querySelectorAll('#pgrid input[type=checkbox]').forEach(function(cb) {
            cb.checked = chk;
            cb.closest('.pi-row').classList.toggle('checked', chk);
        });
        updateCount();
    }
    function copyUrl() {
        var txt = document.getElementById('epm-url').textContent.trim();
        navigator.clipboard.writeText(txt).then(function() {
            var btn = event.target;
            btn.textContent = '✅ Copied!';
            setTimeout(function(){ btn.textContent = '📋 Copy URL'; }, 2000);
        });
    }
    </script>
    <?php
}

// ─────────────────────────────────────────────────────────────
//  ACTIVATION NOTICE
// ─────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, function() {
    add_option('epm_fresh', 1);
});

add_action('admin_notices', function() {
    if (!get_option('epm_fresh')) return;
    delete_option('epm_fresh');
    $url = admin_url('tools.php?page=epm-settings');
    echo '<div class="notice notice-warning is-dismissible"><p>
        🚨 <strong>Emergency Plugin Manager:</strong>
        <a href="'.esc_url($url).'"><strong>Setup karein — secret key aur plugins select karein</strong></a>
    </p></div>';
});
