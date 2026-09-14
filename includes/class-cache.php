<?php
/**
 * Front-end cache flush after settings change.
 *
 * @package ImageRadius
 */

namespace ImageRadius;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clears known page caches so radius CSS is not stale.
 */
class Cache {
	public static function flush(): void {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( class_exists( 'WpFastestCache' ) ) {
			$cache = new \WpFastestCache();
			if ( method_exists( $cache, 'deleteCache' ) ) {
				$cache->deleteCache( true );
			}
		}
	}
}
