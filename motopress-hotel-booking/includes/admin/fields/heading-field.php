<?php

declare(strict_types=1);

namespace MPHB\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HeadingField extends InputField {
	const TYPE = 'heading';

	public function hasLabel(): bool {
		return false;
	}

	public function getLabelTag(): string {
		return '';
	}

	protected function renderInput(): string {
		$heading = '<h2>';
			$heading .= wp_kses( $this->getLabel(), 'entities' );
		$heading .= '</h2>';

		return $heading;
	}
}
