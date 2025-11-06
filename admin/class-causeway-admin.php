<?php

class Causeway_Admin {
    public static function init() {
        add_action('admin_menu', [self::class, 'add_admin_page']);
    }

    public static function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=listing',       // Parent menu under Listings CPT
            'Import Causeway',                 // Page title
            'Import Causeway',                 // Menu title
            'manage_options',                    // Capability
            'causeway-importer',                 // Menu slug
            [self::class, 'render_page']         // Callback function
        );
    }

    public static function render_page() {
        error_log('show page');
        $is_headless = (bool) get_field('is_headless', 'option');
        if (isset($_GET['import_queued']) && $_GET['import_queued'] === '1') {
            echo '<div class="notice notice-info is-dismissible"><p>🕑 Import scheduled. It will run in the background shortly. Importing can take up to 30 minutes.</p></div>';
        }
        if (isset($_GET['imported']) && $_GET['imported'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Listings imported successfully.</p></div>';
        }
        if ($is_headless && isset($_GET['exported']) && $_GET['exported'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Listings exported successfully.</p></div>';
        }
        ?>
<div class="wrap">
    <h1>Causeway Data Importer <span style='font-size: 1.1rem;'>(Causeway to Here)</span></h1>
    <p>This tool will manually import all listings and taxonomy data from the external Causeway API into your WordPress site.</p>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('causeway_import_action', 'causeway_import_nonce'); ?>
        <input type="hidden" name="action" value="causeway_manual_import">
        <input type="submit" name="causeway_import_submit" class="button button-primary" value="Start Import">
    </form>

    <?php if ($is_headless): ?>
    <h1>Causeway Data Exporter <span style='font-size: 1.1rem;'>(Here to Public Website)</span></h1>
    <p>This tool will manually export all listings and taxonomy data from this website into the public headless site.</p>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 20px;">
        <?php wp_nonce_field('causeway_export_action', 'causeway_export_nonce'); ?>
        <input type="hidden" name="action" value="causeway_manual_export">
        <input type="submit" name="causeway_export_submit" class="button button-secondary" value="Start Export">
    </form>
    <?php endif; ?>
</div>
<?php
    }
} 

// Hook it up
Causeway_Admin::init();
