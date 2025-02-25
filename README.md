# Soft Transients

Asynchronously update transients via WordPress Cron while serving stale data.

This pattern provides a few advantages.

1. **Faster page loads**: If the transient is expired, the stale data is returned immediately and no "unlucky user(s)" have slower pageloads while data is refreshed.
2. **Reduced load on remote services**: If the transient is expired, the stale data is returned immediately and no request is made to the remote service until the cron task kicks off. This can be especially useful for high-traffic sites making remote requests, where many pageloads after an expiration could all attempt to update data at the same time. Instead, only one request is made to the remote service.
3. **No domino effect from unavailable services**: If a remote service is slow or unavailable, the site will continue to serve stale data until the service is available again. This can prevent a domino effect where one site becomes slow or unavailable because of a service disruption on another site.

## Installation

Install the latest version with:

```bash
composer require alleyinteractive/wp-soft-transients
```

## Usage

Soft Transients provides a wrapper for [WordPress's Transients API](https://developer.wordpress.org/apis/transients/), modifying its behavior such that the transient data may remain accessible after its expiration. Soft Transients stores the transient data alongside metadata, including the desired expiration time. After the expiration, stale data is returned and a cron task is enqueued. When that cron task executes, an application may then choose to take some action to refresh the expired data.

### Helper Functions

The library provides a class to interact with the transients, as well as a set of simplified drop-in replacement functions fully compatible with the WordPress Transients API. The helper functions are:

- `get_soft_transient( $transient_key )`: Retrieve the value of a transient.
- `set_soft_transient( $transient_key, $value, $expiration )`: Set the value of a transient.
- `delete_soft_transient( $transient_key )`: Delete a transient.

While the helper functions are convenient and easy to use, they do not provide the full functionality of the Soft Transients library. If you need to set custom cron hooks or pass custom arguments to the cron task, you should use the `Soft_Transient` class directly.

### Example

In this example, we're getting organizations from GitHub's API. We have a function to get the organizations from the API, and we have a function to get the organizations from the transient (`get_github_orgs()`).

In `get_github_orgs()`, if the transient is not available (e.g. this is the first time it's ever been called, or transients have been flushed), we'll get the organizations synchronously from the API and set the transient. We don't have to do this if we don't want, we could instead schedule the cron task to refresh the transient and return some "pending" signal to the caller.

If the transient is available, `get_github_orgs()` will return organization data indefinitely, even past the expiration time. If this function is called after the soft transient has expired, the Soft Transients library will schedule a cron task to run immediately (`transient_refresh_github_orgs`) which we then hook into to update the transient data.

```php
use function Alley\WP\Soft_Transients\get_soft_transient;
use function Alley\WP\Soft_Transients\set_soft_transient;

function get_github_orgs_from_api() {
  $request = wp_remote_get( 'https://api.github.com/organizations' );
  if ( is_wp_error( $request ) ) {
    return $request;
  } elseif ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
    return new WP_Error( 'bad_response', 'Bad response from GitHub API', $request );
  }
  $orgs = json_decode( wp_remote_retrieve_body( $request ), true );
  set_soft_transient( 'github_orgs', $orgs, HOUR_IN_SECONDS );
  return $orgs;
}

function get_github_orgs() {
  return get_soft_transient( 'github_orgs' ) ?: get_github_orgs_from_api();
}

function refresh_github_orgs_cron_task( $transient_key ) {
  $response = get_github_orgs_from_api();
  if ( is_wp_error( $response ) ) {
    // If the response is an error, it's necessary to schedule a new cron event or else the
    // transient will never update. We can schedule this for whenever makes sense for the
    // given service, e.g. one minute from now.
    wp_schedule_single_event( time() + MINUTE_IN_SECONDS, current_action(), [ $transient_key ] );
  }
}
add_action( 'transient_refresh_github_orgs', 'refresh_github_orgs_cron_task' );
```

### Custom Cron Hooks and Arguments

This library allows you to set a custom cron hooks and/or pass custom arguments to the cron task. This can be useful if you're working with a variety of transient keys (e.g. a hash made up of some data like a url and arguments). In order to use this fuctionality, you can use the `Soft_Transient` class directly.

```php
// Create the transient object.
$key       = 'my_request_' . md5( serialize( [ $url, $args ] ) );
$transient = ( new Soft_Transient( $key ) )
  ->set_cron_args( [ $url, $args ] )
  ->set_cron_hook( 'my_custom_cron_event' );

// Set a value.
$transient->set( get_remote_data( $url, $args ), HOUR_IN_SECONDS );

// Get a value.
$transient->get();

// Delete a value.
$transient->delete();

// Refresh a value.
add_action(
  'my_custom_cron_event',
  function( $transient_key, $url, $args ) use ( $transient ) {
    $transient = new Soft_Transient( $transient_key );
    $transient->set( get_remote_data( $url, $args ), HOUR_IN_SECONDS );
  },
  10,
  3
);
```

### Caveats

- When the transient has expired, the cron event is scheduled and the metadata is updated to indicate that the state is "loading". During the cron event, you must either successfully update the transient or schedule a new cron event to refresh the transient. If you do not, the transient will remain in the "loading" state indefinitely and will never update.
- As with WordPress's transients API, transients are not guaranteed; it is possible for the transient to not be available _before_ the expiration time. Much like what is done with caching, your code should have a fall back method to re-generate the data if the transient is not available.

## About

WP Soft Transients is a derivative work of [Soft Transients by Matthew Boynes](https://github.com/mboynes/soft-transients) (c) 2014.

### License

[GPL-2.0-or-later](https://github.com/alleyinteractive/wp-soft-transients/blob/main/LICENSE)

### Maintainers

[Alley Interactive](https://github.com/alleyinteractive)
