<?php

namespace MPHB\Repositories;

use \MPHB\Entities;

/**
 * Do not use this class directly! Use mphb_prices_facade()->[methods...]!
 */
class RateRepository extends AbstractPostRepository {

	protected $type = 'rate';

	/**
	 *
	 * @param int  $id
	 * @param bool $force
	 * @return Entities\Rate
	 */
	public function findById( $id, $force = false ) {
		return parent::findById( $id, $force );
	}

	/**
	 *
	 * @param array     $atts
	 * @param \DateTime $atts['check_in_date']
	 * @param \DateTime $atts['check_out_date']
	 *
	 * @return type
	 */
	public function findAll( $atts = array() ) {

		$rates = parent::findAll( $atts );

		if ( isset( $atts['check_in_date'] ) && isset( $atts['check_out_date'] ) ) {

			$rates = array_filter(
				$rates,
				function( Entities\Rate $rate ) use ( $atts ) {
					return $rate->isAvailableForDates( $atts['check_in_date'], $atts['check_out_date'] );
				}
			);
		}

		return $rates;
	}

	/**
	 *
	 * @param int $roomTypeId
	 * @return Entities\Rate[]
	 */
	public function findAllByRoomType( $roomTypeId, $atts = array() ) {

		$forceAtts = array(
			'room_type_id' => $roomTypeId,
			'fields'       => 'ids',
		);

		$atts = array_merge( $atts, $forceAtts );

		return $this->findAll( $atts );
	}

	/**
	 *
	 * @param int $roomTypeId
	 * @return Entities\Rate[]
	 */
	public function findAllActiveByRoomType( $roomTypeId, $atts = array() ) {
		$forceAtts = array(
			'active' => true,
		);

		$atts = array_merge( $atts, $forceAtts );

		return $this->findAllByRoomType( $roomTypeId, $atts );
	}


	public function isExistsForRoomType( $roomTypeId, $atts = array() ) {

		$forceAtts = array(
			'fields' => 'ids',
		);

		$atts = array_merge( $atts, $forceAtts );

		$rates = $this->findAllActiveByRoomType( $roomTypeId, $atts );

		return count( $rates ) > 0;
	}

	/**
	 *
	 * @param int|WP_Post $post
	 * @return \MPHB\Entities\Rate
	 */
	public function mapPostToEntity( $post ) {

		$id = ( is_a( $post, '\WP_Post' ) ) ? $post->ID : $post;

		$rawSeasonPrices = get_post_meta( $id, 'mphb_season_prices', true );

		if ( $rawSeasonPrices === '' ) {
			$rawSeasonPrices = array();
		}

		$roomTypeId = (int) get_post_meta( $id, 'mphb_room_type_id', true );

		$seasonPrices = array();

		foreach ( $rawSeasonPrices as $i => $rawSeasonPrice ) {
			$rawSeasonPriceArgs = array(
				'id'           => $i, // 0, 1, 2, ...
				'season_id'    => $rawSeasonPrice['season'],
				'room_type_id' => $roomTypeId,
				// [
				//     "periods", "prices", "base_adults", "base_children",
				//     "extra_adult_prices", "extra_child_prices",
				//     "enable_variations", "variations"
				// ]
				'price'        => $rawSeasonPrice['price'],
			);

			$seasonPrice = Entities\SeasonPrice::create( $rawSeasonPriceArgs );
			if ( $seasonPrice ) {
				$seasonPrices[] = $seasonPrice;
			}
		}

		$rateArgs = array(
			'id'            => $id,
			'title'         => get_the_title( $id ),
			'room_type_id'  => $roomTypeId,
			'description'   => get_post_meta( $id, 'mphb_description', true ),
			'active'        => get_post_status( $id ) === 'publish',
			'season_prices' => $seasonPrices,
		);

		return new Entities\Rate( $rateArgs );
	}

	/**
	 *
	 * @param Entities\Rate $entity
	 * @return \MPHB\Entities\WPPostData
	 */
	public function mapEntityToPostData( $entity ) {
		$postAtts = array(
			'ID'          => $entity->getId(),
			'post_metas'  => array(),
			'post_status' => $entity->isActive() ? 'publish' : 'draft',
			'post_title'  => $entity->getTitle(),
			'post_type'   => MPHB()->postTypes()->rate()->getPostType(),
		);

		$seasonPrices = array_map(
			function( Entities\SeasonPrice $seasonPrice ) {
				return array(
					'id'     => $seasonPrice->getId(),
					'season' => $seasonPrice->getSeasonId(),
					// [
					//     "periods", "prices", "base_adults", "base_children"
					//     "extra_adult_prices", "extra_child_prices",
					//     "enable_variations", "variations"
					// ]
					'price'  => $seasonPrice->getPricesAndVariations(),
				);
			},
			array_reverse( $entity->getSeasonPrices() )
		);

		$postAtts['post_metas'] = array(
			'mphb_description'   => $entity->getDescription(),
			'mphb_room_type_id'  => $entity->getRoomTypeId(),
			'mphb_season_prices' => $seasonPrices,
		);

		return new Entities\WPPostData( $postAtts );
	}

	/**
	 *
	 * @param \MPHB\Entities\Rate $rate
	 * @return int
	 */
	public function duplicate( Entities\Rate $rate ) {

		$postData = $this->mapEntityToPostData( $rate );

		$postData->setID( null );
		/* translators: %s - original Rate title */
		$postData->setTitle( sprintf( __( '%s - copy', 'motopress-hotel-booking' ), $postData->getTitle() ) );
		$postData->setPostMeta( 'mphb_room_type_id', '' );

		return $this->persistence->createOrUpdate( $postData );
	}

}
