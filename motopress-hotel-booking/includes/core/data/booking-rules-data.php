<?php

namespace MPHB\Core;

use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data transfer object for booking rules.
 */
class BookingRulesData {

	/**
	 * @var array [ season_id (int) => \MPHB\Entities\Season, ... ]
	 */
	private $seasons;

	/**
	 * @var int
	 */
	private $countOfAllRoomTypeOriginalIds;

	/**
	 * @var array [ room_type_original_id (int if 0 then rules for all room types) => [
	 *                   'check_in_days'           => [ [ 'season_id' => int (if 0 then rule for all seasons), 'rule_value' => int[] (week days numbers 0 - 6) ], ... ],
	 *                   'check_out_days'          => [ [ 'season_id' => int (if 0 then rule for all seasons), 'rule_value' => int[] (week days numbers 0 - 6) ], ... ],
	 *                   'min_advance_reservation' => [ [ 'season_id' => int (if 0 then rule for all seasons), 'rule_value' => int ], ... ],
	 *                   'max_advance_reservation' => [ [ 'season_id' => int (if 0 then rule for all seasons), 'rule_value' => int ], ... ],
	 *                   'min_stay_length'         => [ [ 'season_id' => int (if 0 then rule for all seasons), 'rule_value' => int ], ... ],
	 *                   'max_stay_length'         => [ [ 'season_id' => int (if 0 then rule for all seasons), 'rule_value' => int ], ... ],
	 *                   'buffer_days'             => [ [ 'season_id' => int (if 0 then rule for all seasons), 'rule_value' => int ], ... ],
	 *                ], ...
	 *            ]
	 */
	private $reservationRulesByRoomTypeIds = array();

	/**
	 * @var array [ [
	 *                 'room_type_id'        => int,
	 * 			       'room_id'             => $roomId,
	 *                 'date_from'           => string (Ymd),
	 *                 'date_to'             => string (Ymd),
	 *                 'date_period'         => DatePeriod,
	 *                 'not_check_in'        => bool,
	 *                 'not_check_out'       => bool,
	 *                 'not_stay_in'         => bool,
	 *                 'custom_rule_comment' => string
	 *              ], ...
	 *            ]
	 */
	private $customRules = array();

	
	private $hasCheckInDaysRules = false;
	private $hasCheckOutDaysRules = false;
	private $hasMinStayLengthRules = false;
	private $hasMaxStayLengthRules = false;
	private $hasMinAdvanceReservationRules = false;
	private $hasMaxAdvanceReservationRules = false;
	private $hasBufferDaysRules = false;
	private $hasNotCheckInRules = false;
	private $hasNotCheckOutRules = false;
	private $hasNotStayInRules = false;

	/**
	 * @var array [ date (string Ymd) => [
	 *                 room_type_original_id (int)  => [
	 *                   'check_in_days'            => int[] (week days numbers 0 - 6),
	 *                   'check_out_days'           => int[] (week days numbers 0 - 6),
	 *                   'min_advance_reservation'  => int,
	 *                   'max_advance_reservation'  => int,
	 *                   'min_stay_length'          => int,
	 *                   'max_stay_length'          => int,
	 *                   'buffer_days'              => int,
	 *                   'not_check_in'             => bool,
	 *                   'not_check_out'            => bool,
	 *                   'not_stay_in'              => bool,
	 *                   'custom_rule_comment'      => string,
	 *                   'custom_rules_for_room_id' => [
	 *                      room_id (int) => [
	 *                         'not_check_in'        => bool,
	 *                         'not_check_out'       => bool,
	 *                         'not_stay_in'         => bool,
	 *                         'custom_rule_comment' => string,
	 *                      ], ...
	 *                   ]
	 *                 ], ...
	 *              ], ...
	 *            ]
	 */
	private $cachedRulesByDates = array();


	public function __construct() {

		/**
		 * [
		 *	'check_in_days'           => [ [ 'season_ids' => int[], 'room_type_ids' => int[], 'check_in_days' => int[] (week day numbers: 0..6) ], ... ],
		 *	'check_out_days'          => [ [ 'season_ids' => int[], 'room_type_ids' => int[], 'check_out_days' => int[] (week day numbers: 0..6) ], ... ],
		 *	'min_stay_length'         => [ [ 'season_ids' => int[], 'room_type_ids' => int[], 'min_stay_length' => int ], ... ],
		 *	'max_stay_length'         => [ [ 'season_ids' => int[], 'room_type_ids' => int[], 'max_stay_length' => int ], ... ],
		 *	'min_advance_reservation' => [ [ 'season_ids' => int[], 'room_type_ids' => int[], 'min_advance_reservation' => int ], ... ],
		 *	'max_advance_reservation' => [ [ 'season_ids' => int[], 'room_type_ids' => int[], 'max_advance_reservation' => int ], ... ]
		 * ]
		 */
		$reservationRules = MPHB()->settings()->bookingRules()->getReservationRules();

		/**
		 * [ [ 'season_ids' => int[], 'room_type_ids' => int[], 'buffer_days' => int ], ... ]
		 */
		$reservationRules['buffer_days'] = MPHB()->settings()->bookingRules()->getBufferRules();
		$this->hasBufferDaysRules        = ! empty( $reservationRules['buffer_days'] );

		$allRoomTypeOriginalIds = mphb_rooms_facade()->getAllRoomTypeOriginalIds();
		$this->countOfAllRoomTypeOriginalIds = count( $allRoomTypeOriginalIds );

		$seasons       = MPHB()->getSeasonRepository()->findAll();
		$this->seasons = array();

		foreach ( $seasons as $season ) {

			$this->seasons[ $season->getId() ] = $season;
		}

		foreach ( $reservationRules as $ruleType => $ruleDatas ) {

			foreach ( $ruleDatas as $ruleData ) {

				$ruleValue = null;
				$isRuleValueValid = false;

				if ( 'buffer_days' === $ruleType ||
					'min_stay_length' === $ruleType || 'max_stay_length' === $ruleType ||
					'min_advance_reservation' === $ruleType || 'max_advance_reservation' === $ruleType
				) {

					$ruleValue = absint( $ruleData[ $ruleType ] );
					$isRuleValueValid = 0 < $ruleValue;

				} elseif ( 'check_in_days' === $ruleType || 'check_out_days' === $ruleType ) {

					$ruleValue = $ruleData[ $ruleType ];
					$isRuleValueValid = is_array( $ruleValue ) && 0 < count( $ruleValue );
				}

				if ( ! empty( $ruleData['room_type_ids'] ) && is_array( $ruleData['room_type_ids'] ) &&
					! empty( $ruleData['season_ids'] ) && is_array( $ruleData['season_ids'] ) &&
					$isRuleValueValid
				) {

					switch ( $ruleType ) {

						case 'check_in_days':
							$this->hasCheckInDaysRules = true;
							break;
						case 'check_out_days':
							$this->hasCheckOutDaysRules = true;
							break;
						case 'min_stay_length':
							$this->hasMinStayLengthRules = true;
							break;
						case 'max_stay_length':
							$this->hasMaxStayLengthRules = true;
							break;
						case 'min_advance_reservation':
							$this->hasMinAdvanceReservationRules = true;
							break;
						case 'max_advance_reservation':
							$this->hasMaxAdvanceReservationRules = true;
							break;
						case 'buffer_days':
							$this->hasBufferDaysRules = true;
							break;
					}

					$ruleSeasons = $ruleData['season_ids'];

					// if season_ids contains 0 then rule is for all seasons
					if ( in_array( 0, $ruleSeasons ) ) {

						// we keep copy of ruleas for all seasons ( where season_id = 0 ) to be able to find common rules
						$ruleSeasons = array( 0 );
					}


					$ruleRoomTypeIds = $ruleData['room_type_ids'];

					// if $ruleData['room_type_ids'] contains 0 then rule is for all room types
					if ( in_array( 0, $ruleRoomTypeIds ) ) {

						$ruleRoomTypeIds = $allRoomTypeOriginalIds;
					}

					foreach ( $ruleSeasons as $seasonId ) {

						// we expect original room type ids on base language!
						foreach ( $ruleRoomTypeIds as $roomTypeOriginalId ) {

							// we keep all rules in set order because top rules have bigger priority than bottoms
							$this->reservationRulesByRoomTypeIds[ $roomTypeOriginalId ][ $ruleType ][] = array(
								'season_id'  => $seasonId,
								'rule_value' => $ruleValue,
							);
						}
					}
				}
			}
		}

		/**
		 * [
		 *   'room_type_id' => int,
		 *   'room_id'      => int,
		 *   'date_from'    => string (Y-m-d),
		 *   'date_to'      => string (Y-m-d),
		 *   'restrictions' => [ 'check-in'?, 'check-out'?, 'stay-in'? ],
		 *   'comment'      => string
		 * ]
		 */
		$customRules = MPHB()->settings()->bookingRules()->getCustomRules();

		foreach ( $customRules as $customRule ) {

			$timeZone = DateUtils::getSiteTimeZone();
			$dateFrom = \DateTime::createFromFormat( 'Y-m-d', $customRule['date_from'], $timeZone );
			$dateTo   = \DateTime::createFromFormat( 'Y-m-d', $customRule['date_to'], $timeZone );

			if ( false !== $dateFrom && false !== $dateTo ) {

				$roomTypeId = absint( $customRule['room_type_id'] );
				$roomId     = absint( $customRule['room_id'] );

				$isNotCheckIn = in_array( 'check-in', $customRule['restrictions'] );
				$isNotCheckOut = in_array( 'check-out', $customRule['restrictions'] );
				$isNotStayIn = in_array( 'stay-in', $customRule['restrictions'] );

				$this->hasNotCheckInRules = $this->hasNotCheckInRules || $isNotCheckIn;
				$this->hasNotCheckOutRules = $this->hasNotCheckOutRules || $isNotCheckOut;
				$this->hasNotStayInRules = $this->hasNotStayInRules || $isNotStayIn;

				// we keep all rules in order from UI list
				// to make sure top rules overwrite bottoms rules
				$this->customRules[] = array(
					'room_type_id'        => $roomTypeId,
					'room_id'             => $roomId,
					'date_from'           => $dateFrom->format('Ymd'),
					'date_to'             => $dateTo->format('Ymd'),
					'date_period'         => DateUtils::createDatePeriod( $dateFrom, $dateTo ),
					'not_check_in'        => $isNotCheckIn,
					'not_check_out'       => $isNotCheckOut,
					'not_stay_in'         => $isNotStayIn,
					'custom_rule_comment' => isset( $customRule['comment'] ) ? $customRule['comment'] : '',
				);
			}
		}

		if ( ! $this->hasNotStayInRules ) {
			/**
			 * @since 4.10.0
			 *
			 * @param bool $hasNotStayInRules
			 */
			$this->hasNotStayInRules = apply_filters( 'mphb_has_not_stay_in_rules', $this->hasNotStayInRules );
		}
	}

	/**
	 * @return array [ 'check_in_days'            => int[] (week days numbers 0 - 6),
	 *                 'check_out_days'           => int[] (week days numbers 0 - 6),
	 *                 'min_advance_reservation'  => int,
	 *                 'max_advance_reservation'  => int,
	 *                 'min_stay_length'          => int,
	 *                 'max_stay_length'          => int,
	 *                 'buffer_days'              => int,
	 *                 'not_check_in'             => bool,
	 *                 'not_check_out'            => bool,
	 *                 'not_stay_in'              => bool,
	 *                 'custom_rule_comment'      => string,
	 *                 'custom_rules_for_room_id' => [
	 *                    room_id (int) => [
	 *                       'not_check_in'        => bool,
	 *                       'not_check_out'       => bool,
	 *                       'not_stay_in'         => bool,
	 *                       'custom_rule_comment' => string,
	 *                    ], ...
	 *                 ]
	 *               ]
	 */
	private function getBookingRulesForDate( int $roomTypeOriginalId, \DateTime $requestedDate ) {

		$requestedDateString = $requestedDate->format('Ymd');

		if ( ! isset( $this->cachedRulesByDates[ $requestedDateString ][ $roomTypeOriginalId ] ) ) {

			$result = array();
			
			if ( 0 === $roomTypeOriginalId ) {

				// find common reservation rules for not found rule types
				// if requested $roomTypeOriginalId = 0 (for example, for search form)
				$collectingRules = array();

				foreach ( $this->reservationRulesByRoomTypeIds as $roomTypeId => $rulesByTypes ) {

					foreach ( $rulesByTypes as $ruleType => $ruleDatas ) {

						if ( ! isset( $collectingRules[ $ruleType ]['room_type_ids'] ) ) {

							$collectingRules[ $ruleType ]['room_type_ids']     = array();
							$collectingRules[ $ruleType ]['common_rule_value'] = null;
						}

						if ( empty( $result[ $ruleType ] ) && 
							'buffer_days' !== $ruleType &&
							! in_array( $roomTypeId, $collectingRules[ $ruleType ]['room_type_ids'] )
						) {

							foreach ( $ruleDatas as $seasonRuleData ) {

								$season = isset( $this->seasons[ $seasonRuleData['season_id'] ] ) ? $this->seasons[ $seasonRuleData['season_id'] ] : null;

								if ( 0 === $seasonRuleData['season_id'] ||
									( null !== $season && $season->isDateInSeason( $requestedDate ) )
								) {

									if ( ( 'min_stay_length' === $ruleType || 'min_advance_reservation' === $ruleType ) &&
										( null === $collectingRules[ $ruleType ]['common_rule_value'] || 
											$collectingRules[ $ruleType ]['common_rule_value'] > $seasonRuleData['rule_value'] )
									) {
	
										// searching min rule value as common rule value
										$collectingRules[ $ruleType ]['common_rule_value'] = $seasonRuleData['rule_value'];
	
									} elseif ( ( 'max_stay_length' === $ruleType || 'max_advance_reservation' === $ruleType ) &&
										( null === $collectingRules[ $ruleType ]['common_rule_value'] || 
										$collectingRules[ $ruleType ]['common_rule_value'] < $seasonRuleData['rule_value'] )
									) {
	
										// searching max rule value as common rule value
										$collectingRules[ $ruleType ]['common_rule_value'] = $seasonRuleData['rule_value'];
	
									} elseif ( 'check_in_days' === $ruleType || 'check_out_days' === $ruleType ) {
	
										if ( null === $collectingRules[ $ruleType ]['common_rule_value'] ) {
	
											$collectingRules[ $ruleType ]['common_rule_value'] = array();
										}
	
										$collectingRules[ $ruleType ]['common_rule_value'] = array_merge(
											$collectingRules[ $ruleType ]['common_rule_value'], 
											$seasonRuleData['rule_value']
										);
									}

									$collectingRules[ $ruleType ]['room_type_ids'][] = $roomTypeId;

									if ( 0 === $roomTypeId || $this->countOfAllRoomTypeOriginalIds === count( $collectingRules[ $ruleType ]['room_type_ids'] ) ) {
			
										if ( 'check_in_days' === $ruleType || 'check_out_days' === $ruleType ) {
			
											$collectingRules[ $ruleType ]['common_rule_value'] = array_unique( $collectingRules[ $ruleType ]['common_rule_value'] );
										}
			
										$result[ $ruleType ] = $collectingRules[ $ruleType ]['common_rule_value'];
									}

									// we collect only first suitable rule value for each roomTypeId
									break;
								}
							}
						}
					}
				}

			} elseif ( isset( $this->reservationRulesByRoomTypeIds[ $roomTypeOriginalId ] ) ) {

				// find reservation rules data for certain room type id
				foreach ( $this->reservationRulesByRoomTypeIds[ $roomTypeOriginalId ] as $ruleType => $ruleData ) {

					foreach ( $ruleData as $seasonRuleData ) {

						$season = isset( $this->seasons[ $seasonRuleData['season_id'] ] ) ? $this->seasons[ $seasonRuleData['season_id'] ] : null;

						if ( 0 === $seasonRuleData['season_id'] ||
							( null !== $season && $season->isDateInSeason( $requestedDate ) )
						) {

							$result[ $ruleType ] = $seasonRuleData['rule_value'];
							break;
						}
					}
				}
			}

			// find custom rules data for requested date
			foreach ( $this->customRules as $ruleData ) {

				if ( ( $roomTypeOriginalId === $ruleData['room_type_id'] || 0 === $ruleData['room_type_id'] ) &&
					( $requestedDateString >= $ruleData['date_from'] && $requestedDateString <= $ruleData['date_to'] )
				) {

					$roomId = $ruleData['room_id'];

					if ( 0 < $roomId ) {

						$result['custom_rules_for_room_id'][ $roomId ]['not_check_in'] = ( isset( $result['custom_rules_for_room_id'][ $roomId ]['not_check_in'] ) && 
							$result['custom_rules_for_room_id'][ $roomId ]['not_check_in'] ) || 
							( isset( $ruleData['not_check_in'] ) && $ruleData['not_check_in'] );

						$result['custom_rules_for_room_id'][ $roomId ]['not_check_out'] = (	isset( $result['custom_rules_for_room_id'][ $roomId ]['not_check_out'] ) &&
							$result['custom_rules_for_room_id'][ $roomId ]['not_check_out'] ) || 
							( isset( $ruleData['not_check_out'] ) && $ruleData['not_check_out'] );

						$result['custom_rules_for_room_id'][ $roomId ]['not_stay_in'] = ( isset( $result['custom_rules_for_room_id'][ $roomId ]['not_stay_in'] ) &&
							$result['custom_rules_for_room_id'][ $roomId ]['not_stay_in'] ) ||
							( isset( $ruleData['not_stay_in'] ) && $ruleData['not_stay_in'] );

						if ( ! isset( $result['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] ) ) {

							$result['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] = $ruleData['custom_rule_comment'];

						} else {

							$result['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] .= ', ' . $ruleData['custom_rule_comment'];
						}

					} else {

						$result['not_check_in'] = ( isset( $result['not_check_in'] ) && $result['not_check_in'] ) || 
								( isset( $ruleData['not_check_in'] ) && $ruleData['not_check_in'] );

						$result['not_check_out'] = ( isset( $result['not_check_out'] ) && $result['not_check_out'] ) ||
							( isset( $ruleData['not_check_out'] ) && $ruleData['not_check_out'] );

						$result['not_stay_in'] = ( isset( $result['not_stay_in'] ) && $result['not_stay_in'] ) ||
							( isset( $ruleData['not_stay_in'] ) && $ruleData['not_stay_in'] );

						if ( ! isset( $result['custom_rule_comment'] ) ) {

							$result['custom_rule_comment'] = $ruleData['custom_rule_comment'];

						} else {

							$result['custom_rule_comment'] .= ', ' . $ruleData['custom_rule_comment'];
						}
					}
				}
			}

			$result = array_merge(
				array(
					'check_in_days'            => array( 0, 1, 2, 3, 4, 5, 6 ),
					'in_check_in_days'         => true,
					'check_out_days'           => array( 0, 1, 2, 3, 4, 5, 6 ),
					'in_check_out_days'        => true,
					'min_advance_reservation'  => 0,
					'max_advance_reservation'  => 0,
					'min_stay_length'          => 1,
					'max_stay_length'          => 0,
					'buffer_days'              => 0,
					'not_check_in'             => false,
					'not_check_out'            => false,
					'not_stay_in'              => false,
					'custom_rules_comment'     => '',
					'custom_rules_for_room_id' => array(),
				),
				$result
			);

			$requestedDateWeekDay = (int) $requestedDate->format( 'w' );

			$result['in_check_in_days'] = in_array( $requestedDateWeekDay, $result['check_in_days'] );

			$result['in_check_out_days'] = in_array( $requestedDateWeekDay, $result['check_out_days'] );

			/**
			 * @since 4.10.0
			 *
			 * @param array $bookingRules
			 * @param int $roomTypeId
			 * @param \DateTime $date
			 */
			$result = apply_filters( 'mphb_get_booking_rules_for_date', $result, $roomTypeOriginalId, $requestedDate );

			$this->cachedRulesByDates[ $requestedDateString ][ $roomTypeOriginalId ] = $result;
		}

		return $this->cachedRulesByDates[ $requestedDateString ][ $roomTypeOriginalId ];
	}

	private static function getCheckInWithCorrectTimeInSiteTimeZone( \DateTime $checkInDate ): \DateTime {

		$checkInDateTime = clone $checkInDate;
		$checkInDateTime->setTimezone( DateUtils::getSiteTimeZone() );
		$checkInTime = MPHB()->settings()->dateTime()->getCheckInTime( true );
		$checkInDateTime->setTime( $checkInTime[0], $checkInTime[1], $checkInTime[2] );
		return $checkInDateTime;
	}

	private static function getCheckOutWithCorrectTimeInSiteTimeZone( \DateTime $checkOutDate ): \DateTime {

		$checkOutDateTime = clone $checkOutDate;
		$checkOutDateTime->setTimezone( DateUtils::getSiteTimeZone() );
		$checkOutTime = MPHB()->settings()->dateTime()->getCheckOutTime( true );
		$checkOutDateTime->setTime( $checkOutTime[0], $checkOutTime[1], $checkOutTime[2] );
		return $checkOutDateTime;
	}


	public function isCheckInEarlierThanMinAdvanceDate( int $roomTypeOriginalId, \DateTime $checkInDate, bool $isIgnoreBookingRules ) {

		$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $checkInDate );

		return ! $isIgnoreBookingRules &&
			$this->hasMinAdvanceReservationRules &&
			DateUtils::calcNightsSinceToday( $checkInDateTime ) < $this->getMinAdvanceReservationDaysCount(
				$roomTypeOriginalId,
				$checkInDateTime,
				$isIgnoreBookingRules
			);
	}


	public function getMinAdvanceReservationDaysCount( int $roomTypeOriginalId, \DateTime $requestedDate, bool $isIgnoreBookingRules ): int {

		$result = 0;

		if ( ! $isIgnoreBookingRules && $this->hasMinAdvanceReservationRules ) {

			$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $requestedDate );
			$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $checkInDateTime );
			$result = $bookingRules['min_advance_reservation'];
		}

		return $result;
	}


	public function isCheckInLaterThanMaxAdvanceDate( int $roomTypeOriginalId, \DateTime $checkInDate, bool $isIgnoreBookingRules ) {

		$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $checkInDate );

		$maxStayDaysCount = $this->getMaxAdvanceReservationDaysCount(
			$roomTypeOriginalId,
			$checkInDateTime,
			$isIgnoreBookingRules
		);

		return ! $isIgnoreBookingRules &&
			$this->hasMaxAdvanceReservationRules &&
			0 < $maxStayDaysCount &&
			DateUtils::calcNightsSinceToday( $checkInDateTime ) > $maxStayDaysCount;
	}


	public function getMaxAdvanceReservationDaysCount( int $roomTypeOriginalId, \DateTime $requestedDate, bool $isIgnoreBookingRules ): int {

		$result = 0;

		if ( ! $isIgnoreBookingRules && $this->hasMaxAdvanceReservationRules ) {

			$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $requestedDate );
			$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $checkInDateTime );
			$result = $bookingRules['max_advance_reservation'];
		}

		return $result;
	}


	public function getMinStayNightsCountForAllSeasons( int $roomTypeOriginalId ): int {

		$result = 1;

		if ( isset( $this->reservationRulesByRoomTypeIds[ $roomTypeOriginalId ]['min_stay_length'] ) ) {

			foreach ( $this->reservationRulesByRoomTypeIds[ $roomTypeOriginalId ]['min_stay_length'] as $ruleData ) {

				if ( 0 === $ruleData['season_id'] ) {

					$result = $ruleData['rule_value'];
					$isRuleFound = true;
					break;
				}
			}
		}

		return $result;
	}


	public function isMinStayNightsRuleViolated( int $roomTypeOriginalId, \DateTime $checkInDate, \DateTime $checkOutDate, bool $isIgnoreBookingRules ) {

		$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $checkInDate );
		$checkOutDateTime = self::getCheckOutWithCorrectTimeInSiteTimeZone( $checkOutDate );

		return ! $isIgnoreBookingRules &&
			$this->hasMinStayLengthRules &&
			DateUtils::calcNights( $checkInDateTime, $checkOutDateTime ) < $this->getMinStayNightsCount(
				$roomTypeOriginalId,
				$checkInDateTime,
				$isIgnoreBookingRules
			);
	}


	public function getMinStayNightsCount( int $roomTypeOriginalId, \DateTime $requestedDate, bool $isIgnoreBookingRules ): int {

		$result = 1;

		if ( ! $isIgnoreBookingRules && $this->hasMinStayLengthRules ) {

			$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $requestedDate );
			$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $checkInDateTime );
			$result = $bookingRules['min_stay_length'];
		}

		return $result;
	}


	public function isMaxStayNightsRuleViolated( int $roomTypeOriginalId, \DateTime $checkInDate, \DateTime $checkOutDate, bool $isIgnoreBookingRules ) {

		$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $checkInDate );
		$checkOutDateTime = self::getCheckOutWithCorrectTimeInSiteTimeZone( $checkOutDate );

		$maxStayDaysCount = $this->getMaxStayNightsCount(
			$roomTypeOriginalId,
			$checkInDateTime,
			$isIgnoreBookingRules
		);

		return ! $isIgnoreBookingRules &&
			$this->hasMaxStayLengthRules &&
			0 < $maxStayDaysCount &&
			DateUtils::calcNights( $checkInDateTime, $checkOutDateTime ) > $maxStayDaysCount;
	}


	public function getMaxStayNightsCount( int $roomTypeOriginalId, \DateTime $requestedDate, bool $isIgnoreBookingRules ): int {

		$result = 0;

		if ( ! $isIgnoreBookingRules && $this->hasMaxStayLengthRules ) {

			$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $requestedDate );
			$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $checkInDateTime );
			$result = $bookingRules['max_stay_length'];
		}

		return $result;
	}


	public function hasBufferDaysRules( bool $isIgnoreBookingRules ): bool {

		return ! $isIgnoreBookingRules && $this->hasBufferDaysRules;
	}


	public function getBufferDaysCount( int $roomTypeOriginalId, \DateTime $requestedDate, bool $isIgnoreBookingRules ): int {

		$result = 0;

		if ( ! $isIgnoreBookingRules && $this->hasBufferDaysRules ) {

			$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $requestedDate );
			$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $checkInDateTime );
			$result = $bookingRules['buffer_days'];
		}

		return $result;
	}


	public function getBlockedRoomsCountForRoomType( int $roomTypeOriginalId, \DateTime $requestedDate, bool $isIgnoreBookingRules ): int {

		$result = 0;

		if ( ! $isIgnoreBookingRules && $this->hasNotStayInRules ) {

			$availableRoomsCount = mphb_rooms_facade()->getActiveRoomsCountForRoomType( $roomTypeOriginalId );

			if ( 0 < $availableRoomsCount ) {

				$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $requestedDate );
				$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $checkInDateTime );

				if ( $bookingRules['not_stay_in'] ) {

					$result = $availableRoomsCount;

				} elseif ( 0 < count( $bookingRules['custom_rules_for_room_id'] ) ) {

					foreach ( $bookingRules['custom_rules_for_room_id'] as $roomSpecificRuleData ) {

						if ( $roomSpecificRuleData['not_stay_in'] ) {
							$result++;
						}
					}
				}
			}
		}

		return $result;
	}


	public function isCheckInNotAllowed( int $roomTypeOriginalId, \DateTime $checkInDate, bool $isIgnoreBookingRules ): bool {

		$result = false;

		if ( ! $isIgnoreBookingRules &&
			( $this->hasNotCheckInRules || $this->hasCheckInDaysRules )
		) {

			$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $checkInDate );
			$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $checkInDateTime );
			$result = $bookingRules['not_check_in'] || ! $bookingRules['in_check_in_days'];
		}

		return $result;
	}


	public function isCheckOutNotAllowed( int $roomTypeOriginalId, \DateTime $checkOutDate, bool $isIgnoreBookingRules ): bool {

		$result = false;

		if ( ! $isIgnoreBookingRules &&
			( $this->hasNotCheckOutRules || $this->hasCheckOutDaysRules )
		) {

			$checkOutDateTime = self::getCheckOutWithCorrectTimeInSiteTimeZone( $checkOutDate );
			$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $checkOutDateTime );
			$result = $bookingRules['not_check_out'] || ! $bookingRules['in_check_out_days'];
		}

		return $result;
	}

	public function isStayInNotAllowed( int $roomTypeOriginalId, \DateTime $checkInDate, \DateTime $checkOutDate, bool $isIgnoreBookingRules ): bool {

		if ( ! $isIgnoreBookingRules && $this->hasNotStayInRules ) {

			$testingDate = self::getCheckInWithCorrectTimeInSiteTimeZone( $checkInDate );
			$checkOutDateString = self::getCheckOutWithCorrectTimeInSiteTimeZone( $checkOutDate )->format('Ymd');

			do {

				$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $testingDate );

				if ( $bookingRules['not_stay_in'] ) {

					return true;
				}

				$testingDate->modify( '+1 day' );
				$testingDateString = $testingDate->format('Ymd');

			} while ( $testingDateString < $checkOutDateString );
		}

		return false;
	}


	public function isBookingRulesViolated( int $roomTypeOriginalId, \DateTime $checkInDate, \DateTime $checkOutDate, bool $isIgnoreBookingRules ): bool {

		// do not correct check-in check-out dates because we do this in each method separatly
		return $this->isCheckInEarlierThanMinAdvanceDate( $roomTypeOriginalId, $checkInDate, $isIgnoreBookingRules ) ||
			$this->isCheckInLaterThanMaxAdvanceDate( $roomTypeOriginalId, $checkInDate, $isIgnoreBookingRules ) ||
			$this->isMinStayNightsRuleViolated( $roomTypeOriginalId, $checkInDate, $checkOutDate, $isIgnoreBookingRules ) ||
			$this->isMaxStayNightsRuleViolated( $roomTypeOriginalId, $checkInDate, $checkOutDate, $isIgnoreBookingRules ) ||
			$this->isCheckInNotAllowed( $roomTypeOriginalId, $checkInDate, $isIgnoreBookingRules ) ||
			$this->isCheckOutNotAllowed( $roomTypeOriginalId, $checkOutDate, $isIgnoreBookingRules ) ||
			$this->isStayInNotAllowed( $roomTypeOriginalId, $checkInDate, $checkOutDate, $isIgnoreBookingRules );
	}

	/**
	 * @return int[]
	 */
	public function getUnavailableRoomIds( int $roomTypeOriginalId, \DateTime $checkInDate, \DateTime $checkOutDate, bool $isIgnoreBookingRules ) {

		$checkInDateTime = self::getCheckInWithCorrectTimeInSiteTimeZone( $checkInDate );
		$checkOutDateTime = self::getCheckOutWithCorrectTimeInSiteTimeZone( $checkOutDate );

		if ( $isIgnoreBookingRules ) {
			return array();

		} elseif ( ! $this->hasCheckInDaysRules && ! $this->hasCheckOutDaysRules &&
			! $this->hasMinAdvanceReservationRules && ! $this->hasMaxAdvanceReservationRules &&
			! $this->hasMinStayLengthRules && ! $this->hasMaxStayLengthRules &&
			! $this->hasNotCheckInRules && ! $this->hasNotCheckOutRules && ! $this->hasNotStayInRules
		) {
			return array();

		} elseif ( $this->isBookingRulesViolated( $roomTypeOriginalId, $checkInDateTime, $checkOutDateTime, $isIgnoreBookingRules ) ) {

			return MPHB()->getRoomPersistence()->findAllIdsByType( $roomTypeOriginalId );
		}

		$unavailableRoomIds = array();

		$testingDate = clone $checkInDateTime;
		$checkOutDateString = $checkOutDateTime->format('Ymd');

		do {

			$bookingRules = $this->getBookingRulesForDate( $roomTypeOriginalId, $testingDate );

			foreach ( $bookingRules['custom_rules_for_room_id'] as $roomId => $roomSpecificRuleData ) {

				if ( $roomSpecificRuleData['not_stay_in'] ) {

					$unavailableRoomIds[] = $roomId;
				}
			}

			$testingDate->modify( '+1 day' );
			$testingDateString = $testingDate->format('Ymd');

		} while ( $testingDateString < $checkOutDateString );

		$unavailableRoomIds = array_unique( $unavailableRoomIds );
		// reset keys after array_unique() and sort room ids
		sort( $unavailableRoomIds );

		return $unavailableRoomIds;
	}

	/**
	 * @since 4.10.0 added new parameter - $period.
	 *
	 * @param DatePeriod|array $period Optional. Null by default (not limited by period).
	 * @return array [ room_id (int) => [ date (string as Y-m-d) => 'comment_1, comment_2, ...' ], ... ]
	 */
	public function getNotStayInComments( int $roomTypeOriginalId, array $roomIds, $period = null ) {

		$result = array();

		foreach ( $this->customRules as $ruleData ) {

			if ( $roomTypeOriginalId === $ruleData['room_type_id'] || 0 === $ruleData['room_type_id'] ) {

				if ( $ruleData['not_stay_in'] ) {

					$ruleDate = \DateTime::createFromFormat( 'Ymd', $ruleData['date_from'], DateUtils::getSiteTimeZone() );
					$ruleDateString = $ruleDate->format('Ymd');
					$formattedRuleDate = $ruleDate->format('Y-m-d');

					if ( ! is_null( $period )
						&& ! DateUtils::isPeriodsIntersect( $ruleData['date_period'], $period )
					) {
						continue; // Skip rule
					}

					$roomId = $ruleData['room_id'];

					do {

						if ( 0 === $roomId ) {

							foreach ( $roomIds as $id ) {

								if ( empty( $result[ $id ][ $formattedRuleDate ] ) ) {
									$result[ $id ][ $formattedRuleDate ] = $ruleData['custom_rule_comment'];
								} else {
									$result[ $id ][ $formattedRuleDate ] .= ', ' . $ruleData['custom_rule_comment'];
								}
							}
						} elseif ( in_array( $roomId, $roomIds ) ) {

							if ( empty( $result[ $roomId ][ $formattedRuleDate ] ) ) {
								$result[ $roomId ][ $formattedRuleDate ] = $ruleData['custom_rule_comment'];
							} else {
								$result[ $roomId ][ $formattedRuleDate ] .= ', ' . $ruleData['custom_rule_comment'];
							}
						}

						$ruleDate->modify( '+1 day' );
						$ruleDateString = $ruleDate->format('Ymd');
						$formattedRuleDate = $ruleDate->format('Y-m-d');

					} while ( $ruleDateString <= $ruleData['date_to'] );
				}
			}
		}

		/**
		 * @since 4.10.0
		 *
		 * @param array $calendarComments
		 * @param int $roomTypeId
		 * @param int[] $roomIds
		 * @param ?array $period
		 */
		$result = apply_filters( 'mphb_get_calendar_comments_for_room_type', $result, $roomTypeOriginalId, $roomIds, $period );

		return $result;
	}

	/**
	 * @return array [ [ 
	 *                  'roomTypeId' => int,
	 *                  'roomId'     => int,
	 *                  'startDate'  => string (Ymd),
	 *                  'endDate'    => string (Ymd),
	 *                  'comment'    => string
	 *                  
	 *               ], ... ]
	 */
	public function getNotStayInRulesData( int $roomTypeOriginalId, int $requestedRoomId ) {

		$result = array();

		foreach ( $this->customRules as $ruleData ) {

			if ( $ruleData['not_stay_in'] &&
				( $roomTypeOriginalId === $ruleData['room_type_id'] || 0 === $ruleData['room_type_id'] ) &&
				( 0 === $requestedRoomId || 0 === $ruleData['room_id'] || $requestedRoomId === $ruleData['room_id'] )
			) {

				$timeZone  = DateUtils::getSiteTimeZone();
				$startDate = \DateTime::createFromFormat( 'Ymd', $ruleData['date_from'], $timeZone );
				$endDate   = \DateTime::createFromFormat( 'Ymd', $ruleData['date_to'], $timeZone );

				$result[] = array(
					'roomTypeId' => $roomTypeOriginalId,
					'roomId'     => $requestedRoomId,
					'startDate'  => $startDate,
					'endDate'    => $endDate,
					'comment'    => $ruleData['custom_rule_comment'],
				);
			}
		}

		/**
		 * @since 4.10.0
		 *
		 * @param array $adminBlocks
		 * @param int $roomTypeId
		 * @param int $roomId
		 */
		$result = apply_filters( 'mphb_get_admin_blocks_for_export', $result, $roomTypeOriginalId, $requestedRoomId );

		return $result;
	}
}
