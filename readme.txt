=== Custom Fields Snapshots ===
Contributors: alexgeorgiev
Tags: acf, custom fields, export, import, snapshot
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create backups of your Advanced Custom Fields data for easy migration, version control, and restoration.

== Description ==

Custom Fields Snapshots allow you to easily create backups of your Advanced Custom Fields (ACF) data by exporting post types, taxonomies, options, users, and comments. These snapshots enable version control, make it easier to share setups with team members, assist with migrations between WordPress environments, and allow quick restoration of previous configurations.

**This plugin requires Advanced Custom Fields (ACF) or ACF Pro to be installed and activated.**

= Features =
* Export and import ACF field data for specific post types, taxonomies, options, users, and comments
* Fully compatible with all ACF field types, including repeaters, galleries, and flexible content
* Supports nested fields within complex field group structures
* Selective export: choose which field groups, post types, taxonomies, and individual posts, terms, users or user roles to export
* Rollbacks: automatically revert all changes if an import fails
* Detailed logging for import processes
* Developer-friendly: Extensive hook system for customization
* Multisite compatibility for seamless use across networked sites

Custom Fields Snapshots simplifies ACF data management, offering a reliable solution for site migrations, staging environment setup, and data backups. This tool ensures a smooth workflow and provides peace of mind for developers and site administrators.

== Installation ==

1. Ensure that Advanced Custom Fields (ACF) or ACF Pro is installed and activated.
2. Upload the plugin files to the `/wp-content/plugins/custom-fields-snapshots` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Use the Field Snapshots screen to configure the plugin and manage your snapshots.

== How to Use ==

1. Navigate to the 'Field Snapshots' menu in your WordPress admin panel.
2. Select the field groups, post types, taxonomies, options, specific posts, terms, users, or user roles you want to export.
3. Click 'Export Snapshot' to download a JSON file of your data.
4. To import, upload the JSON file and click 'Import Snapshot'.

== Frequently Asked Questions ==

= Which ACF versions are compatible with the plugin? =

Custom Fields Snapshots is compatible with both ACF Free and ACF Pro. While the plugin should work with versions prior to 5.0, as it relies on core ACF functions, it's recommended to use the latest version of ACF for optimal performance, security, and compatibility.

The plugin is regularly tested with the latest ACF versions to ensure continued compatibility. If you're using an older ACF version (prior to 5.0), the plugin should still function correctly with your setup.

If you encounter any compatibility issues, please don't hesitate to reach out for support.

= Can I export data from one site and import it to another? =

Absolutely! This is one of the main features of the plugin. Just make sure both sites have the same ACF field groups set up before importing.

= What kind of fields and data can I export? =

You can export any field types supported by ACF Free or ACF Pro, including repeater fields, galleries, and flexible content, from post types, taxonomies, options, users and comments.

= How does the rollback feature work? =

If an import fails, the plugin will automatically attempt to revert any changes made during the import process, ensuring your data remains intact.

= Is there a limit to the size of snapshots? =

There's no hard limit set by the plugin, but very large snapshots may be affected by PHP memory limits or max upload sizes set by your server.

= Is the plugin multisite compatible? =

Yes, the plugin supports WordPress Multisite installations, allowing you to manage ACF data across all network sites.

== Screenshots ==

1. Export interface - Select field groups, post types, taxonomies, options, users, user roles and/or comments to export
2. Import interface - Upload and import your snapshot file
3. Settings page - Configure plugin settings

== Changelog ==

= 1.2.0 =
* Ability to export data from taxonomies and comments
* Optimized handling of field group objects during imports
* Added actions for both successful and failed rollbacks
* Improved event log with copy functionality
* Added additional filters
* Interface improvements

= 1.1.1 =
* Added tooltips with additional information for field groups, post types, and user roles on the Export tab
* Prevent selection of disabled items when selecting all items

= 1.1.0 =
* Added support for exporting user field data.
* Restructured import/export processes to avoid naming conflicts.
* Enhanced data handling for smoother import/export operations.
* Added a success message when saving settings.

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.0 =
* Ability to export data from taxonomies and comments
* Optimized handling of field group objects during imports
* Added actions for both successful and failed rollbacks
* Improved event log with copy functionality
* Added additional filters
* Interface improvements

= 1.1.1 =
* Added tooltips with additional information for field groups, post types, and user roles on the Export tab
* Prevent selection of disabled items when selecting all items

= 1.1.0 =
**This version introduces a new export/import structure that prevents naming conflicts. Export files from earlier versions are not compatible. Please re-export your data after updating.**

* Support for exporting user field data
* Improved import/export processes
* Added success message for settings

= 1.0.0 =
Initial release of Custom Fields Snapshots.

== Support ==

For support, please use the [support forum](https://wordpress.org/support/plugin/custom-fields-snapshots/) on WordPress.org or the [GitHub repository](https://github.com/alex02/custom-fields-snapshots).