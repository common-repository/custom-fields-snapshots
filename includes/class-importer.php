<?php
/**
 * Custom Fields Snapshots Importer class
 *
 * @since 1.0.0
 *
 * @package CustomFieldsSnapshots
 */

namespace Custom_Fields_Snapshots;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Fields Snapshots Importer Class
 *
 * @since 1.0.0
 */
class Importer {

	/**
	 * The logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The field processor instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Field_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Logger $logger The logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Import field data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $json_data    The JSON data to import.
	 * @param bool   $rollback Whether to use rollback on failure.
	 * @return bool True on success, false on failure.
	 */
	public function import_field_data( $json_data, $rollback = true ) {
		// Load the field processor class.
		require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-field-processor.php';

		$this->processor = new Field_Processor();

		$data = json_decode( $json_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->log( 'error', __( 'Invalid JSON data', 'custom-fields-snapshots' ) );

			return false;
		}

		if ( ! is_array( $data ) ) {
			$this->logger->log( 'error', __( 'Import failed: Invalid data format.', 'custom-fields-snapshots' ) );

			return false;
		}

		$original_data  = array();
		$import_success = true;

		foreach ( $data as $group_key => $fields ) {
			/* translators: %s: group key */
			$this->logger->log( 'info', sprintf( __( 'Importing group: "%s"', 'custom-fields-snapshots' ), $group_key ) );

			if ( ! $this->import_group_data( $group_key, $fields, $original_data ) ) {
				$import_success = false;
				break;
			}
		}

		if ( ! $import_success && $rollback ) {
			$this->rollback_changes( $original_data );

			$this->logger->log( 'error', __( 'Changes rolled back due to import failure.', 'custom-fields-snapshots' ) );
		}

		$this->logger->log( $import_success ? 'success' : 'error', __( 'Import process finished. Status:', 'custom-fields-snapshots' ) . ' ' . ( $import_success ? __( 'Success', 'custom-fields-snapshots' ) : __( 'Failed', 'custom-fields-snapshots' ) ) );

		return $import_success;
	}

	/**
	 * Import data for a single field group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group_key     The field group key.
	 * @param array  $fields        The fields data to import.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @return bool True on success, false on failure.
	 */
	private function import_group_data( $group_key, $fields, &$original_data ) {
		if ( empty( array_filter( $fields ) ) ) {
			$this->logger->log( 'info', __( 'No fields to import, skipping update.', 'custom-fields-snapshots' ) );
			return true; // Return true as this is not a failure case.
		}

		foreach ( $fields as $field_name => $field_data ) {
			if ( ! is_array( $field_data ) ) {
				$this->logger->log(
					'info',
					/* translators: %1$s: field name, %2$s: group key */
					sprintf( __( 'Invalid data structure for field "%1$s" in group "%2$s"', 'custom-fields-snapshots' ), $field_name, $group_key )
				);
				return false;
			}

			// Import post type fields.
			if ( isset( $field_data['post_types'] ) ) {
				foreach ( $field_data['post_types'] as $post_type => $posts ) {
					foreach ( $posts as $post_id => $value ) {
						if ( ! $this->import_post_field( $field_name, $post_id, $value, $original_data, $group_key, $post_type ) ) {
							return false;
						}
					}
				}
			}

			// Import taxonomy fields.
			if ( isset( $field_data['taxonomies'] ) && ! $this->import_taxonomy_fields( $field_name, $field_data['taxonomies'], $original_data, $group_key ) ) {
				return false;
			}

			// Import options fields.
			if ( isset( $field_data['options'] ) && ! $this->import_options_field( $field_name, $field_data['options'], $original_data, $group_key ) ) {
				return false;
			}

			// Import users fields.
			if ( isset( $field_data['users'] ) && ! $this->import_user_fields( $field_name, $field_data['users'], $original_data, $group_key ) ) {
				return false;
			}

			// Import comment fields.
			if ( isset( $field_data['comments'] ) && ! $this->import_comment_fields( $field_name, $field_data['comments'], $original_data, $group_key ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Import a field for an options page.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name    The field name.
	 * @param mixed  $value         The field value.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @param string $group_key     The field group key.
	 * @return bool True on success, false on failure.
	 */
	private function import_options_field( $field_name, $value, &$original_data, $group_key ) {
		$field_objects = get_field_objects( 'option' );
		$field_object  = $field_objects[ $field_name ] ?? array();

		$existing_value = get_field( $field_name, 'option', $this->processor->maybe_format_value( $field_object ?? array() ) );

		$original_data[ $group_key ][ $field_name ]['options'] = $existing_value;

		/**
		 * Filters the value of a field before importing.
		 *
		 * This filter allows modification of field values before they are imported.
		 * It can be used to adjust, validate, or transform the data as needed.
		 *
		 * @since 1.2.0
		 *
		 * @param mixed  $value          The field value to be imported.
		 * @param mixed  $existing_value The existing value of the field in the database.
		 * @param string $field          The field configuration array.
		 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
		 * @param string $group_key      The key of the field group to which this field belongs.
		 * @param array  $context_data   Additional context-specific data.
		 * @return mixed The filtered field value to be imported.
		 */
		$value = apply_filters(
			'custom_fields_snapshots_import_field_value',
			$value,
			$existing_value,
			$field_object,
			'option',
			$group_key,
			array()
		);

		if ( $existing_value === $value ) {
			/* translators: %s: option name */
			$this->logger->log( 'info', sprintf( __( 'Option "%s" has the same value. Skipping update.', 'custom-fields-snapshots' ), $field_name ) );

			return true;
		}

		$update_result = update_field( $field_name, $value, 'option' );

		if ( false === $update_result && $this->verify_update_failed( $field_object, $existing_value, 'option' ) ) {
			/* translators: %s: option name */
			$this->logger->log( 'error', sprintf( __( 'Failed to update option "%s".', 'custom-fields-snapshots' ), $field_name ) );

			/**
			 * Fires when a field import fails.
			 *
			 * This action is triggered when an attempt to import a field fails.
			 * It provides information about the field, the attempted value, the existing value,
			 * and the context of the import.
			 *
			 * @since 1.1.0
			 *
			 * @param string $field          The field configuration array.
			 * @param mixed  $value          The value that was attempted to be imported.
			 * @param mixed  $existing_value The current value of the field before the import attempt.
			 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
			 * @param string $group_key      The key of the field group to which this field belongs.
			 * @param array  $context_data   Additional context-specific data.
			 */
			do_action( 'custom_fields_snapshots_import_field_failed', $field_object, $value, $existing_value, 'option', $group_key, array() );

			return false;
		}

		/* translators: %s: field name */
		$this->logger->log( 'success', sprintf( __( 'Successfully updated option "%s"', 'custom-fields-snapshots' ), $field_name ) );

		/**
		 * Fires when a field import is successful.
		 *
		 * This action is triggered after a field has been successfully imported.
		 * It provides information about the imported field, including its name,
		 * value, group key, and the context of the import.
		 *
		 * @since 1.1.0
		 *
		 * @param string $field     The field configuration array.
		 * @param mixed  $value     The value that was imported for the field.
		 * @param string $group_key The key of the field group to which this field belongs.
		 * @param string $context   The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
		 */
		do_action( 'custom_fields_snapshots_import_field_complete', $field_object, $value, 'option', $group_key, array() );

		return true;
	}

	/**
	 * Import a field for a post.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name    The field name.
	 * @param int    $post_id       The post ID.
	 * @param mixed  $value         The field value.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @param string $group_key     The field group key.
	 * @param string $post_type     The post type.
	 * @return bool True on success, false on failure.
	 */
	private function import_post_field( $field_name, $post_id, $value, &$original_data, $group_key, $post_type ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			/* translators: %1$s: field name, %2$s: group key */
			$this->logger->log( 'error', sprintf( __( 'Invalid post ID for field "%1$s" in group "%2$s"', 'custom-fields-snapshots' ), $field_name, $group_key ) );

			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			/* translators: %1$d: post ID, %2$s: field name */
			$this->logger->log( 'error', sprintf( __( 'Permission denied for post ID %1$d. Cannot edit field "%2$s"', 'custom-fields-snapshots' ), $post_id, $field_name ) );

			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			/* translators: %1$d: post ID, %2$s: field name */
			$this->logger->log( 'error', sprintf( __( 'Post with ID %1$d does not exist. Cannot update field "%2$s"', 'custom-fields-snapshots' ), $post_id, $field_name ) );

			return false;
		}

		$field_object = get_field_object( $field_name, $post_id );

		// Check if the value is different before updating.
		$existing_value = get_field( $field_name, $post_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

		// Store original value for potential rollback.
		$original_data[ $group_key ][ $field_name ]['post_types'][ $post_type ][ $post_id ] = $existing_value;

		/**
		 * Filters the value of a field before importing.
		 *
		 * This filter allows modification of field values before they are imported.
		 * It can be used to adjust, validate, or transform the data as needed.
		 *
		 * @since 1.2.0
		 *
		 * @param mixed  $value          The field value to be imported.
		 * @param mixed  $existing_value The existing value of the field in the database.
		 * @param string $field          The field configuration array.
		 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
		 * @param string $group_key      The key of the field group to which this field belongs.
		 * @param array  $context_data   Additional context-specific data.
		 * @return mixed The filtered field value to be imported.
		 */
		$value = apply_filters(
			'custom_fields_snapshots_import_field_value',
			$value,
			$existing_value,
			$field_object,
			'post',
			$group_key,
			array(
				'post_type' => $post_type,
				'post_id'   => $post_id,
			)
		);

		if ( $existing_value === $value ) {
			/* translators: %1$s: field name, %2$d: post ID */
			$this->logger->log( 'info', sprintf( __( 'Field "%1$s" for post ID %2$d has the same value. Skipping update.', 'custom-fields-snapshots' ), $field_name, $post_id ) );
			return true;
		}

		$update_result = update_field( $field_name, $value, $post_id );

		if ( false === $update_result && $this->verify_update_failed( $field_object, $existing_value, $post_id ) ) {
			/* translators: %1$s: field name, %2$d: post ID */
			$this->logger->log( 'error', sprintf( __( 'Failed to update field "%1$s" for post ID %2$d.', 'custom-fields-snapshots' ), $field_name, $post_id ) );

			/**
			 * Fires when a post field import fails.
			 *
			 * @since 1.2.0
			 *
			 * @param string $field          The field configuration array.
			 * @param mixed  $value          The field value.
			 * @param mixed  $existing_value The current value of the field before the import attempt.
			 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
			 * @param string $group_key      The field group key.
			 * @param array  $context_data   Additional context-specific data.
			 */
			do_action(
				'custom_fields_snapshots_import_field_failed',
				$field_object,
				$value,
				$existing_value,
				'post',
				$group_key,
				array(
					'post_id'   => $post_id,
					'post_type' => $post_type,
				)
			);

			return false;
		}

		/* translators: %1$s: field name, %2$d: post ID */
		$this->logger->log( 'success', sprintf( __( 'Successfully updated field "%1$s" for post ID %2$d.', 'custom-fields-snapshots' ), $field_name, $post_id ) );

		/**
		 * Fires when a field import is successful.
		 *
		 * @since 1.0.0
		 *
		 * @param string $field        The field configuration array.
		 * @param mixed  $value        The field value.
		 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
		 * @param string $group_key    The field group key.
		 * @param array  $context_data Additional context-specific data.
		 */
		do_action(
			'custom_fields_snapshots_import_field_complete',
			$field_object,
			$value,
			'post',
			$group_key,
			array(
				'post_id'   => $post_id,
				'post_type' => $post_type,
			)
		);

		return true;
	}

	/**
	 * Import fields for users.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name    The field name.
	 * @param array  $user_data     The user data to import.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @param string $group_key     The field group key.
	 * @return bool True on success, false on failure.
	 */
	private function import_user_fields( $field_name, $user_data, &$original_data, $group_key ) {
		foreach ( $user_data as $user_id => $value ) {
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				$this->logger->log(
					'error',
					/* translators: %1$s: field name, %2$s: group key */
					sprintf( __( 'Invalid user ID for field "%1$s" in group "%2$s"', 'custom-fields-snapshots' ), $field_name, $group_key )
				);
				return false;
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				$this->logger->log(
					'error',
					/* translators: %1$d: user ID, %2$s: field name */
					sprintf( __( 'User with ID %1$d does not exist. Cannot update field "%2$s"', 'custom-fields-snapshots' ), $user_id, $field_name )
				);
				return false;
			}

			$field_object   = get_field_object( $field_name, 'user_' . $user_id );
			$existing_value = get_field( $field_name, 'user_' . $user_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

			$original_data[ $group_key ][ $field_name ]['users'][ $user_id ] = $existing_value;

			/**
			 * Filters the value of a field before importing.
			 *
			 * This filter allows modification of field values before they are imported.
			 * It can be used to adjust, validate, or transform the data as needed.
			 *
			 * @since 1.2.0
			 *
			 * @param mixed  $value          The field value to be imported.
			 * @param mixed  $existing_value The existing value of the field in the database.
			 * @param string $field          The field configuration array.
			 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
			 * @param string $group_key      The key of the field group to which this field belongs.
			 * @param array  $context_data   Additional context-specific data.
			 * @return mixed The filtered field value to be imported.
			 */
			$value = apply_filters(
				'custom_fields_snapshots_import_field_value',
				$value,
				$existing_value,
				$field_object,
				'user',
				$group_key,
				array(
					'user_id' => $user_id,
				)
			);

			if ( $existing_value === $value ) {
				$this->logger->log(
					'info',
					/* translators: %1$s: field name, %2$d: user ID */
					sprintf( __( 'Field "%1$s" for user ID %2$d has the same value. Skipping update.', 'custom-fields-snapshots' ), $field_name, $user_id )
				);
				continue;
			}

			$update_result = update_field( $field_name, $value, 'user_' . $user_id );

			if ( false === $update_result && $this->verify_update_failed( $field_object, $existing_value, 'user_' . $user_id ) ) {
				$this->logger->log(
					'error',
					/* translators: %1$s: field name, %2$d: user ID */
					sprintf( __( 'Failed to update field "%1$s" for user ID %2$d.', 'custom-fields-snapshots' ), $field_name, $user_id )
				);

				/**
				 * Fires when a user field import fails.
				 *
				 * This action is triggered when an attempt to import a field for a user fails.
				 * It provides information about the field, the attempted value, the existing value,
				 * and the user context.
				 *
				 * @since 1.1.0
				 *
				 * @param string $field          The field configuration array.
				 * @param mixed  $value          The value that was attempted to be imported.
				 * @param mixed  $existing_value The current value of the field before the import attempt.
				 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key      The key of the field group to which this field belongs.
				 * @param array  $context_data   Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_import_field_failed',
					$field_object,
					$value,
					$existing_value,
					'user',
					$group_key,
					array(
						'user_id' => $user_id,
					)
				);

				return false;
			}

			$this->logger->log(
				'success',
				/* translators: %1$s: field name, %2$d: user ID */
				sprintf( __( 'Successfully updated field "%1$s" for user ID %2$d.', 'custom-fields-snapshots' ), $field_name, $user_id )
			);

			/**
			 * Fires when a field import is successful.
			 *
			 * This action is triggered after a field has been successfully imported for a user.
			 * It provides information about the imported field, including its name, value,
			 * the user it was imported for, and the context of the import.
			 *
			 * @since 1.1.0
			 *
			 * @param string $field        The field configuration array.
			 * @param mixed  $value        The value that was imported for the field.
			 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
			 * @param string $group_key    The key of the field group to which this field belongs.
			 * @param array  $context_data Additional context-specific data.
			 */
			do_action(
				'custom_fields_snapshots_import_field_complete',
				$field_object,
				$value,
				'user',
				$group_key,
				array(
					'user_id' => $user_id,
				)
			);
		}

		return true;
	}

	/**
	 * Import fields for taxonomies.
	 *
	 * @since 1.2.0
	 *
	 * @param string $field_name    The field name.
	 * @param array  $taxonomies    The taxonomy data to import.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @param string $group_key     The field group key.
	 * @return bool True on success, false on failure.
	 */
	private function import_taxonomy_fields( $field_name, $taxonomies, &$original_data, $group_key ) {
		foreach ( $taxonomies as $taxonomy => $terms ) {
			foreach ( $terms as $term_id => $value ) {
				$term_id = absint( $term_id );

				if ( ! $term_id ) {
					$this->logger->log(
						'error',
						/* translators: %1$s: field name, %2$s: taxonomy, %3$s: group key */
						sprintf( __( 'Invalid term ID for field "%1$s" in taxonomy "%2$s" in group "%3$s"', 'custom-fields-snapshots' ), $field_name, $taxonomy, $group_key )
					);
					return false;
				}

				$field_object   = get_field_object( $field_name, $taxonomy . '_' . $term_id );
				$existing_value = get_field( $field_name, $taxonomy . '_' . $term_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

				$original_data[ $group_key ][ $field_name ]['taxonomies'][ $taxonomy ][ $term_id ] = $existing_value;

				/**
				 * Filters the value of a field before importing.
				 *
				 * This filter allows modification of field values before they are imported.
				 * It can be used to adjust, validate, or transform the data as needed.
				 *
				 * @since 1.2.0
				 *
				 * @param mixed  $value          The field value to be imported.
				 * @param mixed  $existing_value The existing value of the field in the database.
				 * @param string $field          The field configuration array.
				 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key      The key of the field group to which this field belongs.
				 * @param array  $context_data   Additional context-specific data.
				 * @return mixed The filtered field value to be imported.
				 */
				$value = apply_filters(
					'custom_fields_snapshots_import_field_value',
					$value,
					$existing_value,
					$field_object,
					'taxonomy',
					$group_key,
					array(
						'taxonomy' => $taxonomy,
						'term_id'  => $term_id,
					)
				);

				if ( $existing_value === $value ) {
					$this->logger->log(
						'info',
						/* translators: %1$s: field name, %2$s: taxonomy, %3$d: term ID */
						sprintf( __( 'Field "%1$s" for %2$s term ID %3$d has the same value. Skipping update.', 'custom-fields-snapshots' ), $field_name, $taxonomy, $term_id )
					);
					continue;
				}

				$update_result = update_field( $field_name, $value, $taxonomy . '_' . $term_id );

				if ( false === $update_result && $this->verify_update_failed( $field_object, $existing_value, $taxonomy . '_' . $term_id ) ) {
					$this->logger->log(
						'error',
						/* translators: %1$s: field name, %2$s: taxonomy, %3$d: term ID */
						sprintf( __( 'Failed to update field "%1$s" for %2$s term ID %3$d.', 'custom-fields-snapshots' ), $field_name, $taxonomy, $term_id )
					);

					/**
					 * Fires when a taxonomy field import fails.
					 *
					 * This action is triggered when an attempt to import a field for a taxonomy term fails.
					 * It provides information about the field, the attempted value, the existing value,
					 * and the taxonomy context.
					 *
					 * @since 1.1.0
					 *
					 * @param string $field          The field configuration array.
					 * @param mixed  $value          The value that was attempted to be imported.
					 * @param mixed  $existing_value The current value of the field before the import attempt.
					 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
					 * @param string $group_key      The key of the field group to which this field belongs.
					 * @param array  $context_data   Additional context-specific data.
					 */
					do_action(
						'custom_fields_snapshots_import_field_failed',
						$field_object,
						$value,
						$existing_value,
						'taxonomy',
						$group_key,
						array(
							'term_id'  => $term_id,
							'taxonomy' => $taxonomy,
						)
					);

					return false;
				}

				$this->logger->log(
					'success',
					/* translators: %1$s: field name, %2$s: taxonomy, %3$d: term ID */
					sprintf( __( 'Successfully updated field "%1$s" for %2$s term ID %3$d.', 'custom-fields-snapshots' ), $field_name, $taxonomy, $term_id )
				);

				/**
				 * Fires when a field import is successful.
				 *
				 * This action is triggered after a field has been successfully imported for a taxonomy term.
				 * It provides information about the imported field, including its name, value,
				 * the taxonomy and term it was imported for, and the context of the import.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field        The field configuration array.
				 * @param mixed  $value        The value that was imported for the field.
				 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key    The key of the field group to which this field belongs.
				 * @param array  $context_data Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_import_field_complete',
					$field_object,
					$value,
					'taxonomy',
					$group_key,
					array(
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Import fields for comments.
	 *
	 * @since 1.2.0
	 *
	 * @param string $field_name    The field name.
	 * @param array  $comments      The comment data to import, grouped by post type.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @param string $group_key     The field group key.
	 * @return bool True on success, false on failure.
	 */
	private function import_comment_fields( $field_name, $comments, &$original_data, $group_key ) {
		foreach ( $comments as $post_type => $comment_data ) {
			foreach ( $comment_data as $comment_id => $value ) {
				$comment_id = absint( $comment_id );
				if ( ! $comment_id ) {
					$this->logger->log(
						'error',
						/* translators: %1$s: field name, %2$s: group key, %3$s: post type */
						sprintf( __( 'Invalid comment ID for field "%1$s" in group "%2$s" for post type "%3$s"', 'custom-fields-snapshots' ), $field_name, $group_key, $post_type )
					);
					return false;
				}

				$field_object   = get_field_object( $field_name, 'comment_' . $comment_id );
				$existing_value = get_field( $field_name, 'comment_' . $comment_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

				$original_data[ $group_key ][ $field_name ]['comments'][ $post_type ][ $comment_id ] = $existing_value;

				/**
				 * Filters the value of a field before importing.
				 *
				 * This filter allows modification of field values before they are imported.
				 * It can be used to adjust, validate, or transform the data as needed.
				 *
				 * @since 1.2.0
				 *
				 * @param mixed  $value          The field value to be imported.
				 * @param mixed  $existing_value The existing value of the field in the database.
				 * @param string $field          The field configuration array.
				 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key      The key of the field group to which this field belongs.
				 * @param array  $context_data   Additional context-specific data.
				 * @return mixed The filtered field value to be imported.
				 */
				$value = apply_filters(
					'custom_fields_snapshots_import_field_value',
					$value,
					$existing_value,
					$field_object,
					'comment',
					$group_key,
					array(
						'comment_id' => $comment_id,
						'post_type'  => $post_type,
					)
				);

				if ( $existing_value === $value ) {
					$this->logger->log(
						'info',
						/* translators: %1$s: field name, %2$d: comment ID, %3$s: post type */
						sprintf( __( 'Field "%1$s" for comment ID %2$d in post type "%3$s" has the same value. Skipping update.', 'custom-fields-snapshots' ), $field_name, $comment_id, $post_type )
					);
					continue;
				}

				$update_result = update_field( $field_name, $value, 'comment_' . $comment_id );

				if ( false === $update_result && $this->verify_update_failed( $field_object, $existing_value, 'comment_' . $comment_id ) ) {
					$this->logger->log(
						'error',
						/* translators: %1$s: field name, %2$d: comment ID, %3$s: post type */
						sprintf( __( 'Failed to update field "%1$s" for comment ID %2$d in post type "%3$s".', 'custom-fields-snapshots' ), $field_name, $comment_id, $post_type )
					);

					/**
					 * Fires when a field import fails.
					 *
					 * This action is triggered when an attempt to import a field for a comment fails.
					 * It provides information about the field, the attempted value, the existing value,
					 * and the context of the import.
					 *
					 * @since 1.1.0
					 *
					 * @param string $field          The field configuration array.
					 * @param mixed  $value          The value that was attempted to be imported.
					 * @param mixed  $existing_value The current value of the field before the import attempt.
					 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
					 * @param string $group_key      The key of the field group to which this field belongs.
					 * @param string $context_data   Additional context-specific data.
					 */
					do_action(
						'custom_fields_snapshots_import_field_failed',
						$field_object,
						$value,
						$existing_value,
						'comment',
						$group_key,
						array(
							'comment_id' => $comment_id,
							'post_type'  => $post_type,
						)
					);

					return false;
				}

				$this->logger->log(
					'success',
					/* translators: %1$s: field name, %2$d: comment ID, %3$s: post type */
					sprintf( __( 'Successfully updated field "%1$s" for comment ID %2$d in post type "%3$s".', 'custom-fields-snapshots' ), $field_name, $comment_id, $post_type )
				);

				/**
				 * Fires when a field import is successful.
				 *
				 * This action is triggered after a field has been successfully imported for a comment.
				 * It provides information about the imported field, including its name, value,
				 * the comment it was imported for, and the context of the import.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field        The field configuration array.
				 * @param mixed  $value        The value that was imported for the field.
				 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key    The key of the field group to which this field belongs.
				 * @param array  $context_data Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_import_field_complete',
					$field_object,
					$value,
					'comment',
					$group_key,
					array(
						'comment_id' => $comment_id,
						'post_type'  => $post_type,
					)
				);

			}
		}

		return true;
	}

	/**
	 * Rollback changes made during import.
	 *
	 * @since 1.0.0
	 *
	 * @param array $original_data The original data to restore.
	 */
	private function rollback_changes( $original_data ) {
		foreach ( $original_data as $group_key => $fields ) {
			foreach ( $fields as $field_name => $field_data ) {
				if ( isset( $field_data['post_types'] ) ) {
					// Handle post types.
					foreach ( $field_data['post_types'] as $post_type => $posts ) {
						foreach ( $posts as $post_id => $value ) {
							$this->rollback_post_field( $field_name, $post_id, $value, $post_type );
						}
					}
				}

				if ( isset( $field_data['options'] ) ) {
					// Handle options.
					$this->rollback_option( $field_name, $field_data['options'] );
				}

				if ( isset( $field_data['taxonomies'] ) ) {
					// Handle taxonomies.
					foreach ( $field_data['taxonomies'] as $taxonomy => $terms ) {
						foreach ( $terms as $term_id => $value ) {
							$this->rollback_taxonomy_field( $field_name, $term_id, $value, $taxonomy );
						}
					}
				}

				if ( isset( $field_data['comments'] ) ) {
					// Handle comments.
					foreach ( $field_data['comments'] as $post_type => $comments ) {
						foreach ( $comments as $comment_id => $value ) {
							$this->rollback_comment_field( $field_name, $comment_id, $value, $post_type );
						}
					}
				}

				if ( isset( $field_data['users'] ) ) {
					// Handle users.
					foreach ( $field_data['users'] as $user_id => $value ) {
						$this->rollback_user_field( $field_name, $user_id, $value );
					}
				}
			}
		}
	}

	/**
	 * Rollback a post field.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name The field name.
	 * @param int    $post_id    The post ID.
	 * @param mixed  $value      The original value to restore.
	 * @param string $post_type  The post type.
	 */
	private function rollback_post_field( $field_name, $post_id, $value, $post_type ) {
		$field_object   = get_field_object( $field_name, $post_id );
		$existing_value = get_field( $field_name, $post_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

		if ( $value !== $existing_value ) {
			$rollback_result = update_field( $field_name, $value, $post_id );

			if ( false === $rollback_result && $this->verify_update_failed( $field_object, $existing_value, $post_id ) ) {
				/* translators: %1$s: field name, %2$s: post type, %3$d: post ID */
				$this->logger->log( 'error', sprintf( __( 'Failed to rollback field "%1$s" for %2$s ID %3$d.', 'custom-fields-snapshots' ), $field_name, $post_type, $post_id ) );

				/**
				 * Fires when a post field rollback fails.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field          The field configuration array.
				 * @param mixed  $value          The value that was attempted to be rolled back to.
				 * @param mixed  $existing_value The current value of the field before the rollback attempt.
				 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key      The key of the field group to which this field belongs.
				 * @param array  $context_data   Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_failed',
					$field_object,
					$value,
					$existing_value,
					'post',
					$group_key,
					array(
						'post_id'   => $post_id,
						'post_type' => $post_type,
					)
				);
			} else {
				/* translators: %1$s: field name, %2$s: post type, %3$d: post ID */
				$this->logger->log( 'info', sprintf( __( 'Rolled back field "%1$s" for %2$s ID %3$d.', 'custom-fields-snapshots' ), $field_name, $post_type, $post_id ) );

				/**
				 * Fires when a post field rollback is successful.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field        The field configuration array.
				 * @param mixed  $value        The value that was attempted to be rolled back to.
				 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key    The key of the field group to which this field belongs.
				 * @param array  $context_data Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_complete',
					$field_object,
					$value,
					'post',
					$group_key,
					array(
						'post_id'   => $post_id,
						'post_type' => $post_type,
					)
				);
			}
		}
	}

	/**
	 * Rollback a taxonomy field.
	 *
	 * @since 1.2.0
	 *
	 * @param string $field_name The name of the field.
	 * @param int    $term_id    The ID of the term.
	 * @param mixed  $value      The original value to restore.
	 * @param string $taxonomy   The taxonomy name.
	 */
	private function rollback_taxonomy_field( $field_name, $term_id, $value, $taxonomy ) {
		$term_id = absint( $term_id );
		if ( ! $term_id ) {
			return;
		}

		$field_object   = get_field_object( $field_name, $taxonomy . '_' . $term_id );
		$existing_value = get_field( $field_name, $taxonomy . '_' . $term_id, $this->processor->maybe_format_value( $field_object ) );

		if ( $value !== $existing_value ) {
			$rollback_result = update_field( $field_name, $value, $taxonomy . '_' . $term_id );

			if ( false === $rollback_result && $this->verify_update_failed( $field_object, $existing_value, $taxonomy . '_' . $term_id ) ) {
				$this->logger->log(
					'error',
					/* translators: %1$s: field name, %2$s: taxonomy name, %3$d: term ID */
					sprintf( __( 'Failed to rollback field "%1$s" for %2$s term ID %3$d', 'custom-fields-snapshots' ), $field_name, $taxonomy, $term_id )
				);

				/**
				 * Fires when a taxonomy field rollback fails.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field          The field configuration array.
				 * @param mixed  $value          The value that was attempted to be rolled back to.
				 * @param mixed  $existing_value The current value of the field before the rollback attempt.
				 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key      The key of the field group to which this field belongs.
				 * @param array  $context_data   Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_failed',
					$field_object,
					$value,
					$existing_value,
					'taxonomy',
					$group_key,
					array(
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					)
				);
			} else {
				$this->logger->log(
					'info',
					/* translators: %1$s: field name, %2$s: taxonomy name, %3$d: term ID */
					sprintf( __( 'Rolled back field "%1$s" for %2$s term ID %3$d', 'custom-fields-snapshots' ), $field_name, $taxonomy, $term_id )
				);

				/**
				 * Fires when a taxonomy field rollback is successful.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field        The field configuration array.
				 * @param mixed  $value        The value that was attempted to be rolled back to.
				 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key    The key of the field group to which this field belongs.
				 * @param array  $context_data Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_complete',
					$field_object,
					$value,
					'taxonomy',
					$group_key,
					array(
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					)
				);
			}
		}
	}

	/**
	 * Rollback an option field.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name The field name.
	 * @param mixed  $value      The original value to restore.
	 */
	private function rollback_option( $field_name, $value ) {
		$field_objects  = get_field_objects( 'option' );
		$field_object   = $field_objects[ $field_name ] ?? array();
		$existing_value = get_field( $field_name, 'option', $this->processor->maybe_format_value( $field_object ) );

		if ( $value !== $existing_value ) {
			$rollback_result = update_field( $field_name, $value, 'option' );

			if ( false === $rollback_result && $this->verify_update_failed( $field_object, $existing_value, 'option' ) ) {
				/* translators: %s: option name */
				$this->logger->log( 'error', sprintf( __( 'Failed to rollback option "%s"', 'custom-fields-snapshots' ), $field_name ) );

				/**
				 * Fires when a option field rollback fails.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field          The field configuration array.
				 * @param mixed  $value          The value that was attempted to be rolled back to.
				 * @param mixed  $existing_value The current value of the field before the rollback attempt.
				 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key      The key of the field group to which this field belongs.
				 * @param array  $context_data   Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_failed',
					$field_object,
					$value,
					$existing_value,
					'option',
					$group_key,
					array()
				);
			} else {
				/* translators: %s: option name */
				$this->logger->log( 'info', sprintf( __( 'Rolled back option "%s"', 'custom-fields-snapshots' ), $field_name ) );

				/**
				 * Fires when a option field rollback is successful.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field        The field configuration array.
				 * @param mixed  $value        The value that was attempted to be rolled back to.
				 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key    The key of the field group to which this field belongs.
				 * @param array  $context_data Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_complete',
					$field_object,
					$value,
					'option',
					$group_key,
					array()
				);
			}
		}
	}

	/**
	 * Rollback a user field.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name The field name.
	 * @param int    $user_id    The user ID.
	 * @param mixed  $value      The original value to restore.
	 */
	private function rollback_user_field( $field_name, $user_id, $value ) {
		$field_object   = get_field_object( $field_name, 'user_' . $user_id );
		$existing_value = get_field( $field_name, 'user_' . $user_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

		if ( $value !== $existing_value ) {
			$rollback_result = update_field( $field_name, $value, 'user_' . $user_id );

			if ( false === $rollback_result && $this->verify_update_failed( $field_object, $existing_value, 'user_' . $user_id ) ) {
				/* translators: %1$s: field name, %2$d: user ID */
				$this->logger->log( 'error', sprintf( __( 'Failed to rollback field "%1$s" for user ID %2$d.', 'custom-fields-snapshots' ), $field_name, $user_id ) );

				/**
				 * Fires when a user field rollback fails.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field          The field configuration array.
				 * @param mixed  $value          The value that was attempted to be rolled back to.
				 * @param mixed  $existing_value The current value of the field before the rollback attempt.
				 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key      The key of the field group to which this field belongs.
				 * @param array  $context_data   Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_failed',
					$field_object,
					$value,
					$existing_value,
					'user',
					$group_key,
					array(
						'user_id' => $user_id,
					)
				);
			} else {
				/* translators: %1$s: field name, %2$d: user ID */
				$this->logger->log( 'info', sprintf( __( 'Rolled back field "%1$s" for user ID %2$d.', 'custom-fields-snapshots' ), $field_name, $user_id ) );

				/**
				 * Fires when a user field rollback is successful.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field        The field configuration array.
				 * @param mixed  $value        The value that was attempted to be rolled back to.
				 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key    The key of the field group to which this field belongs.
				 * @param array  $context_data Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_complete',
					$field_object,
					$value,
					'user',
					$group_key,
					array(
						'user_id' => $user_id,
					)
				);
			}
		}
	}

	/**
	 * Rollback a comment field.
	 *
	 * @since 1.2.0
	 *
	 * @param string $field_name The name of the field.
	 * @param int    $comment_id The ID of the comment.
	 * @param mixed  $value      The original value to restore.
	 * @param string $post_type  The post type of the comment's post.
	 */
	private function rollback_comment_field( $field_name, $comment_id, $value, $post_type ) {
		$comment_id = absint( $comment_id );

		if ( ! $comment_id ) {
			return;
		}

		$field_object   = get_field_object( $field_name, 'comment_' . $comment_id );
		$existing_value = get_field( $field_name, 'comment_' . $comment_id, $this->processor->maybe_format_value( $field_object ) );

		if ( $value !== $existing_value ) {
			$rollback_result = update_field( $field_name, $value, 'comment_' . $comment_id );

			if ( false === $rollback_result && $this->verify_update_failed( $field_object, $existing_value, 'comment_' . $comment_id ) ) {
				$this->logger->log(
					'error',
					/* translators: %1$s: field name, %2$d: comment ID, %3$s: post type */
					sprintf( __( 'Failed to rollback field "%1$s" for comment ID %2$d (Post Type: %3$s)', 'custom-fields-snapshots' ), $field_name, $comment_id, $post_type )
				);

				/**
				 * Fires when a comment field rollback fails.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field          The field configuration array.
				 * @param mixed  $value          The value that was attempted to be rolled back to.
				 * @param mixed  $existing_value The current value of the field before the rollback attempt.
				 * @param string $context        The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key      The key of the field group to which this field belongs.
				 * @param array  $context_data   Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_failed',
					$field_object,
					$value,
					$existing_value,
					'comment',
					$group_key,
					array(
						'comment_id' => $comment_id,
						'post_type'  => $post_type,
					)
				);
			} else {
				$this->logger->log(
					'info',
					/* translators: %1$s: field name, %2$d: comment ID, %3$s: post type */
					sprintf( __( 'Rolled back field "%1$s" for comment ID %2$d (Post Type: %3$s)', 'custom-fields-snapshots' ), $field_name, $comment_id, $post_type )
				);

				/**
				 * Fires when a comment field rollback is successful.
				 *
				 * @since 1.2.0
				 *
				 * @param string $field        The field configuration array.
				 * @param mixed  $value        The value that was attempted to be rolled back to.
				 * @param string $context      The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'.
				 * @param string $group_key    The key of the field group to which this field belongs.
				 * @param array  $context_data Additional context-specific data.
				 */
				do_action(
					'custom_fields_snapshots_rollback_field_complete',
					$field_object,
					$value,
					'comment',
					$group_key,
					array(
						'comment_id' => $comment_id,
						'post_type'  => $post_type,
					)
				);
			}
		}
	}

	/**
	 * Verify if the update operation actually failed.
	 *
	 * This function is used to double-check if an update operation failed,
	 * as sometimes update_field() returns false even when the data was successfully updated.
	 * It compares the current field value with the previously existing value to determine
	 * if an actual change occurred.
	 *
	 * @since 1.0.0
	 *
	 * @param array|string    $field          The field object or field name.
	 * @param mixed           $existing_value The value of the field before the update attempt.
	 * @param int|string|null $object_id      The object ID. This can be:
	 *                                        - Post ID for posts
	 *                                        - Term ID prefixed with '{taxonomy}_' for taxonomies
	 *                                        - User ID prefixed with 'user_' for users
	 *                                        - Comment ID prefixed with 'comment_' for comments
	 *                                        - 'option' or 'options' for option fields.
	 *
	 * @return bool True if the update failed (values are the same), false if it succeeded (values differ).
	 */
	private function verify_update_failed( $field, $existing_value, $object_id = null ) {
		$field_name   = is_array( $field ) ? $field['name'] : $field;
		$field_object = is_array( $field ) ? $field : null;

		if ( null === $field_object ) {
			if ( 'option' === $object_id || 'options' === $object_id ) {
				$field_objects = get_field_objects( $object_id );
				$field_object  = $field_objects[ $field_name ] ?? array();
			} else {
				$field_object = get_field_object( $field_name, $object_id );
			}
		}

		return get_field( $field_name, $object_id, $this->processor->maybe_format_value( $field_object ?? array() ) ) === $existing_value;
	}
}
