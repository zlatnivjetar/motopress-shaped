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
