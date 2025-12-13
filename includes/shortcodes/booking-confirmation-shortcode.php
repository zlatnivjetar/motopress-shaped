<?php

namespace MPHB\Shortcodes;

use MPHB\Entities\Booking;
use MPHB\Entities\Payment;
use MPHB\PostTypes\PaymentCPT\Statuses as PaymentStatuses;
use MPHB\UserActions\BookingConfirmationAction;
use MPHB\Utils\BookingUtils;
use MPHB\Utils\DateUtils;

/**
 * @since 3.7.0 added payment confirmation message.
 * @since 3.7.0 added booking details.
 * @since 3.7.0 added payment details.
 * @since 3.7.0 added payment instructions.
 * @since 5.0.0 shows all payments instead of just one.
 */
class BookingConfirmationShortcode extends AbstractShortcode {

	const NO_VALUE_PLACEHOLDER = '&#8212;';

	protected $name = 'mphb_booking_confirmation';

	/**
	 * @since 5.0.0
	 *
	 * @var Booking|null
	 */
	protected $booking = null;

	/**
	 * @since 5.0.0
	 *
	 * @var Payment[]
	 */
	protected $payments = array();

	/**
	 * @since 5.0.0 replaced the $payment field.
	 *
	 * @var Payment|null
	 */
	protected $focusPayment = null;

	public function render( $atts, $content, $shortcodeName ) {
		$defaultAtts = array(
			'class' => '',
		);

		$atts = shortcode_atts( $defaultAtts, $atts, $shortcodeName );

		$this->afterLoad();

		// Render shortcode
		$wrapperClass = apply_filters( 'mphb_sc_booking_confirmation_wrapper_class', 'mphb_sc_booking_confirmation' );
		$wrapperClass = trim( $wrapperClass . ' ' . $atts['class'] );

		$output = '<div class="' . esc_attr( $wrapperClass ) . '">';
			// Show confirmation messages
			$output .= $this->renderBookingConfirmation();
			$output .= $this->renderPaymentConfirmation();
			// Show booking/payment details
			$output .= $this->renderBookingDetails();
			$output .= $this->renderPaymentDetails();
			// Show payment instructions
			$output .= $this->renderPaymentInstructions();
			// Method with action to output some custom booking details by addons
			$output .= $this->renderBottomInformation();

		$output .= '</div>';

		return $output;
	}

	public function renderBookingConfirmation() {
		$status = $this->detectBookingConfirmationStatus();

		if ( $status === false ) {
			return '';
		}

		ob_start();

		do_action( 'mphb_sc_booking_confirmation_before_confirmation_messages' );

		if ( BookingConfirmationAction::STATUS_CONFIRMED == $status ||
			BookingConfirmationAction::STATUS_ALREADY_CONFIRMED == $status ) {

			mphb_get_template_part( 'shortcodes/booking-confirmation/received' );
		}

		do_action( 'mphb_sc_booking_confirmation_between_confirmation_messages' );

		switch ( $status ) {
			case BookingConfirmationAction::STATUS_INVALID_REQUEST:
				mphb_get_template_part( 'shortcodes/booking-confirmation/invalid-request' );
				break;
			case BookingConfirmationAction::STATUS_CONFIRMED:
				mphb_get_template_part( 'shortcodes/booking-confirmation/confirmed' );
				break;
			case BookingConfirmationAction::STATUS_EXPIRED:
				mphb_get_template_part( 'shortcodes/booking-confirmation/expired' );
				break;
			case BookingConfirmationAction::STATUS_CONFIRMATION_NOT_POSSIBLE:
				mphb_get_template_part( 'shortcodes/booking-confirmation/not-possible' );
				break;
			case BookingConfirmationAction::STATUS_ALREADY_CONFIRMED:
				mphb_get_template_part( 'shortcodes/booking-confirmation/already-confirmed' );
				break;
		}

		do_action( 'mphb_sc_booking_confirmation_after_confirmation_messages' );

		$messages = ob_get_clean();

		return '<div class="mphb-booking-confirmation-messages">' . $messages . '</div>';
	}

	/**
	 * @return string|false
	 */
	public function detectBookingConfirmationStatus() {
		if ( ! isset( $_GET['mphb_confirmation_status'] ) ) {
			return false;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['mphb_confirmation_status'] ) );

		$allowedStatuses = array(
			BookingConfirmationAction::STATUS_ALREADY_CONFIRMED,
			BookingConfirmationAction::STATUS_CONFIRMATION_NOT_POSSIBLE,
			BookingConfirmationAction::STATUS_CONFIRMED,
			BookingConfirmationAction::STATUS_EXPIRED,
			BookingConfirmationAction::STATUS_INVALID_REQUEST,
		);

		if ( ! in_array( $status, $allowedStatuses ) ) {
			return false;
		}

		return $status;
	}

	public function renderPaymentConfirmation() {
		$status = $this->detectPaymentConfirmationStatus();

		if ( $status === false ) {
			return '';
		}

		ob_start();

		do_action( 'mphb_sc_booking_confirmation_before_payment_message' );

		switch ( $status ) {
			case PaymentStatuses::STATUS_COMPLETED:
				mphb_get_template_part( 'shortcodes/payment-confirmation/completed' );
				break;
			case PaymentStatuses::STATUS_ON_HOLD:
			case PaymentStatuses::STATUS_PENDING:
			case 'received':
				mphb_get_template_part( 'shortcodes/payment-confirmation/received' );
				break;
		}

		do_action( 'mphb_sc_booking_confirmation_after_payment_message' );

		$message = ob_get_clean();

		return '<div class="mphb-payment-messages">' . $message . '</div>';
	}

	/**
	 * @return string|false
	 */
	public function detectPaymentConfirmationStatus() {
		if ( ! isset( $_GET['mphb_payment_status'] ) ) {
			return false;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['mphb_payment_status'] ) );

		if ( $status == 'auto' ) {
			if ( ! is_null( $this->focusPayment ) ) {
				$status = $this->focusPayment->getStatus();
			} else {
				// Example: StripeGatewey::getCheckoutData() builds URL of
				// Payment Success Page booking/payment ID is not set, but we
				// need to show some message
				$status = 'received';
			}
		}

		$allowedStatuses = array(
			PaymentStatuses::STATUS_COMPLETED,
			PaymentStatuses::STATUS_ON_HOLD,
			PaymentStatuses::STATUS_PENDING,
			'received',
		);

		if ( ! in_array( $status, $allowedStatuses ) ) {
			return false;
		}

		return $status;
	}

	/**
	 * @return string
	 */
	public function renderPaymentInstructions() {

		if ( is_null( $this->focusPayment ) ) {
			return '';
		}

		$output = '';

		$gatewayId    = $this->focusPayment->getGatewayId();
		$instructions = MPHB()->gatewayManager()->getGateway( $gatewayId )->getInstructions();

		if ( ! empty( $instructions ) ) {

			$output .= '<div class="mphb-payment-instructions">';
			$output .= wp_kses_post( wpautop( wptexturize( wp_kses_post( $instructions ) ) ) );
			$output .= '</div>';
		}

		return $output;
	}

	/**
	 * @return string
	 */
	public function renderBookingDetails() {
		if ( is_null( $this->booking ) ) {
			return '';
		}

		$reservedTypes = BookingUtils::getReservedRoomTypesList( $this->booking );
		if ( empty( $reservedTypes ) ) {
			$accommodations = '&#8212;';
		} else {
			$links          = array_map(
				function ( $roomTypeId, $title ) {
					return '<a href="' . esc_url( get_permalink( $roomTypeId ) ) . '">' . esc_html( $title ) . '</a>';
				},
				array_keys( $reservedTypes ),
				$reservedTypes
			);
			$accommodations = implode( ', ', $links );
		}

		$checkInDateFormatted  = DateUtils::formatDateWPFront( $this->booking->getCheckInDate() );
		$checkOutDateFormatted = DateUtils::formatDateWPFront( $this->booking->getCheckOutDate() );

		ob_start();

		mphb_get_template_part(
			'shortcodes/booking-details/booking-details',
			array(
				'booking'               => $this->booking,
				'checkInDateFormatted'  => $checkInDateFormatted,
				'checkOutDateFormatted' => $checkOutDateFormatted,
				'accommodations'        => $accommodations,
			)
		);
		$output = ob_get_clean();
		return $output;
	}

	/**
	 * @return string
	 */
	public function renderPaymentDetails() {
		if ( empty( $this->payments ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="mphb-booking-details-section payment">
			<h3 class="mphb-booking-details-title"><?php esc_html_e( 'Payment Details', 'motopress-hotel-booking' ); ?></h3>

			<?php
			foreach ( $this->payments as $payment ) {
				$gateway      = MPHB()->gatewayManager()->getGateway( $payment->getGatewayId() );
				$gatewayTitle = ! is_null( $gateway ) ? $gateway->getAdminTitle() : self::NO_VALUE_PLACEHOLDER;
				?>

				<ul class="mphb-booking-details">
					<li class="payment-number">
						<span class="label"><?php esc_html_e( 'Payment:', 'motopress-hotel-booking' ); ?></span>
						<span class="value"><?php echo esc_html( $payment->getId() ); ?></span>
					</li>
					<li class="payment-number">
						<span class="label"><?php esc_html_e( 'Date:', 'motopress-hotel-booking' ); ?></span>
						<span class="value"><?php echo esc_html( DateUtils::formatDateWPFront( $payment->getDate() ) ); ?></span>
					</li>
					<li class="payment-number">
						<span class="label"><?php esc_html_e( 'Payment Method:', 'motopress-hotel-booking' ); ?></span>
						<span class="value"><?php echo esc_html( $gatewayTitle ); ?></span>
					</li>
					<li class="payment-number">
						<span class="label"><?php esc_html_e( 'Total:', 'motopress-hotel-booking' ); ?></span>
						<span class="value">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo mphb_format_price( $payment->getAmount() );
							?>
						</span>
					</li>
					<li class="payment-number">
						<span class="label"><?php esc_html_e( 'Status:', 'motopress-hotel-booking' ); ?></span>
						<span class="value"><?php echo esc_html( mphb_get_status_label( $payment->getStatus() ) ); ?></span>
					</li>
				</ul>
			<?php } ?>
		</div>
		<?php

		$output = ob_get_clean();
		return $output;
	}

	public function renderBottomInformation() {
		ob_start();
		do_action( 'mphb_sc_booking_confirmation_bottom' );
		return ob_get_clean();
	}

	/**
	 * @since 4.2.2
	 */
	protected function afterLoad() {
		$this->booking = $this->findBookingByCredentials();

		if ( ! is_null( $this->booking ) ) {
			/**
			 * @param Booking $booking
			 */
			do_action( 'mphb_focus_on_booking', $this->booking );

			$this->payments = mphb_bookings_facade()->findPaymentsByBookingId( $this->booking->getId() );
		}

		$this->focusPayment = $this->findPaymentByCredentials();

		if ( ! is_null( $this->focusPayment ) && empty( $this->payments ) ) {
			$this->payments[] = $this->focusPayment;
		}

		if ( ! is_null( $this->focusPayment ) && $this->detectPaymentConfirmationStatus() !== false ) {
			$this->focusPayment->setAuthorized();
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @return Booking|null
	 */
	protected function findBookingByCredentials() {
		if ( isset( $_GET['booking_id'], $_GET['booking_key'] ) ) {
			$bookingId  = absint( $_GET['booking_id'] );
			$bookingKey = mphb_clean( wp_unslash( $_GET['booking_key'] ) );

			return mphb_bookings_facade()->findBookingByIdAndKey( $bookingId, $bookingKey );
		} else {
			return $this->findBookingByPaymentCredentials();
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @return Booking|null
	 */
	protected function findBookingByPaymentCredentials() {
		// Get booking by payment ID/key
		if ( isset( $_GET['payment_id'], $_GET['payment_key'] ) ) {
			$paymentId  = absint( $_GET['payment_id'] );
			$paymentKey = mphb_clean( wp_unslash( $_GET['payment_key'] ) );

			return mphb_bookings_facade()->findBookingByPaymentIdAndKey( $paymentId, $paymentKey );
		}

		if ( isset( $_GET['payment_intent'], $_GET['payment_intent_client_secret'] ) ) {
			// Get booking by Stripe payment intent
			$paymentIntentId = mphb_clean( wp_unslash( $_GET['payment_intent'] ) );
			$clientSecret    = mphb_clean( wp_unslash( $_GET['payment_intent_client_secret'] ) );

			return mphb_bookings_facade()->findBookingByPaymentStripeIntentId( $paymentIntentId, $clientSecret );

		} elseif ( isset( $_GET['source'], $_GET['client_secret'] ) ) {
			// Get booking by Stripe source

			// TODO: remove source later when all clients update plugin and
			// finish all source payments

			$sourceId     = mphb_clean( wp_unslash( $_GET['source'] ) );
			$clientSecret = mphb_clean( wp_unslash( $_GET['client_secret'] ) );

			return mphb_bookings_facade()->findBookingByPaymentStripeSourceId( $sourceId, $clientSecret );
		}

		// Nothing found at this point
		return null;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return Payment|null
	 */
	protected function findPaymentByCredentials() {
		if ( isset( $_GET['payment_id'], $_GET['payment_key'] ) ) {
			$paymentId  = absint( $_GET['payment_id'] );
			$paymentKey = mphb_clean( wp_unslash( $_GET['payment_key'] ) );

			return mphb_bookings_facade()->findPaymentByIdAndKey( $paymentId, $paymentKey );
		} else {
			return null;
		}
	}
}
