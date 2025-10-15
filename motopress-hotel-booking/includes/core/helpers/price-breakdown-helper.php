<?php

namespace MPHB\Core;

use MPHB\Entities\Booking;
use MPHB\Entities\Coupon;
use MPHB\Entities\ReservedRoom;
use MPHB\PostTypes\CouponCPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 5.0.0
 */
class PriceBreakdownHelper {
	/**
	 * @var Booking
	 */
	protected $booking = null;

	/**
	 * @var Coupon|null
	 */
	protected $coupon = null;

	/**
	 * @var array [ reserved_room_id (int) => room_price_breakdown (array) ]
	 */
	protected static $cachedRoomPriceBreakdowns = [];

	/**
	 * @param Booking $booking
	 * @param bool $isUseCoupon Optional. True by default.
	 */
	public function __construct( Booking $booking, bool $isUseCoupon = true ) {
		$this->booking = $booking;

		if ( $isUseCoupon && $booking->getCouponId() > 0 ) {
			$coupon = MPHB()->getCouponRepository()->findById( $booking->getCouponId() );

			if ( ! is_null( $coupon ) && $coupon->validate( $booking ) ) {
				$this->coupon = $coupon;
			}
		}
	}

	/**
	 * @param Booking $booking
	 * @return array
	 */
	public static function generatePriceBreakdown( Booking $booking ) {
		$self = new static( $booking, MPHB()->settings()->main()->isCouponsEnabled() );

		return $self->getPriceBreakdown();
	}

	/**
	 * @param ReservedRoom $reservedRoom
	 * @return array|null
	 */
	public static function getLastRoomPriceBreakdown( ReservedRoom $reservedRoom ) {
		$reservedRoomId = $reservedRoom->getId();

		if ( empty( static::$cachedRoomPriceBreakdowns[ $reservedRoomId ] ) ) {

			$booking     = $reservedRoom->getBooking();
			$bookedRooms = $booking ? $booking->getReservedRoomIds() : [];

			// Note: imported bookings don't have a price breakdown
			$lastPriceBreakdown = $booking ? $booking->getLastPriceBreakdown() : [];

			if ( ! is_null( $booking )
				&& ! empty( $lastPriceBreakdown['rooms'] ) // Imported bookings will fail this check
				&& count( $lastPriceBreakdown['rooms'] ) == count( $bookedRooms )
			) {
				// Search reserved room by index
				for ( $i = 0; $i < count( $bookedRooms ); $i++ ) {
					if ( $bookedRooms[ $i ] == $reservedRoomId ) {
						static::$cachedRoomPriceBreakdowns[ $reservedRoomId ] = $lastPriceBreakdown['rooms'][ $i ];
						break;
					}
				}
			}

		}

		// Was anything found?
		if ( ! empty( static::$cachedRoomPriceBreakdowns[ $reservedRoomId ] ) ) {
			return static::$cachedRoomPriceBreakdowns[ $reservedRoomId ];
		} else {
			return null;
		}
	}

	/**
	 * @param string|null $language Optional. Null by default (get the current language).
	 * @return array [
	 *     'rooms'   => [
	 *         // ... Too many details. See getRoomPriceBreakdown().
	 *     ],
	 *     'total'   => float, // Total price with discount
	 *     'coupon'  => [      // Optional
	 *         'code'     => string,
	 *         'discount' => float, // Total discount
	 *     ],
	 *     'deposit' => float, // Optional. Deposit amount.
	 * ]
	 */
	public function getPriceBreakdown( $language = null ) {
		if ( ! $language ) {
			$language = $this->booking->getLanguage();
		}

		$roomsBreakdown = [];
		$discount = $discountTotal = 0.0;

		foreach ( $this->booking->getReservedRooms() as $reservedRoom ) {
			$roomBreakdown = $this->getRoomPriceBreakdown( $reservedRoom, $language );

			$roomsBreakdown[] = $roomBreakdown;

			$discount      += $roomBreakdown['discount'];
			$discountTotal += $roomBreakdown['discount_total'];
		}

		/**
		 * @param float $totalPrice
		 * @param Booking $booking
		 */
		$discountTotal = apply_filters( 'mphb_booking_calculate_total_price', $discountTotal, $this->booking );

		$priceBreakdown = [
			'rooms' => $roomsBreakdown,
			'total' => $discountTotal,
		];

		// Add coupon data
		if ( ! is_null( $this->coupon ) && $discount > 0 ) {
			$priceBreakdown['coupon'] = [
				'code'     => $this->coupon->getCode(),
				'discount' => $discount,
			];
		}

		// Add deposit data
		if ( MPHB()->settings()->main()->getConfirmationMode() == 'payment'
			&& MPHB()->settings()->payment()->getAmountType() == 'deposit'
		) {
			$deposit = $this->booking->calcDepositAmount( $discountTotal );

			// $discountTotal and $deposit will be equal if not in the proper
			// time frame for deposit
			if ( $deposit < $discountTotal ) {
				$priceBreakdown['deposit'] = $deposit;
			}
		}

		/**
		 * @param array $priceBreakdown
		 * @param Booking $booking
		 */
		$priceBreakdown = apply_filters( 'mphb_booking_price_breakdown', $priceBreakdown, $this->booking );

		return $priceBreakdown;
	}

	/**
	 * @param ReservedRoom $reservedRoom
	 * @param string|null $language Optional. Null by default (get the current language).
	 * @return array [
	 *     'room' => [
	 *         'type'              => string, // Room type title
	 *         'rate'              => string, // Rate title
	 *         'list'              => [
	 *             date ('Y-m-d')      => price (float)
	 *         ],
	 *         'total'             => float,  // Total price without discount
	 *         'discount'          => float,
	 *         'discount_total'    => float,  // Total price with discount
	 *         'adults'            => int,    // Booked adults
	 *         'children'          => int,    // Booked children
	 *         'children_capacity' => int,    // Room type children capacity
	 *     ],
	 *     'services'       => [ 'total', 'discount', 'discount_total', 'list' => [ 'title' (string), 'details' (string), 'total' (float) ] ],
	 *     'fees'           => [ 'total', 'discount', 'discount_total', 'list' => [ 'label' (string), 'price' (float) ] ],
	 *     'taxes'          => [
	 *         'room'           => [ 'total', 'list' => [ 'label' (string), 'price' (float) ] ],
	 *         'services'       => [ 'total', 'list' => [ 'label' (string), 'price' (float) ] ],
	 *         'fees'           => [ 'total', 'list' => [ 'label' (string), 'price' (float) ] ],
	 *     ],
	 *     'total'          => float, // Total price without discount
	 *     'discount'       => float, // Added since 5.0.0
	 *     'discount_total' => float, // Total price with discount
	 * ]
	 */
	protected function getRoomPriceBreakdown( ReservedRoom $reservedRoom, $language = null ) {
		if ( ! $language ) {
			$language = MPHB()->translation()->getCurrentLanguage();
		}

		$roomBreakdown     = $this->getRoomBreakdown( $reservedRoom, $language );
		$servicesBreakdown = $this->getServicesBreakdown( $reservedRoom, $language );
		$feesBreakdown     = $this->getFeesBreakdown( $reservedRoom, $roomBreakdown['discount_total'] );
		$taxesBreakdown    = [
			'room'     => $this->getRoomTaxesBreakdown( $reservedRoom, $roomBreakdown['discount_total'] ),
			'services' => $this->getServiceTaxesBreakdown( $servicesBreakdown['discount_total'] ),
			'fees'     => $this->getFeeTaxesBreakdown( $feesBreakdown['discount_total'] ),
		];

		// Total price without discounts. We'll use the "total" value later to
		// add "coupon" data to breakdown.
		$totalPrice = $roomBreakdown['total']
			+ $servicesBreakdown['total']
			+ $feesBreakdown['total']
			+ $taxesBreakdown['room']['total']
			+ $taxesBreakdown['services']['total']
			+ $taxesBreakdown['fees']['total'];

		$discount = $roomBreakdown['discount']
			+ $servicesBreakdown['discount']
			+ $feesBreakdown['discount'];

		$discountTotal = max( 0, $totalPrice - $discount ); // Prevent total less than 0

		$priceBreakdown = [
			'room'           => $roomBreakdown,
			'services'       => $servicesBreakdown,
			'fees'           => $feesBreakdown,
			'taxes'          => $taxesBreakdown,
			'total'          => $totalPrice,
			'discount'       => $discount, // Added since 5.0.0
			'discount_total' => $discountTotal,
		];

		return $priceBreakdown;
	}

	/**
	 * @param ReservedRoom $reservedRoom
	 * @param string|null $language Optional. Null by default (get the current language).
	 * @return array [
	 *     'type'              => string, // Room type title
	 *     'rate'              => string, // Rate title
	 *     'list'              => [
	 *         date ('Y-m-d')      => price (float)
	 *     ],
	 *     'total'             => float,  // Total price without discount
	 *     'discount'          => float,
	 *     'discount_total'    => float,  // Total price with discount
	 *     'adults'            => int,    // Booked adults
	 *     'children'          => int,    // Booked children
	 *     'children_capacity' => int,    // Room type children capacity
	 * ]
	 */
	protected function getRoomBreakdown( ReservedRoom $reservedRoom, $language = null ) {
		if ( ! $language ) {
			$language = MPHB()->translation()->getCurrentLanguage();
		}

		$checkInDate  = $this->booking->getCheckInDate();
		$checkOutDate = $this->booking->getCheckOutDate();

		MPHB()->reservationRequest()->setupParameters(
			[
				'adults'         => $reservedRoom->getAdults(),
				'children'       => $reservedRoom->getChildren(),
				'check_in_date'  => $checkInDate,
				'check_out_date' => $checkOutDate,
			]
		);

		$roomTypeId = apply_filters( '_mphb_translate_post_id', $reservedRoom->getRoomTypeId(), $language );
		$roomType   = $roomTypeId ? MPHB()->getRoomTypeRepository()->findById( $roomTypeId ) : null;

		$rate = $reservedRoom->getRate( $language );

		// [ date ('Y-m-d') => price (float) ]
		$dayPrices  = $rate ? $rate->getPriceBreakdown( $checkInDate, $checkOutDate ) : [];
		$totalPrice = (float) array_sum( $dayPrices );

		// Calc discount
		if ( ! is_null( $this->coupon ) && $this->coupon->isApplicableForRoomType( $roomType->getOriginalId() ) ) {
			$discount = $this->coupon->calcRoomDiscount( $dayPrices );
		} else {
			$discount = 0.0;
		}

		$discountTotal = max( 0, $totalPrice - $discount ); // Prevent total less than 0

		$roomBreakdown = [
			'type'              => $roomType ? $roomType->getTitle() : '',
			'rate'              => $rate ? $rate->getTitle() : '',
			'list'              => $dayPrices,
			'total'             => $totalPrice,    // "Dates Subtotal"
			'discount'          => $discount,      // "Discount"
			'discount_total'    => $discountTotal, // "Accommodation Subtotal"
			'adults'            => $reservedRoom->getAdults(),
			'children'          => $reservedRoom->getChildren(),
			'children_capacity' => $roomType ? $roomType->getChildrenCapacity() : $reservedRoom->getChildren(),
		];

		MPHB()->reservationRequest()->resetDefaults();

		return $roomBreakdown;
	}

	/**
	 * @param ReservedRoom $reservedRoom
	 * @param string|null $language
	 * @return array [
	 *     'list'           => [
	 *         [
	 *             'title'   => string,
	 *             'details' => string,
	 *             'total'   => float,
	 *         ],
	 *         // ...
	 *     ],
	 *     'total'          => float,
	 *     'discount'       => float, // Added since 5.0.0
	 *     'discount_total' => float, // Added since 5.0.0
	 * ]
	 */
	protected function getServicesBreakdown( ReservedRoom $reservedRoom, $language = null ) {
		if ( ! $language ) {
			$language = MPHB()->translation()->getCurrentLanguage();
		}

		$checkInDate  = $this->booking->getCheckInDate();
		$checkOutDate = $this->booking->getCheckOutDate();
		$nights       = $this->booking->getNightsCount();
		$roomTypeId   = $reservedRoom->getRoomTypeId();

		$servicesBreakdown = [
			'list'           => [],
			'total'          => 0.0,
			'discount'       => 0.0, // Added since 5.0.0
			'discount_total' => 0.0, // Added since 5.0.0
		];

		foreach ( $reservedRoom->getReservedServices() as $reservedService ) {
			$serviceId    = apply_filters( '_mphb_translate_post_id', $reservedService->getId(), $language );
			$service      = MPHB()->getServiceRepository()->findById( $serviceId );
			$servicePrice = $reservedService->calcPrice( $checkInDate, $checkOutDate );

			// Calc discount
			if ( ! is_null( $this->coupon )
				&& $this->coupon->isApplicableForRoomType( $roomTypeId )
				&& $this->coupon->isApplicableForService( $reservedService->getId() )
			) {
				$discount = $this->coupon->calcServiceDiscount( $servicePrice );
			} else {
				$discount = 0.0;
			}

			$servicesBreakdown['list'][] = [
				'title'   => $service ? $service->getTitle() : $reservedService->getTitle(),
				'details' => $reservedService->toString( 'price', $nights ),
				'total'   => $servicePrice,
			];

			$servicesBreakdown['total']    += $servicePrice;
			$servicesBreakdown['discount'] += $discount;
		}

		// Limit fixed discount
		if ( ! is_null( $this->coupon )
			&& $this->coupon->getServiceDiscountType() == CouponCPT::TYPE_SERVICE_FIXED
		) {
			$servicesBreakdown['discount'] = min( $servicesBreakdown['discount'], $this->coupon->getServiceAmount() );
		}

		// Prevent total less than 0
		$servicesBreakdown['discount_total'] = max(
			0,
			$servicesBreakdown['total'] - $servicesBreakdown['discount']
		);

		return $servicesBreakdown;
	}

	/**
	 * @param ReservedRoom $reservedRoom
	 * @param float $roomDiscountTotal
	 * @return array [
	 *     'list'           => [
	 *         [
	 *             'label' => string,
	 *             'price' => float,
	 *         ],
	 *         // ...
	 *     ],
	 *     'total'          => float,
	 *     'discount'       => float, // Added since 5.0.0
	 *     'discount_total' => float, // Added since 5.0.0
	 * ]
	 */
	protected function getFeesBreakdown( ReservedRoom $reservedRoom, float $roomDiscountTotal ) {
		$nights     = $this->booking->getNightsCount();
		$adults     = $reservedRoom->getAdults();
		$children   = $reservedRoom->getChildren();
		$roomTypeId = $reservedRoom->getRoomTypeId();

		$fees = MPHB()->settings()->taxesAndFees()->getFees( $roomTypeId );

		$feesBreakdown = [
			'list'           => [],
			'total'          => 0.0,
			'discount'       => 0.0, // Added since 5.0.0
			'discount_total' => 0.0, // Added since 5.0.0
		];

		foreach ( $fees as $fee ) {
			$feeId    = $fee['id'] ?? '';
			$feePrice = 0.0;

			switch ( $fee['type'] ) {
				case 'per_guest_per_day':
					$feePrice = $adults * $fee['amount']['adults'] + $children * $fee['amount']['children'];

					if ( ! $fee['limit'] ) {
						$feePrice *= $nights;
					} else {
						$feePrice *= min( $nights, $fee['limit'] );
					}

					break;

				case 'per_room_per_day':
					if ( ! $fee['limit'] ) {
						$feePrice = $fee['amount'] * $nights;
					} else {
						$feePrice = $fee['amount'] * min( $nights, $fee['limit'] );
					}
					break;

				case 'per_room_percentage':
					$feePrice = $roomDiscountTotal * ( $fee['amount'] / 100 );
					break;
			}

			// Calc discount
			if ( ! is_null( $this->coupon )
				&& $this->coupon->isApplicableForRoomType( $roomTypeId )
				&& $this->coupon->isApplicableForFee( $feeId )
			) {
				$discount = $this->coupon->calcFeeDiscount( $feePrice );
			} else {
				$discount = 0.0;
			}

			$feesBreakdown['list'][] = [
				'label' => $fee['label'],
				'price' => $feePrice,
			];

			$feesBreakdown['total']    += $feePrice;
			$feesBreakdown['discount'] += $discount;
		}

		// Limit fixed discount
		if ( ! is_null( $this->coupon )
			&& $this->coupon->getFeeDiscountType() == CouponCPT::TYPE_FEE_FIXED
		) {
			$feesBreakdown['discount'] = min( $feesBreakdown['discount'], $this->coupon->getFeeAmount() );
		}

		// Prevent total less than 0
		$feesBreakdown['discount_total'] = max(
			0,
			$feesBreakdown['total'] - $feesBreakdown['discount']
		);

		return $feesBreakdown;
	}

	/**
	 * @param ReservedRoom $reservedRoom
	 * @param float $roomDiscountTotal
	 * @return array [
	 *     'list'  => [
	 *         [
	 *             'label' => string,
	 *             'price' => float,
	 *         ],
	 *         // ...
	 *     ],
	 *     'total' => float,
	 * ]
	 */
	protected function getRoomTaxesBreakdown( ReservedRoom $reservedRoom, float $roomDiscountTotal ) {
		$nights     = $this->booking->getNightsCount();
		$adults     = $reservedRoom->getAdults();
		$children   = $reservedRoom->getChildren();
		$roomTypeId = $reservedRoom->getRoomTypeId();

		$taxes = MPHB()->settings()->taxesAndFees()->getAccommodationTaxes( $roomTypeId );

		$taxesBreakdown = [
			'list'  => [],
			'total' => 0.0,
		];

		foreach ( $taxes as $tax ) {
			$taxPrice = 0.0;

			switch ( $tax['type'] ) {
				case 'per_guest_per_day':
					$taxPrice = $adults * $tax['amount']['adults'] + $children * $tax['amount']['children'];

					if ( ! $tax['limit'] ) {
						$taxPrice *= $nights;
					} else {
						$taxPrice *= min( $nights, $tax['limit'] );
					}

					break;

				case 'per_room_per_day':
					if ( ! $tax['limit'] ) {
						$taxPrice = $tax['amount'] * $nights;
					} else {
						$taxPrice = $tax['amount'] * min( $nights, $tax['limit'] );
					}
					break;

				case 'per_room_percentage':
					$taxPrice = $roomDiscountTotal * ( $tax['amount'] / 100 );
					break;
			}

			$taxesBreakdown['list'][] = [
				'label' => $tax['label'],
				'price' => $taxPrice,
			];

			$taxesBreakdown['total'] += $taxPrice;
		}

		return $taxesBreakdown;
	}

	/**
	 * @param float $servicesTotal
	 * @return array [
	 *     'list'  => [
	 *         [
	 *             'label' => string,
	 *             'price' => float,
	 *         ],
	 *         // ...
	 *     ],
	 *     'total' => float,
	 * ]
	 */
	protected function getServiceTaxesBreakdown( float $servicesTotal ) {
		$taxes = MPHB()->settings()->taxesAndFees()->getServiceTaxes();

		$taxesBreakdown = [
			'list'  => [],
			'total' => 0.0,
		];

		foreach ( $taxes as $tax ) {
			$taxPrice = 0.0;

			switch ( $tax['type'] ) {
				case 'percentage':
					$taxPrice = $servicesTotal * ( $tax['amount'] / 100 );
					break;
			}

			$taxesBreakdown['list'][] = [
				'label' => $tax['label'],
				'price' => $taxPrice,
			];

			$taxesBreakdown['total'] += $taxPrice;
		}

		return $taxesBreakdown;
	}

	/**
	 * @param float $feesTotal
	 * @return array [
	 *     'list'  => [
	 *         [
	 *             'label' => string,
	 *             'price' => float,
	 *         ],
	 *         // ...
	 *     ],
	 *     'total' => float,
	 * ]
	 */
	protected function getFeeTaxesBreakdown( float $feesTotal ) {
		$taxes = MPHB()->settings()->taxesAndFees()->getFeeTaxes();

		$taxesBreakdown = [
			'list'  => [],
			'total' => 0.0,
		];

		foreach ( $taxes as $tax ) {
			$taxPrice = 0.0;

			switch ( $tax['type'] ) {
				case 'percentage':
					$taxPrice = $feesTotal * ( $tax['amount'] / 100 );
					break;
			}

			$taxesBreakdown['list'][] = [
				'label' => $tax['label'],
				'price' => $taxPrice,
			];

			$taxesBreakdown['total'] += $taxPrice;
		}

		return $taxesBreakdown;
	}
}
