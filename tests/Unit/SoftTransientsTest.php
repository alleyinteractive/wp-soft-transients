<?php
/**
 * Class file for SoftTransientsTest
 *
 * (c) Alley <info@alley.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package wp-soft-transients
 */

declare( strict_types = 1 );

namespace Alley\WP\Tests\Unit;

use Alley\WP\Soft_Transients\Soft_Transient;
use Mantle\Testkit\Test_Case;

use function Alley\WP\Soft_Transients\get_soft_transient;
use function Alley\WP\Soft_Transients\set_soft_transient;
use function Alley\WP\Soft_Transients\delete_soft_transient;

/**
 * Tests the SoftTransients functionality.
 */
final class SoftTransientsTest extends Test_Case {
	/**
	 * Basic test.
	 */
	public function test_the_basics(): void {
		$key    = 'basic_transient';
		$value  = 'value 1.1';
		$value2 = 'value 1.2';

		$this->assertFalse( get_soft_transient( 'doesnotexist' ) );
		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertFalse( set_soft_transient( $key, $value ) );
		$this->assertTrue( set_soft_transient( $key, $value2 ) );
		$this->assertEquals( $value2, get_soft_transient( $key ) );
		$this->assertTrue( delete_soft_transient( $key ) );
		$this->assertFalse( get_soft_transient( $key ) );
		$this->assertFalse( delete_soft_transient( $key ) );
	}

	/**
	 * Test serialized data.
	 */
	public function test_serialized_data(): void {
		$key   = 'serialized_data';
		$value = [
			'foo' => true,
			'bar' => true,
		];

		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		$value = (object) $value;
		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertTrue( delete_soft_transient( $key ) );
	}

	/**
	 * Test default cron hooks.
	 */
	public function test_soft_transient_default_cron_hooks(): void {
		$key   = 'default_cron_hooks';
		$value = 'value 2';

		$this->assertTrue( set_soft_transient( $key, $value, 100 ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		// Get the actual stored value of the transient and expire it.
		$stored_value               = get_transient( $key );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		// Ensure that when the expired transient is accessed, deletion is
		// scheduled with the default hook.
		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'transient_refresh_' . $key, [ $key ] ) > 0 );
		$this->assertTrue( delete_soft_transient( $key ) );
		$this->assertFalse( wp_next_scheduled( 'transient_refresh_' . $key, [ $key ] ) );
	}

	/**
	 * Test transients with an expiration.
	 */
	public function test_soft_transient_data_with_expiration(): void {
		$key   = 'has_expiration';
		$value = 'value 3';

		$this->assertTrue( set_soft_transient( $key, $value, 100 ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		// Update the expiration to a second in the past and watch the transient be invalidated.
		$stored_value = get_transient( $key );
		$this->assertFalse( empty( $stored_value['expiration'] ) );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'transient_refresh_' . $key, [ $key ] ) > 0 );
	}

	/**
	 * Test adding an expiration to an existing transient.
	 */
	public function test_soft_transient_add_expiration(): void {
		$key    = 'no_expiration';
		$value  = 'value 4.1';
		$value2 = 'value 4.2';
		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		$stored_value = get_transient( $key );
		$this->assertTrue( empty( $stored_value['expiration'] ) );

		// Add expiration to existing expiration-less transient.
		$this->assertTrue( set_soft_transient( $key, $value2, 1 ) );
		$stored_value = get_transient( $key );
		$this->assertFalse( empty( $stored_value['expiration'] ) );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		$this->assertEquals( $value2, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'transient_refresh_' . $key, [ $key ] ) > 0 );
	}

	/**
	 * Test setting transients with a custom cron hook.
	 */
	public function test_soft_transient_custom_cron_hook() {
		$key   = 'custom_hook';
		$value = 'value 5';

		$transient = ( new Soft_Transient( $key ) )
			->set_cron_hook( 'test_soft_transient_0' );

		// Create the transient with a custom hook.
		$this->assertTrue( $transient->set( $value, 100 ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		// Get the actual stored value of the transient and expire it.
		$stored_value               = get_transient( $key );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		// Ensure that when the expired transient is accessed, deletion is
		// scheduled with the custom hook.
		$this->assertEquals( $value, $transient->get() );
		$this->assertTrue( wp_next_scheduled( 'test_soft_transient_0', [ $key ] ) > 0 );
		$this->assertTrue( $transient->delete() );
		$this->assertFalse( wp_next_scheduled( 'test_soft_transient_0', [ $key ] ) );
	}

	/**
	 * Test setting transients with custom cron args.
	 */
	public function test_soft_transient_custom_cron_args() {
		$key   = 'custom_cron_args';
		$value = 'value 6';

		$transient = ( new Soft_Transient( $key ) )
			->set_cron_args( [ 'foo', 'bar' ] );

		// Create the transient with custom cron args.
		$this->assertTrue( $transient->set( $value, 100 ) );
		$this->assertEquals( $value, $transient->get( $key ) );

		// Get the actual stored value of the transient and expire it.
		$stored_value               = get_transient( $key );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		// Ensure that when the expired transient is accessed, the cron event is scheduled with the
		// custom args.
		$this->assertEquals( $value, $transient->get() );
		$this->assertTrue( wp_next_scheduled( 'transient_refresh_' . $key, [ $key, 'foo', 'bar' ] ) > 0 );
		$this->assertTrue( $transient->delete() );
		$this->assertFalse( wp_next_scheduled( 'transient_refresh_' . $key, [ $key, 'foo', 'bar' ] ) );
	}

	/**
	 * Test that the cron event gets rescheduled when in a loading state too long.
	 */
	public function test_soft_transient_reschedule_loading() {
		$key       = 'reschedule_loading';
		$value     = 'value 7';
		$transient = ( new Soft_Transient( $key ) );

		// Create the transient and set it to loading.
		$this->assertTrue( $transient->set( $value, 100 ) );
		$data           = get_transient( $key );
		$data['status'] = 'loading';
		set_transient( $key, $data );

		// Ensure that when the expired transient is accessed, the cron event IS NOT scheduled.
		$this->assertEquals( $value, $transient->get() );
		$this->assertFalse( wp_next_scheduled( 'transient_refresh_reschedule_loading', [ $key ] ) );

		// Update the expiration further in the past.
		$data['expiration'] = time() - DAY_IN_SECONDS;
		set_transient( $key, $data );

		// Ensure that when the expired transient is accessed now, the cron event IS scheduled.
		$this->assertEquals( $value, $transient->get() );
		$this->assertTrue( wp_next_scheduled( 'transient_refresh_reschedule_loading', [ $key ] ) > 0 );
	}
}
