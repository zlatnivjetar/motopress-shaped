<?php

namespace MPHB\Entities;

use MPHB\Utils\ValidateUtils;

class ReservedService extends Service {

	/**
	 *
	 * @var int
	 */
	private $adults;

	protected $quantity;

	/**
	 *
	 * @param array $atts
	 * @param int   $atts['id'] Id of service
	 * @param int   $atts['adults'] Number of adults reserved service. For service per room equal 1.
	 */
	protected function __construct( $atts ) {
		parent::__construct( $atts );
		$this->adults   = $atts['adults'];
		$this->quantity = isset( $atts['quantity'] ) ? absint( $atts['quantity'] ) : 1;
	}

	/**
	 *
	 * @param array $atts
	 * @return ReservedService|null
	 */
	public static function create( $atts ) {

		if ( ! isset( $atts['id'], $atts['adults'] ) ) {
			return null;
		}

		$service = MPHB()->getServiceRepository()->findById( $atts['id'] );
		if ( ! $service ) {
			return null;
		}

		if ( $service->isFlexiblePay() ) {
			if ( ! isset( $atts['quantity'] ) ) {
				return null;
			}

			if ( $service->isAutoLimit() || $service->isUnlimited() ) {
				// With autolimit we don't know the max quantity (nights count);
				// with unlimited - we don't have the max quantity
				$quantity = ValidateUtils::validateInt( $atts['quantity'], $service->getMinQuantity() );
			} else {
				// Fix max quantity if max < min
				$maxQuantity = max( $service->getMinQuantity(), $service->getMaxQuantityNumber() );
				$quantity    = ValidateUtils::validateInt( $atts['quantity'], $service->getMinQuantity(), $maxQuantity );
			}

			if ( $quantity === false ) {
				return null;
			}
		}

		$serviceAtts = array(
			'original_id'   => $service->getOriginalId(),
			'title'         => $service->getTitle(),
			'description'   => $service->getDescription(),
			'periodicity'   => $service->getPeriodicity(),
			'min_quantity'  => $service->getMinQuantity(),
			'max_quantity'  => $service->getMaxQuantity(),
			'is_auto_limit' => $service->isAutoLimit(),
			'repeat'        => $service->getRepeatability(),
			'price'         => $service->getPrice(),
		);

		$atts = array_merge( $serviceAtts, $atts );

		return new self( $atts );
	}


	/**
	 * @since 5.0.0 replaced the <code>generatePriceDetailsString()</code> method.
	 *
	 * @param 'price'|'title' $format Optional. 'price' by default.
	 * @param int $nights Optional.
	 * @return string
	 */
	public function toString( $format = 'price', $nights = 0 ) {
		if ( $format == 'price' ) {
			$string = mphb_format_price( $this->getPrice() );
		} else {
			$string = $this->getTitle();
		}

		if ( $this->isPayPerNight() && $nights >= 1 ) {
			$string .= sprintf( _n( ' &#215; %d night', ' &#215; %d nights', $nights, 'motopress-hotel-booking' ), $nights );
		}

		if ( $this->isPayPerAdult() ) {
			if ( MPHB()->settings()->main()->isChildrenAllowed() ) {
				$string .= ' &#215; ' . sprintf( _n( '%d adult', '%d adults', $this->adults, 'motopress-hotel-booking' ), $this->adults );
			} else {
				$string .= ' &#215; ' . sprintf( _n( '%d guest', '%d guests', $this->adults, 'motopress-hotel-booking' ), $this->adults );
			}
		}

		if ( $this->isFlexiblePay() ) {
			$string .= sprintf( _n( ' &#215; %d time', ' &#215; %d times', $this->quantity, 'motopress-hotel-booking' ), $this->quantity );
		}

		return $string;
	}

	/**
	 *
	 * @return int
	 */
	public function getAdults() {
		return $this->adults;
	}

	public function getQuantity() {
		return $this->quantity;
	}

	/**
	 *
	 * @param \DateTime $checkInDate
	 * @param \DateTime $checkOutDate
	 * @return float
	 */
	public function calcPrice( $checkInDate, $checkOutDate ) {
		$multiplier = 1;
		if ( $this->isPayPerNight() ) {
			$nights     = \MPHB\Utils\DateUtils::calcNights( $checkInDate, $checkOutDate );
			$multiplier = $multiplier * $nights;
		} elseif ( $this->isFlexiblePay() ) {
			$multiplier *= $this->quantity;
		}

		if ( $this->isPayPerAdult() ) {
			$multiplier = $multiplier * $this->adults;
		}

		return $multiplier * $this->getPrice();
	}
}
