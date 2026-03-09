<?php
/**
 * Plugin Name: Bulk Content Cleaner
 * Plugin URI: https://matteomorreale.com
 * Description: A plugin to bulk delete posts and media attachments.
 * Version: 1.0.1
 * Author: Matteo Morreale
 * Author URI: https://matteomorreale.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bulk-content-cleaner
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'BCC_VERSION', '1.0.0' );
define( 'BCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bulk-content-cleaner.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks, 
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_bulk_content_cleaner() {
    $plugin = new Bulk_Content_Cleaner();
    $plugin->run();
}

run_bulk_content_cleaner();
