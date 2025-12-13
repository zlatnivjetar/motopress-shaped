<?php

namespace MPHB\Payments\Gateways\Stripe;

use MPHB\Payments\Gateways\{ AbstractNotificationListener, StripeGateway };
use MPHB\PostTypes\PaymentCPT\Statuses as PaymentStatuses;
use MPHB\Stripe\Event;

/**
 * @since 3.6.0
 */
class WebhookListener extends AbstractNotificationListener {
	/**
	 * @var StripeGateway
	 */
	protected $gateway;

	private static $isStripeAPIClassesLoaded = false;

	private $eventType     = '';
	private $eventObject   = null; // Payment intent or Charge (or Source)
	private $isEventProcessingMustBeOk = false;

	protected function initUrlIdentificationValue() {
		return 'stripe';
	}

	/**
	 * @return array
	 */
	protected function parseInput() {

		$payload = @file_get_contents( 'php://input' );
		$payload = json_decode( $payload, true );

		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * @param array $input
	 * @return boolean
	 */
	protected function validate( $input ) {

		if ( ! isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) {
			return false;
		}

		if ( ! self::$isStripeAPIClassesLoaded ) {

			require_once MPHB()->getPluginPath( 'vendors/stripe-api/init.php' );
			self::$isStripeAPIClassesLoaded = true;
		}

		$this->gateway->getApi()->setApp();

		$event = null;

		try {

			// See code example at https://stripe.com/docs/webhooks/setup#create-endpoint
			$event = Event::constructFrom( $input );

			$this->eventType   = $event->type;
			$this->eventObject = $event->data->object;

		} catch ( \Throwable $e ) {
			return false;
		}

		return ! empty( $this->eventType ) && ! empty( $this->eventObject );
	}

	/**
	 * @return \MPHB\Entities\Payment|null
	 */
	protected function retrievePayment() {

		$payment = null;

		if ( 'source' == $this->eventObject->object ) {

			// TODO: remove source later when all clients update plugin
			// and finish all source payments
			return MPHB()->getPaymentRepository()->findByMeta(
				'_mphb_transaction_source_id',
				$this->eventObject->id
			);
		} elseif ( 'payment_intent' == $this->eventObject->object ) {

			// To find payment we need to get Payment Itent Id
			$payment = MPHB()->getPaymentRepository()->findByMeta(
				'_mphb_transaction_id',
				$this->eventObject->id
			);

			// Stripe triggers webhook too early for create payment intent and 
			// we do not create the payment yet. So we skip the event and don't mark it
			// as "Failed" in Stripe Dashboard. Otherwise the customer will have
			// "Failed" for each first try of the webhook
			$this->isEventProcessingMustBeOk = null === $payment;

		} elseif ( 'charge' == $this->eventObject->object ) {
			
			// If this is a Charge for old source payment
			// To find payment we need to get Source Id = $this->eventObject->id
			// TODO: remove source later when all clients update plugin
			// and finish all source payments
			$payment = MPHB()->getPaymentRepository()->findByMeta(
				'_mphb_transaction_id',
				$this->eventObject->id
			);

			// If this is a Charge for зфньуте штеуте
			// To find payment we need to get Payment Itent Id
			// If this is a Charge event then Payment Intent Id = $this->eventObject->payment_intent
			if ( empty( $payment ) && ! empty( $this->eventObject->payment_intent ) ) {

				$payment = MPHB()->getPaymentRepository()->findByMeta(
					'_mphb_transaction_id',
					$this->eventObject->payment_intent
				);
			}

			// Stripe triggers webhook too early for create payment intent and 
			// we do not create the payment yet. So we skip the event and don't mark it
			// as "Failed" in Stripe Dashboard. Otherwise the customer will have
			// "Failed" for each first try of the webhook
			$this->isEventProcessingMustBeOk = null === $payment &&
				(
					! empty( $this->eventObject->payment_method_details ) &&
					! empty( $this->eventObject->payment_method_details->type ) &&
					(
						'card' === $this->eventObject->payment_method_details->type ||
						'charge.pending' === $this->eventType // for sepa_debit
					)
				);
		}

		return $payment;
	}

	protected function process() {

		switch ( $this->eventType ) {

			case 'payment_intent.canceled':

				$this->paymentFailed(
					sprintf(
						// translators: %s - Stripe event object ID
						__( 'Webhook received. Payment %s was cancelled by the customer.', 'motopress-hotel-booking' ),
						$this->eventObject->id
					)
				);
				break;

			case 'payment_intent.payment_failed':

				$this->paymentFailed(
					sprintf(
						// translators: %s - Stripe event object ID
						__( "Webhook received. Payment %s failed and couldn't be processed.", 'motopress-hotel-booking' ),
						$this->eventObject->id
					)
				);
				break;

			case 'payment_intent.requires_action':

				$this->paymentOnHold(
					sprintf(
						// translators: %s - Stripe event object ID
						__( 'Webhook received. Payment %s is waiting for customer confirmation.', 'motopress-appointment' ),
						$this->eventObject->id
					)
				);

				break;

			case 'payment_intent.succeeded':

				if ( PaymentStatuses::STATUS_PENDING === $this->payment->getStatus() ||
					PaymentStatuses::STATUS_ON_HOLD === $this->payment->getStatus()
				) {
					$this->paymentCompleted(
						sprintf(
							// translators: %s - Stripe event object ID
							__( 'Webhook received. Payment %s was successfully processed.', 'motopress-hotel-booking' ),
							$this->eventObject->id
						)
					);
				}
				break;

			case 'charge.succeeded':

				if ( PaymentStatuses::STATUS_PENDING === $this->payment->getStatus() ||
					PaymentStatuses::STATUS_ON_HOLD === $this->payment->getStatus()
				) {
					$this->paymentCompleted(
						sprintf(
							// translators: %s - Stripe Charge ID
							__( 'Webhook received. Charge %s succeeded.', 'motopress-hotel-booking' ),
							$this->eventObject->id
						)
					);
				}
				break;

			case 'charge.failed':

				$this->paymentFailed(
					sprintf(
						// translators: %s - Stripe Charge ID
						__( 'Webhook received. Charge %s failed.', 'motopress-hotel-booking' ),
						$this->eventObject->id
					)
				);
				break;

			// TODO: remove source later when all clients update plugin
			// and finish all source payments
			case 'source.chargeable':

				// translators: %s - Stripe Source ID
				$message = sprintf( __( 'Webhook received. The source %s is chargeable.', 'motopress-hotel-booking' ), $this->eventObject->id );
				$this->payment->addLog( $message );

				MPHB()->gatewayManager()->getStripeGateway()->chargePayment(
					$this->payment,
					$this->eventObject
				);
				break;

			case 'source.canceled':

				$this->paymentFailed(
					sprintf(
						// translators: %s - Stripe Source ID
						__( 'Webhook received. Payment source %s was cancelled by customer.', 'motopress-hotel-booking' ),
						$this->eventObject->id
					)
				);
				break;

			case 'source.failed':

				$this->paymentFailed(
					sprintf(
						// translators: %s - Stripe Source ID
						__( "Webhook received. Payment source %s failed and couldn't be processed.", 'motopress-hotel-booking' ),
						$this->eventObject->id
					)
				);
				break;
		}
	}

	public function fireExit( $succeed ) {

		if ( $succeed || $this->isEventProcessingMustBeOk ) {

			http_response_code( 200 );

		} else {

			http_response_code( 400 );
		}

		parent::fireExit( $succeed );
	}

	protected function paymentCompleted( $log ) {
		return MPHB()->paymentManager()->completePayment( $this->payment, $log );
	}

	protected function paymentRefunded( $log ) {
		return MPHB()->paymentManager()->refundPayment( $this->payment, $log );
	}

	protected function paymentFailed( $log ) {
		return MPHB()->paymentManager()->failPayment( $this->payment, $log );
	}

	protected function paymentOnHold( $log ) {
		return MPHB()->paymentManager()->holdPayment( $this->payment, $log );
	}
}
