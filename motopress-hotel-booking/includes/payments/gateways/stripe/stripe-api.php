<?php

namespace MPHB\Payments\Gateways\Stripe;

use MPHB\Payments\Gateways\StripeGateway;
use MPHB\Stripe\Charge;
use MPHB\Stripe\PaymentIntent;
use MPHB\Stripe\Source;
use MPHB\Stripe\Stripe;

/**
 * https://github.com/stripe/stripe-php
 * https://stripe.com/docs/payments/payment-intents
 */
class StripeAPI {

	const PARTNER_ID  = 'pp_partner_Fs0jSMbknaJwVC';
	// latest API version from https://dashboard.stripe.com/test/developers
	const API_VERSION = '2023-10-16';

	private static $isStripeAPIClassesLoaded = false;

	private StripeGateway $gateway;

	public function __construct( StripeGateway $stipeGateway ) {
		$this->gateway = $stipeGateway;
	}

	/**
	 * See also convertToSmallestUnit() in stripe-gateway.js.
	 *
	 * @param float  $amount
	 * @param string $currency
	 * @return int
	 */
	public function convertToSmallestUnit( $amount, $currency = null ) {

		if ( is_null( $currency ) ) {
			$currency = MPHB()->settings()->currency()->getCurrencyCode();
		}

		// See all currencies presented as links on page
		// https://stripe.com/docs/currencies#presentment-currencies
		switch ( strtoupper( $currency ) ) {
			// Zero decimal currencies
			case 'BIF':
			case 'CLP':
			case 'DJF':
			case 'GNF':
			case 'JPY':
			case 'KMF':
			case 'KRW':
			case 'MGA':
			case 'PYG':
			case 'RWF':
			case 'UGX':
			case 'VND':
			case 'VUV':
			case 'XAF':
			case 'XOF':
			case 'XPF':
				$amount = absint( $amount );
				break;
			default:
				$amount = round( $amount * 100 ); // In cents
				break;
		}

		return (int) $amount;
	}

	/**
	 * @param string $currency
	 * @return float
	 */
	public function getMinimumAmount( $currency ) {

		// See https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts
		switch ( strtoupper( $currency ) ) {
			case 'USD':
			case 'AUD':
			case 'BRL':
			case 'CAD':
			case 'CHF':
			case 'EUR':
			case 'NZD':
			case 'SGD':
				$minimumAmount = 0.50;
				break;

			case 'DKK':
				$minimumAmount = 2.50;
				break;
			case 'GBP':
				$minimumAmount = 0.30;
				break;
			case 'HKD':
				$minimumAmount = 4.00;
				break;
			case 'JPY':
				$minimumAmount = 50.00;
				break;
			case 'MXN':
				$minimumAmount = 10.00;
				break;

			case 'NOK':
			case 'SEK':
				$minimumAmount = 3.00;
				break;

			default:
				$minimumAmount = 0.50;
				break;
		}

		return $minimumAmount;
	}

	/**
	 * Checks Stripe minimum amount value authorized per currency.
	 *
	 * @param float  $amount
	 * @param string $currency
	 * @return bool
	 */
	public function checkMinimumAmount( $amount, $currency ) {

		$currentAmount = $this->convertToSmallestUnit( $amount, $currency );
		$minimumAmount = $this->convertToSmallestUnit( $this->getMinimumAmount( $currency ), $currency );
		return $currentAmount >= $minimumAmount;
	}


	public function setApp() {

		if ( ! self::$isStripeAPIClassesLoaded ) {

			require_once MPHB()->getPluginPath( 'vendors/stripe-api/init.php' );
			self::$isStripeAPIClassesLoaded = true;
		}

		Stripe::setAppInfo(
			MPHB()->getName(),
			MPHB()->getVersion(),
			MPHB()->getPluginStoreUri(),
			self::PARTNER_ID
		);
		Stripe::setApiKey( $this->gateway->getSecretKey() );
		Stripe::setApiVersion( self::API_VERSION );
	}

	/**
	 * @param array $atts [ string key => value ]
	 * @return PaymentIntent|\WP_Error
	 */
	public function createPaymentIntent( string $paymentMethodType, string $paymentMethodId, string $currency, 
		float $amount, string $description, $atts = array() ) {
	
		if ( is_null( $currency ) ) {
			$currency = MPHB()->settings()->currency()->getCurrencyCode();
		}

		$allowedPaymentMethods = $this->gateway->getAllowedPaymentMethodIds();

		if ( ! in_array( $paymentMethodType, $allowedPaymentMethods, true ) ) {

			return new \WP_Error(
				'stripe_api_error',
				sprintf(
					// translators: %s - payment method type code like: card
					__( 'Could not create PaymentIntent for a not allowed payment type: %s', 'motopress-hotel-booking' ),
					$paymentMethodType
				)
			);
		}

		$this->setApp();

		try {
			$requestArgs = array(
				'amount'               => $this->convertToSmallestUnit( $amount, $currency ),
				'currency'             => strtolower( $currency ),
				'payment_method_types' => array( $paymentMethodType ),
			);

			if ( ! empty( $description ) ) {
				$requestArgs['description'] = $description;
			}

			if ( ! empty( $atts ) ) {
				foreach ( $atts as $key => $att ) {
					$additionalAtts[ $key ] = $att;
				}
			}

			if ( null != $paymentMethodId ) {
				$requestArgs['payment_method'] = $paymentMethodId;
			}

			$checkoutLocale = $this->gateway->getCheckoutLocale();

			if ( in_array( 'bancontact', $allowedPaymentMethods ) &&
				in_array( $checkoutLocale, array( 'en', 'de', 'fr', 'nl' ) )
			) {

				$requestArgs['payment_method_options']['bancontact']['preferred_language'] = $checkoutLocale;
			}

			// See details in https://stripe.com/docs/api/payment_intents/create
			if ( ! empty( $additionalAtts ) ) {

				$paymentIntent = PaymentIntent::create( $requestArgs, $additionalAtts );

			} else {

				$paymentIntent = PaymentIntent::create( $requestArgs );
			}

			return $paymentIntent;

		} catch ( \Exception $e ) {

			return new \WP_Error( 'stripe_api_error', $e->getMessage() );
		}
	}

	/**
	 * @param PaymentIntent $paymentIntent
	 * @param string $description
	 * @return true|\WP_Error
	 */
	public function updateDescription( $paymentIntent, $description ) {

		$this->setApp();

		try {
			$paymentIntent->update( $paymentIntent->id, array( 'description' => $description ) );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'stripe_api_error', $e->getMessage() );
		}
	}


	public function retrievePaymentIntent( $paymentIntentId ) {

		$this->setApp();

		return PaymentIntent::retrieve( $paymentIntentId );
	}

	// TODO: remove source later when all clients update plugin
	// and finish all source payments
	public function retrieveSource( $sourceId ) {

		$this->setApp();

		return Source::retrieve( $sourceId );
	}

	// TODO: remove source later when all clients update plugin
	// and finish all source payments
	/**
	 * @param string $sourceId
	 * @param float  $amount
	 * @param string $description
	 * @param string $currency
	 * @return Source
	 */
	public function chargeSource( $sourceId, $amount, $description = '', $currency = null ) {

		if ( is_null( $currency ) ) {
			$currency = MPHB()->settings()->currency()->getCurrencyCode();
		}

		$this->setApp();

		$requestArgs = array(
			'amount'   => $this->convertToSmallestUnit( $amount, $currency ),
			'currency' => strtolower( $currency ),
			'source'   => $sourceId,
		);

		if ( ! empty( $description ) ) {
			$requestArgs['description'] = $description;
		}

		$charge = Charge::create( $requestArgs );

		return $charge;
	}
}
