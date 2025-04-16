<?php

class Causeway_Admin {
    public static function init() {
        add_action('admin_menu', [self::class, 'add_admin_page']);
    }

    public static function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=listing',       // Parent menu under Listings CPT
            'Causeway Importer',                 // Page title
            'Causeway Importer',                 // Menu title
            'manage_options',                    // Capability
            'causeway-importer',                 // Menu slug
            [self::class, 'render_page']         // Callback function
        );
    }

    public static function render_page() {
        error_log('show page');
        if (isset($_GET['imported']) && $_GET['imported'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Listings imported successfully.</p></div>';
        }
        ?>
<div class="wrap">
    <h1>Causeway Listings Importer</h1>
    <p>This tool will manually import all listings and taxonomy data from the external Causeway API into your WordPress site.</p>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="causeway_manual_import">
        <?php wp_nonce_field('causeway_import_action', 'causeway_import_nonce'); ?>
        <input type="submit" name="causeway_import_submit" class="button button-primary" value="Start Import">
    </form>
</div>
<?php
    }
} 

// Hook it up
Causeway_Admin::init();
