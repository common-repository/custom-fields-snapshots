<?php
/**
 * Custom Fields Snapshots Logger class
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
 * Custom Fields Snapshots Logger Class
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Stores log entries.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $log = array();

	/**
	 * Log an event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    The type of event ('success', 'error', 'info').
	 * @param string $message The event message.
	 */
	public function log( $type, $message ) {
		$this->log[] = array(
			'type'    => sanitize_key( $type ),
			'message' => wp_kses_post( $message ),
			'time'    => current_time( 'c' ),
		);
	}

	/**
	 * Get all log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return array The log entries.
	 */
	public function get_log() {
		$log_types = array(
			'info'    => esc_html__( 'INFO', 'custom-fields-snapshots' ), // translators: Log entry type.
			'error'   => esc_html__( 'ERROR', 'custom-fields-snapshots' ), // translators: Log entry type.
			'success' => esc_html__( 'SUCCESS', 'custom-fields-snapshots' ), // translators: Log entry type.
		);

		return array_map(
			function ( $entry ) use ( $log_types ) {
				$type      = isset( $log_types[ $entry['type'] ] ) ? $log_types[ $entry['type'] ] : $entry['type'];
				$type_span = wp_kses(
					sprintf(
						'<span class="log-%1$s">[%2$s]</span>',
						esc_attr( strtolower( $type ) ),
						esc_html( $type )
					),
					array(
						'span' => array(
							'class' => array(),
						),
					)
				);

				return sprintf(
					/* translators: %1$s: Log entry type (HTML), %2$s: Log entry time, %3$s: Log entry message */
					__( '%1$s %2$s: %3$s', 'custom-fields-snapshots' ),
					$type_span,
					esc_html( $entry['time'] ),
					esc_html( $entry['message'] )
				);
			},
			$this->log
		);
	}

	/**
	 * Clear all log entries.
	 *
	 * @since 1.0.0
	 */
	public function clear_log() {
		$this->log = array();
	}

	/**
	 * Get log entries as a formatted string.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted log entries.
	 */
	public function get_formatted_log() {
		$formatted_log = '';

		foreach ( $this->log as $entry ) {
			$formatted_log .= sprintf(
				"%s %s %s\n",
				esc_html( $entry['time'] ),
				esc_html( $entry['type'] ),
				esc_html( $entry['message'] )
			);
		}

		return $formatted_log;
	}
}
