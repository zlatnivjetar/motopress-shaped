<?php

namespace MPHB\Admin\ManageCPTPages;

use MPHB\PostTypes\CouponCPT;

class CouponManageCPTPage extends ManageCPTPage {

	public function __construct( $postType, $atts = array() ) {

		parent::__construct( $postType, $atts );

		$this->enableCouponsNotice();
	}

	public function filterColumns( $columns ) {

		$customColumns = array(
			'amount'          => esc_html__( 'Amount', 'motopress-hotel-booking' ),
			'usage_count'     => esc_html__( 'Uses', 'motopress-hotel-booking' ),
			'expiration_date' => esc_html__( 'Expiration Date', 'motopress-hotel-booking' ),
			'status'          => esc_html__( 'Status', 'motopress-hotel-booking' ),
		);

		$offset  = array_search( 'date', array_keys( $columns ) ); // Set custom columns position before "DATE" column
		$columns = array_slice( $columns, 0, $offset, true ) + $customColumns + array_slice( $columns, $offset, count( $columns ) - 1, true );

		unset( $columns['date'] );

		return $columns;
	}

	public function renderColumns( $column, $postId ) {

		$coupon = MPHB()->getCouponRepository()->findById( $postId );

		switch ( $column ) {

			case 'amount':
				// Accommodation discount
				esc_html_e( 'Accommodation:', 'motopress-hotel-booking' );

				echo '&nbsp;';

				switch ( $coupon->getRoomDiscountType() ) {
					case CouponCPT::TYPE_ACCOMMODATION_NONE:
						echo esc_html( self::EMPTY_VALUE_PLACEHOLDER );
						break;

					case CouponCPT::TYPE_ACCOMMODATION_PERCENTAGE:
						printf( '%d%%', $coupon->getRoomAmount() );
						break;

					case CouponCPT::TYPE_ACCOMMODATION_FIXED:
						echo mphb_format_price( $coupon->getRoomAmount() );
						break;

					case CouponCPT::TYPE_ACCOMMODATION_FIXED_PER_DAY:
						// translators: %s is a coupon amount per day
						printf( esc_html__( '%s per day', 'motopress-hotel-booking' ), mphb_format_price( $coupon->getRoomAmount() ) );
						break;
				}

				echo '<br>';

				// Service discount
				esc_html_e( 'Service:', 'motopress-hotel-booking' );

				echo '&nbsp;';

				switch ( $coupon->getServiceDiscountType() ) {
					case CouponCPT::TYPE_SERVICE_NONE:
						echo esc_html( self::EMPTY_VALUE_PLACEHOLDER );
						break;

					case CouponCPT::TYPE_SERVICE_PERCENTAGE:
						printf( '%d%%', $coupon->getServiceAmount() );
						break;

					case CouponCPT::TYPE_SERVICE_FIXED:
						echo mphb_format_price( $coupon->getServiceAmount() );
						break;
				}

				echo '<br>';

				// Fee discount
				esc_html_e( 'Fee:', 'motopress-hotel-booking' );

				echo '&nbsp;';

				switch ( $coupon->getFeeDiscountType() ) {
					case CouponCPT::TYPE_FEE_NONE:
						echo esc_html( self::EMPTY_VALUE_PLACEHOLDER );
						break;

					case CouponCPT::TYPE_FEE_PERCENTAGE:
						printf( '%d%%', $coupon->getFeeAmount() );
						break;

					case CouponCPT::TYPE_FEE_FIXED:
						echo mphb_format_price( $coupon->getFeeAmount() );
						break;
				}

				break;

			case 'usage_count':
				if ( $coupon->getUsageLimit() ) {

					printf( '%d/%d', $coupon->getUsageCount(), $coupon->getUsageLimit() );

				} else {

					esc_html_e( $coupon->getUsageCount() );
				}
				break;

			case 'expiration_date':
				$expirationDate = $coupon->getExpirationDate();

				if ( $expirationDate ) { ?>
					<abbr title="<?php echo esc_attr( date_i18n( MPHB()->settings()->dateTime()->getDateFormatWP(), $expirationDate->getTimestamp() ) ); ?>">
						<?php
						echo date_i18n( 'Y/m/d', $expirationDate->getTimestamp() );
						?>
					</abbr>
					<?php
				} else {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<span aria-hidden="true">' . static::EMPTY_VALUE_PLACEHOLDER . '</span>';
				}
				break;

			case 'status':
				echo $coupon->isExpired() ? esc_html__( 'Expired', 'motopress-hotel-booking' ) : esc_html__( 'Active', 'motopress-hotel-booking' );
				break;
		}
	}

	private function enableCouponsNotice() {

		if ( false == MPHB()->settings()->main()->isCouponsEnabled() &&
			isset( $_REQUEST['mphb_action'] ) && $_REQUEST['mphb_action'] == 'mphb_enable_coupons' &&
			wp_verify_nonce( $_REQUEST['mphb_nonce'], 'mphb_enable_coupons' ) && current_user_can( 'manage_options' ) ) {

			update_option( 'mphb_enable_coupons', true );
		}

		if ( false == MPHB()->settings()->main()->isCouponsEnabled() ) {

			$description = '<span class="notice notice-error" style="display: block;padding: 1em;">' .
				esc_html__( 'Note: the use of coupons is disabled in settings.', 'motopress-hotel-booking' );

			if ( current_user_can( 'manage_options' ) ) {

				$action = add_query_arg(
					array(
						'mphb_action' => 'mphb_enable_coupons',
						'mphb_nonce'  => wp_create_nonce( 'mphb_enable_coupons' ),
					)
				);

				$description .= ' <a href="' . esc_url( $action ) . '">' .
					esc_html__( 'Enable the use of coupons.', 'motopress-hotel-booking' ) . '</a>';
			}

			$description .= '</span>';

			$this->description = $description;
		}
	}
}
