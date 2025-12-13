<?php

namespace MPHB\Repositories;

use MPHB\Entities;
use MPHB\PostTypes\SeasonCPT;
use MPHB\Utils\DateUtils;

class SeasonRepository extends AbstractPostRepository {

	protected $type = 'season';

	/**
	 *
	 * @param array $atts
	 * @return Entities\Season[]
	 */
	public function findAll( $atts = array() ) {
		return parent::findAll( $atts );
	}

	/**
	 *
	 * @param int $id
	 * @return Entities\Season|null
	 */
	public function findById( $id, $force = false ) {
		return parent::findById( $id, $force );
	}

	public function mapPostToEntity( $post ) {

		$id = ( is_a( $post, '\WP_Post' ) ) ? $post->ID : $post;

		$startDate       = get_post_meta( $id, 'mphb_start_date', true );
		$endDate         = get_post_meta( $id, 'mphb_end_date', true );
		$days            = get_post_meta( $id, 'mphb_days', true );
		$repeatPeriod    = get_post_meta( $id, 'mphb_repeat_period', true );
		$repeatUntilDate = get_post_meta( $id, 'mphb_repeat_until_date', true );

		$seasonArgs = array(
			'id'                => $id,
			'title'             => get_the_title( $id ),
			'description'       => get_post_field( 'post_content', $id ),
			'start_date'        => DateUtils::createDateTime( $startDate ),
			'end_date'          => DateUtils::createDateTime( $endDate ),
			'days'              => ! empty( $days ) ? $days : array(),
			'repeat_period'     => $repeatPeriod ?: SeasonCPT::REPEAT_PERIOD_DEFAULT,
			'repeat_until_date' => DateUtils::createDateTime( $repeatUntilDate ),
		);

		if ( $repeatPeriod !== SeasonCPT::REPEAT_PERIOD_YEAR ) {
			return new Entities\Season( $seasonArgs );
		} else {
			return new Entities\RecurrentSeason( $seasonArgs );
		}
	}

	/**
	 *
	 * @param Entities\Season $entity
	 * @return \MPHB\Entities\WPPostData
	 */
	public function mapEntityToPostData( $entity ) {
		$postAtts = array(
			'ID'           => $entity->getId(),
			'post_metas'   => array(),
			'post_status'  => $entity->getId() ? get_post_status( $entity->getId() ) : 'publish',
			'post_title'   => $entity->getTitle(),
			'post_content' => $entity->getDescription(),
			'post_type'    => MPHB()->postTypes()->season()->getPostType(),
		);

		$postAtts['post_metas'] = array(
			'mphb_start_date'        => ! is_null( $entity->getStartDate() ) ? $entity->getStartDate()->format( 'Y-m-d' ) : null,
			'mphb_end_date'          => ! is_null( $entity->getEndDate() ) ? $entity->getEndDate()->format( 'Y-m-d' ) : null,
			'mphb_days'              => $entity->getDays(),
			'mphb_repeat_period'     => $entity->getRepeatPeriod(),
			'mphb_repeat_until_date' => ! is_null( $entity->getRepeatUntilDate() ) ? $entity->getRepeatUntilDate()->format( 'Y-m-d' ) : null,
		);

		return new Entities\WPPostData( $postAtts );
	}

}
