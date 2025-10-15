<?php

namespace MPHB\Core;

use DateTime;
use MPHB\Entities\Booking;
use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class BookingHelper {

	private function __construct() {}


	/**
	 * @param bool $isExtendDates - include to the result check-in and next to the check-out dates (used in admin booking calendar)
	 * @return array [ date (string 'Y-m-d') => DateTime ] - only the dates of the buffer.
	 */
	public static function getBookingBufferDates( DateTime $checkInDate, DateTime $checkOutDate, int $bufferDaysCount, bool $isExtendDates = false ) {

		$offsetDays = $isExtendDates ? $bufferDaysCount + 1 : $bufferDaysCount;

		list( $beforeCheckIn, $afterCheckOut ) = static::addBufferToCheckInAndCheckOutDates(
			$checkInDate,
			$checkOutDate,
			$bufferDaysCount
		);

		if ( $isExtendDates ) {
			$afterCheckOut->modify( '+1 day' );
		}

		$fullPeriod = DateUtils::createDatePeriod( $beforeCheckIn, $afterCheckOut );

		// Split period to dates
		$bufferDates = iterator_to_array( $fullPeriod );
		array_splice( $bufferDates, $offsetDays, -$offsetDays ); // Remove booking inner dates

		$dateFormat  = MPHB()->settings()->dateTime()->getDateTransferFormat();
		$dateStrings = array_map(
			function ( $date ) use ( $dateFormat ) {
				return $date->format( $dateFormat );
			},
			$bufferDates
		);

		return array_combine( $dateStrings, $bufferDates );
	}

	/**
	 * @return DateTime[] [ 0 => modified check-in date, 1 => modified check-out date ]
	 */
	public static function addBufferToCheckInAndCheckOutDates( DateTime $checkInDate, DateTime $checkOutDate, int $bufferDaysCount ) {

		if ( 0 < $bufferDaysCount ) {

			$beforeCheckIn = DateUtils::cloneModify( $checkInDate, "-{$bufferDaysCount} days" );
			$afterCheckOut = DateUtils::cloneModify( $checkOutDate, "+{$bufferDaysCount} days" );

			return array( $beforeCheckIn, $afterCheckOut );
		}

		return array( $checkInDate, $checkOutDate );
	}

	/**
	 * @since 5.0.0
	 *
	 * @param Booking $booking
	 * @return bool
	 */
	public static function isBufferApplicableToBooking( $booking ) {
		return ! $booking->isImported() || ! MPHB()->settings()->main()->isSyncWithBuffers();
	}

	/**
	 * @since 5.0.0
	 *
	 * @param Booking $booking
	 * @return bool
	 */
	public static function isBufferExportableForBooking( $booking ) {
		return ! $booking->isImported() && MPHB()->settings()->main()->isSyncWithBuffers();
	}
}
