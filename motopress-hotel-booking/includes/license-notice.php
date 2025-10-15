<?php

namespace MPHB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LicenseNotice {

	const ACTION_DISMISS = 'mphb_dismiss_license_notice';

	/**
	 * @since 5.0.0
	 */
	private string $pluginSlug = 'motopress-hotel-booking';

	/**
	 * @since 5.0.0
	 */
	private string $pluginFile = 'motopress-hotel-booking/motopress-hotel-booking.php';

	/**
	 * @since 5.0.0 the <code>$pluginFile</code> parameter was added.
	 *
	 * @param string $pluginFile
	 */
	public function __construct( $pluginFile ) {
		$this->pluginSlug = basename( $pluginFile, '.php' );
		$this->pluginFile = plugin_basename( $pluginFile );

		$this->registerPluginNotice();
		// $this->registerAdminNotice();
	}

	/**
	 * @since 5.0.0
	 */
	private function registerPluginNotice() {
		add_action( "after_plugin_row_{$this->pluginFile}", array( $this, 'showPluginNotice' ) );
	}

	/**
	 * @since 5.0.0
	 */
	private function registerAdminNotice() {
		add_action( 'admin_notices', array( $this, 'showAdminNotice' ) );

		if ( is_multisite() ) {
			add_action( 'network_admin_notices', array( $this, 'showAdminNotice' ) );
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @access private
	 *
	 * @global \WP_Plugins_List_Table $wp_list_table
	 */
	public function showPluginNotice() {
		global $wp_list_table;

		if ( ! is_main_site() || MPHB()->settings()->license()->needHideNotice() ) {
			return;
		}

		$hasLicenseKey = ! empty( MPHB()->settings()->license()->getLicenseKey() );
		$licenseStatus = MPHB()->settings()->license()->getLicenseStatus();

		if ( $licenseStatus['status'] == 'valid'
			|| ( $licenseStatus['status'] == 'undefined' && $hasLicenseKey )
		) {
			return;
		}

		$columnsCount = $wp_list_table->get_column_count();

		?>
		<tr class="plugin-update-tr active" id="<?php echo esc_attr( $this->pluginSlug ); ?>-license" data-slug="<?php echo esc_attr( $this->pluginSlug ); ?>" data-plugin="<?php echo esc_attr( $this->pluginFile ); ?>">
			<td colspan="<?php echo esc_attr( $columnsCount ); ?>" class="plugin-update">
				<div class="notice inline notice-warning notice-alt">
					<p>
						<?php
						printf(
							wp_kses(
								__( 'Your License Key is not active. Please, <a href="%s">activate your License Key</a> to get plugin updates.', 'motopress-hotel-booking' ),
								[ 'a' => [ 'href' => [] ] ],
							),
							esc_url( $this->getLicensePageUrl() )
						);
						?>
					</p>
				</div>
			</td>
		</tr>
		<?php

		add_action( 'admin_footer', [ $this, 'printPluginNoticeScript' ] );
	}

	/**
	 * @since 5.0.0
	 *
	 * @access private
	 */
	public function printPluginNoticeScript() {
		?>
		<script type="text/javascript">
			"use strict";

			let pluginRow = document.querySelector( 'tr[data-plugin$="motopress-hotel-booking.php"]' );

			if ( ! pluginRow.classList.contains( 'update' ) ) {
				pluginRow.classList.add( 'update' );

			} else {
				let updateNoticeRow    = pluginRow.nextElementSibling;
				let updateNoticeColumn = updateNoticeRow ? updateNoticeRow.firstElementChild : null;
				let updateNotice       = updateNoticeColumn ? updateNoticeColumn.firstElementChild : null;

				updateNoticeColumn.style.boxShadow = 'none';
				updateNotice.style.marginBottom = '0';
			}
		</script>
		<?php
	}

	/**
	 * @access private
	 *
	 * @global string $pagenow
	 */
	public function showAdminNotice() {

		global $pagenow;

		if ( $pagenow !== 'plugins.php' || ! is_main_site() || MPHB()->settings()->license()->needHideNotice() ) {
			return;
		}

		$license = MPHB()->settings()->license()->getLicenseKey();
		$licenseData = $license ? MPHB()->settings()->license()->getLicenseData() : null;

		if ( isset( $licenseData, $licenseData->license ) && $licenseData->license === 'valid' ) {
			return;
		}

		?>
		<div class="error">
			<a id="mphb-dismiss-license-notice" href="javascript:void(0);" style="float: right; padding-top: 9px; text-decoration: none;">
				<?php esc_html_e( 'Dismiss ', 'motopress-hotel-booking' ); ?><strong>X</strong>
			</a>
			<p>
				<b><?php echo esc_html( MPHB()->settings()->license()->getProductName() ); ?></b>
				<br/>
				<?php
				printf(
					wp_kses(
						__( 'Your License Key is not active. Please, <a href="%s">activate your License Key</a> to get plugin updates.', 'motopress-hotel-booking' ),
						array( 'a' => array( 'href' => array() ) ),
					),
					esc_url( $this->getLicensePageUrl() )
				);
				?>
			</p>
		</div>
		<script type="text/javascript">
			(function ( $ ) {
				var dismissBtn = $( '#mphb-dismiss-license-notice' );

				dismissBtn.one( 'click', function() {
					$.ajax( {
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
						type: 'POST',
						dataType: 'json',
						data: {
							<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							action: '<?php echo self::ACTION_DISMISS; ?>',
							<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							mphb_nonce: '<?php echo wp_create_nonce( self::ACTION_DISMISS ); ?>',
						},
						success: function( data ) {
							if ( ! data.hasOwnProperty( 'success' ) ) {
								return;
							}
							if ( data.success ) {
								dismissBtn.closest( 'div.error' ).remove();
							} else {
								dismissBtn.closest( 'div.error' ).append( data.data.message );
							}
						}
					} );
				} );
			})( jQuery );
		</script>
		<?php
	}

	/**
	 * @since 5.0.0
	 *
	 * @return string
	 */
	private function getLicensePageUrl() {
		return add_query_arg(
			array(
				'page' => MPHB()->getSettingsMenuPage()->getName(),
				'tab'  => 'license',
			),
			admin_url( 'admin.php' )
		);
	}
}
