<?php

namespace MPHB\PostTypes;

use MPHB\Admin\Fields;
use MPHB\Admin\Groups;
use MPHB\Admin\ManageCPTPages;

class CouponCPT extends EditableCPT {

	const TYPE_ACCOMMODATION_NONE          = 'none';
	const TYPE_ACCOMMODATION_PERCENTAGE    = 'percentage';
	const TYPE_ACCOMMODATION_FIXED         = 'per_accomm';
	const TYPE_ACCOMMODATION_FIXED_PER_DAY = 'per_accomm_per_day';
	const TYPE_ACCOMMODATION_DEFAULT       = 'percentage';

	const TYPE_SERVICE_NONE       = 'none';
	const TYPE_SERVICE_PERCENTAGE = 'percentage';
	const TYPE_SERVICE_FIXED      = 'fixed';
	const TYPE_SERVICE_DEFAULT    = 'none';

	const TYPE_FEE_NONE       = 'none';
	const TYPE_FEE_PERCENTAGE = 'percentage';
	const TYPE_FEE_FIXED      = 'fixed';
	const TYPE_FEE_DEFAULT    = 'none';

	protected $postType = 'mphb_coupon';

	public function __construct() {
		parent::__construct();
		add_action( 'mphb_booking_confirmed', array( $this, 'udpateCouponUsage' ), 10, 2 );

		add_filter( 'parent_file', array( $this, 'parent_file' ), 10, 1 );
	}

	public function getFieldGroups() {
		$descriptionGroup = new Groups\MetaBoxGroup( 'mphb_description', esc_html__( 'Description', 'motopress-hotel-booking' ), $this->postType, 'normal' );
		$descriptionGroup->addFields(
			array(
				Fields\FieldFactory::create(
					'_mphb_description',
					array(
						'type'        => 'textarea',
						'label'       => false,
						'description' => esc_html__( 'A brief description to remind you what this code is for.', 'motopress-hotel-booking' ),
						'default'     => '',
					)
				),
			)
		);

		$conditionsGroup = new Groups\MetaBoxGroup( 'mphb_conditions', esc_html__( 'Conditions', 'motopress-hotel-booking' ), $this->postType, 'normal' );
		$conditionsGroup->addFields(
			array(
				Fields\FieldFactory::create(
					'_mphb_include_room_types',
					array(
						'type'        => 'multiple-select',
						'label'       => esc_html__( 'Accommodation Types', 'motopress-hotel-booking' ),
						'description' => esc_html__( 'Apply a coupon code to selected accommodations in a booking. Leave blank to apply to all accommodations.', 'motopress-hotel-booking' ),
						'list'        => MPHB()->getRoomTypePersistence()->getIdTitleList(
							array(
								'mphb_language' => 'original',
							)
						),
						'default'     => array(),
					)
				),
			)
		);

		$accommodationGroup = new Groups\MetaBoxGroup( 'mphb_accommodation_discount', esc_html__( 'Accommodation Discount', 'motopress-hotel-booking' ), $this->postType, 'normal' );
		$accommodationGroup->addFields(
			array(
				Fields\FieldFactory::create(
					'_mphb_room_discount_type',
					array(
						'type'    => 'radio',
						'label'   => esc_html__( 'Type', 'motopress-hotel-booking' ),
						'list'    => array(
							self::TYPE_ACCOMMODATION_NONE          => esc_html__( 'None', 'motopress-hotel-booking' ),
							self::TYPE_ACCOMMODATION_PERCENTAGE    => esc_html__( 'Percentage discount on accommodation price', 'motopress-hotel-booking' ),
							self::TYPE_ACCOMMODATION_FIXED         => esc_html__( 'Fixed discount on accommodation price', 'motopress-hotel-booking' ),
							self::TYPE_ACCOMMODATION_FIXED_PER_DAY => esc_html__( 'Fixed discount on daily/nightly price', 'motopress-hotel-booking' ),
						),
						'default' => self::TYPE_ACCOMMODATION_DEFAULT,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_room_amount',
					array(
						'type'        => 'number',
						'label'       => esc_html__( 'Amount', 'motopress-hotel-booking' ),
						'description' => esc_html__( 'Enter percent or fixed amount according to selected type.', 'motopress-hotel-booking' ),
						'min'         => 0,
						'step'        => 0.01,
						'default'     => 0,
						'size'        => 'long-price',
					)
				),
			)
		);

		$serviceDiscount = new Groups\MetaBoxGroup( 'mphb_service_discount', esc_html__( 'Service Discount', 'motopress-hotel-booking' ), $this->postType, 'normal' );
		$serviceDiscount->addFields(
			array(
				Fields\FieldFactory::create(
					'_mphb_service_discount_type',
					array(
						'type'    => 'radio',
						'label'   => esc_html__( 'Type', 'motopress-hotel-booking' ),
						'list'    => array(
							self::TYPE_SERVICE_NONE       => esc_html__( 'None', 'motopress-hotel-booking' ),
							self::TYPE_SERVICE_PERCENTAGE => esc_html__( 'Percentage', 'motopress-hotel-booking' ),
							self::TYPE_SERVICE_FIXED      => esc_html__( 'Fixed', 'motopress-hotel-booking' ),
						),
						'default' => self::TYPE_SERVICE_DEFAULT,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_service_amount',
					array(
						'type'        => 'number',
						'label'       => esc_html__( 'Amount', 'motopress-hotel-booking' ),
						'description' => esc_html__( 'Enter percent or fixed amount according to selected type.', 'motopress-hotel-booking' ),
						'min'         => 0,
						'step'        => 0.01,
						'default'     => 0,
						'size'        => 'long-price',
					)
				),
				Fields\FieldFactory::create(
					'_mphb_include_services',
					array(
						'type'        => 'multiple-select',
						'label'       => esc_html__( 'Services', 'motopress-hotel-booking' ),
						'description' => esc_html__( 'Apply a coupon code to selected services in a booking. Leave blank to apply to all services.', 'motopress-hotel-booking' ),
						'list'        => MPHB()->getServiceRepository()->getIdTitleList(
							array(
								'mphb_language' => 'original',
							)
						),
						'default'     => array(),
					)
				),
			)
		);

		$feeDiscount = new Groups\MetaBoxGroup( 'mphb_fee_discount', esc_html__( 'Fee Discount', 'motopress-hotel-booking' ), $this->postType, 'normal' );
		$feeDiscount->addFields(
			array(
				Fields\FieldFactory::create(
					'_mphb_fee_discount_type',
					array(
						'type'     => 'radio',
						'label'    => esc_html__( 'Type', 'motopress-hotel-booking' ),
						'list'     => array(
							self::TYPE_FEE_NONE       => esc_html__( 'None', 'motopress-hotel-booking' ),
							self::TYPE_FEE_PERCENTAGE => esc_html__( 'Percentage', 'motopress-hotel-booking' ),
							self::TYPE_FEE_FIXED      => esc_html__( 'Fixed', 'motopress-hotel-booking' ),
						),
						'default'  => self::TYPE_FEE_DEFAULT,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_fee_amount',
					array(
						'type'        => 'number',
						'label'       => esc_html__( 'Amount', 'motopress-hotel-booking' ),
						'description' => esc_html__( 'Enter percent or fixed amount according to selected type.', 'motopress-hotel-booking' ),
						'min'         => 0,
						'step'        => 0.01,
						'default'     => 0,
						'size'        => 'long-price',
					)
				),
			)
		);

		$restrictionsGroup = new Groups\MetaBoxGroup( 'mphb_main', esc_html__( 'Usage Restrictions', 'motopress-hotel-booking' ), $this->postType, 'normal' );
		$restrictionsGroup->addFields(
			array(
				Fields\FieldFactory::create(
					'_mphb_expiration_date',
					array(
						'type'     => 'datepicker',
						'label'    => esc_html__( 'Expiration Date', 'motopress-hotel-booking' ),
						'readonly' => false,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_check_in_date_after',
					array(
						'type'     => 'datepicker',
						'label'    => esc_html__( 'Check-in After', 'motopress-hotel-booking' ),
						'readonly' => false,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_check_out_date_before',
					array(
						'type'     => 'datepicker',
						'label'    => esc_html__( 'Check-out Before', 'motopress-hotel-booking' ),
						'readonly' => false,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_min_days_before_check_in',
					array(
						'type'        => 'number',
						'label'       => esc_html__( 'Min days before check-in', 'motopress-hotel-booking' ),
						'description' => esc_html__( 'For early bird discount. The coupon code applies if a booking is made in a minimum set number of days before the check-in date.', 'motopress-hotel-booking' ),
						'min'         => 0,
						'step'        => 1,
						'default'     => 0,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_max_days_before_check_in',
					array(
						'type'        => 'number',
						'label'       => esc_html__( 'Max days before check-in', 'motopress-hotel-booking' ),
						'description' => esc_html__( 'For last minute discount. The coupon code applies if a booking is made in a maximum set number of days before the check-in date.', 'motopress-hotel-booking' ),
						'min'         => 0,
						'step'        => 1,
						'default'     => 0,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_min_nights',
					array(
						'type'    => 'number',
						'label'   => esc_html__( 'Minimum Days', 'motopress-hotel-booking' ),
						'min'     => 1,
						'step'    => 1,
						'default' => 1,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_max_nights',
					array(
						'type'    => 'number',
						'label'   => esc_html__( 'Maximum Days', 'motopress-hotel-booking' ),
						'min'     => 0,
						'step'    => 1,
						'default' => 0,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_usage_limit',
					array(
						'type'    => 'number',
						'label'   => esc_html__( 'Usage Limit', 'motopress-hotel-booking' ),
						'min'     => 0,
						'step'    => 1,
						'default' => 0,
					)
				),
				Fields\FieldFactory::create(
					'_mphb_usage_count',
					array(
						'type'     => 'text',
						'label'    => esc_html__( 'Usage Count', 'motopress-hotel-booking' ),
						'default'  => 0,
						'size'     => 'small',
						'readonly' => true,
					)
				),
			)
		);

		return array(
			$descriptionGroup,
			$conditionsGroup,
			$accommodationGroup,
			$serviceDiscount,
			$feeDiscount,
			$restrictionsGroup,
		);
	}

	/**
	 *
	 * @since 4.0.0 - Add custom capabilities.
	 */
	public function register() {
		$labels = array(
			'name'               => __( 'Coupons', 'motopress-hotel-booking' ),
			'singular_name'      => __( 'Coupon', 'motopress-hotel-booking' ),
			'add_new'            => _x( 'Add New', 'Add New Coupon', 'motopress-hotel-booking' ),
			'add_new_item'       => __( 'Add New Coupon', 'motopress-hotel-booking' ),
			'edit_item'          => __( 'Edit Coupon', 'motopress-hotel-booking' ),
			'new_item'           => __( 'New Coupon', 'motopress-hotel-booking' ),
			'view_item'          => __( 'View Coupon', 'motopress-hotel-booking' ),
			'search_items'       => __( 'Search Coupon', 'motopress-hotel-booking' ),
			'not_found'          => __( 'No coupons found', 'motopress-hotel-booking' ),
			'not_found_in_trash' => __( 'No coupons found in Trash', 'motopress-hotel-booking' ),
			'all_items'          => __( 'All Coupons', 'motopress-hotel-booking' ),
		);

		$args = array(
			'labels'               => $labels,
			'public'               => false,
			'exclude_from_search'  => true,
			'publicly_queryable'   => false,
			'show_ui'              => true,
			'show_in_menu'         => false,
			'query_var'            => false,
			'capability_type'      => $this->getCapabilityType(),
			'map_meta_cap'         => true,
			'register_meta_box_cb' => array( $this, 'registerMetaBoxes' ),
			'has_archive'          => false,
			'hierarchical'         => false,
			'supports'             => array( 'title' ),
		);

		register_post_type( $this->postType, $args );
	}

	/**
	 * @param \MPHB\Entities\Booking $booking
	 * @param string                 $oldStatus
	 */
	public function udpateCouponUsage( $booking, $oldStatus ) {
		if ( $booking->getCouponId() ) {
			$coupon = MPHB()->getCouponRepository()->findById( $booking->getCouponId() );
			if ( $coupon ) {
				$coupon->increaseUsageCount();
				MPHB()->getCouponRepository()->save( $coupon );
			}
		}
	}

	protected function createEditPage() {
		return new \MPHB\Admin\EditCPTPages\CouponEditCPTPage( $this->postType, $this->getFieldGroups() );
	}

	protected function createManagePage() {
		return new ManageCPTPages\CouponManageCPTPage( $this->postType );
	}

	/**
	 * Set correct active/current menu and submenu in the WordPress Admin menu
	 */
	public function parent_file( $parent_file ) {

		global $submenu_file, $current_screen;

		if ( $current_screen->post_type == $this->postType ) {
			$submenu_file = 'edit.php?post_type=' . $this->postType;
			$parent_file  = MPHB()->menus()->getMainMenuSlug();
		}

		return $parent_file;
	}

}
