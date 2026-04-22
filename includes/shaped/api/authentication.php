<?php
/**
 * Shaped dashboard API authentication.
 *
 * Assumption: possession of the configured shaped dashboard API key grants
 * access to the shaped dashboard season endpoints. Logged-in WordPress
 * capability checks remain as a fallback for local/admin use.
 *
 * @package MPHB\Shaped\Api
 */

namespace MPHB\Shaped\Api;

use MPHB\Advanced\Api\ApiHelper;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class Authentication {

	const OPTION_KEY = 'shaped_dashboard_api_key';

	/**
	 * Check whether the request is authorized for the shaped dashboard API.
	 *
	 * @param WP_REST_Request|null $request   Request object.
	 * @param string               $context   Capability context.
	 * @param int                  $object_id Object ID for capability fallback.
	 *
	 * @return true|WP_Error
	 */
	public static function authorize( $request = null, $context = 'read', $object_id = 0 ) {
		if ( self::has_valid_api_key( $request ) ) {
			return true;
		}

		if ( ApiHelper::checkPostPermissions( 'mphb_season', $context, $object_id ) ) {
			return true;
		}

		return new WP_Error(
			'shaped_dashboard_auth_required',
			'Sorry, you are not allowed to manage seasons.',
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Check whether the request supplied the configured shaped dashboard API key.
	 *
	 * @param WP_REST_Request|null $request Request object.
	 *
	 * @return bool
	 */
	public static function has_valid_api_key( $request = null ) {
		$configured_key = self::get_configured_api_key();
		if ( '' === $configured_key ) {
			return false;
		}

		$request_key = self::get_request_api_key( $request );
		if ( '' === $request_key ) {
			return false;
		}

		return hash_equals( $configured_key, $request_key );
	}

	/**
	 * Get the configured API key from a constant or option.
	 *
	 * @return string
	 */
	public static function get_configured_api_key() {
		if ( defined( 'SHAPED_DASHBOARD_API_KEY' ) && is_string( SHAPED_DASHBOARD_API_KEY ) ) {
			$constant_key = trim( SHAPED_DASHBOARD_API_KEY );
			if ( '' !== $constant_key ) {
				return $constant_key;
			}
		}

		$option_key = get_option( self::OPTION_KEY, '' );
		if ( is_string( $option_key ) ) {
			return trim( $option_key );
		}

		return '';
	}

	/**
	 * Extract the API key from the request headers.
	 *
	 * @param WP_REST_Request|null $request Request object.
	 *
	 * @return string
	 */
	public static function get_request_api_key( $request = null ) {
		if ( $request instanceof WP_REST_Request ) {
			$header = $request->get_header( 'x-shaped-api-key' );
			if ( is_string( $header ) && '' !== trim( $header ) ) {
				return trim( $header );
			}
		}

		if ( ! empty( $_SERVER['HTTP_X_SHAPED_API_KEY'] ) ) {
			return trim( wp_unslash( $_SERVER['HTTP_X_SHAPED_API_KEY'] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			foreach ( $headers as $key => $value ) {
				if ( 'x-shaped-api-key' === strtolower( $key ) && is_string( $value ) ) {
					return trim( $value );
				}
			}
		}

		return '';
	}
}
