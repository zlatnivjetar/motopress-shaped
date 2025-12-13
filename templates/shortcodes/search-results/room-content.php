<?php
/**
 * RoomCloud Availability Filter
 * Block display of rooms with 0 inventory
 * Updated: Handle null (no data) vs 0 (explicitly unavailable)
 */
if (class_exists('Shaped_RC_Availability_Manager')) {
    $room_type_id = get_the_ID();
    $room_post = get_post($room_type_id);
    $room_slug = $room_post->post_name;
    
    // Get search dates from URL or session
    $check_in = isset($_GET['mphb_check_in_date']) ? sanitize_text_field($_GET['mphb_check_in_date']) : '';
    $check_out = isset($_GET['mphb_check_out_date']) ? sanitize_text_field($_GET['mphb_check_out_date']) : '';
    
    if ($check_in && $check_out) {
        // Check if room is available according to RoomCloud
        $available = Shaped_RC_Availability_Manager::get_available_rooms($check_in, $check_out);
        
        // Only block if RoomCloud explicitly says 0 available
        // If null (no data), allow MotoPress to handle availability
        if (isset($available[$room_slug]) && $available[$room_slug] === 0) {
            // 0 inventory - don't display this room
            return;
        }
        // If $available[$room_slug] is null or > 0, show the room
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
