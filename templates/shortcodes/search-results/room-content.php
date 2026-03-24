<?php
/**
 * Strict RoomCloud availability gate for search-result cards.
 */
if (class_exists('Shaped_RC_Availability_Manager')) {
    $room_type_id = get_the_ID();
    $check_in = isset($checkInDate) && $checkInDate instanceof DateTime
        ? $checkInDate
        : (isset($_GET['mphb_check_in_date']) ? sanitize_text_field(wp_unslash($_GET['mphb_check_in_date'])) : '');
    $check_out = isset($checkOutDate) && $checkOutDate instanceof DateTime
        ? $checkOutDate
        : (isset($_GET['mphb_check_out_date']) ? sanitize_text_field(wp_unslash($_GET['mphb_check_out_date'])) : '');

    if ($check_in && $check_out) {
        $decision = Shaped_RC_Availability_Manager::evaluate_room_type_availability($room_type_id, $check_in, $check_out, 1);

        if (($decision['source'] ?? '') === 'roomcloud' && empty($decision['is_sellable'])) {
            return;
        }
    }
}
?>

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	$isShowGallery = $isShowImage = $isShowDetails = $isShowPrice = $isShowViewButton = $isShowBookButton = false;
}

do_action( 'mphb_sc_search_results_before_room' );

$wrapperClass = apply_filters( 'mphb_sc_search_results_room_type_class', join( ' ', mphb_tmpl_get_filtered_post_class( 'mphb-room-type' ) ) );
?>
<div class="<?php echo esc_attr( $wrapperClass ); ?>">

	<?php do_action( 'mphb_sc_search_results_room_top' ); ?>

	<?php
	if ( $isShowGallery && mphb_tmpl_has_room_type_gallery() ) {
		/**
		 * @hooked \MPHB\Views\LoopRoomTypeView::renderGallery - 10
		 */
		do_action( 'mphb_sc_search_results_render_gallery' );
	} elseif ( $isShowImage && has_post_thumbnail() ) {
		/**
		 * @hooked \MPHB\Views\LoopRoomTypeView::renderFeaturedImage - 10
		 */
		do_action( 'mphb_sc_search_results_render_image' );
	}

	if ( $isShowTitle || $isShowExcerpt || $isShowDetails || $isShowPrice || $isShowViewButton || $isShowBookButton ) {

		do_action( 'mphb_sc_search_results_before_info' );

		if ( $isShowTitle ) {
			/**
			 * @hooked \MPHB\Views\LoopRoomTypeView::renderTitle - 10
			 */
			do_action( 'mphb_sc_search_results_render_title' );
		}
		
		

		if ( $isShowExcerpt ) {
			/**
			 * @hooked \MPHB\Views\LoopRoomTypeView::renderExcerpt - 10
			 */
			do_action( 'mphb_sc_search_results_render_excerpt' );
		}
		
		/*if ( $isShowViewButton ) {
            echo '<p class="mphb-view-details-button-wrapper" id="detailsbutton">';
            \MPHB\Views\LoopRoomTypeView::renderViewDetailsButton();
            echo '</p>';
        }*/


		if ( $isShowDetails ) {
			/**
			 * 
			 */
			do_action( 'mphb_sc_search_results_render_details' );
		}
		


		if ( $isShowPrice ) {
			/**
			 * @hooked \MPHB\Views\LoopRoomTypeView::renderPriceForDates - 10
			 */
			do_action( 'mphb_sc_search_results_render_price', $checkInDate, $checkOutDate );
		}


		if ( $isShowBookButton ) {
			/**
			 * @hooked \MPHB\Views\LoopRoomTypeView::renderBookButton - 10
			 */
			do_action( 'mphb_sc_search_results_render_book_button' );
		}

		do_action( 'mphb_sc_search_results_after_info' );
	}
	?>

	<?php do_action( 'mphb_sc_search_results_room_bottom' ); ?>

</div>
<?php
do_action( 'mphb_sc_search_results_after_room' );
