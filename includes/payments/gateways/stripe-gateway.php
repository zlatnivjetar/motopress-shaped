<?php

namespace MPHB\Payments\Gateways;

use MPHB\Admin\Fields;
use MPHB\Admin\Groups;
use MPHB\Core\StringEncryptHelper;
use MPHB\Entities\Booking;
use MPHB\Entities\Payment;
use MPHB\Payments\Gateways\Stripe\StripeAPI;
use MPHB\Payments\Gateways\Stripe\WebhookListener;
use MPHB\PostTypes\PaymentCPT\Statuses as PaymentStatuses;
use MPHB\Shortcodes\CheckoutShortcode;
use MPHB\Stripe\Source;

/**
 * How to test:
 *     https://docs.stripe.com/testing
 *     https://docs.stripe.com/payments/payment-intents/upgrade-to-handle-actions?platform=web#test-manual
 */
class StripeGateway extends Gateway {
	public const GATEWAY_ID = 'stripe';

	// https://stripe.com/docs/payments/klarna
	private $klarnaAllowedCurrencyCodes = array( 'AUD', 'CAD', 'CHF', 'CZK', 'EUR', 'GBP', 'DKK', 'NOK', 'NZD', 'PLN', 'SEK', 'USD' );

	/**
	 * @var string
	 */
	protected $locale = 'auto';

	/**
	 * @var StripeAPI
	 */
	protected $api = null;

	/**
	 * @var WebhookListener
	 */
	protected $webhookListener = null;

	// See method parsePaymentFields()
	protected $paymentFields = array(
		'payment_method'    => 'card',
		'payment_intent_id' => '',
		'source_id'         => '',
		'redirect_url'      => '',
	);

	public function __construct() {

		add_filter( 'mphb_gateway_has_instructions', array( $this, 'hideInstructions' ), 10, 2 );

		parent::__construct();

		$this->api = new StripeAPI( $this );

		add_action( 'init', array( $this, 'setupWebhooks' ), 9 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );

		// show payment status after user returns from Stripe side
		// must work as well as webhook even if gateway is already not active
		add_action(
			'init',
			function () {
				$this->checkStripeErrorBeforeShowingPaymentReceivedPage();
			}
		);
	}

	public function getPublicKey(): string {
		return StringEncryptHelper::decryptString( $this->getOption( 'public_key' ) );
	}

	public function getSecretKey(): string {
		return StringEncryptHelper::decryptString( $this->getOption( 'secret_key' ) );
	}

	public function getEndpointSecret(): string {
		return StringEncryptHelper::decryptString( $this->getOption( 'endpoint_secret' ) );
	}

	/**
	 * @return string[] "card", "ideal", "sepa_debit" etc.
	 */
	public function getPaymentMethods(): array {
		$paymentMethods = $this->getOption( 'payment_methods' );

		// Add "card" to payment methods
		if ( ! is_array( $paymentMethods ) ) {
			$paymentMethods = array( 'card' );
		} elseif ( ! in_array( 'card', $paymentMethods ) ) {
			$paymentMethods = array_merge( array( 'card' ), $paymentMethods );
		}

		return $paymentMethods;
	}

	/**
	 * @return boolean
	 */
	public function isActive() {

		return parent::isActive()
			&& ! empty( $this->getPublicKey() )
			&& ! empty( $this->getSecretKey() );
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		$description = parent::getDescription();

		if ( $this->isSandbox() ) {
			$description .= ' ' . sprintf( __( 'Use the card number %1$s with CVC %2$s, a valid expiration date and random 5-digit ZIP-code to test a payment.', 'motopress-hotel-booking' ), '4242424242424242', '123' );
			$description = trim( $description );
		}

		return $description;
	}

	/**
	 * @return strings
	 */
	public function getAdminDescription() {
		$adminDescription = parent::getAdminDescription();

		if ( ! is_null( $this->webhookListener ) ) {
			$adminDescription .= ' ' . sprintf( __( 'Webhooks Destination URL: %s', 'motopress-hotel-booking' ), '<code>' . esc_url( $this->webhookListener->getNotifyUrl() ) . '</code>' );
			$adminDescription = trim( $adminDescription );
		}

		return $adminDescription;
	}

	/**
	 * @param bool   $show
	 * @param string $gatewayId
	 * @return bool
	 *
	 * @since 3.6.1
	 */
	public function hideInstructions( $show, $gatewayId ) {

		if ( $gatewayId == $this->id ) {
			$show = false;
		}

		return $show;
	}

	/**
	 * @access private
	 */
	public function setupWebhooks() {
		$this->webhookListener = new WebhookListener( $this );
	}

	protected function setupProperties() {

		parent::setupProperties();

		$this->adminTitle = __( 'Stripe', 'motopress-hotel-booking' );
		$this->locale = $this->getOption( 'locale' );
	}

	public function enqueueScripts() {

		if ( $this->isActive() && mphb_is_checkout_page() ) {

			wp_enqueue_script( 'mphb-vendor-stripe-library' );
		}
	}

	protected function initDefaultOptions() {

		$defaults = array(
			'title'           => __( 'Pay by Card (Stripe)', 'motopress-hotel-booking' ),
			'description'     => __( 'Pay with your credit card via Stripe.', 'motopress-hotel-booking' ),
			'enabled'         => false,
			'is_sandbox'      => false,
			'public_key'      => '',
			'secret_key'      => '',
			'endpoint_secret' => '',
			'payment_methods' => array(),
			'locale'          => 'auto',
		);

		return array_merge( parent::initDefaultOptions(), $defaults );
	}

	protected function initOptionFields(): array {
		$paymentMethods = array(
			'bancontact' => __( 'Bancontact', 'motopress-hotel-booking' ),
			'ideal'      => __( 'iDEAL', 'motopress-hotel-booking' ),
			'giropay'    => __( 'Giropay', 'motopress-hotel-booking' ),
			'sepa_debit' => __( 'SEPA Direct Debit', 'motopress-hotel-booking' ),
			'klarna'     => __( 'Klarna', 'motopress-hotel-booking' ),
		);

		$currencySettingsUrl = get_admin_url( null, 'edit.php?post_type=mphb_room_type&page=mphb_settings#mphb_currency' );

		$paymentsWarning =
			'<a href="' . esc_url( $currencySettingsUrl ) . '" target="_blank">'
				. sprintf(
					// translators: %s - currency codes
					__( 'The %s currency is selected in the main settings.', 'motopress-hotel-booking' ),
					MPHB()->settings()->currency()->getCurrencyCode()
				)
			. '</a> '
			. sprintf(
				// translators: %1$s - names of payment methods, %2$s - currency codes
				__( '%1$s support the following currencies: %2$s.', 'motopress-hotel-booking' ),
				__( 'Bancontact', 'motopress-hotel-booking' ) . ', '
					. __( 'iDEAL', 'motopress-hotel-booking' ) . ', '
					. __( 'Giropay', 'motopress-hotel-booking' ) . ' and '
					. __( 'SEPA Direct Debit', 'motopress-hotel-booking' ),
				'EUR'
			)
			. ' ' . sprintf(
				// translators: %1$s - name of payment method, %2$s - currency codes
				__( '%1$s supports: %2$s.', 'motopress-hotel-booking' ),
				__( 'Klarna', 'motopress-hotel-booking' ),
				implode( ', ', $this->klarnaAllowedCurrencyCodes )
			)
			. ' <a href="https://stripe.com/docs/payments/klarna" target="_blank">'
				. sprintf(
					// translators: %s - payment method name
					__( '%s special restrictions.', 'motopress-hotel-booking' ),
					__( 'Klarna', 'motopress-hotel-booking' )
				)
			. '</a>';

		$stripeFields = array(
			'public_key' => array(
				'type'        => 'text',
				'label'       => __( 'Public Key', 'motopress-hotel-booking' ),
				'description' => '<a href="https://support.stripe.com/questions/locate-api-keys" target="_blank">Find API Keys</a>',
				'default'     => $this->getDefaultOption( 'public_key' ),
				'encoded'     => true,
			),
			'secret_key' => array(
				'type'    => 'text',
				'label'   => __( 'Secret Key', 'motopress-hotel-booking' ),
				'default' => $this->getDefaultOption( 'secret_key' ),
				'encoded' => true,
			),
			'endpoint_secret' => array(
				'type'        => 'text',
				'label'       => __( 'Webhook Secret', 'motopress-hotel-booking' ),
				'description' => '<a href="https://stripe.com/docs/webhooks/setup#configure-webhook-settings" target="_blank">Setting Up Webhooks</a>',
				'default'     => $this->getDefaultOption( 'endpoint_secret' ),
				'encoded'     => true,
			),
			'payment_methods' => array(
				'type'                => 'multiple-checkbox',
				'label'               => __( 'Payment Methods', 'motopress-hotel-booking' ),
				'description'         => $paymentsWarning,
				'list'                => $paymentMethods,
				'always_enabled'      => array( 'card' => __( 'Card Payments', 'motopress-hotel-booking' ) ),
				'allow_group_actions' => false, // Disable "Select All" and "Unselect All"
				'default'             => $this->getDefaultOption( 'payment_methods' ),
			),
			'locale' => array(
				'type'        => 'select',
				'label'       => __( 'Checkout Locale', 'motopress-hotel-booking' ),
				'description' => __( 'Display Checkout in the user\'s preferred language, if available.', 'motopress-hotel-booking' ),
				'list'        => $this->getAvailableLocales(),
				'default'     => $this->getDefaultOption( 'locale' ),
			),
		);

		return parent::initOptionFields() + $stripeFields;
	}

	public function registerOptionsFields( &$subtab ) {

		parent::registerOptionsFields( $subtab );

		// Show warning if the SSL not enabled
		if ( ! MPHB()->isSiteSSL() && ( ! MPHB()->settings()->payment()->isForceCheckoutSSL() && ! class_exists( 'WordPressHTTPS' ) ) ) {
			$enableField = $subtab->findField( "mphb_payment_gateway_{$this->id}_enable" );

			if ( ! is_null( $enableField ) ) {
				if ( $this->isActive() ) {
					$message = __( '%1$s is enabled, but the <a href="%2$s">Force Secure Checkout</a> option is disabled. Please enable SSL and ensure your server has a valid SSL certificate. Otherwise, %1$s will only work in Test Mode.', 'motopress-hotel-booking' );
				} else {
					$message = __( 'The <a href="%2$s">Force Secure Checkout</a> option is disabled. Please enable SSL and ensure your server has a valid SSL certificate. Otherwise, %1$s will only work in Test Mode.', 'motopress-hotel-booking' );
				}

				$message = sprintf( $message, __( 'Stripe', 'motopress-hotel-booking' ), esc_url( MPHB()->getSettingsMenuPage()->getUrl( array( 'tab' => 'payments' ) ) ) );

				$enableField->setDescription( $message );
			}
		}

		$fields = $this->getFields();

		$group = new Groups\SettingsGroup( "mphb_payments_{$this->id}_group1", '', $subtab->getOptionGroupName() );

		$groupFields = array(
			Fields\FieldFactory::create( "mphb_payment_gateway_{$this->id}_public_key", $fields['public_key'] ),
			Fields\FieldFactory::create( "mphb_payment_gateway_{$this->id}_secret_key", $fields['secret_key'] ),
			Fields\FieldFactory::create( "mphb_payment_gateway_{$this->id}_endpoint_secret", $fields['endpoint_secret'] ),
			Fields\FieldFactory::create( "mphb_payment_gateway_{$this->id}_payment_methods", $fields['payment_methods'] ),
			Fields\FieldFactory::create( "mphb_payment_gateway_{$this->id}_locale", $fields['locale'] ),
		);

		$group->addFields( $groupFields );

		$subtab->addGroup( $group );
	}

	public function initPaymentFields() {
		$fields = array(
			'mphb_stripe_payment_method'    => array(
				'type'     => 'hidden',
				'required' => true,
			),
			'mphb_stripe_payment_intent_id' => array(
				'type'     => 'hidden',
				'required' => false,
			),
			'mphb_stripe_source_id'         => array(
				'type'     => 'hidden',
				'required' => false,
			),
			'mphb_stripe_redirect_url'      => array(
				'type'     => 'hidden',
				'required' => false,
			),
		);

		return $fields;
	}

	public function parsePaymentFields( $input, &$errors ) {

		$isParsed = parent::parsePaymentFields( $input, $errors );

		if ( $isParsed ) {
			foreach ( array( 'payment_method', 'payment_intent_id', 'redirect_url' ) as $param ) {
				$field = 'mphb_stripe_' . $param;

				if ( isset( $this->postedPaymentFields[ $field ] ) ) {
					$this->paymentFields[ $param ] = $this->postedPaymentFields[ $field ];
					unset( $this->postedPaymentFields[ $field ] );
				}
			}
		}

		return $isParsed;
	}

	/**
	 * @return string[] Equal to getPaymentMethods() if the currency is euro,
	 *     ["card"] otherwise.
	 */
	public function getAllowedPaymentMethodIds() {
		$paymentMethods = $this->getPaymentMethods();
		$currency = MPHB()->settings()->currency()->getCurrencyCode();

		if ( $currency === 'EUR' ) {
			return $paymentMethods;

		} elseif ( in_array( 'klarna', $paymentMethods ) && in_array( $currency, $this->klarnaAllowedCurrencyCodes ) ) {
			return array( 'card', 'klarna' );

		} else {
			return array( 'card' );
		}
	}

	public function getCheckoutLocale(): string {
		return $this->locale;
	}


	public function processPayment( Booking $booking, Payment $payment ) {

		$paymentMethod   = $this->paymentFields['payment_method'];
		$paymentIntentId = $this->paymentFields['payment_intent_id'];

		if ( empty( $paymentMethod ) ) {

			$payment->addLog( __( 'The payment method is not selected.', 'motopress-hotel-booking' ) );
			$this->paymentFailed( $payment );
		}

		if ( empty( $paymentIntentId ) ) {

			$payment->addLog( __( 'PaymentIntent ID is not set.', 'motopress-hotel-booking' ) );
			$this->paymentFailed( $payment );
		}

		if ( PaymentStatuses::STATUS_FAILED === $payment->getStatus() ) {

			wp_redirect( MPHB()->settings()->pages()->getPaymentFailedPageUrl( $payment ) );
			exit;
		}

		// start payment processing
		update_post_meta( $payment->getId(), '_mphb_payment_type', $paymentMethod );
		update_post_meta( $payment->getId(), '_mphb_transaction_id', $paymentIntentId );
		$payment->setTransactionId( $paymentIntentId );

		try {

			$paymentIntent = $this->getApi()->retrievePaymentIntent( $paymentIntentId );

			/*
			 * https://stripe.com/docs/payments/intents#intent-statuses
			 *
			 * Stripe has many statuses, but we are using only 2 of them:
			 * "succeeded" and "processing". "canceled" and other will not pass
			 * checks from stripe-gateway.js.
			 */
			if ( 'succeeded' == $paymentIntent->status ) {

				// translators: %s - Stripe PaymentIntent ID
				$payment->addLog( sprintf( __( 'Payment for PaymentIntent %s succeeded.', 'motopress-hotel-booking' ), $paymentIntentId ) );
				$this->paymentCompleted( $payment );

			} else { // "processing"

				// translators: %s - Stripe PaymentIntent ID
				$payment->addLog( sprintf( __( 'Payment for PaymentIntent %s is processing.', 'motopress-hotel-booking' ), $paymentIntentId ) );
				$this->paymentOnHold( $payment );
			}

			// Set description to "Reservation #..." when we know the booking ID
			$description = $this->generateItemName( $booking );
			$this->getApi()->updateDescription( $paymentIntent, $description );

			if ( ! empty( $this->paymentFields['redirect_url'] ) ) {

				wp_redirect( $this->paymentFields['redirect_url'] );

			} else {

				wp_redirect( MPHB()->settings()->pages()->getReservationReceivedPageUrl( $payment ) );
			}
		} catch ( \Throwable $e ) {

			$payment->addLog(
				sprintf(
					// translators: %1$s - Stripe PaymentIntent ID, %2$s - Stripe error message text
					__( 'Failed to process PaymentIntent %1$s. %2$s', 'motopress-hotel-booking' ),
					$paymentIntentId,
					$e->getMessage()
				)
			);

			wp_redirect( MPHB()->settings()->pages()->getPaymentFailedPageUrl( $payment ) );
		}

		exit;
	}

	// TODO: remove source later when all clients update plugin
	// and finish all source payments
	public function chargePayment( Payment $payment, Source $source ) {

		if ( ! in_array( $payment->getStatus(), array( PaymentStatuses::STATUS_PENDING, PaymentStatuses::STATUS_ON_HOLD ) ) ) {
			$message = __( "Can't charge the payment again: payment's flow already completed.", 'motopress-hotel-booking' );
			$payment->addLog( $message );

			return false;
		}

		try {
			// Generate description
			$booking     = MPHB()->getBookingRepository()->findById( $payment->getBookingId() );
			$description = ! is_null( $booking ) ? $this->generateItemName( $booking ) : '';

			// Create Charge object
			$charge = $this->getApi()->chargeSource( $source->id, $payment->getAmount(), $description, $payment->getCurrency() );

			$payment->setTransactionId( $charge->id );

			// If paymentXXX() will not trigger any changes, then we must save
			// transaction ID manually
			update_post_meta( $payment->getId(), '_mphb_transaction_id', $charge->id );

			if ( $charge->status == 'succeeded' ) {
				// translators: %s - Stripe Charge ID
				$payment->addLog( sprintf( __( 'Charge %s succeeded.', 'motopress-hotel-booking' ), $charge->id ) );
				$this->paymentCompleted( $payment );

			} elseif ( $charge->status == 'pending' ) {
				$chargedPrice = mphb_format_price( $payment->getAmount(), array( 'currency_symbol' => MPHB()->settings()->currency()->getBundle()->getSymbol( $payment->getCurrency() ) ) );

				// translators: %1$s - Stripe Charge ID; %2$s - payment price
				$payment->addLog( sprintf( __( 'Charge %1$s for %2$s created.', 'motopress-hotel-booking' ), $charge->id, $chargedPrice ) );
				$this->paymentOnHold( $payment );

			} else { // failed
				// translators: %s - Stripe Charge ID
				$payment->addLog( sprintf( __( 'Charge %s failed.', 'motopress-hotel-booking' ), $charge->id ) );
				$this->paymentFailed( $payment );
			}

			return $charge->status != 'failed';

		} catch ( \Exception $e ) {
			$payment->addLog( sprintf( __( 'Charge error. %s', 'motopress-hotel-booking' ), $e->getMessage() ) );

			// Wait for webhooks
			$this->paymentOnHold( $payment );

			return false;
		}
	}

	private function getAvailableLocales() {

		// Available locales: https://stripe.com/docs/stripe-js/reference#locale
		return array(
			'auto' => __( 'Auto', 'motopress-hotel-booking' ),
			'ar'   => __( 'Argentinean', 'motopress-hotel-booking' ),
			'zh'   => __( 'Simplified Chinese', 'motopress-hotel-booking' ),
			'da'   => __( 'Danish', 'motopress-hotel-booking' ),
			'nl'   => __( 'Dutch', 'motopress-hotel-booking' ),
			'en'   => __( 'English', 'motopress-hotel-booking' ),
			'fi'   => __( 'Finnish', 'motopress-hotel-booking' ),
			'fr'   => __( 'French', 'motopress-hotel-booking' ),
			'de'   => __( 'German', 'motopress-hotel-booking' ),
			'it'   => __( 'Italian', 'motopress-hotel-booking' ),
			'ja'   => __( 'Japanese', 'motopress-hotel-booking' ),
			'no'   => __( 'Norwegian', 'motopress-hotel-booking' ),
			'pl'   => __( 'Polish', 'motopress-hotel-booking' ),
			'ru'   => __( 'Russian', 'motopress-hotel-booking' ),
			'es'   => __( 'Spanish', 'motopress-hotel-booking' ),
			'sv'   => __( 'Swedish', 'motopress-hotel-booking' ),
			// 'he' => what is "he"?
		);
	}

	private function checkStripeErrorBeforeShowingPaymentReceivedPage() {

		if ( ! isset( $_REQUEST['mphb_action'] ) || 'handle_stripe_errors' !== $_REQUEST['mphb_action'] ) {
			return;
		}

		if ( ! mphb_verify_nonce( 'handle_stripe_errors' ) ) {

			if ( is_admin() ) {

				wp_die(
					esc_html__( 'Nonce verification failed.', 'motopress-hotel-booking' ),
					esc_html__( 'Error', 'motopress-hotel-booking' ),
					array( 'response' => 403 )
				);
			}
		}

		if ( ! isset( $_REQUEST['source'] ) && ! isset( $_REQUEST['payment_intent'] ) ) {

			if ( is_admin() ) {

				wp_die(
					esc_html__( 'PaymentIntent ID is missing.', 'motopress-hotel-booking' ),
					esc_html__( 'Error', 'motopress-hotel-booking' ),
					array( 'response' => 403 )
				);
			}
		}

		// phpcs:ignore
		$paymentIntentId = isset( $_REQUEST['payment_intent'] ) ? $_REQUEST['payment_intent'] : '';

        // phpcs:ignore
		$sourceId = isset( $_REQUEST['source'] ) ? $_REQUEST['source'] : '';

		if ( is_array( $sourceId ) ) {

			$sourceId = array_map( 'wp_unslash', $sourceId );
			$sourceId = array_map( 'sanitize_text_field', $sourceId );

		} else {
			$sourceId = sanitize_text_field( wp_unslash( $sourceId ) );
		}

		try {

			if ( ! empty( $paymentIntentId ) ) {

				$payment = MPHB()->getPaymentRepository()->findByMeta(
					'_mphb_transaction_id',
					$paymentIntentId
				);

				if ( PaymentStatuses::STATUS_PENDING !== $payment->getStatus() &&
					PaymentStatuses::STATUS_COMPLETED !== $payment->getStatus() &&
					PaymentStatuses::STATUS_ON_HOLD !== $payment->getStatus()
				) {
					wp_redirect( MPHB()->settings()->pages()->getPaymentFailedPageUrl() );
					exit;
				}
			}

			// TODO: remove source later when all clients update plugin
			// and finish all source payments
			if ( ! empty( $sourceId ) ) {

				$source = $this->getApi()->retrieveSource( $sourceId );

				if ( ( $source->status == 'canceled' || $source->status == 'failed' ) &&
					! is_admin()
				) {
					wp_redirect( MPHB()->settings()->pages()->getPaymentFailedPageUrl() );
					exit;
				}
			}
		} catch ( \Exception $e ) {

			if ( is_admin() ) {

				wp_die(
					// phpcs:ignore
					$e->getMessage(),
					esc_html__( 'Error', 'motopress-hotel-booking' ),
					array( 'response' => 403 )
				);
			}
		}
	}

	/**
	 * @param \MPHB\Entities\Booking $booking
	 */
	public function getCheckoutData( $booking ) {

		$successPaymentPageUrl = add_query_arg(
			array(
				'mphb_action' => 'handle_stripe_errors',
				'mphb_nonce'  => wp_create_nonce( 'handle_stripe_errors' ),
			),
			MPHB()->settings()->pages()->getReservationReceivedPageUrl(
				null,
				array(
					'mphb_payment_status' => 'auto',
				)
			)
		);

		// Put some basic customer info required for the StripeGateway.js
		// (important only for Payment Request and its checkout page)
		$customer = $booking->getCustomer();

		if ( ! is_null( $customer ) ) {
			$customerData = array(
				'email'      => $customer->getEmail(),
				'name'       => $customer->getName(),
				'first_name' => $customer->getFirstName(),
				'last_name'  => $customer->getLastName(),
			);
		} else {
			$customerData = array();
		}

		$data = array(
			'publicKey'               => $this->getPublicKey(),
			'locale'                  => $this->locale,
			'currency'                => MPHB()->settings()->currency()->getCurrencyCode(),
			'successUrl'              => $successPaymentPageUrl,
			'defaultCountry'          => MPHB()->settings()->main()->getDefaultCountry(),
			'statementDescriptor'     => substr( MPHB()->getName(), 0, 22 ), // 22 is max for some methods
			'paymentMethods'          => $this->getAllowedPaymentMethodIds(),
			'idempotencyKeyFieldName' => CheckoutShortcode::BOOKING_CID_NAME,
			'amount'                  => $booking->calcDepositAmount(),
			'customer'                => $customerData,
			// Docs: https://stripe.com/docs/stripe-js/reference#element-options
			// Example: https://github.com/stripe/stripe-payments-demo/blob/master/public/javascripts/payments.js#L38
			'style'                   => apply_filters( 'mphb_stripe_elements_style', array( 'base' => array( 'fontSize' => '15px' ) ) ),
			'i18n'                    => array(
				// Payment methods (labels)
				'card'             => __( 'Card', 'motopress-hotel-booking' ),
				'bancontact'       => __( 'Bancontact', 'motopress-hotel-booking' ),
				'ideal'            => __( 'iDEAL', 'motopress-hotel-booking' ),
				'giropay'          => __( 'Giropay', 'motopress-hotel-booking' ),
				'sepa_debit'       => __( 'SEPA Direct Debit', 'motopress-hotel-booking' ),
				'klarna'           => __( 'Klarna', 'motopress-hotel-booking' ),
				// Additional labels
				'card_description' => __( 'Credit or debit card', 'motopress-hotel-booking' ),
				'iban'             => __( 'IBAN', 'motopress-hotel-booking' ),
				// Messages
				'redirect_notice'  => __( 'You will be redirected to a secure page to complete the payment.', 'motopress-hotel-booking' ),
				'iban_policy'      => __( 'By providing your IBAN and confirming this payment, you are authorizing this merchant and Stripe, our payment service provider, to send instructions to your bank to debit your account and your bank to debit your account in accordance with those instructions. You are entitled to a refund from your bank under the terms and conditions of your agreement with your bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited.', 'motopress-hotel-booking' ), // From https://stripe.com/docs/sources/sepa-debit#prerequisite
			),
		);

		return array_merge( parent::getCheckoutData( $booking ), $data );
	}

	/**
	 * @return StripeAPI
	 */
	public function getApi() {
		return $this->api;
	}
}
