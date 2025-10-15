<?php

namespace MPHB\Upgrades;

use \MPHB\Entities;

class BackgroundUpgrader extends \MPHB\BackgroundPausableProcess {

	protected $action = 'upgrader';

	/**
	 * @todo Fix null callbacks properly.
	 */
	protected function task( $callback ) {

		// Todo: sometimes $callback is null
		if ( ! is_null( $callback ) && method_exists( MPHB()->upgrader(), $callback ) ) {
			return call_user_func( array( MPHB()->upgrader(), $callback ) );
		}
		return false;
	}
}
