<?php

namespace MPHB;

use MPHB\Utils\BookingUtils;
use MPHB\Utils\DateUtils;
use DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 4.10.0
 */
class LinkedRooms {

	/**
	 * @var array [
	 *     room_type_id (int) => [
	 *         linked_room_id (int) => room_ids (int[]),
	 *         'all' => all_linked_room_ids (int[])
	 *     ]
	 * ]
	 */
	protected static $cachedLinkedRoomsByRoomType = array();

	protected $comment = '';

	public function __construct() {

		// we must init translated strings after init hook
		add_action(
			'init',
			function () {

				$this->comment = esc_html__( 'Blocked because the linked accommodation is booked', 'motopress-hotel-booking' );
			}
		);

		add_filter( 'mphb_has_not_stay_in_rules', array( $this, 'checkForLinkedBookings' ) );
		add_filter( 'mphb_get_booking_rules_for_date', array( $this, 'extendBookingRulesForDate' ), 10, 3 );
		add_filter( 'mphb_get_admin_blocks_for_export', array( $this, 'extendAdminBlocksForExport' ), 10, 3 );
		add_filter( 'mphb_get_calendar_comments_for_room_type', array( $this, 'extendBookingCalendarBlocks' ), 10, 4 );
	}

	/**
	 * @access protected
	 *
	 * @param bool $hasNotStayInRules
	 * @return bool
	 */
	public function checkForLinkedBookings( $hasNotStayInRules ) {
		if ( ! $hasNotStayInRules ) {
			$hasNotStayInRules = static::hasBookingsForLinkedRooms();
		}

		return $hasNotStayInRules;
	}

	/**
	 * @access protected
	 *
	 * @see Core\BookingRulesData::getBookingRulesForDate()
	 *
	 * @param array $bookingRules
	 * @param int $roomTypeId
	 * @param DateTime $date
	 * @return array Extended booking rules.
	 */
	public function extendBookingRulesForDate( $bookingRules, $roomTypeId, $date ) {
		$linkedRoomIds = static::getLinkedRoomsForRoomType( $roomTypeId );

		if ( empty( $linkedRoomIds['all'] ) ) {
			return $bookingRules;
		}

		$linkedBookings = static::findLinkedBookingsForRoomType( $date, $date, $roomTypeId );
		$linkedBlocks   = BookingUtils::convertToBlocks( $linkedBookings, $this->comment );

		foreach ( $linkedBlocks as $block ) {
			foreach ( $block['room_ids'] as $linkedRoomId ) {
				if ( in_array( $linkedRoomId, $linkedRoomIds['all'] ) ) {
					foreach ( $linkedRoomIds[ $linkedRoomId ] as $roomId ) {
						$bookingRules['custom_rules_for_room_id'][ $roomId ]['not_stay_in'] = true;

						if ( empty( $bookingRules['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] ) ) {
							$bookingRules['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] = $block['comment'];
						} else {
							$bookingRules['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] .= ', ' . $block['comment'];
						}
					}
				}
			}
		}

		return $bookingRules;
	}

	/**
	 * @access protected
	 *
	 * @param array $adminBlocks
	 * @param int $roomTypeId
	 * @param int $roomId
	 * @return array [
	 *     [
	 *         'roomTypeId' => int,
	 *         'roomId'     => int,
	 *         'startDate'  => DateTime,
	 *         'endDate'    => DateTime,
	 *         'comment'    => string,
	 *     ],
	 *     ...
	 * ]
	 */
	public function extendAdminBlocksForExport( $adminBlocks, $roomTypeId, $roomId ) {
		$linkedRoomIds = MPHB()->getRoomRepository()->getLinkedRoomIds( $roomId );

		if ( empty( $linkedRoomIds ) ) {
			return $adminBlocks;
		}

		$linkedBookings = MPHB()->getBookingRepository()->findAll( array(
			'room_locked' => true,
			'rooms'       => $linkedRoomIds,
		) );

		$linkedBlocks = BookingUtils::convertToBlocks( $linkedBookings, $this->comment );

		foreach ( $linkedBlocks as $block ) {
			$adminBlocks[] = array(
				'roomTypeId' => $roomTypeId,
				'roomId'     => $roomId,
				'startDate'  => $block['date_from'],
				'endDate'    => $block['date_to'],
				'comment'    => $block['comment'],
			);
		}

		return $adminBlocks;
	}

	/**
	 * @access protected
	 *
	 * @see \MPHB\Core\Data\BookingRulesData::getNotStayInComments()
	 *
	 * @param array $blockComments
	 * @param int $roomTypeId
	 * @param int[] $roomIds
	 * @param ?array $period
	 * @return array [
	 *     room_id (int) => [
	 *         date (string, 'Y-m-d') => 'Comment 1, Comment 2, ...'
	 *     ]
	 * ]
	 */
	public function extendBookingCalendarBlocks( $blockComments, $roomTypeId, $roomIds, $period ) {
		// Skip requests without period
		if ( empty( $period ) ) {
			return $blockComments;
		}

		list( $dateFrom, $dateTo ) = DateUtils::getPeriodRangeDates( $period );

		$linkedRoomIds = MPHB()->getRoomRepository()->getLinkedRoomIds( $roomIds );

		$linkedBookings = static::findLinkedBookingsForRoomType( $dateFrom, $dateTo, $roomTypeId );
		$linkedBlocks   = BookingUtils::convertToBlocks( $linkedBookings, $this->comment );

		foreach ( $linkedBlocks as $block ) {
			$periodDates = array_keys( DateUtils::getPeriodDates( $block['date_period'], true ) ); // 'Y-m-d'[]

			foreach ( $roomIds as $roomId ) {
				$blockLinkedRoomIds = array_intersect( $linkedRoomIds[ $roomId ], $block['room_ids'] );

				if ( ! empty( $blockLinkedRoomIds ) ) {
					foreach ( $periodDates as $date ) {
						if ( empty( $blockComments[ $roomId ][ $date ] ) ) {
							$blockComments[ $roomId ][ $date ] = $block['comment'];
						} else {
							$blockComments[ $roomId ][ $date ] .= ', ' . $block['comment'];
						}
					}
				}
			} // For each room ID
		} // For each block

		return $blockComments;
	}

	/**
	 * @global \wpdb $wpdb
	 *
	 * @return bool
	 */
	public static function hasBookingsForLinkedRooms() {
		global $wpdb;

		// The answer isn't 100% accurate because the query doesn't look for
		// distinct values, but that doesn't matter here - we need at least 1
		$linkedRoomsBooked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->posts}` AS `reserved_room` INNER JOIN `{$wpdb->postmeta}` AS `postmeta` ON `postmeta`.`meta_value` = `reserved_room`.`ID` WHERE `postmeta`.`meta_key` = 'mphb_linked_room'" );

		return $linkedRoomsBooked > 0;
	}

	/**
	 * @param DateTime|string $dateFrom
	 * @param DateTime|string $dateTo
	 * @param int $roomTypeId
	 * @return Entities\Booking[]
	 */
	public static function findLinkedBookingsForRoomType( $dateFrom, $dateTo, $roomTypeId ) {
		$linkedRoomIds = static::getLinkedRoomsForRoomType( $roomTypeId );

		return MPHB()->getBookingRepository()->findAllInPeriod( $dateFrom, $dateTo, array(
			'room_locked'         => true,
			'rooms'               => $linkedRoomIds['all'],
			'period_edge_overlap' => array(
				'check_in'  => true,
				'check_out' => false, // Check-outs will create +1 blocked day
			),
		) );
	}

	/**
	 * @param int $roomTypeId
	 * @return array
	 */
	protected static function getLinkedRoomsForRoomType( $roomTypeId ) {
		if ( ! isset( static::$cachedLinkedRoomsByRoomType[ $roomTypeId ] ) ) {
			$roomIds = MPHB()->getRoomRepository()->findIds( array( 'room_type_id' => $roomTypeId ) );

			if ( ! empty( $roomIds ) ) {
				$linkedRoomIds = MPHB()->getRoomRepository()->getLinkedRoomIds( $roomIds, 'reverse', true );

				static::$cachedLinkedRoomsByRoomType[ $roomTypeId ] = $linkedRoomIds;
			} else {
				static::$cachedLinkedRoomsByRoomType[ $roomTypeId ] = array( 'all' => array() );
			}
		}

		return static::$cachedLinkedRoomsByRoomType[ $roomTypeId ];
	}

}
