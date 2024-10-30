<?php
/**
 * Main Plugin Class
 *
 * @since 1.0.0
 *
 * @package CustomFieldsSnapshots
 */

namespace Custom_Fields_Snapshots;

/**
 * Plugin Class
 *
 * @since 1.0.0
 *
 * Handles the core functionality of the Custom Fields Snapshots plugin.
 */
class Plugin {

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 *
	 * Sets up plugin hooks and filters.
	 */
	public static function init() {
		if ( ! self::is_acf_active() ) {
			add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
			return;
		}

		self::init_admin();
	}

	/**
	 * Initialize the plugin admin.
	 *
	 * @since 1.0.0
	 *
	 * Loads admin-specific functionality.
	 */
	private static function init_admin() {
		if ( ! is_admin() ) {
			return;
		}

		// Load plugin text domain for internationalization.
		load_plugin_textdomain(
			'custom-fields-snapshots',
			false,
			CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . '/languages'
		);

		require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-admin.php';

		$admin = new Admin();
		$admin->init();
	}

	/**
	 * Check if ACF or ACF Pro is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if ACF or ACF Pro is active, false otherwise.
	 */
	public static function is_acf_active() {
		return class_exists( 'ACF' );
	}

	/**
	 * Check if Advanced Custom Fields PRO is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if ACF PRO is active, false otherwise.
	 */
	public static function is_acf_pro_active() {
		return is_plugin_active( 'advanced-custom-fields-pro/acf.php' );
	}

	/**
	 * Display admin notice for missing ACF dependency.
	 */
	public static function admin_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'Custom Fields Snapshots requires Advanced Custom Fields or Advanced Custom Fields Pro to be active.', 'custom-fields-snapshots' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add plugin row meta.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $plugin_meta An array of the plugin's metadata.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return array Modified plugin metadata.
	 */
	public static function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( plugin_basename( CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_FILE ) === $plugin_file && ! self::is_acf_active() ) {
			$plugin_meta[] = '<span class="error">' . esc_html__( 'Requires Advanced Custom Fields or Advanced Custom Fields Pro to be active.', 'custom-fields-snapshots' ) . '</span>';
		}
		return $plugin_meta;
	}

	/**
	 * Uninstall the plugin and remove associated options.
	 *
	 * This static method is called when the plugin is uninstalled. It removes
	 * plugin-specific options based on whether it's a multisite installation
	 * and if the option to delete plugin data on uninstall is enabled.
	 *
	 * For multisite:
	 * - Removes 'custom_fields_snapshots_event_logging' option from each site.
	 * - Removes 'custom_fields_snapshots_delete_plugin_data' network option.
	 *
	 * For single site:
	 * - Removes all plugin-specific options.
	 *
	 * @since 1.0.0
	 */
	public static function uninstall() {
		if ( is_multisite() ) {
			// Check if we should delete plugin data.
			if ( get_site_option( 'custom_fields_snapshots_delete_plugin_data', false ) ) {
				// Get all sites in the network.
				$sites = get_sites();

				foreach ( $sites as $site ) {
					switch_to_blog( $site->blog_id );
					delete_option( 'custom_fields_snapshots_event_logging' );
					restore_current_blog();
				}

				// Delete network options.
				delete_site_option( 'custom_fields_snapshots_delete_plugin_data' );
			}
		} else {
			// Single site deletion.
			self::delete_site_options();
		}
	}

	/**
	 * Plugin uninstall callback.
	 *
	 * Cleans up plugin data when uninstalled.
	 *
	 * @since 1.0.0
	 */
	public static function delete_site_options() {
		if ( get_option( 'custom_fields_snapshots_delete_plugin_data', false ) ) {
			delete_option( 'custom_fields_snapshots_event_logging' );
			delete_option( 'custom_fields_snapshots_delete_plugin_data' );
		}
	}
}
