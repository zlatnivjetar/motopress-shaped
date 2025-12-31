<?php

/*
 * Plugin Name: Hotel Booking
 * Plugin URI: https://motopress.com/products/hotel-booking/
 * Description: Manage your hotel booking services. Perfect for hotels, villas, guest houses, hostels, and apartments of all sizes.
 * Version: 5.2.3
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: MotoPress
 * Author URI: https://motopress.com/
 * License: GPLv2 or later
 * Text Domain: motopress-hotel-booking
 * Domain Path: /languages
 */

if ( ! class_exists( 'HotelBookingPlugin' ) ) {

	define( 'MPHB_PLUGIN_FILE', __FILE__ );

	require plugin_dir_path( __FILE__ ) . 'plugin.php';
}
