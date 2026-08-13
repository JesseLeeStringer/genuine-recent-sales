<?php
/**
 * Public REST endpoint that serves the cached feed to the front-end script.
 * Decoupled from page cache so page HTML stays cacheable while the feed stays fresh.
 *
 * @package GenuineRecentSales
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GRS_Rest {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route( 'grs/v1', '/feed', array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'callback'            => array( $this, 'feed' ),
		) );
	}

	public function feed() {
		$response = rest_ensure_response( GRS_Feed::get() );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}
}
