<?php

namespace MPHB\Admin\Fields;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @since 5.0.0
 */
class ThousandSeparatorField extends TextField {

	const TYPE = 'thousand-separator';

	protected $inputType = 'text';

	public function sanitize( $value ) {
		if ( $value === ' ') {
			return $value;
		} else {
			return parent::sanitize( $value );
		}
	}
}
