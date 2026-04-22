<?php
/**
 * Shaped dashboard API bootstrap.
 *
 * @package MPHB\Shaped\Api
 */

namespace MPHB\Shaped\Api;

defined( 'ABSPATH' ) || exit;

class Server {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
	}

	public function register_routes() {
		$controller = new Controllers\V1\SeasonsController();
		$controller->register_routes();
	}
}
