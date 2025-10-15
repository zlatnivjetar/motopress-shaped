<?php

namespace MPHB\Admin\EditCPTPages;

/**
 * @since 4.10.0
 */
class RoomEditCPTPage extends EditCPTPage {

	public function customizeMetaBoxes() {

		// Remove current accommodation from "Linked Accommodations" metabox
		add_action( 'current_screen', function () {
			if ( $this->isCurrentEditPage() && ! MPHB()->translation()->isTranslationPage() ) {
				// "post" is not present during update
				if ( isset( $_GET['post'] ) ) {
					$postId = absint( $_GET['post'] );

					$this->fieldGroups['linked_rooms']->getFieldByName( 'mphb_linked_room' )->removeItem( $postId );
				}
			}
		} );

	}

}
