<?php

class Causeway_Admin {
    public static function init() {
        add_action('admin_menu', [self::class, 'add_admin_page']);
        add_action('admin_init', [self::class, 'maybe_force_update_check']);
    }

    public static function add_admin_page() {
        // Import page (always visible)
        add_submenu_page(
            'edit.php?post_type=listing',       // Parent menu under Listings CPT
            'Import Causeway',                   // Page title
            'Import Causeway',                   // Menu title
            'manage_options',                    // Capability
            'causeway-importer',                 // Menu slug
            [self::class, 'render_import_page']  // Callback function
        );

        // Export page (only if headless)
        $is_headless = (bool) get_field('is_headless', 'option');
        if ($is_headless) {
            add_submenu_page(
                'edit.php?post_type=listing',       // Parent menu under Listings CPT
                'Export Causeway',                   // Page title
                'Export Causeway',                   // Menu title
                'manage_options',                    // Capability
                'causeway-exporter',                 // Menu slug
                [self::class, 'render_export_page']  // Callback function
            );
        }

        // Plugin Updates utility page (always visible)
        add_submenu_page(
            'edit.php?post_type=listing',
            'Plugin Updates',
            'Plugin Updates',
            'manage_options',
            'causeway-updates',
            [self::class, 'render_updates_page']
        );
    }

    private static function render_activity_panel(): void {
        $tracking = get_option('causeway_import_tracking', []);
        if (!is_array($tracking)) {
            $tracking = [];
        }

        $format_time = static function ($timestamp): string {
            if (empty($timestamp)) {
                return '—';
            }

            return wp_date(
                get_option('date_format') . ' ' . get_option('time_format'),
                (int) $timestamp
            );
        };
        $format_duration = static function ($seconds): string {
            return is_numeric($seconds) ? number_format_i18n((float) $seconds, 2) . ' seconds' : '—';
        };
        $format_status = static function ($status): string {
            $status = (string) $status;
            if ($status === '') {
                return '—';
            }

            $colors = [
                'success' => '#008a20',
                'failed' => '#b32d2e',
                'running' => '#2271b1',
                'checking' => '#996800',
                'skipped' => '#646970',
            ];
            $color = $colors[$status] ?? '#646970';
            return '<strong style="color:' . esc_attr($color) . ';">' . esc_html(ucfirst($status)) . '</strong>';
        };

        $processed = isset($tracking['last_import_processed']) ? (int) $tracking['last_import_processed'] : null;
        $total = isset($tracking['last_import_total']) ? (int) $tracking['last_import_total'] : null;
        $listing_count = ($processed !== null && $total !== null && ($processed > 0 || $total > 0))
            ? number_format_i18n($processed) . ' / ' . number_format_i18n($total)
            : '—';
        ?>
        <div class="causeway-activity" style="max-width:1000px;margin:18px 0 22px;">
            <h2 style="margin-bottom:10px;">Import &amp; Export Activity</h2>
            <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;">
                <table class="widefat striped" style="width:480px;max-width:100%;">
                    <thead><tr><th colspan="2">Imports</th></tr></thead>
                    <tbody>
                        <tr><td>Last started</td><td><?php echo esc_html($format_time($tracking['last_import_started_at'] ?? null)); ?></td></tr>
                        <tr><td>Last successful</td><td><?php echo esc_html($format_time($tracking['last_import_succeeded_at'] ?? null)); ?></td></tr>
                        <tr><td>Last failed</td><td><?php echo esc_html($format_time($tracking['last_import_failed_at'] ?? null)); ?></td></tr>
                        <tr><td>Latest result</td><td><?php echo $format_status($tracking['last_import_status'] ?? ''); ?></td></tr>
                        <tr><td>Mode</td><td><?php echo esc_html(ucfirst((string) ($tracking['last_import_mode'] ?? '—'))); ?></td></tr>
                        <tr><td>Duration</td><td><?php echo esc_html($format_duration($tracking['last_import_duration_seconds'] ?? null)); ?></td></tr>
                        <tr><td>Listings processed</td><td><?php echo esc_html($listing_count); ?></td></tr>
                    </tbody>
                </table>
                <table class="widefat striped" style="width:480px;max-width:100%;">
                    <thead><tr><th colspan="2">Exports</th></tr></thead>
                    <tbody>
                        <tr><td>Last requested</td><td><?php echo esc_html($format_time($tracking['last_export_requested_at'] ?? null)); ?></td></tr>
                        <tr><td>Last attempt</td><td><?php echo esc_html($format_time($tracking['last_export_started_at'] ?? null)); ?></td></tr>
                        <tr><td>Last successful</td><td><?php echo esc_html($format_time($tracking['last_export_succeeded_at'] ?? null)); ?></td></tr>
                        <tr><td>Last failed</td><td><?php echo esc_html($format_time($tracking['last_export_failed_at'] ?? null)); ?></td></tr>
                        <tr><td>Latest result</td><td><?php echo $format_status($tracking['last_export_status'] ?? ''); ?></td></tr>
                        <tr><td>Duration</td><td><?php echo esc_html($format_duration($tracking['last_export_duration_seconds'] ?? null)); ?></td></tr>
                        <tr><td>HTTP status</td><td><?php echo esc_html(!empty($tracking['last_export_http_code']) ? (string) $tracking['last_export_http_code'] : '—'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($tracking['last_import_error'])) : ?>
                <p style="color:#b32d2e;margin:10px 0 0;"><strong>Latest import error:</strong> <?php echo esc_html($tracking['last_import_error']); ?></p>
            <?php endif; ?>
            <?php if (!empty($tracking['last_export_error'])) : ?>
                <p style="color:#b32d2e;margin:6px 0 0;"><strong>Latest export note:</strong> <?php echo esc_html($tracking['last_export_error']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_import_page() {
        error_log('show page');
        $is_headless = (bool) get_field('is_headless', 'option');
        if (isset($_GET['import_queued']) && $_GET['import_queued'] === '1') {
            echo '<div class="notice notice-info is-dismissible"><p>🕑 Import scheduled. It will run in the background shortly. Importing can take up to 30 minutes.</p></div>';
        }
        if (isset($_GET['taxonomies_queued']) && $_GET['taxonomies_queued'] === '1') {
            echo '<div class="notice notice-info is-dismissible"><p>🕑 Taxonomy-only import scheduled. It will run in the background shortly.</p></div>';
        }
        if (isset($_GET['listings_queued']) && $_GET['listings_queued'] === '1') {
            echo '<div class="notice notice-info is-dismissible"><p>🕑 Listings-only import scheduled. It will run in the background shortly.</p></div>';
        }
        if (isset($_GET['import_running']) && $_GET['import_running'] === '1') {
            echo '<div class="notice notice-warning is-dismissible"><p>⚠️ An import is already running. You can watch progress below.</p></div>';
        }
        if (isset($_GET['imported']) && $_GET['imported'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Listings imported successfully.</p></div>';
        }
        if (isset($_GET['taxonomies_imported']) && $_GET['taxonomies_imported'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Taxonomies imported successfully.</p></div>';
        }
        if (isset($_GET['import_reset']) && $_GET['import_reset'] === '1') {
            echo '<div class="notice notice-info is-dismissible"><p>🔄 Import status reset.</p></div>';
        }
        ?>
<div class="wrap">
    <h1>Causeway Data Importer <span style='font-size: 1.1rem;'>(Causeway to WordPress)</span></h1>
    <p>This tool will manually import all listings and taxonomy data from the external Causeway API into your WordPress site.</p>
    <?php self::render_activity_panel(); ?>
    <?php
    $status = function_exists('causeway_get_import_status')
        ? causeway_get_import_status()
        : get_option('causeway_import_status', []);
    $running = is_array($status) && !empty($status['running']);
    $percent = isset($status['percent']) ? floatval($status['percent']) : 0;
    $processed = intval($status['processed'] ?? 0);
    $total = intval($status['total'] ?? 0);
    $phase = esc_html($status['phase'] ?? 'idle');
    $state = esc_html($status['state'] ?? 'idle');
    $error_message = isset($status['error_message']) ? esc_html($status['error_message']) : '';
    $has_total = $total > 0;
    ?>
    <div id="causeway-progress" style="max-width:600px;margin:15px 0;display:<?php echo ($running || $state === 'error') ? 'block' : 'none'; ?>;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <div>
                <strong>Import status:</strong> <span id="cw-phase"><?php echo $phase; ?></span>
                <div id="cw-error" style="<?php echo ($state === 'error' && $error_message) ? '' : 'display:none;'; ?>margin-top:4px;color:#b32d2e;font-weight:600;">Error:
                    <?php echo $error_message; ?></div>
            </div>
            <div id="cw-count-wrap" style="color:#555;<?php echo $has_total ? '' : 'display:none;'; ?>;text-align:right;">
                <span id="cw-count"><?php echo $processed; ?></span>
                /
                <span id="cw-total"><?php echo $total; ?></span>
                Listings Imported
            </div>
        </div>
        <div style="background:#e5e5e5;border-radius:3px;overflow:hidden;height:18px;margin-top:6px;">
            <div id="cw-bar" style="background:#2271b1;height:100%;width:<?php echo round($percent*100); ?>%;transition:width .3s;"></div>
        </div>
        <div style="margin-top:6px;display:flex;align-items:center;justify-content:space-between;">
            <div style="color:#555;">
                <span id="cw-percent"><?php echo round($percent*100); ?></span>%
            </div>
            <div style="flex:1"></div>
            <div style="visibility:hidden;"></div>
        </div>
    </div>
    <div style="margin-top:10px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin:0;">
            <?php wp_nonce_field('causeway_import_action', 'causeway_import_nonce'); ?>
            <input type="hidden" name="action" value="causeway_manual_import">
            <input type="submit" name="causeway_import_submit" class="button button-primary" value="Start Import" <?php echo $running ? 'disabled' : ''; ?>>
        </form>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin:0;">
            <?php wp_nonce_field('causeway_import_taxonomies_action', 'causeway_import_taxonomies_nonce'); ?>
            <input type="hidden" name="action" value="causeway_manual_import_taxonomies">
            <input type="submit" name="causeway_import_taxonomies_submit" class="button button-secondary" value="Import Only Taxonomies" <?php echo $running ? 'disabled' : ''; ?>>
        </form>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin:0;">
            <?php wp_nonce_field('causeway_import_listings_action', 'causeway_import_listings_nonce'); ?>
            <input type="hidden" name="action" value="causeway_manual_import_listings">
            <input type="submit" name="causeway_import_listings_submit" class="button button-secondary" value="Import Only Listings" <?php echo $running ? 'disabled' : ''; ?>>
        </form>

        <div id="cw-inline-controls" style="display:<?php echo $running ? 'flex' : 'none'; ?>; gap:8px; align-items:center;">
            <button type="button" class="button" id="cw-cancel-btn">Cancel Import</button>
            <span id="cw-cancelled" style="display:none;color:#555;">Cancel requested…</span>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Reset status? This will clear the running flag.');"
                style="margin:0;display:inline;">
                <?php wp_nonce_field('causeway_import_reset_action', 'causeway_import_reset_nonce'); ?>
                <input type="hidden" name="action" value="causeway_manual_import_reset" />
                <button type="submit" class="button">Force Reset Status</button>
            </form>
        </div>
    </div>
</div>
<script>
(function() {
    var $wrap = document.getElementById('causeway-progress');
    var $bar = document.getElementById('cw-bar');
    var $phase = document.getElementById('cw-phase');
    var $countWrap = document.getElementById('cw-count-wrap');
    var $count = document.getElementById('cw-count');
    var $total = document.getElementById('cw-total');
    var $pct = document.getElementById('cw-percent');
    var $cancelBtn = document.getElementById('cw-cancel-btn');
    var $cancelled = document.getElementById('cw-cancelled');
    var $inlineControls = document.getElementById('cw-inline-controls');
    var $err = document.getElementById('cw-error');

    function poll() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onload = function() {
            try {
                var res = JSON.parse(xhr.responseText);
                if (!res || !res.success) return;
                var s = res.data || {};
                if (s.running) {
                    if ($wrap) $wrap.style.display = 'block';
                    var percent = Math.max(0, Math.min(100, Math.round((s.percent || 0) * 100)));
                    if ($bar) $bar.style.width = percent + '%';
                    if ($phase) $phase.textContent = s.phase || 'running';
                    if ($count) $count.textContent = s.processed || 0;
                    if ($total) $total.textContent = s.total || 0;
                    if ($countWrap) {
                        if ((s.total || 0) > 0) {
                            $countWrap.style.display = '';
                        } else {
                            $countWrap.style.display = 'none';
                        }
                    }
                    if ($pct) $pct.textContent = percent;
                    if ($err) $err.style.display = 'none';
                    if ($cancelBtn) $cancelBtn.disabled = !!s.cancel_requested;
                    if ($cancelled) $cancelled.style.display = s.cancel_requested ? '' : 'none';
                    if ($inlineControls) $inlineControls.style.display = 'flex';
                } else if (s.state === 'error') {
                    if ($wrap) $wrap.style.display = 'block';
                    if ($phase) $phase.textContent = 'error';
                    if ($err) {
                        $err.textContent = 'Error: ' + (s.error_message || 'Import failed');
                        $err.style.display = '';
                    }
                    clearInterval(timer);
                } else {
                    if ($wrap) $wrap.style.display = 'none';
                    if ($inlineControls) $inlineControls.style.display = 'none';
                    clearInterval(timer);
                }
            } catch (e) {}
        };
        xhr.send('action=causeway_import_status');
    }
    if ($cancelBtn) {
        $cancelBtn.addEventListener('click', function() {
            $cancelBtn.disabled = true;
            if ($cancelled) $cancelled.style.display = '';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.onload = function() {};
            xhr.send('action=causeway_import_cancel');
        });
    }
    var timer = setInterval(poll, 4000);
    poll();
})();
</script>
<?php
    }

    public static function render_export_page() {
        $is_headless = (bool) get_field('is_headless', 'option');
        if (!$is_headless) {
            wp_die('Export is disabled: this site is not configured as headless.');
        }
        if (isset($_GET['exported']) && $_GET['exported'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Listings exported successfully.</p></div>';
        }
        if (isset($_GET['export_failed']) && $_GET['export_failed'] === '1') {
            echo '<div class="notice notice-error is-dismissible"><p>❌ Export notification failed or was skipped. See the activity details below.</p></div>';
        }
        ?>
<div class="wrap">
    <h1>Causeway Data Exporter <span style='font-size: 1.1rem;'>(Here to Public Website)</span></h1>
    <p>This tool will manually export all listings and taxonomy data from this website into the public headless site.</p>
    <?php self::render_activity_panel(); ?>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 20px;">
        <?php wp_nonce_field('causeway_export_action', 'causeway_export_nonce'); ?>
        <input type="hidden" name="action" value="causeway_manual_export">
        <input type="submit" name="causeway_export_submit" class="button button-secondary" value="Start Export">
    </form>
</div>
<?php
    }

    /**
     * Render the Plugin Updates utility page.
     */
    public static function render_updates_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $plugin_file = dirname(__DIR__) . '/causeway.php';
        $plugin_data = get_file_data($plugin_file, [ 'Version' => 'Version' ]);
        $current_version = $plugin_data['Version'] ?? 'unknown';

        $cached_release = get_site_transient('causeway_github_latest_release');
        $cached_tag = is_array($cached_release) ? ($cached_release['tag_name'] ?? '') : '';

        if (isset($_GET['force_update']) && $_GET['force_update'] === '1') {
            echo '<div class="notice notice-info is-dismissible"><p>🔄 Update cache cleared and rechecked. If a new release exists it should now appear on the Plugins page.</p></div>';
        }
        ?>
<div class="wrap">
    <h1>Plugin Updates</h1>
    <p>Manage update checks for the Causeway Listings Importer plugin.</p>
    <table class="widefat" style="max-width:600px;margin-top:15px;">
        <thead>
            <tr>
                <th>Item</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Current Installed Version</td>
                <td><?php echo esc_html($current_version); ?></td>
            </tr>
            <tr>
                <td>Cached Latest GitHub Tag</td>
                <td><?php echo esc_html($cached_tag ?: '—'); ?></td>
            </tr>
            <tr>
                <td>Cache TTL</td>
                <td>30 minutes (release & plugin update transients)</td>
            </tr>
        </tbody>
    </table>
    <h2 style="margin-top:30px;">Force Update Check</h2>
    <p>Clears cached release + plugin update data and triggers an immediate refresh.</p>
    <form method="post" action="<?php echo esc_url(admin_url('edit.php?post_type=listing&page=causeway-updates')); ?>" style="margin-top:10px;">
        <?php wp_nonce_field('causeway_force_update_action', 'causeway_force_update_nonce'); ?>
        <input type="hidden" name="causeway_force_update" value="1" />
        <button type="submit" class="button button-primary">Force Update Check</button>
    </form>
    <p style="margin-top:15px; font-size:0.9rem; color:#555;">Tip: The Plugins page also refreshes automatically on its own schedule. This button is only needed to bypass the
        30‑minute cache window.</p>
</div>
<?php
    }

    /**
     * Handle the force update request: clear our release transient & core plugin update transient,
     * then trigger an update check so the Plugins page reflects the latest version immediately.
     */
    public static function maybe_force_update_check() {
        if (!is_admin()) return;
        if (!current_user_can('manage_options')) return;
        if (!isset($_POST['causeway_force_update'])) return;
        if (!isset($_POST['causeway_force_update_nonce']) || !wp_verify_nonce($_POST['causeway_force_update_nonce'], 'causeway_force_update_action')) return;

        // Delete our cached GitHub release data
        delete_site_transient('causeway_github_latest_release');

        // Clear the core plugin update data so WP refetches
        delete_site_transient('update_plugins');

        // Prime a fresh check
        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }
        wp_update_plugins();

        // Redirect to avoid resubmission; add notice flag
        wp_redirect(add_query_arg('force_update', '1', admin_url('edit.php?post_type=listing&page=causeway-updates')));
        exit;
    }
} 

// Hook it up
Causeway_Admin::init();
