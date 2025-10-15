<?php

namespace MPHB;

/**
 * @since 3.6.0
 * @since 3.6.0 MPHB\Downloader was replaced with MPHB\ActionsHandler.
 * @since 3.6.0 method doActions() was replaced with doEarlyActions() and doLateActions().
 */
class ActionsHandler {

	public function __construct() {

		// Late action: wait for the plugins when it initialize more components
		add_action( 'init', array( $this, 'doLateActions' ), 1004 );
	}

	/**
	 * @since 3.6.0
	 */
	public function doLateActions() {

		if ( ! isset( $_GET['mphb_action'] ) ) {
			return;
		}

		switch ( $_GET['mphb_action'] ) {

			case 'force_upgrade':
				$this->forceUpgrader();
				break;
			case 'update_confirmation_endpoints':
				$this->updateConfirmationEndpoints();
				break;
			case 'hide_notice':
				$this->hideNotice();
				break;
		}
	}

	protected function forceUpgrader() {
		if ( ! isset( $_GET['mphb_action'] ) ||
			! mphb_verify_nonce( sanitize_text_field( wp_unslash( $_GET['mphb_action'] ) ), 'mphb_notice_nonce' ) ) {
			return;
		}

		MPHB()->upgrader()->forceUpgrade();
	}

	protected function updateConfirmationEndpoints() {
		if ( ! isset( $_GET['mphb_action'] ) ||
			! mphb_verify_nonce( sanitize_text_field( wp_unslash( $_GET['mphb_action'] ) ), 'mphb_notice_nonce' ) ) {
			return;
		}

		$bookingConfirmedId    = MPHB()->settings()->pages()->getBookingConfirmedPageId();
		$reservationReceivedId = MPHB()->settings()->pages()->getReservationReceivedPageId();

		$pageContent = MPHB()->getShortcodes()->getBookingConfirmation()->generateShortcode();

		if ( $bookingConfirmedId != 0 ) {
			wp_update_post(
				array(
					'ID'           => $bookingConfirmedId,
					'post_content' => $pageContent,
				)
			);
		}

		if ( $reservationReceivedId != 0 ) {
			wp_update_post(
				array(
					'ID'           => $reservationReceivedId,
					'post_content' => $pageContent,
				)
			);
		}

		MPHB()->notices()->hideNotice( sanitize_text_field( wp_unslash( $_GET['mphb_action'] ) ) );
	}

	protected function hideNotice() {
		if ( ! isset( $_GET['mphb_action'] ) ||
			! mphb_verify_nonce( sanitize_text_field( wp_unslash( $_GET['mphb_action'] ) ), 'mphb_notice_nonce' ) ) {
			return;
		}

		if ( ! isset( $_GET['notice_id'] ) ) {
			return;
		}

		$noticeId = sanitize_text_field( wp_unslash( $_GET['notice_id'] ) );

		MPHB()->notices()->hideNotice( $noticeId );
	}

	public function fireError( $message ) {
		if ( is_admin() ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			wp_die( $message, esc_html__( 'Error', 'motopress-hotel-booking' ), array( 'response' => 403 ) );
		}

		return false;
	}
}
