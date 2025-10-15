<?php

namespace MPHB\AjaxApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateStripePaymentIntent extends AbstractAjaxApiAction {

	const REQUEST_DATA_AMOUNT = 'amount';
	const REQUEST_DATA_DESCRIPTION = 'description';
	const REQUEST_DATA_PAYMENT_METHOD_TYPE = 'paymentMethodType'; // for example: card
	const REQUEST_DATA_PAYMENT_METHOD_ID = 'paymentMethodId';
	const REQUEST_DATA_IDEMPOTENCY_KEY = 'idempotencyKey';
	const REQUEST_DATA_ROOM_TYPE_IDS = 'roomTypeIds';


	public static function getAjaxActionNameWithouPrefix() {
		return 'create_stripe_payment_intent';
	}

	/**
	 * @return array [ request_key (string) => request_value (mixed) ]
	 * @throws Exception when validation of request parameters failed
	 */
	protected static function getValidatedRequestData() {

		$requestData = parent::getValidatedRequestData();

		$requestData[ static::REQUEST_DATA_AMOUNT ] = static::getFloatFromRequest( static::REQUEST_DATA_AMOUNT );

		if ( 0 >= $requestData[ static::REQUEST_DATA_AMOUNT ] ) {
			throw new \Exception( __( 'Please complete all required fields and try again.', 'motopress-hotel-booking' ) );
		}

		$requestData[ static::REQUEST_DATA_DESCRIPTION ] = mphb_clean(
			static::getStringFromRequest( static::REQUEST_DATA_DESCRIPTION )
		);
		$requestData[ static::REQUEST_DATA_PAYMENT_METHOD_TYPE ] = static::getStringFromRequest( static::REQUEST_DATA_PAYMENT_METHOD_TYPE, true );
		$requestData[ static::REQUEST_DATA_PAYMENT_METHOD_ID ] = static::getStringFromRequest( static::REQUEST_DATA_PAYMENT_METHOD_ID, true );
		$requestData[ static::REQUEST_DATA_IDEMPOTENCY_KEY ] = static::getStringFromRequest( static::REQUEST_DATA_IDEMPOTENCY_KEY );
		$requestData[ static::REQUEST_DATA_ROOM_TYPE_IDS ] = static::getIdListFromRequest( static::REQUEST_DATA_ROOM_TYPE_IDS );

		return $requestData;
	}

	protected static function doAction( array $requestData ) {

		$currency  = MPHB()->settings()->currency()->getCurrencyCode();
		$stripeApi = MPHB()->gatewayManager()->getStripeGateway()->getApi();

		if ( ! $stripeApi->checkMinimumAmount( $requestData[ static::REQUEST_DATA_AMOUNT ], $currency ) ) {

			throw new \Exception(
				sprintf(
					__( 'Sorry, the minimum allowed payment amount is %s to use this payment method.', 'motopress-hotel-booking' ),
					mphb_format_price( $stripeApi->getMinimumAmount( $currency ) )
				)
			);
		}

		/**
		 * @param int[] $roomTypeIds
		 */
		do_action( 'mphb_create_stripe_payment_intent_for_room_types', $requestData[ static::REQUEST_DATA_ROOM_TYPE_IDS ] );

		$response = $stripeApi->createPaymentIntent(
			$requestData[ static::REQUEST_DATA_PAYMENT_METHOD_TYPE ],
			$requestData[ static::REQUEST_DATA_PAYMENT_METHOD_ID ],
			$currency,
			$requestData[ static::REQUEST_DATA_AMOUNT ],
			$requestData[ static::REQUEST_DATA_DESCRIPTION ],
			// we do not send idempotency_key because in case when card payment is failed
			// we do not refresh page and idempotency_key but we can not use it twise
			// so as quick fix we just do not send it at all!
			// array(
			// 	'idempotency_key' => $requestData[ static::REQUEST_DATA_IDEMPOTENCY_KEY ]
			// ),
			array()
		);

		if ( is_wp_error( $response ) ) {

			throw new \Exception( $response->get_error_message() );
		}

		wp_send_json_success(
			array(
				'id'            => $response->id,
				'client_secret' => $response->client_secret,
			),
			200
		);
	}
}
