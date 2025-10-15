<?php

namespace MPHB\Entities;

use MPHB\PostTypes\CouponCPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 5.0.0 replaced the AbstractCoupon class.
 */
class Coupon {

	/**
	 * @since 5.0.0
	 *
	 * @var string
	 */
	protected $roomDiscountType;

	/**
	 * @since 5.0.0
	 *
	 * @var string
	 */
	protected $serviceDiscountType;

	/**
	 * @since 5.0.0
	 *
	 * @var string
	 */
	protected $feeDiscountType;

	/**
	 * @since 5.0.0
	 *
	 * @var float
	 */
	protected $roomAmount;

	/**
	 * @since 5.0.0
	 *
	 * @var float
	 */
	protected $serviceAmount;

	/**
	 * @since 5.0.0
	 *
	 * @var float
	 */
	protected $feeAmount;

	/**
	 * @var string
	 */
	protected $code;

	/**
	 * @var int
	 */
	protected $usageLimit;

	/**
	 * @var \DateTime
	 */
	protected $checkOutDateBefore;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var \DateTime
	 */
	protected $checkInDateAfter;

	/**
	 * @var int
	 */
	protected $minDaysBeforeCheckIn;

	/**
	 * @var int
	 */
	protected $maxDaysBeforeCheckIn;

	/**
	 * @var int
	 */
	protected $maxNights;

	/**
	 * @var int
	 */
	protected $usageCount = 0;

	/**
	 * @var int
	 */
	protected $minNights;

	/**
	 * @var array
	 */
	protected $roomTypes = array();

	/**
	 * @since 5.0.0
	 *
	 * @var int[]
	 */
	protected $applicableServiceIds;

	/**
	 * @since 5.0.0
	 *
	 * @var array
	 */
	protected $applicableFeeIds;

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $status;

	/**
	 * @var \DateTime
	 */
	protected $expirationDate;


	function __construct( $atts ) {
		$this->id          = $atts['id']          ?? 0;
		$this->code        = $atts['code']        ?? '';
		$this->description = $atts['description'] ?? '';
		$this->status      = $atts['status']      ?? 'publish';

		$this->roomDiscountType    = $atts['room_discount_type']    ?? CouponCPT::TYPE_ACCOMMODATION_PERCENTAGE;
		$this->serviceDiscountType = $atts['service_discount_type'] ?? CouponCPT::TYPE_SERVICE_NONE;
		$this->feeDiscountType     = $atts['fee_discount_type']     ?? CouponCPT::TYPE_FEE_NONE;

		$this->roomAmount    = $atts['room_amount']    ?? 0.0;
		$this->serviceAmount = $atts['service_amount'] ?? 0.0;
		$this->feeAmount     = $atts['fee_amount']     ?? 0.0;

		$this->roomTypes            = ! empty( $atts['room_types'] ) ? array_map( 'intval', $atts['room_types'] ) : array();
		$this->applicableServiceIds = ! empty( $atts['applicable_service_ids'] ) ? array_map( 'intval', $atts['applicable_service_ids'] ) : array();
		$this->applicableFeeIds     = $atts['allowed_fee_ids'] ?? array();

		$this->expirationDate     = ! empty( $atts['expiration_date'] ) ? $atts['expiration_date'] : null;
		$this->checkInDateAfter   = ! empty( $atts['check_in_date_after'] ) ? $atts['check_in_date_after'] : null;
		$this->checkOutDateBefore = ! empty( $atts['check_out_date_before'] ) ? $atts['check_out_date_before'] : null;

		$this->minDaysBeforeCheckIn = ! empty( $atts['min_days_before_check_in'] ) ? $atts['min_days_before_check_in'] : 0;
		$this->maxDaysBeforeCheckIn = ! empty( $atts['max_days_before_check_in'] ) ? $atts['max_days_before_check_in'] : 0;

		$this->minNights  = ! empty( $atts['min_nights'] ) ? $atts['min_nights'] : 1;
		$this->maxNights  = ! empty( $atts['max_nights'] ) ? $atts['max_nights'] : 0;
		$this->usageLimit = ! empty( $atts['usage_limit'] ) ? $atts['usage_limit'] : 0;
		$this->usageCount = ! empty( $atts['usage_count'] ) ? $atts['usage_count'] : 0;
	}

	/**
	 * @return int
	 */
	function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	function getStatus() {
		return $this->status;
	}

	/**
	 * @return string
	 */
	function getCode() {
		return $this->code;
	}

	/**
	 * @return string
	 */
	function getDescription() {
		return $this->description;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return string
	 */
	function getRoomDiscountType() {
		return $this->roomDiscountType;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return string
	 */
	function getServiceDiscountType() {
		return $this->serviceDiscountType;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return string
	 */
	function getFeeDiscountType() {
		return $this->feeDiscountType;
	}

	/**
	 * @deprecated 5.0.0
	 *
	 * @return float
	 */
	function getAmount() {
		return $this->getRoomAmount();
	}

	/**
	 * @since 5.0.0
	 *
	 * @return float
	 */
	function getRoomAmount() {
		return $this->roomAmount;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return float
	 */
	function getServiceAmount() {
		return $this->serviceAmount;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return float
	 */
	function getFeeAmount() {
		return $this->feeAmount;
	}

	/**
	 * An alias of <code>getRoomTypes()</code>.
	 *
	 * @since 5.0.0
	 *
	 * @return int[] An empty array means allow all.
	 */
	function getApplicableRoomTypeIds() {
		return $this->getRoomTypes();
	}

	/**
	 * @since 5.0.0
	 *
	 * @return int[] An empty array means allow all.
	 */
	function getApplicableServiceIds() {
		return $this->applicableServiceIds;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return array An empty array means allow all.
	 */
	function getApplicableFeeIds() {
		return $this->applicableFeeIds;
	}

	/**
	 * @return \DateTime|null
	 */
	function getExpirationDate() {
		return $this->expirationDate;
	}

	/**
	 * @return int[] An empty array means allow all.
	 */
	function getRoomTypes() {
		return $this->roomTypes;
	}

	/**
	 * @return \DateTime|null
	 */
	function getCheckInDateAfter() {
		return $this->checkInDateAfter;
	}

	/**
	 * @return \DateTime|null
	 */
	function getCheckOutDateBefore() {
		return $this->checkOutDateBefore;
	}

	/**
	 * @return int
	 */
	function getMinDaysBeforeCheckIn() {
		return $this->minDaysBeforeCheckIn;
	}

	/**
	 * @return int
	 */
	function getMaxDaysBeforeCheckIn() {
		return $this->maxDaysBeforeCheckIn;
	}

	/**
	 * @return int
	 */
	function getMinNights() {
		return $this->minNights;
	}

	/**
	 * @return int
	 */
	function getMaxNights() {
		return $this->maxNights;
	}

	/**
	 * @return int
	 */
	function getUsageLimit() {
		return $this->usageLimit;
	}

	/**
	 * @param Booking $booking
	 * @param boolean $returnError
	 *
	 * @return boolean|\WP_Error
	 */
	public function validate( $booking, $returnError = false ) {

		if ( ! $this->isPublished() ) {
			return $returnError ? new \WP_Error( 'not_valid', __( 'Coupon is not valid.', 'motopress-hotel-booking' ) ) : false;
		}

		if ( $this->isExpired() ) {
			return $returnError ? new \WP_Error( 'expired', __( 'This coupon has expired.', 'motopress-hotel-booking' ) ) : false;
		}

		if ( ! $this->isValidForBookingContents( $booking ) ) {
			return $returnError ? new \WP_Error( 'not_applicable', __( 'Sorry, this coupon is not applicable to your booking contents.', 'motopress-hotel-booking' ) ) : false;
		}

		if ( $this->isExceedUsageLimit() ) {
			return $returnError ? new \WP_Error( 'not_applicable', __( 'Coupon usage limit has been reached.', 'motopress-hotel-booking' ) ) : false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function isExpired() {
		return $this->expirationDate && $this->expirationDate->format( 'Y-m-d' ) <= current_time( 'Y-m-d' );
	}

	/**
	 * @return bool
	 */
	public function isPublished() {
		return $this->status === 'publish';
	}

	public function isApplicableForRoomType( $roomTypeId ) {
		return empty( $this->roomTypes ) || in_array( $roomTypeId, $this->roomTypes );
	}

	/**
	 * @since 5.0.0
	 *
	 * @param int $serviceId
	 * @return bool
	 */
	public function isApplicableForService( $serviceId ) {
		return empty( $this->applicableServiceIds ) || in_array( $serviceId, $this->applicableServiceIds );
	}

	/**
	 * @since 5.0.0
	 *
	 * @param mixed $feeId
	 * @return bool
	 */
	public function isApplicableForFee( $feeId ) {
		return empty( $this->applicableFeeIds ) || in_array( $feeId, $this->applicableFeeIds );
	}

	/**
	 * @param Booking $booking
	 * @return boolean
	 */
	public function isValidForBookingContents( $booking ) {

		if ( ! empty( $this->roomTypes ) ) {
			$validRoomTypeIds = array_intersect( $this->roomTypes, $booking->getReservedRoomTypeIds() );
		} else {
			$validRoomTypeIds = $booking->getReservedRoomTypeIds();
		}

		// Search for valid accommodation types
		if ( empty( $validRoomTypeIds ) ) {
			return false;
		}

		$haveDiscountForRooms = $this->roomAmount > 0
			&& $this->roomDiscountType != CouponCPT::TYPE_ACCOMMODATION_NONE;

		// Search for valid services
		$haveDiscountForServices = false;

		if ( $this->serviceAmount > 0 && $this->serviceDiscountType != CouponCPT::TYPE_SERVICE_NONE ) {
			foreach ( $booking->getReservedRooms() as $reservedRoom ) {
				$roomTypeId = (int) $reservedRoom->getRoomTypeId();

				if ( ! in_array( $roomTypeId, $validRoomTypeIds ) ) {
					continue;
				}

				if ( ! empty( $this->applicableServiceIds ) ) {
					$validServiceIds = array_intersect( $this->applicableServiceIds, $reservedRoom->getReservedServiceIds() );
				} else {
					$validServiceIds = $reservedRoom->getReservedServiceIds();
				}

				if ( ! empty( $validServiceIds ) ) {
					$haveDiscountForServices = true;
					break;
				}
			}
		}

		// Search for valid fees
		$haveDiscountForFees = false;

		if ( $this->feeAmount > 0 && $this->feeDiscountType != CouponCPT::TYPE_FEE_NONE ) {
			foreach ( $validRoomTypeIds as $roomTypeId ) {
				if ( MPHB()->settings()->taxesAndFees()->hasFees( $roomTypeId ) ) {
					$haveDiscountForFees = true;
					break;
				}
			}
		}

		if ( ! $haveDiscountForRooms && ! $haveDiscountForServices && ! $haveDiscountForFees ) {
			return false;
		}

		// Other checks
		if ( ! is_null( $this->checkInDateAfter ) &&
			 $this->checkInDateAfter->format( 'Y-m-d' ) > $booking->getCheckInDate()->format( 'Y-m-d' ) ) {
			return false;
		}

		if ( ! is_null( $this->checkOutDateBefore ) &&
			 $this->checkOutDateBefore->format( 'Y-m-d' ) < $booking->getCheckOutDate()->format( 'Y-m-d' ) ) {
			return false;
		}

		if ( 0 < $this->minDaysBeforeCheckIn || 0 < $this->maxDaysBeforeCheckIn ) {

			$bookingReservationDateTime = null == $booking->getDateTime() ? new \DateTime() : $booking->getDateTime();

			$daysBetweenReservationAndCheckIn = $bookingReservationDateTime->diff( $booking->getCheckInDate() )->format( '%a' );

			if ( 0 < $this->minDaysBeforeCheckIn && $daysBetweenReservationAndCheckIn < $this->minDaysBeforeCheckIn ) {
				return false;
			}

			if ( 0 < $this->maxDaysBeforeCheckIn && $daysBetweenReservationAndCheckIn > $this->maxDaysBeforeCheckIn ) {
				return false;
			}
		}

		$bookingNights = \MPHB\Utils\DateUtils::calcNights( $booking->getCheckInDate(), $booking->getCheckOutDate() );

		if ( $this->minNights > 0 && $bookingNights < $this->minNights ) {
			return false;
		}

		if ( $this->maxNights > 0 && $bookingNights > $this->maxNights ) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function isExceedUsageLimit() {
		return $this->usageLimit > 0 && $this->usageCount >= $this->usageLimit;
	}

	/**
	 * @return int
	 */
	public function getUsageCount() {
		return $this->usageCount;
	}

	public function increaseUsageCount() {
		$this->usageCount ++;
	}

	/**
	 * @param array $dayPrices [ date ('Y-m-d') => price (float) ]
	 * @return float
	 */
	public function calcRoomDiscount( $dayPrices ) {
		$discount = 0.0;

		switch ( $this->roomDiscountType ) {
			case CouponCPT::TYPE_ACCOMMODATION_PERCENTAGE:
				$price = array_sum( $dayPrices );

				$discount = $price * ( $this->roomAmount / 100 );
				$discount = round( $discount, 4 );
				$discount = mphb_limit( $discount, 0, $price ); // 0-100%% of the price

				break;

			case CouponCPT::TYPE_ACCOMMODATION_FIXED:
				$price    = array_sum( $dayPrices );
				$discount = mphb_limit( $this->roomAmount, 0, $price ); // Don't exceed the price
				break;

			case CouponCPT::TYPE_ACCOMMODATION_FIXED_PER_DAY:
				foreach ( $dayPrices as $price ) {
					$discount += mphb_limit( $this->roomAmount, 0, $price ); // Don't exceed the price
				}
				break;
		}

		return $discount;
	}

	/**
	 * @since 5.0.0
	 *
	 * @param float $servicePrice
	 * @return float
	 */
	public function calcServiceDiscount( $servicePrice ) {
		$discount = 0.0;

		switch ( $this->serviceDiscountType ) {
			case CouponCPT::TYPE_SERVICE_PERCENTAGE:
				$discount = $servicePrice * ( $this->serviceAmount / 100 );
				$discount = round( $discount, 4 );
				$discount = mphb_limit( $discount, 0, $servicePrice ); // 0-100%% of the price
				break;

			case CouponCPT::TYPE_SERVICE_FIXED:
				$discount = mphb_limit( $this->serviceAmount, 0, $servicePrice ); // Don't exceed the price
				break;
		}

		return $discount;
	}

	/**
	 * @since 5.0.0
	 *
	 * @param float $feePrice
	 * @return float
	 */
	public function calcFeeDiscount( $feePrice ) {
		$discount = 0.0;

		switch ( $this->feeDiscountType ) {
			case CouponCPT::TYPE_FEE_PERCENTAGE:
				$discount = $feePrice * ( $this->feeAmount / 100 );
				$discount = round( $discount, 4 );
				$discount = mphb_limit( $discount, 0, $feePrice ); // 0-100%% of the price
				break;

			case CouponCPT::TYPE_FEE_FIXED:
				$discount = mphb_limit( $this->feeAmount, 0, $feePrice ); // Don't exceed the price
				break;
		}

		return $discount;
	}
}
