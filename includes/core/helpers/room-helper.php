<?php

namespace MPHB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 5.0.0
 */
class RoomHelper {
	/**
	 * @param int $roomId
	 * @return int Room type ID or 0.
	 */
	public static function getRoomTypeId( $roomId ) {
		$roomTypeId = get_post_meta( $roomId, 'mphb_room_type_id', true );

		if ( $roomTypeId !== false && $roomTypeId !== '' ) {
			return absint( $roomTypeId );
		} else {
			return 0;
		}
	}
}
