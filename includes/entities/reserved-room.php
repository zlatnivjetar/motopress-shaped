<?php

namespace MPHB\Entities;

use MPHB\Core\PriceBreakdownHelper;

class ReservedRoom {

	/**
	 *
	 * @var int
	 */
	private $id;

	/**
	 *
	 * @var int
	 */
	private $roomId;

	/**
	 *
	 * @var int
	 */
	private $bookingId;

	/**
	 *
	 * @var int
	 */
	private $rateId;

	/**
	 *
	 * @var int
	 */
	private $adults;

	/**
	 *
	 * @var int
	 */
	private $children;

	/**
	 *
	 * @var ReservedService[]
	 */
	private $reservedServices;

	/**
	 *
	 * @var string
	 */
	private $guestName;

	/**
	 *
	 * @var string
	 */
	private $status;

	/**
	 *
	 * @var string
	 */
	private $uid;

	/**
	 * @var array
	 */
	private $cachedRoomPriceBreakdown = null;
	/**
	 *
	 * @param array              $atts Array of atts
	 * @param int                $atts['id'] Id of room reservation record
	 * @param int                $atts['room_id'] Id of room
	 * @param int                $atts['booking_id'] Id of booking
	 * @param int                $atts['rate_id'] Id of reserved rate
	 * @param int                $atts['adults'] Adults count
	 * @param int                $atts['children'] Children count
	 * @param \ReservedService[] $atts['reserved_services'] Array of Reserved Services
	 * @param string             $atts['guest_name'] Full name of guest
	 * @param string             $atts['status'] Status. Optional. Publish by default.
	 */
	public function __construct( $atts ) {
		if ( isset( $atts['id'] ) ) {
			$this->id = $atts['id'];
		}

		if ( isset( $atts['room_id'] ) ) {
			$this->roomId = (int) $atts['room_id'];
		}

		// Rate ID can be 0. See also \MPHB\Entities\Booking::isImported()
		$this->rateId = (int) $atts['rate_id'];

		$this->adults   = (int) $atts['adults'];
		$this->children = (int) $atts['children'];

		$this->reservedServices = isset( $atts['reserved_services'] ) ? $atts['reserved_services'] : array();

		$this->guestName = isset( $atts['guest_name'] ) ? $atts['guest_name'] : '';

		$this->bookingId = isset( $atts['booking_id'] ) ? (int) $atts['booking_id'] : 0;

		$this->status = isset( $atts['status'] ) ? $atts['status'] : 'publish';

		if ( array_key_exists( 'uid', $atts ) ) { // isset() will return false for null
			$this->uid = $atts['uid'];
		} else {
			$this->uid = mphb_generate_uid();
		}
	}

	/**
	 *
	 * @param array              $atts Array of atts
	 * @param int                $atts['id'] Id of room reservation record
	 * @param int                $atts['room_id'] Id of room
	 * @param int                $atts['booking_id'] Id of booking
	 * @param int                $atts['rate_id'] Id of reserved rate
	 * @param int                $atts['adults'] Adults count
	 * @param int                $atts['children'] Children count
	 * @param \ReservedService[] $atts['reserved_services'] Array of Reserved Services
	 * @param string             $atts['guest_name'] Full name of guest
	 * @return ReservedRoom
	 */
	public static function create( $atts ) {
		return new self( $atts );
	}

	/**
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 *
	 * @return int
	 */
	public function getRoomId() {
		return $this->roomId;
	}

	/**
	 *
	 * @return int
	 */
	public function getRateId() {
		return $this->rateId;
	}

	/**
	 * @param string $languageCode = 'current' or some language code.
	 *                               If empty then returns rate without translation.
	 * @return Rate|null
	 */
	public function getRate( $languageCode = '' ) {

		return mphb_prices_facade()->getRateById(
			$this->getRateId(),
			$languageCode
		);
	}

	/**
	 * @param string $languageCode = 'current' or some language code.
	 *                               If empty then returns rate without translation.
	 * @return string
	 */
	public function getRateTitle( $languageCode = '' ) {

		$rate = $this->getRate( $languageCode );

		return $rate ? $rate->getTitle() : '';
	}

	/**
	 * @param string $languageCode = 'current' or some language code.
	 *                               If empty then returns rate without translation.
	 * @return string
	 */
	public function getRateDescription( $languageCode = '' ) {

		$rate = $this->getRate( $languageCode );

		return $rate ? $rate->getDescription() : '';
	}

	/**
	 *
	 * @return int
	 */
	public function getBookingId() {
		return $this->bookingId;
	}

	/**
	 * @return Booking|null
	 */
	public function getBooking() {
		// we do not need to cache it because repository does this
		return MPHB()->getBookingRepository()->findById( $this->bookingId );
	}

	/**
	 * Retrieve room type id of reserved room
	 *
	 * @return int
	 */
	public function getRoomTypeId() {
		$roomTypeId = 0;
		if ( isset( $this->roomId ) ) {
			$room = MPHB()->getRoomRepository()->findById( $this->roomId );
			if ( $room ) {
				$roomTypeId = $room->getRoomTypeId();
			}
		}
		if ( ! $roomTypeId && isset( $this->rateId ) ) {
			$rate = $this->getRate();
			if ( $rate ) {
				$roomTypeId = $rate->getRoomTypeId();
			}
		}
		return $roomTypeId;
	}

	/**
	 * @return RoomType|null
	 */
	public function getRoomType() {
		// we do not need to cache it because repository does this
		return MPHB()->getRoomTypeRepository()->findById( $this->getRoomTypeId() );
	}

	/**
	 *
	 * @return int
	 */
	public function getAdults() {
		return $this->adults;
	}

	/**
	 *
	 * @return int
	 */
	public function getChildren() {
		return $this->children;
	}

	/**
	 *
	 * @return ReservedService[]
	 */
	public function getReservedServices() {
		return $this->reservedServices;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return int[]
	 */
	public function getReservedServiceIds() {
		$serviceIds = array_map(
			function ( $reservedService ) {
				return (int) $reservedService->getId();
			},
			$this->reservedServices
		);

		return $serviceIds;
	}

	/**
	 *
	 * @return string
	 */
	public function getGuestName() {
		return $this->guestName;
	}

	/**
	 *
	 * @return string
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 *
	 * @return string
	 */
	public function getUid() {
		return $this->uid;
	}

	/**
	 *
	 * @param \DateTime $checkInDate
	 * @param \DateTime $checkOutDate
	 * @return float
	 */
	public function calcRoomPrice( $checkInDate, $checkOutDate ) {

		$price = 0;

		if ( ! empty( $this->rateId ) ) {
			MPHB()->reservationRequest()->setupParameters(
				array(
					'adults'         => $this->getAdults(),
					'children'       => $this->getChildren(),
					'check_in_date'  => $checkInDate,
					'check_out_date' => $checkOutDate,
				)
			);

			$rate = $this->getRate();

			// Rate still exists? (Example: old booking and removed rate)
			if ( ! is_null( $rate ) ) {
				$price = $rate->calcPrice( $checkInDate, $checkOutDate );
			}
		}

		return $price;
	}

	/**
	 * @return array|null Stored price breakdown data from booking or null.
	 */
	public function getLastRoomPriceBreakdown() {
		return PriceBreakdownHelper::getLastRoomPriceBreakdown( $this );
	}

	/**
	 * @param \DateTime $checkInDate
	 * @param \DateTime $checkOutDate
	 *
	 * @return array [%yyyy-mm-dd% => %price%]
	 */
	public function getRoomPriceBreakdown( $checkInDate, $checkOutDate ) {

		$breakdown = array();

		if ( ! empty( $this->rateId ) ) {
			MPHB()->reservationRequest()->setupParameters(
				array(
					'adults'         => $this->getAdults(),
					'children'       => $this->getChildren(),
					'check_in_date'  => $checkInDate,
					'check_out_date' => $checkOutDate,
				)
			);

			$rate      = $this->getRate();
			$breakdown = $rate->getPriceBreakdown( $checkInDate, $checkOutDate );
		}

		return $breakdown;
	}

	/**
	 * @param int $bookingId
	 */
	public function setBookingId( $bookingId ) {
		$this->bookingId = $bookingId;
	}

	public function setUid( $uid ) {
		$this->uid = $uid;
	}
}
