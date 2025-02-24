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
	 * The action to schedule for refreshing the transient.
	 *
	 * @var null|string
	 */
	public ?string $action = null;

	public function __construct(
		public string $key
	) {
	}

	/**
	 * Get the action to schedule for refreshing the transient.
	 *
	 * @return string
	 */
	private function get_action(): string {
		return $this->action ?? 'transient_refresh_' . $this->key;
	}

	/**
	 * Set the action to schedule for refreshing the transient.
	 *
	 * @param string $action The action to schedule for refreshing the transient.
	 * @return self
	 */
	public function set_action( string $action ): self {
		$this->action = $action;
		return $this;
	}

	/**
	 * Get a "soft" transient. This is a transient which is updated via wp-cron,
	 * so refreshing the transient doesn't slow the request. Otherwise, this works
	 * just like get_transient().
	 *
	 * If the transient does not exist or does not have a value, then the return value
	 * will be false.
	 *
	 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
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
		if ( ! empty( $expiration ) && $expiration <= time() ) {
			// Cache needs to be updated.
			if ( ! empty( $transient['status'] ) && 'ok' === $transient['status'] ) {
				// Schedule the update action.
				\wp_schedule_single_event(
					time(),
					$transient['action'] ?? $this->get_action(),
					[ $this->key ]
				);
				$transient['status'] = 'loading';

				// Update the transient to indicate that we've scheduled a reload.
				\set_transient( $this->key, $transient );
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
	 * @param mixed  $value      Transient value. Must be serializable if non-scalar. Expected to
	 *                           not be SQL-escaped.
	 * @param int    $expiration Optional. Time until expiration in seconds, default 0.
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
			'action'     => $this->action,
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
		$action = $this->get_action();

		$timestamp = \wp_next_scheduled( $action, [ $this->key ] );
		if ( $timestamp ) {
			\wp_unschedule_event( $timestamp, $action, [ $this->key ] );
		}

		return \delete_transient( $this->key );
	}
}
