<?php

namespace MPHB\Admin\Fields;

/**
 * @since 3.9.3
 */
class NotesListField extends RulesListField {

	const TYPE = 'notes-list';

	public function __construct( $name, $details, $value = '' ) {
		parent::__construct( $name, $details, $value );

		if ( is_array( $details['fields'] ) ) {

			$this->fields[] = FieldFactory::create(
				'date',
				array(
					'type'    => 'text',
					'size'    => 'small',
					'default' => time(),
					'label'   => __( 'Date', 'motopress-hotel-booking' ),
				)
			);

			$this->fields[] = FieldFactory::create(
				'user',
				array(
					'type'    => 'text',
					'default' => get_current_user_id(),
					'size'    => 'small',
					'label'   => __( 'Author', 'motopress-hotel-booking' ),
				)
			);
		}
	}

	/**
	 * @param InputField $field Cloned field with changed name, like:
	 *     "_mphb_booking_internal_notes[0][date]".
	 */
	protected function renderValue( $field ) {
		$name = $field->getInitialName();
		$type = $field->getType();

		$result = '';

		switch ( $type ) {
			case TextField::TYPE:
				if ( $name == 'user' ) {
					$result = $this->renderUserValue( $field );
				} elseif ( $name == 'date' ) {
					$result = $this->renderDatePickerValue( $field );
				} else {
					$result = TextField::renderValue( $field );
				}
				break;

			default:
				$result = parent::renderValue( $field );
				break;
		}

		return $result;
	}

	protected function renderField( $field ) {
		switch ( $field->getInitialName() ) {
			case 'user':
				return $this->renderUserField( $field );
				break;
			case 'date':
				return $this->renderDateField( $field );
				break;
			default:
				return $field->render();
				break;
		}
	}

	protected function renderDateField( $field ) {

		ob_start();

		echo '<div class="mphb-ctrl-wrapper ' . esc_attr( $field->getCtrlClasses() ) . '" data-type="timestamp" data-initial-name="' . esc_attr( $field->getInitialName() ) . '">';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderDateInput( $field );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $field->getInnerLabelTag();

		echo '</div>';

		$result = ob_get_contents();

		ob_end_clean();

		return $result;

	}

	/**
	 * @param InputField $field
	 */
	protected function renderUserField( $field ) {
		ob_start();

		echo '<div class="mphb-ctrl-wrapper ' . esc_attr( $field->getCtrlClasses() ) . '" data-type="username" data-initial-name="' . esc_attr( $field->getInitialName() ) . '">';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderUserInput( $field );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $field->getInnerLabelTag();

		echo '</div>';

		$result = ob_get_contents();

		ob_end_clean();

		return $result;
	}

	/**
	 * @param InputField $field
	 */
	protected function renderDateInput( $field ) {
		$result  = '<span class="mphb-ctrl-date-val">' . $this->renderDatePickerValue( $field ) . '</span>';
		$result .= '<input name="' . esc_attr( $field->getName() ) . '" value="' . esc_attr( $field->getValue() ) . '" id="' . MPHB()->addPrefix( $field->getName() ) . '" type="hidden" />';

		return $result;
	}

	/**
	 * @param InputField $field
	 */
	protected function renderUserInput( $field ) {
		if ( ! empty( $field->getValue() ) ) {
			$user = get_user_by( 'id', (int) $field->getValue() );
		} else {
			$user = wp_get_current_user();
		}

		$displayName = $user ? $user->display_name : '';

		$result  = '<input name="' . esc_attr( $field->getName() ) . '" value="' . esc_attr( $field->getValue() ) . '" id="' . MPHB()->addPrefix( $field->getName() ) . '" type="hidden" />';
		$result .= '<span class="mphb-ctrl-user-name">' . $displayName . '</span>';

		return $result;
	}

	/**
	 * @param InputField $field
	 */
	protected function renderDatePickerValue( $field ) {
		return wp_date( get_option( 'date_format' ), $field->getValue() );
	}

	/**
	 * @param InputField $field
	 */
	protected function renderUserValue( $field ) {
		if ( ! empty( $field->value ) ) {
			$user = get_user_by( 'id', $field->value );
			return $user ? $user->display_name : '';
		}

		return '';
	}

}


