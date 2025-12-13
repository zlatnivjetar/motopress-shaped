<?php

namespace MPHB\CSV\Bookings;

use MPHB\Entities\Booking;
use MPHB\Entities\ReservedRoom;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper contains methods for getting booking data for the CSV export.
 */
final class BookingsExporterHelper {

	const EXPORT_COLUMN_BOOKING_ID                = 'booking-id';
	const EXPORT_COLUMN_BOOKING_STATUS            = 'booking-status';
	const EXPORT_COLUMN_CHECK_IN                  = 'check-in';
	const EXPORT_COLUMN_CHECK_OUT                 = 'check-out';
	const EXPORT_COLUMN_ROOM_TYPE                 = 'room-type';
	const EXPORT_COLUMN_ROOM_TYPE_ID              = 'room-type-id';
	const EXPORT_COLUMN_ROOM                      = 'room';
	const EXPORT_COLUMN_RATE                      = 'rate';
	const EXPORT_COLUMN_ADULTS                    = 'adults';
	const EXPORT_COLUMN_CHILDREN                  = 'children';
	const EXPORT_COLUMN_FIRST_NAME                = 'first-name';
	const EXPORT_COLUMN_LAST_NAME                 = 'last-name';
	const EXPORT_COLUMN_EMAIL                     = 'email';
	const EXPORT_COLUMN_PHONE                     = 'phone';
	const EXPORT_COLUMN_COUNTRY                   = 'country';
	const EXPORT_COLUMN_ADDRESS                   = 'address';
	const EXPORT_COLUMN_CITY                      = 'city';
	const EXPORT_COLUMN_STATE                     = 'state';
	const EXPORT_COLUMN_POSTCODE                  = 'postcode';
	const EXPORT_COLUMN_CUSTOMER_NOTE             = 'customer-note';
	const EXPORT_COLUMN_GUEST_NAME                = 'guest-name';
	const EXPORT_COLUMN_ACCOMMODATION_SUBTOTAL    = 'accommodation-subtotal';
	const EXPORT_COLUMN_ACCOMMODATION_DISCOUNT    = 'accommodation-discount';
	const EXPORT_COLUMN_ACCOMMODATION_TOTAL       = 'accommodation-total';
	const EXPORT_COLUMN_ACCOMMODATION_TAXES       = 'taxes';
	const EXPORT_COLUMN_ACCOMMODATION_TAXES_TOTAL = 'taxes-total';
	const EXPORT_COLUMN_SERVICES                  = 'services';
	const EXPORT_COLUMN_SERVICES_SUBTOTAL         = 'services-subtotal';
	const EXPORT_COLUMN_SERVICES_DISCOUNT         = 'services-discount';
	const EXPORT_COLUMN_SERVICES_TOTAL            = 'services-total';
	const EXPORT_COLUMN_SERVICE_TAXES_TOTAL       = 'service-taxes-total';
	const EXPORT_COLUMN_FEES                      = 'fees';
	const EXPORT_COLUMN_FEES_SUBTOTAL             = 'fees-subtotal';
	const EXPORT_COLUMN_FEES_DISCOUNT             = 'fees-discount';
	const EXPORT_COLUMN_FEES_TOTAL                = 'fees-total';
	const EXPORT_COLUMN_FEE_TAXES_TOTAL           = 'fee-taxes-total';
	const EXPORT_COLUMN_COUPON                    = 'coupon';
	const EXPORT_COLUMN_DISCOUNT                  = 'discount';
	const EXPORT_COLUMN_PRICE                     = 'price';
	const EXPORT_COLUMN_PAID                      = 'paid';
	const EXPORT_COLUMN_PAYMENTS                  = 'payments';
	const EXPORT_COLUMN_DATE                      = 'date';

	// This is helper with static functions only
	private function __construct() {}

	/**
	 * @return array [ column_id => column_label, ... ]
	 */
	public static function getBookingsExportColumns() {

		return apply_filters(
			'mphb_export_bookings_columns',
			array(
				static::EXPORT_COLUMN_BOOKING_ID                => esc_html__( 'ID', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_BOOKING_STATUS            => esc_html__( 'Status', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_CHECK_IN                  => esc_html__( 'Check-in', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_CHECK_OUT                 => esc_html__( 'Check-out', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ROOM_TYPE                 => esc_html__( 'Accommodation Type', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ROOM_TYPE_ID              => esc_html__( 'Accommodation Type ID', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ROOM                      => esc_html__( 'Accommodation', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_RATE                      => esc_html__( 'Rate', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ADULTS                    => esc_html__( 'Adults/Guests', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_CHILDREN                  => esc_html__( 'Children', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_FIRST_NAME                => esc_html__( 'First Name', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_LAST_NAME                 => esc_html__( 'Last Name', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_EMAIL                     => esc_html__( 'Email', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_PHONE                     => esc_html__( 'Phone', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_COUNTRY                   => esc_html__( 'Country', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ADDRESS                   => esc_html__( 'Address', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_CITY                      => esc_html__( 'City', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_STATE                     => esc_html__( 'State / County', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_POSTCODE                  => esc_html__( 'Postcode', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_CUSTOMER_NOTE             => esc_html__( 'Customer Note', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_GUEST_NAME                => esc_html__( 'Full Guest Name', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ACCOMMODATION_SUBTOTAL    => esc_html__( 'Accommodation Subtotal', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ACCOMMODATION_DISCOUNT    => esc_html__( 'Accommodation Discount', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ACCOMMODATION_TOTAL       => esc_html__( 'Accommodation Total', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ACCOMMODATION_TAXES       => esc_html__( 'Accommodation Taxes', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_ACCOMMODATION_TAXES_TOTAL => esc_html__( 'Accommodation Taxes Total', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_SERVICES                  => esc_html__( 'Services', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_SERVICES_SUBTOTAL         => esc_html__( 'Services Subtotal', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_SERVICES_DISCOUNT         => esc_html__( 'Services Discount', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_SERVICES_TOTAL            => esc_html__( 'Services Total', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_SERVICE_TAXES_TOTAL       => esc_html__( 'Service Taxes Total', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_FEES                      => esc_html__( 'Fees', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_FEES_SUBTOTAL             => esc_html__( 'Fees Subtotal', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_FEES_DISCOUNT             => esc_html__( 'Fees Discount', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_FEES_TOTAL                => esc_html__( 'Fees Total', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_FEE_TAXES_TOTAL           => esc_html__( 'Fee Taxes Total', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_COUPON                    => esc_html__( 'Coupon', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_DISCOUNT                  => esc_html__( 'Discount', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_PRICE                     => esc_html__( 'Total', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_PAID                      => esc_html__( 'Paid', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_PAYMENTS                  => esc_html__( 'Payment Details', 'motopress-hotel-booking' ),
				static::EXPORT_COLUMN_DATE                      => esc_html__( 'Date', 'motopress-hotel-booking' ),
			)
		);
	}

	/**
	 * @param ReservedRoom $reservedRoom
	 * @param array $columnNames List of column names from contants in this class
	 * @return array [ column_name => column_value, ... ]
	 */
	public static function getReservedRoomData( $reservedRoom, $columnNames ) {

		$columnsWithData    = array();
		$booking            = $reservedRoom->getBooking();
		$roomPriceBreakdown = ! is_null( $reservedRoom->getLastRoomPriceBreakdown() ) ? $reservedRoom->getLastRoomPriceBreakdown() : array();

		foreach ( $columnNames as $columnName ) {

			$columnValue = '';

			switch ( $columnName ) {

				case static::EXPORT_COLUMN_BOOKING_ID:
					$columnValue = $booking->getId();
					break;

				case static::EXPORT_COLUMN_BOOKING_STATUS:
					$columnValue = mphb_get_status_label( $booking->getStatus() );
					break;

				case static::EXPORT_COLUMN_CHECK_IN:
					$columnValue = $booking->getCheckInDate()->format( MPHB()->settings()->dateTime()->getDateFormat() );
					break;

				case static::EXPORT_COLUMN_CHECK_OUT:
					$columnValue = $booking->getCheckOutDate()->format( MPHB()->settings()->dateTime()->getDateFormat() );
					break;

				case static::EXPORT_COLUMN_ROOM_TYPE:
					$roomTypeIdForCurrentLanguage = MPHB()->translation()->getOriginalId(
						$reservedRoom->getRoomTypeId(),
						MPHB()->postTypes()->roomType()->getPostType()
					);

					$roomTypeForCurrentLanguage = MPHB()->getRoomTypeRepository()->findById( $roomTypeIdForCurrentLanguage );
					$columnValue                = null !== $roomTypeForCurrentLanguage ? $roomTypeForCurrentLanguage->getTitle() : '';
					break;

				case static::EXPORT_COLUMN_ROOM_TYPE_ID:
					$roomTypeIdForCurrentLanguage = MPHB()->translation()->getOriginalId(
						$reservedRoom->getRoomTypeId(),
						MPHB()->postTypes()->roomType()->getPostType()
					);
					$columnValue                  = $roomTypeIdForCurrentLanguage;
					break;

				case static::EXPORT_COLUMN_ROOM:
					$roomIdForCurrentLanguage = MPHB()->translation()->getOriginalId(
						$reservedRoom->getRoomId(),
						MPHB()->postTypes()->room()->getPostType()
					);

					$accommodation = MPHB()->getRoomRepository()->findById( $roomIdForCurrentLanguage );
					$columnValue   = null !== $accommodation ? $accommodation->getTitle() : '';
					break;

				case static::EXPORT_COLUMN_RATE:
					$columnValue = $reservedRoom->getRateTitle();
					break;

				case static::EXPORT_COLUMN_ADULTS:
					$columnValue = $reservedRoom->getAdults();
					break;

				case static::EXPORT_COLUMN_CHILDREN:
					$columnValue = $reservedRoom->getChildren();
					break;

				case static::EXPORT_COLUMN_FIRST_NAME:
					$columnValue = $booking->getCustomer()->getFirstName();
					break;

				case static::EXPORT_COLUMN_LAST_NAME:
					$columnValue = $booking->getCustomer()->getLastName();
					break;

				case static::EXPORT_COLUMN_EMAIL:
					$columnValue = $booking->getCustomer()->getEmail();
					break;

				case static::EXPORT_COLUMN_PHONE:
					$columnValue = $booking->getCustomer()->getPhone();
					break;

				case static::EXPORT_COLUMN_COUNTRY:
					$columnValue = $booking->getCustomer()->getCountry();
					break;

				case static::EXPORT_COLUMN_ADDRESS:
					$columnValue = $booking->getCustomer()->getAddress1();
					break;

				case static::EXPORT_COLUMN_CITY:
					$columnValue = $booking->getCustomer()->getCity();
					break;

				case static::EXPORT_COLUMN_STATE:
					$columnValue = $booking->getCustomer()->getState();
					break;

				case static::EXPORT_COLUMN_POSTCODE:
					$columnValue = $booking->getCustomer()->getZip();
					break;

				case static::EXPORT_COLUMN_CUSTOMER_NOTE:
					$columnValue = $booking->getNote();
					break;

				case static::EXPORT_COLUMN_GUEST_NAME:
					$columnValue = $reservedRoom->getGuestName();
					break;

				case static::EXPORT_COLUMN_ACCOMMODATION_SUBTOTAL:
					if ( ! empty( $roomPriceBreakdown['room']['total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['room']['total'] );
					}
					break;

				case static::EXPORT_COLUMN_ACCOMMODATION_DISCOUNT:
					if ( ! empty( $roomPriceBreakdown['room']['discount'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['room']['discount'] );
					}
					break;

				case static::EXPORT_COLUMN_ACCOMMODATION_TOTAL:
					if ( ! empty( $roomPriceBreakdown['room']['discount_total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['room']['discount_total'] );
					}
					break;

				case static::EXPORT_COLUMN_ACCOMMODATION_TAXES:
					$columnValue = static::getReservedRoomTaxes( $roomPriceBreakdown );
					break;

				case static::EXPORT_COLUMN_ACCOMMODATION_TAXES_TOTAL:
					if ( ! empty( $roomPriceBreakdown['taxes']['room']['total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['taxes']['room']['total'] );
					}
					break;

				case static::EXPORT_COLUMN_SERVICES:
					$columnValue = static::getReservedRoomServices( $reservedRoom, $booking );
					break;

				case static::EXPORT_COLUMN_SERVICES_SUBTOTAL:
					if ( ! empty( $roomPriceBreakdown['services']['total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['services']['total'] );
					}
					break;

				case static::EXPORT_COLUMN_SERVICES_DISCOUNT:
					if ( ! empty( $roomPriceBreakdown['services']['discount'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['services']['discount'] );
					}
					break;

				case static::EXPORT_COLUMN_SERVICES_TOTAL:
					if ( ! empty( $roomPriceBreakdown['services']['discount_total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['services']['discount_total'] );
					}
					break;

				case static::EXPORT_COLUMN_SERVICE_TAXES_TOTAL:
					if ( ! empty( $roomPriceBreakdown['taxes']['services']['total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['taxes']['services']['total'] );
					}
					break;

				case static::EXPORT_COLUMN_FEES:
					$columnValue = static::getReservedRoomFees( $roomPriceBreakdown );
					break;

				case static::EXPORT_COLUMN_FEES_SUBTOTAL:
					if ( ! empty( $roomPriceBreakdown['fees']['total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['fees']['total'] );
					}
					break;

				case static::EXPORT_COLUMN_FEES_DISCOUNT:
					if ( ! empty( $roomPriceBreakdown['fees']['discount'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['fees']['discount'] );
					}
					break;

				case static::EXPORT_COLUMN_FEES_TOTAL:
					if ( ! empty( $roomPriceBreakdown['fees']['discount_total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['fees']['discount_total'] );
					}
					break;

				case static::EXPORT_COLUMN_FEE_TAXES_TOTAL:
					if ( ! empty( $roomPriceBreakdown['taxes']['fees']['total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['taxes']['fees']['total'] );
					}
					break;

				case static::EXPORT_COLUMN_COUPON:
					$columnValue = $booking->getCouponCode();
					break;

				case static::EXPORT_COLUMN_DISCOUNT:
					if ( ! empty( $roomPriceBreakdown['discount'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['discount'] );
					}
					break;

				case static::EXPORT_COLUMN_PRICE:
					if ( ! empty( $roomPriceBreakdown['discount_total'] ) ) {
						$columnValue = static::formatPrice( $roomPriceBreakdown['discount_total'] );
					}
					break;

				case static::EXPORT_COLUMN_PAID:
					$columnValue = static::getReservedRoomPaidAmount( $booking, $roomPriceBreakdown );
					break;

				case static::EXPORT_COLUMN_PAYMENTS:
					$columnValue = static::getBookingPayments( $booking );
					break;

				case static::EXPORT_COLUMN_DATE:
					$columnValue = get_the_date( MPHB()->settings()->dateTime()->getDateFormat() . ' H:i:s', $booking->getId() );
					break;
			}

			$columnsWithData[ $columnName ] = $columnValue;
		}

		return apply_filters( 'mphb_export_bookings_parse_columns', $columnsWithData, $booking, $reservedRoom );
	}

	/**
	 * @since 5.0.0
	 *
	 * @param float $price
	 * @return string
	 */
	private static function formatPrice( $price ) {
		$priceString = mphb_format_price(
			$price,
			array(
				'thousand_separator' => '',
				'as_html'            => false,
			)
		);

		// Decode #&36; into $
		return html_entity_decode( $priceString );
	}

	/**
	 * @param ReservedRoom $reservedRoom
	 * @param Booking $booking
	 */
	private static function getReservedRoomServices( $reservedRoom, $booking ): string {

		$reservedServices = $reservedRoom->getReservedServices();

		if ( empty( $reservedServices ) ) {
			return '';
		}

		$nights   = $booking->getNightsCount();
		$services = array();

		foreach ( $reservedServices as $reservedService ) {

			$reservedService = MPHB()->translation()->translateReservedService( $reservedService );

			$service = html_entity_decode( $reservedService->toString( 'title', $nights ) );

			$services[] = $service;
		}

		return implode( ', ', $services );
	}

	/**
	 * @param array $roomPriceBreakdown
	 */
	private static function getReservedRoomTaxes( $roomPriceBreakdown ): string {

		$taxText = array();

		if ( ! empty( $roomPriceBreakdown['taxes']['room']['list'] ) ) {

			foreach ( $roomPriceBreakdown['taxes']['room']['list'] as $roomTax ) {
				$tax       = static::formatPrice( $roomTax['price'] );
				$taxLabel  = $roomTax['label'];
				$taxText[] = "{$tax},{$taxLabel}";
			}
		}

		return ! empty( $taxText ) ? implode( ';', $taxText ) : '';
	}

	/**
	 * @param array $roomPriceBreakdown
	 */
	private static function getReservedRoomFees( $roomPriceBreakdown ): string {

		$taxText = array();

		if ( ! empty( $roomPriceBreakdown['fees']['list'] ) ) {

			foreach ( $roomPriceBreakdown['fees']['list'] as $roomTax ) {
				$tax       = static::formatPrice( $roomTax['price'] );
				$taxLabel  = $roomTax['label'];
				$taxText[] = "{$tax},{$taxLabel}";
			}
		}

		return implode( ';', $taxText );
	}

	/**
	 * @param Booking $booking
	 * @param array $roomPriceBreakdown
	 */
	private static function getReservedRoomPaidAmount( $booking, $roomPriceBreakdown ): string {

		$reservedRoomPaidAmount = 0;

		$payments = MPHB()->getPaymentRepository()->findAll(
			array(
				'booking_id'  => $booking->getId(),
				'post_status' => \MPHB\PostTypes\PaymentCPT\Statuses::STATUS_COMPLETED,
			)
		);

		$bookingPriceBreakdown = $booking->getLastPriceBreakdown();

		if ( ! empty( $payments ) && 0 < $bookingPriceBreakdown['total'] ) {

			$bookingPaid = 0.0;

			foreach ( $payments as $payment ) {

				$bookingPaid += $payment->getAmount();
			}

			$reservedRoomPaidAmount = $roomPriceBreakdown['discount_total'] / $bookingPriceBreakdown['total'] * $bookingPaid;
		}

		return static::formatPrice( $reservedRoomPaidAmount );
	}

	/**
	 * @param Booking $booking
	 * @return string
	 */
	private static function getBookingPayments( $booking ): string {

		$payments = MPHB()->getPaymentRepository()->findAll(
			array(
				'booking_id'  => $booking->getId(),
				'post_status' => \MPHB\PostTypes\PaymentCPT\Statuses::STATUS_COMPLETED,
			)
		);

		$paymentStrings = array();

		foreach ( $payments as $payment ) {

			$paymentId     = $payment->getId();
			$paymentStatus = mphb_get_status_label( $payment->getStatus() );
			$paidAmount    = static::formatPrice( $payment->getAmount() );

			$paymentGateway      = MPHB()->gatewayManager()->getGateway( $payment->getGatewayId() );
			$paymentGatewayLabel = ! is_null( $paymentGateway ) ? $paymentGateway->getAdminTitle() : $payment->getGatewayId();

			$paymentStrings[] = "#{$paymentId},{$paymentStatus},{$paidAmount},{$paymentGatewayLabel}";
		}

		return implode( ';', $paymentStrings );
	}
}
