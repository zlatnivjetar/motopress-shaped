<?php

namespace MPHB\Core;

use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RoomAvailabilityHelper {

	private function __construct() {}


	public static function getActiveRoomsCountForRoomType( int $roomTypeOriginalId ) {

		$roomsAtts = array(
			'post_status'  => 'publish',
		);

		if ( 0 < $roomTypeOriginalId ) {
			$roomsAtts['room_type_id'] = $roomTypeOriginalId;
		}

		return MPHB()->getRoomPersistence()->getCount( $roomsAtts );
	}

	/**
	 * @return array [
	 *     room_type_id (int) => [
	 *         'booked'     => [ 'Y-m-d' (string) => rooms_count (int) ]
	 *         'check-ins'  => [ 'Y-m-d' (string) => rooms_count (int) ]
	 *         'check-outs' => [ 'Y-m-d' (string) => rooms_count (int) ]
	 *     ],
	 *     ...
	 * ],
	 * where room_type_id = 0 for booked data of all room types.
	 * 'check-ins' and 'check-outs' contain fully booked dates only!
	 *
	 * @global \wpdb $wpdb
	 */
	public static function getBookedDays() {

		$result = array();

		global $wpdb;

		$lockStatuses = MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses();

		// Example of result:
		//     booking_id  check_in_date  check_out_date  room_id  sync_id
		//     2862        2024-02-20     2024-02-22      99       NULL
		//     2862        2024-02-20     2024-02-22      105      NULL
		//     2070        2024-02-28     2024-02-29      111      'e8d2...'
		//     ...
		$bookingsDataRows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bookings.ID AS booking_id,"
					. " check_in.meta_value AS check_in_date,"
					. " check_out.meta_value AS check_out_date,"
					. " rooms.meta_value AS room_id,"
					. " sync.meta_value AS sync_id"
				. " FROM {$wpdb->posts} AS bookings"
					. " INNER JOIN {$wpdb->postmeta} AS check_in"
						. " ON check_in.post_id = bookings.ID AND check_in.meta_key = 'mphb_check_in_date'"
					. " INNER JOIN {$wpdb->postmeta} AS check_out"
						. " ON check_out.post_id = bookings.ID AND check_out.meta_key = 'mphb_check_out_date'"
					. " INNER JOIN {$wpdb->posts} AS reserved_rooms"
						. " ON reserved_rooms.post_parent = bookings.ID"
					. " INNER JOIN {$wpdb->postmeta} AS rooms"
						. " ON rooms.post_id = reserved_rooms.ID AND rooms.meta_key = '_mphb_room_id'"
					. " LEFT JOIN {$wpdb->postmeta} AS sync"
						. " ON sync.post_id = bookings.ID AND sync.meta_key = '_mphb_sync_id'"
				. " WHERE bookings.post_type = %s" // %1$s - booking post type
					. " AND bookings.post_status IN ('" . implode( "', '", $lockStatuses ) . "')"
					. " AND check_out.meta_value >= %s", // %2$s - today

				MPHB()->postTypes()->booking()->getPostType(),
				current_time( 'Y-m-d' )
			),
			ARRAY_A
		);

		if ( ! empty( $bookingsDataRows ) ) {

			// [
			//     check_in_date  => DateTime,
			//     check_out_date => DateTime,
			//     room_ids       => int[]
			// ]
			$bookingsData = array();

			foreach ( $bookingsDataRows as $row ) {
				$bookingId = absint( $row['booking_id'] );
				$roomId    = absint( $row['room_id'] );

				if ( ! isset( $bookingsData[ $bookingId ] ) ) {
					// Parse once with multibooking enabled
					$bookingsData[ $bookingId ] = array(
						'check_in_date'  => \DateTime::createFromFormat( 'Y-m-d', $row['check_in_date'] ),
						'check_out_date' => \DateTime::createFromFormat( 'Y-m-d', $row['check_out_date'] ),
						'room_ids'       => array(),
						'is_imported'    => ! empty( $row['sync_id'] ),
					);
				}

				if ( ! in_array( $roomId, $bookingsData[ $bookingId ]['room_ids'] ) ) {
					$bookingsData[ $bookingId ]['room_ids'][] = $roomId;
				}
			}

			// Get room type ID for all rooms
			$roomIdsWithRoomTypeIds = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT post_id AS room_id, meta_value AS room_type_id"
						. " FROM {$wpdb->postmeta}"
						. " WHERE meta_key = 'mphb_room_type_id'"
							. " AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish')" // %1$s - room post type
							. " AND meta_value IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish')", // %2$s - room type post type

					MPHB()->postTypes()->room()->getPostType(),
					MPHB()->postTypes()->roomType()->getPostType()
				),
				ARRAY_A
			);

			// [ room_id (int) => room_type_id (int) ]
			$roomTypeIdsPerRoomId = wp_list_pluck(
				$roomIdsWithRoomTypeIds,
				'room_type_id',
				'room_id'
			);

			// [
			//     room_type_id => [
			//         Y-m-d => room_id[]
			//     ]
			// ]
			$bookedRoomIdsPerDates = array();
			$bookingRules = mphb_availability_facade();

			foreach ( $bookingsData as $bookingData ) {

				$bookedRoomIdsPerRoomTypeId = array();

				foreach ( $bookingData['room_ids'] as $roomId ) {

					// we do not want to take into account deleted rooms or room types
					if ( isset( $roomTypeIdsPerRoomId[ $roomId ] ) ) {
						$bookedRoomIdsPerRoomTypeId[ (int) $roomTypeIdsPerRoomId[ $roomId ] ][] = $roomId;
					}
				}

				if ( empty( $bookedRoomIdsPerRoomTypeId ) ) {
					continue;
				}

				// get booking dates
				$fromDate = $bookingData['check_in_date']->format( 'Y-m-d' );
				$toDate   = $bookingData['check_out_date']->format( 'Y-m-d' );
				$today    = mphb_current_time( 'Y-m-d' );
				$fromDate = $fromDate >= $today ? $fromDate : $today;

				$bookingDates = \MPHB\Utils\DateUtils::createDateRangeArray( $fromDate, $toDate );

				// add booking buffer dates
				foreach ( $bookedRoomIdsPerRoomTypeId as $bookedRoomTypeId => $roomIds ) {

					// See also BookingHelper::isBufferApplicableToBooking()
					if ( ! $bookingData['is_imported'] || ! MPHB()->settings()->main()->isSyncWithBuffers() ) {
						$bookingBufferDays = $bookingRules->getBufferDaysCount(
							$bookedRoomTypeId,
							$bookingData['check_in_date'],
							MPHB()->settings()->main()->isBookingRulesForAdminDisabled()
						);
					} else {
						$bookingBufferDays = 0;
					}

					$bookingDatesForRoomType = $bookingDates;

					if ( 0 < $bookingBufferDays ) {

						$bufferDatesForRoom = BookingHelper::getBookingBufferDates(
							$bookingData['check_in_date'],
							$bookingData['check_out_date'],
							$bookingBufferDays
						);

						$bookingDatesForRoomType = array_merge( $bookingDatesForRoomType, $bufferDatesForRoom );
					}

					$bookingDatesForRoomType = array_keys( $bookingDatesForRoomType );

					$bookedRoomIdsForRoomType = $bookedRoomIdsPerRoomTypeId[ $bookedRoomTypeId ];

					// add booked room ids to booking dates
					foreach ( $bookingDatesForRoomType as $dateYmd ) {

						if ( ! isset( $bookedRoomIdsPerDates[ $bookedRoomTypeId ][ $dateYmd ] ) ) {

							$bookedRoomIdsPerDates[ $bookedRoomTypeId ][ $dateYmd ] = $bookedRoomIdsForRoomType;

						} else {

							$bookedRoomIdsPerDates[ $bookedRoomTypeId ][ $dateYmd ] = array_merge(
								$bookedRoomIdsPerDates[ $bookedRoomTypeId ][ $dateYmd ],
								$bookedRoomIdsForRoomType
							);
						}

						// add data for all room types ( room_type_id = 0 )
						if ( ! isset( $bookedRoomIdsPerDates[ 0 ][ $dateYmd ] ) ) {

							$bookedRoomIdsPerDates[ 0 ][ $dateYmd ] = $bookedRoomIdsForRoomType;

						} else {

							$bookedRoomIdsPerDates[ 0 ][ $dateYmd ] = array_merge(
								$bookedRoomIdsPerDates[ 0 ][ $dateYmd ],
								$bookedRoomIdsForRoomType
							);
						}
					}
				}
			}

			$roomsTotalCountsPerRoomTypeId = array(
				// all rooms from all room types count
				0 => count( $roomIdsWithRoomTypeIds ),
			);

			foreach ( $roomIdsWithRoomTypeIds as $row ) {

				if ( isset( $roomsTotalCountsPerRoomTypeId[ $row['room_type_id'] ] ) ) {
					$roomsTotalCountsPerRoomTypeId[ $row['room_type_id'] ]++;
				} else {
					$roomsTotalCountsPerRoomTypeId[ $row['room_type_id'] ] = 1;
				}
			}

			foreach ( $bookedRoomIdsPerDates as $roomTypeId => $bookedRoomIdsPerDateYmd ) {

				$bookedRoomIdsPerDateYmd    = array_map( 'array_unique', $bookedRoomIdsPerDateYmd );
				$bookedRoomCountsPerDateYmd = array_map( 'count', $bookedRoomIdsPerDateYmd );
				ksort( $bookedRoomCountsPerDateYmd );

				$checkInCountsPerDateYmd  = array();
				$checkOutCountsPerDateYmd = array();

				$roomTypeTotalRoomsCount = $roomsTotalCountsPerRoomTypeId[ $roomTypeId ];

				foreach ( $bookedRoomCountsPerDateYmd as $bookedDateYmd => $bookedRoomsCount ) {

					if ( $bookedRoomsCount >= $roomTypeTotalRoomsCount ) {

						$beforeBookedDateYmd = \DateTime::createFromFormat( 'Y-m-d', $bookedDateYmd )->modify( '-1 day' )->format( 'Y-m-d' );
						$afterBookedDateYmd  = \DateTime::createFromFormat( 'Y-m-d', $bookedDateYmd )->modify( '+1 day' )->format( 'Y-m-d' );

						if ( empty( $checkInCountsPerDateYmd ) ||
							! isset( $bookedRoomCountsPerDateYmd[ $beforeBookedDateYmd ] ) ||
							$bookedRoomCountsPerDateYmd[ $beforeBookedDateYmd ] < $bookedRoomsCount
						) {
							$checkInCountsPerDateYmd[ $bookedDateYmd ] = $bookedRoomsCount;
							// we assume that after booked date all guests check-out
							// and clarify it later in cycle
							$checkOutCountsPerDateYmd[ $afterBookedDateYmd ] = $bookedRoomsCount;

						} elseif ( ! empty( $checkOutCountsPerDateYmd ) ) {

							$lastCheckOutDateYmd = array_keys( $checkOutCountsPerDateYmd )[ count( $checkOutCountsPerDateYmd ) - 1 ];
							unset( $checkOutCountsPerDateYmd[ $lastCheckOutDateYmd ] );
							$checkOutCountsPerDateYmd[ $afterBookedDateYmd ] = $bookedRoomsCount;
						}
					}
				}

				$result[ $roomTypeId ] = array(
					'booked'     => $bookedRoomCountsPerDateYmd,
					'check-ins'  => $checkInCountsPerDateYmd,
					'check-outs' => $checkOutCountsPerDateYmd,
				);
			}
		}

		return $result;
	}


	public static function getAvailableRoomsCountForRoomType( int $roomTypeOriginalId, \DateTime $date, bool $isIgnoreBookingRules ) {

		$availableRoomsCount = mphb_rooms_facade()->getActiveRoomsCountForRoomType( $roomTypeOriginalId );

		if ( 0 >= $availableRoomsCount ) { // for optimization of calculation
			return $availableRoomsCount;
		}

		$formattedDate = $date->format( 'Y-m-d' );

		$bookedDays = mphb_bookings_facade()->getBookedDaysForRoomType( $roomTypeOriginalId );

		if ( ! empty( $bookedDays['booked'][ $formattedDate ] ) ) {
			$availableRoomsCount = $availableRoomsCount - $bookedDays['booked'][ $formattedDate ];
		}

		if ( 0 >= $availableRoomsCount ) { // for optimization of calculation
			return $availableRoomsCount;
		}

		if ( ! $isIgnoreBookingRules ) {

			$blokedRoomsCount = mphb_availability_facade()->getBlockedRoomsCountForRoomType(
				$roomTypeOriginalId,
				$date,
				$isIgnoreBookingRules
			);

			$availableRoomsCount = $availableRoomsCount - $blokedRoomsCount;
		}

		return $availableRoomsCount;
	}

	/**
	 * @return string status
	 */
	public static function getRoomTypeAvailabilityStatus( int $roomTypeOriginalId, \DateTime $date, bool $isIgnoreBookingRules ) {

		if ( $date < ( new \DateTime( 'today', DateUtils::getSiteTimeZone() ) ) ) {
			return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_PAST;
		}

		if ( mphb_bookings_facade()->isBookedDate( $roomTypeOriginalId, $date ) ) {
			return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_BOOKED;
		}

		if (  mphb_availability_facade()->isCheckInEarlierThanMinAdvanceDate( $roomTypeOriginalId, $date, $isIgnoreBookingRules )	) {
			return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_EARLIER_MIN_ADVANCE;
		}

		if ( mphb_availability_facade()->isCheckInLaterThanMaxAdvanceDate( $roomTypeOriginalId, $date, $isIgnoreBookingRules ) ) {
			return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_LATER_MAX_ADVANCE;
		}

		if ( 0 < $roomTypeOriginalId ) {

			$datesRates = mphb_prices_facade()->getDatesWithRatesByRoomTypeId( $roomTypeOriginalId );

			if ( ! in_array( $date->format( 'Y-m-d' ), $datesRates ) ) {
				return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_NOT_AVAILABLE;
			}

			if ( 0 >= static::getAvailableRoomsCountForRoomType( $roomTypeOriginalId, $date, $isIgnoreBookingRules ) ) {
				return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_NOT_AVAILABLE;
			}
		} else {

			$allRoomTypeIds = mphb_rooms_facade()->getAllRoomTypeOriginalIds();

			$formattedDateYmd = $date->format( 'Y-m-d' );

			foreach ( $allRoomTypeIds as $roomTypeId ) {

				$datesRates = mphb_prices_facade()->getDatesWithRatesByRoomTypeId( $roomTypeId );

				if ( in_array( $formattedDateYmd, $datesRates ) &&
					0 < static::getAvailableRoomsCountForRoomType( $roomTypeId, $date, $isIgnoreBookingRules )
				) {
					// at least one room type has available room
					return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_AVAILABLE;
				}
			}

			return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_NOT_AVAILABLE;
		}

		return RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_AVAILABLE;
	}


	/**
	 * @param $considerCheckIn - if true then check-in date considered as booked if there is no any available room
	 * @param $considerCheckOut - if true then check-out date considered as booked if there is no any available room
	 * @return true if given date is booked (there is no any available room)
	 */
	public static function isBookedDate( int $roomTypeOriginalId, \DateTime $date, $considerCheckIn = true, $considerCheckOut = false ) {

		$bookedDays       = mphb_bookings_facade()->getBookedDaysForRoomType( $roomTypeOriginalId );
		$activeRoomsCount = mphb_rooms_facade()->getActiveRoomsCountForRoomType( $roomTypeOriginalId );

		$formattedDate = $date->format( 'Y-m-d' );

		$isBookedDate = ( ! empty( $bookedDays['booked'][ $formattedDate ] ) &&
			$bookedDays['booked'][ $formattedDate ] >= $activeRoomsCount );

		if ( ! $considerCheckIn && ! empty( $bookedDays['check-ins'][ $formattedDate ] ) ) {
			$isBookedDate = false;
		}

		if ( $considerCheckOut && ! $isBookedDate ) {

			$dateBefore = clone $date;
			$dateBefore->modify( '-1 day' );
			$formattedDateBefore = $dateBefore->format( 'Y-m-d' );

			$isBookedDate = ( ! empty( $bookedDays['booked'][ $formattedDateBefore ] ) &&
				$bookedDays['booked'][ $formattedDateBefore ] >= $activeRoomsCount ) &&
				! empty( $bookedDays['check-outs'][ $formattedDate ] );
		}

		return $isBookedDate;
	}


	/**
	 * @return bool - true if check-in is not allowed in the given date
	 */
	public static function isCheckInNotAllowed( int $roomTypeOriginalId, \DateTime $date, bool $isIgnoreBookingRules ) {

		$availabilityStatus = mphb_availability_facade()->getRoomTypeAvailabilityStatus( $roomTypeOriginalId, $date, $isIgnoreBookingRules );

		if ( RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_EARLIER_MIN_ADVANCE === $availabilityStatus ||
			RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_LATER_MAX_ADVANCE === $availabilityStatus ||
			RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_PAST === $availabilityStatus ||
			RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_BOOKED === $availabilityStatus
		) {

			return false;

		} elseif ( RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_NOT_AVAILABLE === $availabilityStatus ) {

			// check if this is the case when date is blocked by Not Stay In Not Check In and Not Check Out rule
			$isCheckInNotAllowed = mphb_availability_facade()->isCheckInNotAllowed(
				$roomTypeOriginalId,
				$date,
				$isIgnoreBookingRules
			);

			return $isCheckInNotAllowed;
		}

		$isCheckInNotAllowed = mphb_availability_facade()->isCheckInNotAllowed(
			$roomTypeOriginalId,
			$date,
			$isIgnoreBookingRules
		);

		// check Not CheckIn before Not Stay In or Booked days
		if ( ! $isCheckInNotAllowed ) {

			$minStayNights = mphb_availability_facade()->getMinStayNightsCount(
				$roomTypeOriginalId,
				$date,
				$isIgnoreBookingRules
			);

			$checkingDate    = clone $date;
			$nightsAfterDate = 0;

			do {

				$checkingDate->modify( '+1 day' );
				$nightsAfterDate++;

				$checkingDateStatus = mphb_availability_facade()->getRoomTypeAvailabilityStatus( $roomTypeOriginalId, $checkingDate, $isIgnoreBookingRules );

				$isCheckinDateNotAvailable = RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_NOT_AVAILABLE === $checkingDateStatus;
				$isCheckingDateBooked      = RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_BOOKED === $checkingDateStatus;

				$isCheckinDateNotForStayIn = mphb_availability_facade()->isStayInNotAllowed( $roomTypeOriginalId, $checkingDate, $checkingDate, $isIgnoreBookingRules );


				$isBookingNotAllowedInMinStayPeriod = $nightsAfterDate < $minStayNights &&
					( $isCheckinDateNotAvailable || $isCheckinDateNotForStayIn || $isCheckingDateBooked );

				$isCheckOutNotAllowedOnLastDayOfMinStayPeriod = $nightsAfterDate === $minStayNights &&
					mphb_availability_facade()->isCheckOutNotAllowed( $roomTypeOriginalId, $checkingDate, $isIgnoreBookingRules ) &&
					( $isCheckinDateNotAvailable || $isCheckinDateNotForStayIn || $isCheckingDateBooked );

				if ( $isBookingNotAllowedInMinStayPeriod || $isCheckOutNotAllowedOnLastDayOfMinStayPeriod ) {

					$isCheckInNotAllowed = true;
					break;
				}
			} while ( $nightsAfterDate < $minStayNights );
		}

		return $isCheckInNotAllowed;
	}


	/**
	 * @return bool - true if check-out is not allowed in the given date
	 */
	public static function isCheckOutNotAllowed( int $roomTypeOriginalId, \DateTime $date, bool $isIgnoreBookingRules ) {

		$availabilityStatus = mphb_availability_facade()->getRoomTypeAvailabilityStatus( $roomTypeOriginalId, $date, $isIgnoreBookingRules );

		if ( RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_PAST === $availabilityStatus ||
			mphb_bookings_facade()->isBookedDate( $roomTypeOriginalId, $date, false, true )
		) {
			return false;
		}

		$isCheckOutNotAllowed = mphb_availability_facade()->isCheckOutNotAllowed( $roomTypeOriginalId, $date, $isIgnoreBookingRules);

		// check Not Check-out after Not Stay-in, Booked or Not Available days
		if ( ! $isCheckOutNotAllowed ) {

			$checkingDate     = clone $date;
			$nightsBeforeDate = 0;

			do {

				$checkingDate->modify( '-1 day' );
				$nightsBeforeDate++;

				$checkingDateStatus = mphb_availability_facade()->getRoomTypeAvailabilityStatus( $roomTypeOriginalId, $checkingDate, $isIgnoreBookingRules );

				if ( mphb_availability_facade()->isStayInNotAllowed( $roomTypeOriginalId, $checkingDate, $checkingDate, $isIgnoreBookingRules ) ||
					RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_BOOKED === $checkingDateStatus ||
					RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_NOT_AVAILABLE === $checkingDateStatus ||
					RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_PAST === $checkingDateStatus ) {

					$isCheckOutNotAllowed = true;
					break;
				}

				$minStayNights = mphb_availability_facade()->getMinStayNightsCount(
					$roomTypeOriginalId,
					$checkingDate,
					$isIgnoreBookingRules
				);

			} while ( $nightsBeforeDate < $minStayNights );
		}

		return $isCheckOutNotAllowed;
	}


	public static function getRoomTypeAvailabilityData( int $roomTypeOriginalId, \DateTime $date, bool $isIgnoreBookingRules ) {

		$availabilityStatus = mphb_availability_facade()->getRoomTypeAvailabilityStatus( $roomTypeOriginalId, $date, $isIgnoreBookingRules );

		$result = null;

		if ( RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_PAST == $availabilityStatus ) {

			$result = new RoomTypeAvailabilityData( $availabilityStatus );

		} else {

			$availableRoomsCount = self::getAvailableRoomsCountForRoomType( $roomTypeOriginalId, $date, $isIgnoreBookingRules );

			$bookedDays     = mphb_bookings_facade()->getBookedDaysForRoomType( $roomTypeOriginalId );
			$formattedDate  = $date->format( 'Y-m-d' );
			$isCheckInDate  = ! empty( $bookedDays['check-ins'][ $formattedDate ] );
			$isСheckOutDate = ! empty( $bookedDays['check-outs'][ $formattedDate ] );

			$isStayInNotAllowed = mphb_availability_facade()->isStayInNotAllowed( $roomTypeOriginalId, $date, $date, $isIgnoreBookingRules );

			$isEarlierThanMinAdvanceDate = mphb_availability_facade()->isCheckInEarlierThanMinAdvanceDate( $roomTypeOriginalId, $date, $isIgnoreBookingRules );

			$isLaterThanMaxAdvanceDate = mphb_availability_facade()->isCheckInLaterThanMaxAdvanceDate( $roomTypeOriginalId, $date, $isIgnoreBookingRules );

			$minStayNights = mphb_availability_facade()->getMinStayNightsCount(
				$roomTypeOriginalId,
				$date,
				$isIgnoreBookingRules
			);

			$maxStayNights = mphb_availability_facade()->getMaxStayNightsCount( $roomTypeOriginalId, $date, $isIgnoreBookingRules );

			$result = new RoomTypeAvailabilityData(
				$availabilityStatus,
				$availableRoomsCount,
				$isCheckInDate,
				$isСheckOutDate,
				$isStayInNotAllowed,
				static::isCheckInNotAllowed( $roomTypeOriginalId, $date, $isIgnoreBookingRules ),
				static::isCheckOutNotAllowed( $roomTypeOriginalId, $date, $isIgnoreBookingRules ),
				$isEarlierThanMinAdvanceDate,
				$isLaterThanMaxAdvanceDate,
				$minStayNights,
				$maxStayNights
			);
		}

		return $result;
	}

	/**
	 * Returns first available date for check-in for room type or
	 * any of room types if $roomTypeOriginalId = 0
	 * @return \DateTime
	 */
	public static function getFirstAvailableCheckInDate( int $roomTypeOriginalId, bool $isIgnoreBookingRules ) {

		$firstAvailableDate = new \DateTime( 'yesterday', DateUtils::getSiteTimeZone() );
		$maxCheckDatesCount = 370;

		do {
			$firstAvailableDate->modify( '+1 day' );
			$maxCheckDatesCount--;

			$availabilityStatus = mphb_availability_facade()->getRoomTypeAvailabilityStatus(
				$roomTypeOriginalId,
				$firstAvailableDate,
				$isIgnoreBookingRules
			);

		} while (
			RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_AVAILABLE !== $availabilityStatus &&
			RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_LATER_MAX_ADVANCE !== $availabilityStatus &&
			0 < $maxCheckDatesCount
		);
		
		return $firstAvailableDate;
	}
}
