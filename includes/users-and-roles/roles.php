<?php

/**
 * @since 4.0.0
 */

namespace MPHB\UsersAndRoles;

class Roles {

	const VERSION = 3;

	const MANAGER  = 'mphb_manager';
	const WORKER   = 'mphb_worker';
	const CUSTOMER = 'mphb_customer';

	/**
	 * @var array
	 */
	public $roles = array();


	public function __construct() {

		// we must init translated strings after init hook
		add_action(
			'init',
			function () {

				$this->roles[ self::MANAGER ] = new Role(
					array(
						'name'        => self::MANAGER,
						'description' => __( 'Hotel Manager', 'motopress-hotel-booking' ),
					)
				);
		
				$this->roles[ self::WORKER ] = new Role(
					array(
						'name'        => self::WORKER,
						'description' => __( 'Hotel Worker', 'motopress-hotel-booking' ),
					)
				);
		
				$this->roles[ self::CUSTOMER ] = new Role(
					array(
						'name'        => self::CUSTOMER,
						'description' => __( 'Hotel Customer', 'motopress-hotel-booking' ),
					)
				);
			},
			-1
		);
	}

	/**
	 *
	 * @return array
	 */
	public function getRoles() {
		return $this->roles;
	}

	/**
	 *
	 * @return int
	 */
	public static function getCurrentVersion() {
		return self::VERSION;
	}
}
