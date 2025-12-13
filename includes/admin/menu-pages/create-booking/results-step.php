<?php

namespace MPHB\Admin\MenuPages\CreateBooking;

/**
 * Second step.
 */
class ResultsStep extends Step {

	/**
	 * @var int
	 */
	protected $roomTypeId = 0;

	/**
	 * @var int
	 */
	protected $adults = -1;

	/**
	 * @var int
	 */
	protected $children = -1;

	/**
	 * [%Room type ID% => [title, rooms => [id, type_id, title, adults, children, price]]]
	 *
	 * @var array
	 */
	protected $rooms = array();

	public function __construct() {
		parent::__construct( 'results' );
	}

	public function setup() {
		parent::setup();

		/** @see \MPHB\Admin\MenuPages\CreateBooking\Step::render() */
		add_action( "mphb_cb_{$this->name}_after_start", array( $this, 'printWrapperHeader' ) );

		/** @see templates/create-booking/results/reserve-rooms.php */
		add_action( 'mphb_cb_reserve_rooms_form_before_submit_button', array( $this, 'printDateHiddenFields' ) );

		if ( $this->isValidStep ) {
			$rooms = MPHB()->getRoomRepository()->getAvailableRooms( $this->checkInDate, $this->checkOutDate, $this->roomTypeId, array( 'skip_buffer_rules' => false ) );
			$rooms = $this->filterRoomsByRates( $rooms );
			$rooms = $this->filterRoomsByCapacity( $rooms );
			$rooms = $this->filterRoomsByRules( $rooms );

			$this->rooms = $this->pullRoomsData( $rooms );

			if ( MPHB()->settings()->main()->isUseOccupancyPresetsOnCheckout() ) {
				// Save search parameters for future presets
				$this->saveSearchParameters();
			}
		}
	}

	/**
	 * @since 5.0.0
	 */
	private function saveSearchParameters() {
		if ( $this->adults != -1 ) {
			$adults   = $this->adults;
			$children = $this->children != -1 ? $this->children : MPHB()->settings()->main()->getMinChildren();
		} else {
			$adults = $children = '';
		}

		$defaultFormat = MPHB()->settings()->dateTime()->getDateTransferFormat();

		MPHB()->searchParametersStorage()->save(
			array(
				'mphb_check_in_date'  => $this->checkInDate->format( $defaultFormat ),
				'mphb_check_out_date' => $this->checkOutDate->format( $defaultFormat ),
				'mphb_adults'         => $adults,
				'mphb_children'       => $children,
			)
		);
	}

	private function filterRoomsByRates( $rooms ) {

		foreach ( array_keys( $rooms ) as $roomTypeId ) {

			if ( ! mphb_prices_facade()->isRoomTypeHasActiveRate( $roomTypeId, $this->checkInDate, $this->checkOutDate ) ) {

				unset( $rooms[ $roomTypeId ] );
			}
		}

		return $rooms;
	}

	private function filterRoomsByCapacity( $rooms ) {
		foreach ( array_keys( $rooms ) as $roomTypeId ) {
			$roomType = MPHB()->getRoomTypeRepository()->findById( $roomTypeId );

			if ( is_null( $roomType ) || $roomType->getAdultsCapacity() < $this->adults || $roomType->getChildrenCapacity() < $this->children ) {
				unset( $rooms[ $roomTypeId ] );
			}
		}

		return $rooms;
	}

	private function filterRoomsByRules( $rooms ) {

		$isIgnoreBookingRules = MPHB()->settings()->main()->isBookingRulesForAdminDisabled();

		// Don't modify iterating array, use the new one to iterate
		foreach ( array_keys( $rooms ) as $roomTypeId ) {

			$roomTypeId = MPHB()->translation()->getOriginalId(
				$roomTypeId,
				MPHB()->postTypes()->roomType()->getPostType()
			);

			if ( mphb_availability_facade()->isBookingRulesViolated(
					$roomTypeId,
					$this->checkInDate,
					$this->checkOutDate,
					$isIgnoreBookingRules
				)
			) {
				unset( $rooms[ $roomTypeId ] );
				continue;
			}

			$unavailableRooms = mphb_availability_facade()->getUnavailableRoomIds(
				$roomTypeId,
				$this->checkInDate,
				$this->checkOutDate,
				$isIgnoreBookingRules
			);

			if ( ! empty( $unavailableRooms ) ) {

				$availableRooms = array_diff( $rooms[ $roomTypeId ], $unavailableRooms );

				if ( ! empty ($availableRooms ) ) {
					$rooms[ $roomTypeId ] = $availableRooms;
				} else {
					unset( $rooms[ $roomTypeId ] );
				}
			}
		}

		return $rooms;
	}

	private function pullRoomsData( $rooms ) {
		$data = array();

		foreach ( $rooms as $roomTypeId => $roomIds ) {
			$roomType = MPHB()->getRoomTypeRepository()->findById( $roomTypeId );

			$data[ $roomTypeId ] = array(
				'title' => $roomType->getTitle(),
				'rooms' => array(),
				'url'   => get_permalink( $roomTypeId ),
			);

			foreach ( $roomIds as $roomId ) {
				$room = MPHB()->getRoomRepository()->findById( $roomId );

				$data[ $roomTypeId ]['rooms'][] = array(
					'id'       => $roomId,
					'type_id'  => $roomTypeId,
					'title'    => $room->getTitle(),
					'adults'   => $roomType->getAdultsCapacity(),
					'children' => $roomType->getChildrenCapacity(),
					'price'    => mphb_get_room_type_period_price( $this->checkInDate, $this->checkOutDate, $roomType ),
				);
			} // For each room
		} // For each type

		return $data;
	}

	protected function renderValid() {
		$dateFormat   = MPHB()->settings()->dateTime()->getDateTransferFormat();
		$checkInDate  = $this->checkInDate->format( $dateFormat );
		$checkOutDate = $this->checkOutDate->format( $dateFormat );
		$roomsCount   = count( $this->rooms );

		mphb_get_template_part(
			'create-booking/results/rooms-found',
			array(
				'foundRooms'   => $roomsCount,
				'checkInDate'  => \MPHB\Utils\DateUtils::formatDateWPFront( $this->checkInDate ),
				'checkOutDate' => \MPHB\Utils\DateUtils::formatDateWPFront( $this->checkOutDate ),
			)
		);

		if ( $roomsCount > 0 ) {
			mphb_get_template_part(
				'create-booking/results/reserve-rooms',
				array(
					'actionUrl'    => $this->nextUrl,
					'checkInDate'  => $checkInDate,
					'checkOutDate' => $checkOutDate,
					'roomsList'    => $this->rooms,
				)
			);
		}
	}

	public function printWrapperHeader() {
		echo '<h2>' . esc_html__( 'Search Results', 'motopress-hotel-booking' ) . '</h2>';
	}

	protected function parseFields() {
		$this->checkInDate  = $this->parseCheckInDate( INPUT_GET );
		$this->checkOutDate = $this->parseCheckOutDate( INPUT_GET );
		$this->roomTypeId   = $this->parseRoomTypeId( INPUT_GET );
		$this->adults       = $this->parseAdults( INPUT_GET );
		$this->children     = $this->parseChildren( INPUT_GET );
	}
}
