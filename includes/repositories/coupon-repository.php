<?php

namespace MPHB\Repositories;

use MPHB\Entities\Coupon;
use MPHB\Entities\WPPostData;
use MPHB\PostTypes\CouponCPT;
use WP_Post;

class CouponRepository extends AbstractPostRepository {
	/**
	 * @param Coupon $entity
	 * @return WPPostData
	 */
	public function mapEntityToPostData( $entity ) {

		$postAtts = array(
			'ID'          => $entity->getId(),
			'post_metas'  => array(),
			'post_title'  => $entity->getCode(),
			'post_status' => $entity->getStatus(),
			'post_type'   => MPHB()->postTypes()->coupon()->getPostType(),
		);

		$postAtts['post_metas'] = array(
			'_mphb_description'              => $entity->getDescription(),
			'_mphb_room_discount_type'       => $entity->getRoomDiscountType(),
			'_mphb_service_discount_type'    => $entity->getServiceDiscountType(),
			'_mphb_fee_discount_type'        => $entity->getFeeDiscountType(),
			'_mphb_room_amount'              => $entity->getRoomAmount(),
			'_mphb_service_amount'           => $entity->getServiceAmount(),
			'_mphb_fee_amount'               => $entity->getFeeAmount(),
			'_mphb_include_services'         => $entity->getApplicableServiceIds(),
			'_mphb_include_fees'             => $entity->getApplicableFeeIds(),
			'_mphb_expiration_date'          => $entity->getExpirationDate() ? $entity->getExpirationDate()->format( 'Y-m-d' ) : '',
			'_mphb_include_room_types'       => $entity->getRoomTypes(),
			'_mphb_check_in_date_after'      => $entity->getCheckInDateAfter() ? $entity->getCheckInDateAfter()->format( 'Y-m-d' ) : '',
			'_mphb_check_out_date_before'    => $entity->getCheckOutDateBefore() ? $entity->getCheckOutDateBefore()->format( 'Y-m-d' ) : '',
			'_mphb_min_days_before_check_in' => $entity->getMinDaysBeforeCheckIn(),
			'_mphb_max_days_before_check_in' => $entity->getMaxDaysBeforeCheckIn(),
			'_mphb_min_nights'               => $entity->getMinNights(),
			'_mphb_max_nights'               => $entity->getMaxNights(),
			'_mphb_usage_limit'              => $entity->getUsageLimit(),
			'_mphb_usage_count'              => $entity->getUsageCount(),
		);

		return new WPPostData( $postAtts );
	}

	/**
	 * @param WP_Post|int $post
	 * @return Coupon
	 */
	public function mapPostToEntity( $post ) {

		if ( is_a( $post, '\WP_Post' ) ) {
			$id = $post->ID;
		} else {
			$id   = absint( $post );
			$post = get_post( $id );
		}

		$description = get_post_meta( $id, '_mphb_description', true );

		$roomTypes = get_post_meta( $id, '_mphb_include_room_types', true );

		if ( $roomTypes === '' ) {
			$roomTypes = array();
		}

		$applicableServiceIds = get_post_meta( $id, '_mphb_include_services', true );

		if ( $applicableServiceIds === '' ) {
			$applicableServiceIds = array();
		}

		$applicableFeeIds = get_post_meta( $id, '_mphb_include_fees', true );

		if ( $applicableFeeIds === '' ) {
			$applicableFeeIds = array();
		}

		$roomDiscountType = get_post_meta( $id, '_mphb_room_discount_type', true );
		$serviceDiscountType = get_post_meta( $id, '_mphb_service_discount_type', true );
		$feeDiscountType = get_post_meta( $id, '_mphb_fee_discount_type', true );

		$roomAmount = max( 0.0, (float) get_post_meta( $id, '_mphb_room_amount', true ) );
		$serviceAmount = max( 0.0, (float) get_post_meta( $id, '_mphb_service_amount', true ) );
		$feeAmount = max( 0.0, (float) get_post_meta( $id, '_mphb_fee_amount', true ) );

		$minDaysBeforeCheckIn = (int) get_post_meta( $id, '_mphb_min_days_before_check_in', true );
		$maxDaysBeforeCheckIn = (int) get_post_meta( $id, '_mphb_max_days_before_check_in', true );

		$minNights  = (int) get_post_meta( $id, '_mphb_min_nights', true );
		$maxNights  = (int) get_post_meta( $id, '_mphb_max_nights', true );
		$usageLimit = (int) get_post_meta( $id, '_mphb_usage_limit', true );
		$usageCount = (int) get_post_meta( $id, '_mphb_usage_count', true );

		$atts = array(
			'id'                       => $id,
			'status'                   => $post->post_status,
			'code'                     => $post->post_title,
			'description'              => $description,
			'room_discount_type'       => $roomDiscountType ?: CouponCPT::TYPE_ACCOMMODATION_DEFAULT,
			'service_discount_type'    => $serviceDiscountType ?: CouponCPT::TYPE_SERVICE_DEFAULT,
			'fee_discount_type'        => $feeDiscountType ?: CouponCPT::TYPE_FEE_DEFAULT,
			'room_amount'              => $roomAmount,
			'service_amount'           => $serviceAmount,
			'fee_amount'               => $feeAmount,
			'applicable_service_ids'   => $applicableServiceIds,
			'applicable_fee_ids'       => $applicableFeeIds,
			'room_types'               => $roomTypes,
			'min_days_before_check_in' => $minDaysBeforeCheckIn,
			'max_days_before_check_in' => $maxDaysBeforeCheckIn,
			'min_nights'               => $minNights,
			'max_nights'               => $maxNights,
			'usage_limit'              => $usageLimit,
			'usage_count'              => $usageCount,
		);

		$expirationDate = \DateTime::createFromFormat( 'Y-m-d', get_post_meta( $id, '_mphb_expiration_date', true ) );
		if ( $expirationDate ) {
			$atts['expiration_date'] = $expirationDate;
		}

		$checkInDateAfter = \DateTime::createFromFormat( 'Y-m-d', get_post_meta( $id, '_mphb_check_in_date_after', true ) );
		if ( $checkInDateAfter ) {
			$atts['check_in_date_after'] = $checkInDateAfter;
		}

		$checkOutDateBefore = \DateTime::createFromFormat( 'Y-m-d', get_post_meta( $id, '_mphb_check_out_date_before', true ) );
		if ( $checkOutDateBefore ) {
			$atts['check_out_date_before'] = $checkOutDateBefore;
		}

		return new Coupon( $atts );
	}

	/**
	 * @param string $code
	 * @return Coupon|null
	 */
	public function findByCode( $code ) {

		$atts = array(
			'title'          => $code,
			'posts_per_page' => 1,
			'status'         => 'publish',
		);

		$coupons = $this->findAll( $atts );

		return ! empty( $coupons ) ? reset( $coupons ) : null;
	}

	/**
	 * @param int $id
	 * @param bool $force
	 * @return Coupon
	 */
	public function findById( $id, $force = false ) {
		return parent::findById( $id, $force );
	}
}
