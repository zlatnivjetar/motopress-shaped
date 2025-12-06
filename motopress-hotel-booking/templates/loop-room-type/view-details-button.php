<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php
/**
 * @hooked \MPHB\Views\LoopRoomTypeView::_renderViewDetailsButtonParagraphOpen - 10
 */
do_action( 'mphb_render_loop_room_type_before_view_details_button' );
?>

    <a href="<?php echo esc_url(get_permalink()); ?>" class="elementor-button-link elementor-button" role="button">
        <span class="elementor-button-content-wrapper">
            <span class="elementor-button-text">VIEW ROOM DETAILS</span>
            <span class="elementor-button-icon elementor-align-icon-right">
                <i class="opal-icon-long-arrow-right" aria-hidden="true"></i>
            </span>
        </span>
    </a>


<?php
/**
 * @hooked \MPHB\Views\LoopRoomTypeView::_renderViewDetailsButtonParagraphClose - 10
 */
do_action( 'mphb_render_loop_room_type_after_view_details_button' );
?>