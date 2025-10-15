<?php

namespace MPHB\Utils;

use MPHB\Entities\Booking;
use MPHB\Entities\Customer;
use MPHB\Entities\ReservedRoom;
use MPHB\PostTypes\BookingCPT\Statuses as BookingStatuses;

/**
 * @since 3.7.0
 */
class BookingUtils {

	/**
	 * @param \MPHB\Entities\Booking $booking
	 * @param string|null            $language Optional. Language code, "original" (get the
	 *                title on default language) or NULL (use current language translation).
	 *                NULL by default (current language).
	 * @param bool                   $translateIds Optional. TRUE by default.
	 * @return array [Room type ID => Room type title]
	 *
	 * @since 3.7.0
	 */
	public static function getReservedRoomTypesList( $booking, $language = null, $translateIds = true ) {
		$reservedRooms = $booking->getReservedRooms();
		$roomTypes     = array();

		foreach ( $reservedRooms as $reservedRoom ) {
			$saveId = $roomTypeId = $reservedRoom->getRoomTypeId();

			if ( $language !== 'original' ) {
				$roomTypeId = MPHB()->translation()->translateId( $roomTypeId, MPHB()->postTypes()->roomType()->getPostType(), $language );

				if ( $translateIds ) {
					$saveId = $roomTypeId;
				}
			}

			if ( ! array_key_exists( $saveId, $roomTypes ) ) {
				$roomTypes[ $saveId ] = get_the_title( $roomTypeId );
			}
		}

		return $roomTypes;
	}

	/**
	 * @return \MPHB\Entities\Booking
	 *
	 * @since 3.7.2
	 */
	public static function getTestBooking() {
		$booking = MPHB()->getBookingRepository()->findRandom();

		if ( ! is_null( $booking ) ) {
			static::resetBooking( $booking );
		} else {
			$booking = static::getFakeBooking();
		}

		return $booking;
	}

	/**
	 * Prevent all new emails from appearing in customer's mailbox. Prevent all
	 * new logs from appearing in booking logs.
	 *
	 * @param \MPHB\Entities\Booking $booking
	 *
	 * @since 3.7.2
	 */
	public static function resetBooking( $booking ) {
		$booking->setId( 0 );
		$booking->getCustomer()->setEmail( MPHB()->settings()->emails()->getHotelAdminEmail() );

		foreach ( $booking->getReservedRooms() as $reservedRoom ) {
			$reservedRoom->setBookingId( 0 );
		}
	}

	/**
	 * @return \MPHB\Entities\Booking
	 *
	 * @since 3.7.2
	 */
	public static function getFakeBooking() {
		return new Booking(
			array(
				'id'             => 0,
				'check_in_date'  => new \DateTime( '+0 days' ), // "new \DateTime('today')" will set time to 00:00:00
				'check_out_date' => new \DateTime( '+1 day' ),
				'reserved_rooms' => array(
					new ReservedRoom(
						array(
							'id'         => 0,
							'room_id'    => 0,
							'rate_id'    => 0,
							'adults'     => 1,
							'children'   => 0,
							'booking_id' => 0,
							'uid'        => mphb_generate_uid(),
						)
					),
				),
				'customer'       => new Customer(
					array(
						'email'      => mphb()->settings()->emails()->getHotelAdminEmail(),
						'first_name' => 'First',
						'last_name'  => 'Last',
						'phone'      => '+0123456789',
					)
				),
				'status'         => BookingStatuses::STATUS_CONFIRMED,
				'total_price'    => 100,
			)
		);
	}

	/**
	 * @since 4.10.0
	 *
	 * @param Booking $booking
	 * @param string $comment Optional.
	 * @return array
	 */
	public static function convertToBlock( $booking, $comment = '' ) {
		$dateFrom = clone $booking->getCheckInDate();

		$dateTo = clone $booking->getCheckOutDate();
		$dateTo->modify('-1 day');

		return array(
			'date_from'   => $dateFrom,
			'date_to'     => $dateTo,
			'date_period' => DateUtils::createDatePeriod( $dateFrom, $dateTo ),
			'room_ids'    => $booking->getRoomIds(),
			'comment'     => $comment,
		);
	}

	/**
	 * @since 4.10.0
	 *
	 * @param Booking[] $bookings
	 * @param string $comment Optional.
	 * @return array
	 */
	public static function convertToBlocks( $bookings, $comment = '' ) {
		$blocks = array();
		
		foreach ( $bookings as $booking ) {
			$blocks[] = static::convertToBlock( $booking, $comment );
		}

		return $blocks;
	}
}
