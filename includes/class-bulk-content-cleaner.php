<?php

class Bulk_Content_Cleaner {

    protected $loader;

    protected $plugin_name;

    protected $version;

    public function __construct() {
        if ( defined( 'BCC_VERSION' ) ) {
            $this->version = BCC_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'bulk-content-cleaner';

        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    private function load_dependencies() {
        require_once BCC_PLUGIN_DIR . 'includes/class-bulk-content-cleaner-loader.php';
        require_once BCC_PLUGIN_DIR . 'admin/class-bulk-content-cleaner-admin.php';
        $this->loader = new Bulk_Content_Cleaner_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new Bulk_Content_Cleaner_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        
        $this->loader->add_action( 'wp_ajax_bcc_delete_posts', $plugin_admin, 'ajax_delete_posts' );
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }
}
