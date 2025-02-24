<?php
/**
 * Functions for Soft Transients
 *
 * @package wp-soft-transients
 */

declare( strict_types = 1 );
namespace Alley\WP\Soft_Transients;

/**
 * Get a "soft" transient.
 *
 * @see Soft_Transient::get()
 *
 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
 * @return mixed Value of transient.
 */
function get_soft_transient( string $transient_key ) {
	return ( new Soft_Transient( $transient_key ) )->get();
}

/**
 * Set/update the value of a "soft" transient.
 *
 * @see Soft_Transient::set()
 *
 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
 * @param mixed  $value         Transient value. Must be serializable if non-scalar. Expected to not
 *                              be SQL-escaped.
 * @param int    $expiration    Optional. Time until expiration in seconds, default 0.
 * @return bool False if value was not set and true if value was set.
 */
function set_soft_transient( string $transient_key, $value, int $expiration = 0 ) {
	return ( new Soft_Transient( $transient_key ) )->set( $value, $expiration );
}


/**
 * Delete a "soft" transient. Will also unschedule the reload event if one is in queue.
 *
 * @see Soft_Transient::delete()
 *
 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
 * @return bool true if successful, false otherwise
 */
function delete_soft_transient( string $transient_key ) {
	return ( new Soft_Transient( $transient_key ) )->delete();
}
