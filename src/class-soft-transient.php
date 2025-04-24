<?php
/**
 * Class file for Soft_Transient
 *
 * @package wp-soft-transients
 */

declare( strict_types = 1 );

namespace Alley\WP\Soft_Transients;

/**
 * Soft Transient class.
 */
class Soft_Transient {
	/**
	 * The cron hook to schedule for refreshing the transient.
	 *
	 * @var null|string
	 */
	private ?string $cron_hook = null;

	/**
	 * The number of minutes to wait before retrying a "loading" transient.
	 *
	 * @var null|int
	 */
	private ?int $retry_minutes = 60;

	/**
	 * Arguments to pass to the cron event.
	 *
	 * @var array<mixed>
	 */
	private array $cron_args = [];

	/**
	 * Soft_Transient constructor.
	 *
	 * @param string $key The transient key.
	 */
	public function __construct(
		public string $key
	) {
	}

	/**
	 * Get the cron hook to schedule for refreshing the transient.
	 *
	 * @return string
	 */
	public function get_cron_hook(): string {
		return $this->cron_hook ?? 'transient_refresh_' . $this->key;
	}

	/**
	 * Set the cron hook to schedule for refreshing the transient.
	 *
	 * @param string $hook The cron hook to schedule for refreshing the transient.
	 * @return self
	 */
	public function set_cron_hook( string $hook ): self {
		$this->cron_hook = $hook;
		return $this;
	}

	/**
	 * Set the number of minutes to wait before retrying a "loading" transient.
	 *
	 * @param int $minutes The number of minutes to wait before retrying a "loading" transient.
	 * @return self
	 */
	public function set_retry_minutes( int $minutes ): self {
		$this->retry_minutes = $minutes;
		return $this;
	}

	/**
	 * Set the arguments to pass to the cron event.
	 *
	 * @param array<mixed> $args The arguments to pass to the cron event.
	 * @return self
	 */
	public function set_cron_args( array $args ): self {
		$this->cron_args = $args;
		return $this;
	}

	/**
	 * Get the arguments to pass to the cron event.
	 *
	 * @return array<mixed>
	 */
	public function get_cron_args(): array {
		return array_merge( [ $this->key ], $this->cron_args );
	}

	/**
	 * Get a "soft" transient. This is a transient which is updated via wp-cron,
	 * so refreshing the transient doesn't slow the request. Otherwise, this works
	 * just like get_transient().
	 *
	 * If the transient does not exist or does not have a value, then the return value
	 * will be false.
	 *
	 * @return mixed Value of transient.
	 */
	public function get() {
		$transient = \get_transient( $this->key );
		if ( false === $transient ) {
			return false;
		}

		// Ensure that this is a soft transient.
		if ( ! is_array( $transient ) || empty( $transient['expiration'] ) || ! array_key_exists( 'data', $transient ) ) {
			return $transient;
		}

		// Check if the transient is expired.
		$expiration = intval( $transient['expiration'] );
		var_dump( $this->retry_minutes );
		if ( ! empty( $expiration ) && $expiration <= time() ) {
			// Cache needs to be updated.
			if ( ! empty( $transient['status'] ) && (
				'ok' === $transient['status']
				|| (
					// If the transient is still loading, it's been a specified amount
					// of time (default one hour) since it expired, and
					// the cron event isn't scheduled, then schedule it again.
					'loading' === $transient['status']
					&& $expiration <= time() - $this->retry_minutes * MINUTE_IN_SECONDS
					&& ! \wp_next_scheduled( $this->get_cron_hook(), $this->get_cron_args() )
				)
			) ) {
				// Schedule the update event.
				\wp_schedule_single_event( time(), $this->get_cron_hook(), $this->get_cron_args() );
				$transient['status'] = 'loading';

				// Update the transient to indicate that we've scheduled a reload.
				\set_transient( $this->key, $transient );

				/**
				 * Fires when a soft transient is scheduled to refresh.
				 *
				 * @param string         $transient_key The key of the transient.
				 * @param Soft_Transient $transient     This Soft_Transient object.
				 */
				\do_action( 'soft_transients_expiration', $this->key, $this );
			}
		}

		return $transient['data'];
	}

	/**
	 * Set/update the value of a "soft" transient. This is a transient that, when
	 * it expires, will continue to return the value and refresh via wp-cron.
	 *
	 * You do not need to serialize values. If the value needs to be serialized, then
	 * it will be serialized before it is set.
	 *
	 * @param mixed $value      Transient value. Must be serializable if non-scalar. Expected to
	 *                          not be SQL-escaped.
	 * @param int   $expiration Optional. Time until expiration in seconds, default 0. If the value
	 *                          is 0, this will be a normal transient, not a soft transient.
	 * @return bool False if value was not set and true if value was set.
	 */
	public function set( $value, int $expiration = 0 ): bool {
		if ( ! $expiration ) {
			return \set_transient( $this->key, $value );
		}
		$data = [
			'expiration' => $expiration + time(),
			'data'       => $value,
			'status'     => 'ok',
		];

		return \set_transient( $this->key, $data );
	}

	/**
	 * Delete a "soft" transient. Will also unschedule the reload event if one is
	 * in queue.
	 *
	 * @return bool true if successful, false otherwise
	 */
	public function delete(): bool {
		$hook      = $this->get_cron_hook();
		$cron_args = $this->get_cron_args();

		$timestamp = \wp_next_scheduled( $hook, $cron_args );
		if ( $timestamp ) {
			\wp_unschedule_event( $timestamp, $hook, $cron_args );
		}

		return \delete_transient( $this->key );
	}
}
