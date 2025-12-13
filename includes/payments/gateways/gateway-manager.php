<?php

namespace MPHB\Payments\Gateways;

use \MPHB\Admin\Tabs;
use MPHB\Entities\Booking;

class GatewayManager {

	/**
	 * @var Gateway[] [Gateway ID => Gateway], where gateway ID is a string like
	 *      "bank" or "paypal".
	 */
	private $gateways = array();

	public function __construct() {
		add_action( 'init', array( $this, 'initPrebuiltGateways' ), -1 );
		add_action( 'init', array( $this, 'registerGateways' ), 5 );
		add_action( 'mphb_generate_settings_payments', array( $this, 'generateSubTabs' ) );
	}

	/**
	 * @since 3.7.0 added new action - "mphb_register_gateways".
	 */
	public function registerGateways() {
		/**
		 * Payments that need to be suspended and not added to the GatewayManager.
		 *
		 * @since 4.2.4
		 *
		 * @param string[] $suspendPayments [] by default (allow all).
		 */
		$suspendPayments = apply_filters( 'mphb_suspend_payments', array() );

		/**
		 * @since 3.7.0
		 * @since 4.2.4 added the <code>$suspendPayments</code> argument.
		 *
		 * @param string[] $suspendPayments
		 */
		do_action( 'mphb_register_gateways', $suspendPayments );

		// See Gateway::register()
		do_action( 'mphb_init_gateways', $this );
	}

	public function initPrebuiltGateways() {
		$prebuildGateways = array(
			ManualGateway::GATEWAY_ID      => ManualGateway::class,
			TestGateway::GATEWAY_ID        => TestGateway::class,
			CashGateway::GATEWAY_ID        => CashGateway::class,
			BankGateway::GATEWAY_ID        => BankGateway::class,
			PaypalGateway::GATEWAY_ID      => PaypalGateway::class,
			TwoCheckoutGateway::GATEWAY_ID => TwoCheckoutGateway::class,
			StripeGateway::GATEWAY_ID      => StripeGateway::class,
			BraintreeGateway::GATEWAY_ID   => BraintreeGateway::class,
			BeanstreamGateway::GATEWAY_ID  => BeanstreamGateway::class,
		);

		/**
		 * @param string[] <code>[ Key => Payment gateway class ]</code>
		 */
		$prebuildGateways = apply_filters( 'mphb_prebuild_gateways', $prebuildGateways );

		foreach ( $prebuildGateways as $gateway_class ) {
			new $gateway_class();
		}
	}

	/**
	 *
	 * @param \MPHB\Payments\Gateways\GatewayInterface $gateway
	 */
	public function addGateway( GatewayInterface $gateway ) {
		$this->gateways[ $gateway->getId() ] = $gateway;
	}

	public function hasGateway( string $id ): bool {
		return isset( $this->gateways[ $id ] );
	}

	/**
	 *
	 * @param string $id
	 * @return Gateway|null
	 */
	public function getGateway( $id ) {
		return isset( $this->gateways[ $id ] ) ? $this->gateways[ $id ] : null;
	}

	/**
	 * @return StripeGateway|null
	 */
	public function getStripeGateway() {
		return $this->getGateway('stripe');
	}

	/**
	 *
	 * @return Gateway[]
	 */
	public function getList() {
		return $this->gateways;
	}

	/**
	 *
	 * @return Gateway[]
	 */
	public function getListEnabled() {
		return array_filter(
			$this->gateways,
			function ( $gateway ) {
				return $gateway->isEnabled();
			}
		);
	}

	/**
	 *
	 * @return Gateway[]
	 */
	public function getListActive() {
		return array_filter(
			$this->gateways,
			function ( $gateway ) {
				return $gateway->isActive();
			}
		);
	}

	/**
	 *
	 * @param \MPHB\Admin\Tabs\SettingsTab $tab
	 */
	public function generateSubTabs( $tab ) {

		foreach ( $this->gateways as $gateway ) {

			if ( ! $gateway->isShowOptions() ) {
				continue;
			}

			$subTab = new Tabs\SettingsSubTab( $gateway->getId(), $gateway->getAdminTitle(), $tab->getPageName(), $tab->getName() );
			$subTab->setDescription( $gateway->getAdminDescription() );

			$gateway->registerOptionsFields( $subTab );

			do_action( "mphb_generate_settings_payment_gateway_{$gateway->getId()}", $subTab );

			$tab->addSubTab( $subTab );
		}
	}

}
