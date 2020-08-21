<?php
/**
 * REST endpoint providing theme scan results.
 *
 * @package AMP
 * @since 2.1
 */

namespace AmpProject\AmpWP\Validation;

use AmpProject\AmpWP\BackgroundTask\CronBasedBackgroundTask;

/**
 * ValidationCron class.
 *
 * @since 2.1
 */
final class ValidationCron extends CronBasedBackgroundTask {
	const EVENT_NAME = 'amp_validate_urls';

	/**
	 * The key for the transient storing the number of URLs to offset by next time the cron task runs.
	 *
	 * @var string
	 */
	const OFFSET_KEY = 'amp_validate_urls_cron_offset';

	/**
	 * The length of time to store the offset transient.
	 *
	 * @var int
	 */
	const OFFSET_TRANSIENT_TIMEOUT = DAY_IN_SECONDS;

	/**
	 * Get the event name.
	 *
	 * This is the "slug" of the event, not the display name.
	 *
	 * Note: the event name should be prefixed to prevent naming collisions.
	 *
	 * @return string Name of the event.
	 */
	protected function get_event_name() {
		return self::EVENT_NAME;
	}

	/**
	 * Get the interval to use for the event.
	 *
	 * @return string An existing interval name.
	 */
	protected function get_interval() {
		return self::DEFAULT_INTERVAL_HOURLY;
	}

	/**
	 * Callback for the cron action.
	 */
	public function process() {
		$this->validate_urls();
	}

	/**
	 * Validates URLs beginning at the next offset.
	 *
	 * @param boolean $reset_if_no_urls_found If true and no URLs are found, the method will reset the offset to 0 and rerun.
	 */
	public function validate_urls( $reset_if_no_urls_found = true ) {
		$validation_url_provider = new ValidationURLProvider( 2, [], true );
		$offset                  = get_transient( self::OFFSET_KEY ) ?: 0;
		$urls                    = $validation_url_provider->get_urls( $offset );

		// Home (if supported), a date URL, and a search URL are always checked.
		$zero_url_count = 'posts' === get_option( 'show_on_front' ) && $validation_url_provider->is_template_supported( 'is_home' ) ? 3 : 2;

		// If no URLs are found beyond those that are checked every time, reset the offset to 0 and restart.
		if ( $reset_if_no_urls_found && count( $urls ) <= $zero_url_count ) {
			delete_transient( self::OFFSET_KEY );
			$this->validate_urls( false );
			return;
		}

		$validation_provider = new ValidationProvider();

		$validation_provider->with_lock(
			static function() use ( $validation_provider, $urls ) {
				foreach ( $urls as $url ) {
					$validation_provider->get_url_validation( $url['url'], $url['type'] );
				}
			}
		);

		set_transient( self::OFFSET_KEY, $offset + 2 );
	}
}
