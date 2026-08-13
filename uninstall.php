<?php
/**
 * Clean up on uninstall.
 *
 * @package GenuineRecentSales
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'grs_settings' );
delete_transient( 'grs_feed_cache' );
