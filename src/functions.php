<?php
/**
 * Functions for Soft Transients
 *
 * @package wp-soft-transients
 */

declare( strict_types = 1 );
namespace Alley\WP\SoftTransients;

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
function get_soft_transient( string $transient_key ) {
	$transient = get_transient( $transient_key );
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
			if ( ! empty( $transient['action'] ) ) {
				$action = $transient['action'];
			} else {
				$action = 'transient_refresh_' . $transient_key;
			}

			// Schedule the update action.
			wp_schedule_single_event( time(), $action, [ $transient_key ] );
			$transient['status'] = 'loading';

			// Update the transient to indicate that we've scheduled a reload.
			set_transient( $transient_key, $transient );
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
 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
 * @param mixed  $value Transient value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
 * @param int    $expiration Optional. Time until expiration in seconds, default 0.
 * @return bool False if value was not set and true if value was set.
 */
function set_soft_transient( string $transient_key, $value, int $expiration = 0 ) {
	if ( ! $expiration ) {
		return set_transient( $transient_key, $value );
	}
	$data = [
		'expiration' => $expiration + time(),
		'data'       => $value,
		'status'     => 'ok',
		'action'     => null,
	];

	return set_transient( $transient_key, $data );
}


/**
 * Delete a "soft" transient. Will also unschedule the reload event if one is
 * in queue.
 *
 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
 * @return bool true if successful, false otherwise
 */
function delete_soft_transient( string $transient_key ) {
	$action = 'transient_refresh_' . $transient_key;

	$timestamp = wp_next_scheduled( $action, [ $transient_key ] );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $action, [ $transient_key ] );
	}

	return delete_transient( $transient_key );
}
