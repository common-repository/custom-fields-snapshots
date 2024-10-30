<?php
/**
 * Plugin Name: Custom Fields Snapshots
 * Description: Create backups of your Advanced Custom Fields data for easy migration, version control, and restoration.
 * Version: 1.2.0
 * Author: Alex Georgiev
 * Author URI: https://alexgv.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-fields-snapshots
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package CustomFieldsSnapshots
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CUSTOM_FIELDS_SNAPSHOTS_VERSION', '1.2.0' );
define( 'CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_FILE', __FILE__ );
define( 'CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR', plugin_dir_path( CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_FILE ) );
define( 'CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_URL', plugin_dir_url( CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_FILE ) );

// Include the main plugin class.
require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-plugin.php';

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'Custom_Fields_Snapshots\Plugin', 'init' ) );

// Register uninstall hook.
register_uninstall_hook( CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_FILE, array( 'Custom_Fields_Snapshots\Plugin', 'uninstall' ) );
