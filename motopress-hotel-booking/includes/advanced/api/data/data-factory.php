<?php
/**
 * @package MPHB\Advanced\Api
 * @since 4.1.0
 */

namespace MPHB\Advanced\Api\Data;

use MPHB\Entities\AccommodationAttribute;
use MPHB\Entities\Booking;
use MPHB\Entities\Customer;
use MPHB\Entities\Payment;
use MPHB\Entities\Room;
use MPHB\Entities\Rate;
use MPHB\Entities\RoomType;
use MPHB\Entities\Season;
use MPHB\Entities\Service;
use MPHB\Entities\Coupon;

class DataFactory {

	/**
	 * @param  string $rest_base
	 *
	 * @return AbstractData
	 */
	public static function create( $rest_base ) {

		switch ( $rest_base ) {
			case 'accommodations':
				return new AccommodationData( new Room( array() ) );
			case 'accommodation_types':
				$accommodationTypes = self::createEmptyAccommodationType();
				return new AccommodationTypeData( $accommodationTypes );
			case 'accommodation_types/attributes':
				$attributes = self::createEmptyAccommodationAttribute();
				return new AccommodationTypesAttributeData( $attributes );
			case 'accommodation_types/services':
				$services = self::createEmptyService();
				return new ServiceData( $services );
			case 'bookings':
				$booking = self::createEmptyBooking();
				return new BookingData( $booking );
			case 'coupons':
				$coupon = self::createEmptyCoupon();
				return new CouponData( $coupon );
			case 'payments':
				$payment = self::createEmptyPayment();
				return new PaymentData( $payment );
			case 'rates':
				$rate = self::createEmptyRate();
				return new RateData( $rate );
			case 'seasons':
				$season = self::createEmptySeason();
				return new SeasonData( $season );
			default:
				throw new \Exception( 'Not found relevant class for data of endpoint: ' . $rest_base );
		}
	}

	private static function createEmptyService() {
		$requiredAtts = array(
			'id'            => null,
			'original_id'   => null,
			'title'         => null,
			'description'   => null,
			'periodicity'   => null,
			'min_quantity'  => null,
			'max_quantity'  => null,
			'is_auto_limit' => null,
			'repeat'        => null,
			'price'         => null,
		);

		return Service::create( $requiredAtts );
	}

	private static function createEmptySeason() {
		$requiredAtts = array(
			'id'          => null,
			'title'       => null,
			'description' => null,
			'start_date'  => null,
			'end_date'    => null,
			'days'        => array(),
		);

		return new Season( $requiredAtts );
	}

	private static function createEmptyRate() {
		$requiredAtts = array(
			'id'            => null,
			'title'         => null,
			'description'   => null,
			'room_type_id'  => null,
			'season_prices' => array(),
			'active'        => null,
		);

		return new Rate( $requiredAtts );
	}

	private static function createEmptyBooking() {
		$requiredAtts = array(
			'customer' => new Customer(),
		);

		return new Booking( $requiredAtts );
	}

	private static function createEmptyPayment() {
		$requiredAtts = array(
			'gatewayId'   => null,
			'gatewayMode' => null,
			'amount'      => null,
			'currency'    => null,
			'bookingId'   => null,
		);

		return new Payment( $requiredAtts );
	}

	private static function createEmptyAccommodationType() {
		$requiredAtts = array(
			'id'             => null,
			'original_id'    => null,
			'title'          => null,
			'description'    => null,
			'excerpt'        => null,
			'adults'         => null,
			'children'       => null,
			'total_capacity' => null,
			'base_adults'    => null,
			'base_children'  => null,
			'bed_type'       => null,
			'size'           => null,
			'view'           => null,
			'services_ids'   => array(),
			'categories'     => array(),
			'tags'           => array(),
			'facilities'     => array(),
			'attributes'     => array(),
			'image_id'       => null,
			'gallery_ids'    => null,
			'status'         => null,
		);

		return new RoomType( $requiredAtts );
	}

	private static function createEmptyAccommodationAttribute() {
		$requiredAtts = array(
		);

		return new AccommodationAttribute( $requiredAtts );
	}

	private static function createEmptyCoupon() {
		$requiredAtts = array(
		);

		return new Coupon( $requiredAtts );
	}
}
