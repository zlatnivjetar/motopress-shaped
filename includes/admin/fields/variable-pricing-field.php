<?php

namespace MPHB\Admin\Fields;

use MPHB\Entities\RoomType;
use MPHB\Utils\ParseUtils;
use MPHB\Utils\ValidateUtils;

class VariablePricingField extends InputField {

	const TYPE       = 'variable-pricing';
	const MIN_PERIOD = 2; // See also MPHB.VariablePricingCtrl.MIN_PERIOD
	const MIN_PRICE  = 0;

	public function __construct( $name, $details, $value = '' ) {
		$this->default = mphb_normilize_season_price( 0 );

		// Set default base capacity
		$roomType = $this->getEditingRoomType();

		if ( ! is_null( $roomType ) ) {
			$baseAdults   = $roomType->getBaseAdultsCapacity();
			$baseChildren = $roomType->getBaseChildrenCapacity();

			$this->default['base_adults'] = $baseAdults;

			// Don't exceed the total capacity
			$this->default['base_children'] = min( $baseChildren, $roomType->getMaxChildren( $baseAdults ) );
		}

		parent::__construct( $name, $details, '' );

		$this->setValue( $value );
	}

	protected function getCtrlClasses() {
		return parent::getCtrlClasses() . ' mphb-left';
	}

	/**
	 * @since 5.0.0
	 *
	 * @return string
	 */
	protected function getCtrlAtts() {
		return parent::getCtrlAtts() . ' data-full-name="' . esc_attr( $this->getName() ) . '"';
	}

	public function setValue( $value ) {
		// See setValue() in ComplexHorizontalField::generateItem()
		if ( empty( $value ) ) {
			$this->value = $this->default;

		} else {
			// Copy actual base capacity from the room type
			if ( ! isset( $value['base_adults'] ) ) {
				$value['base_adults'] = $this->default['base_adults'];
			}

			if ( ! isset( $value['base_children'] ) ) {
				$value['base_children'] = $this->default['base_children'];
			}

			$this->value = mphb_normilize_season_price( $value );
		}
	}

	protected function renderInput() {
		$result = '';

		$result .= $this->renderPeriods();
		$result .= $this->renderCheckbox();
		$result .= $this->renderVariations();

		return $result;
	}

	protected function renderPeriods() {
		$periods = $this->value['periods'];

		$result = '';

		$result .= '<table class="mphb-pricing-periods-table mphb-pricing-prices-table mphb-pricing-table widefat">';
			$result .= '<tbody>';

				// Render periods
				$result .= '<tr class="mphb-periods-row">';
					$result .= '<th>&nbsp;</th>';
					$result .= '<th>' . esc_html__( 'Nights', 'motopress-hotel-booking' ) . '</th>';

					for ( $i = 0, $count = count( $periods ); $i < $count; $i++ ) {
						$result .= '<td data-period-index="' . esc_attr( $i ) . '">';
							if ( $periods[ $i ] == 1 ) {
								$result .= $this->renderPeriod( '[periods][]', 1, 'disabled="disabled"', 'mphb-keep-disabled' );
							} else {
								$result .= $this->renderPeriod( '[periods][]', $periods[ $i ] );
								$result .= '<span class="mphb-pricing-tiny-description">' . esc_html__( 'and more', 'motopress-hotel-booking' ) . '</span>';
								$result .= '<span class="dashicons dashicons-trash mphb-pricing-action mphb-pricing-remove-period" title="' . esc_attr__( 'Remove', 'motopress-hotel-booking' ) . '"></span>';
							}
						$result .= '</td>';
					}

					$result .= '<td>';
						$result .= '<span class="dashicons dashicons-plus mphb-pricing-action mphb-pricing-add-period" title="' . esc_attr__( 'Add length of stay', 'motopress-hotel-booking' ) . '"></span>';
					$result .= '</td>';
				$result .= '</tr>';

				$result .= '<tr class="mphb-pricing-headers">';
					$result .= '<th colspan="2">' . esc_html__( 'Base Occupancy', 'motopress-hotel-booking' ) . '</th>';
					$result .= '<th class="mphb-pricing-price-per-night" colspan="' . count( $periods ) . '">' . esc_html__( 'Price per night', 'motopress-hotel-booking' ) . '</th>';
					$result .= '<th>&nbsp;</th>';
				$result .= '</tr>';

				// Render base prices
				$result .= '<tr class="mphb-prices-row mphb-base-prices-row">';
					$result .= '<td>';
						$result .= $this->renderAdults( '[base_adults]', $this->value['base_adults'] );
						$result .= '<span class="mphb-pricing-tiny-description">' . esc_html__( 'Adults', 'motopress-hotel-booking' ) . '</span>';
					$result .= '</td>';
					$result .= '<td>';
						$result .= $this->renderChildren( '[base_children]', $this->value['base_children'] );
						$result .= '<span class="mphb-pricing-tiny-description">' . esc_html__( 'Children', 'motopress-hotel-booking' ) . '</span>';
					$result .= '</td>';
					$result .= $this->renderPrices( '[prices]', $this->value['prices'], true );
					$result .= '<td>&nbsp;</td>';
				$result .= '</tr>';

				// Render prices for extra adults
				$result .= '<tr class="mphb-prices-row mphb-extra-adult-prices-row">';
					$result .= '<td colspan="2"><p>' . esc_html__( 'Price per extra adult', 'motopress-hotel-booking' ) . '</p></td>';
					$result .= $this->renderPrices( '[extra_adult_prices]', $this->value['extra_adult_prices'] );
					$result .= '<td>&nbsp;</td>';
				$result .= '</tr>';

				// Render prices for extra children
				$result .= '<tr class="mphb-prices-row mphb-extra-child-prices-row">';
					$result .= '<td colspan="2"><p>' . esc_html__( 'Price per extra child', 'motopress-hotel-booking' ) . '</p></td>';
					$result .= $this->renderPrices( '[extra_child_prices]', $this->value['extra_child_prices'] );
					$result .= '<td>&nbsp;</td>';
				$result .= '</tr>';

			$result .= '</tbody>';
		$result .= '</table>';

		return $result;
	}

	protected function renderCheckbox() {
		$result = '';

		$result .= '<input name="' . esc_attr( $this->getName() . '[enable_variations]' ) . '" value="0" type="hidden" />';
		$result .= '<label class="mphb-pricing-enable-variations-label">';
			$result .= '<input name="' . esc_attr( $this->getName() . '[enable_variations]' ) . '" value="1" type="checkbox" ' . checked( true, $this->value['enable_variations'], false ) . ' class="mphb-pricing-enable-variations" />';
			$result .= ' ' . esc_html__( 'Enable variable pricing', 'motopress-hotel-booking' );
		$result .= '</label>';

		return $result;
	}

	protected function renderVariations() {
		$result = '';

		$result .= '<input type="hidden" name="' . esc_attr( $this->getName() . '[variations]' ) . '" value="" />';
		$result .= '<table class="mphb-pricing-variations-table mphb-pricing-table widefat ' . ( ! $this->value['enable_variations'] ? 'mphb-hide' : '' ) . '">';

			$result .= '<thead class="mphb-pricing-headers">';
				$result .= '<th>' . esc_html__( 'Adults', 'motopress-hotel-booking' ) . '</th>';
				$result .= '<th>' . esc_html__( 'Children', 'motopress-hotel-boking' ) . '</th>';
				$result .= '<th class="mphb-pricing-price-per-night" colspan="' . esc_attr( count( $this->value['periods'] ) ) . '">';
					$result .= esc_html__( 'Price per night', 'motopress-hotel-booking' );
				$result .= '</th>';
				$result .= '<th>&nbsp;</th>';
			$result .= '</thead>';

			// Variations list
			$result .= '<tbody>';
				$result .= $this->generateTemplate();

				foreach ( $this->value['variations'] as $index => $variation ) {
					$result .= $this->generateVariation( $index, $variation );
				}
			$result .= '</tbody>';

			// "Add Variation" button
			$result .= '<tfoot>';
				$result .= '<tr>';
					$result .= '<td colspan="' . ( count( $this->value['periods'] ) + 3 ) . '">';
						$result .= '<button type="button" class="button mphb-pricing-add-variation">' . esc_html__( 'Add Variation', 'motopress-hotel-booking' ) . '</button>';
					$result .= '</td>';
				$result .= '</tr>';
			$result .= '</tfoot>';

		$result .= '</table>';

		return $result;
	}

	protected function generateTemplate() {
		$index  = '$index$';
		$prefix = '[variations][' . $index . ']';

		$result = '';

		$result .= '<tr class="mphb-pricing-variation-template mphb-hide" data-index="' . esc_attr( $index ) . '">';
			$result .= '<td>' . $this->renderAdults( $prefix . '[adults]', '', 'disabled="disabled"' ) . '</td>';
			$result .= '<td>' . $this->renderChildren( $prefix . '[children]', '', 'disabled="disabled"' ) . '</td>';

			for ( $i = 0, $count = count( $this->value['periods'] ); $i < $count; $i++ ) {
				$priceInput = $this->renderPrice( $prefix . '[prices][]', '', 'disabled="disabled"' );
				$result .= '<td data-period-index="' . esc_attr( $i ) . '">' . $priceInput . '</td>';
			}

			$result .= '<td>';
				$result .= '<span class="dashicons dashicons-trash mphb-pricing-action mphb-pricing-remove-variation" title="' . esc_attr__( 'Remove variation', 'motopress-hotel-booking' ) . '"></span>';
			$result .= '</td>';
		$result .= '</tr>';

		return $result;
	}

	protected function generateVariation( $index, $values ) {
		$prefix = '[variations][' . $index . ']';
		$result = '';

		$result .= '<tr data-index="' . esc_attr( $index ) . '">';
			$result .= '<td>' . $this->renderAdults( $prefix . '[adults]', $values['adults'] ) . '</td>';
			$result .= '<td>' . $this->renderChildren( $prefix . '[children]', $values['children'] ) . '</td>';
			$result .= $this->renderPrices( $prefix . '[prices]', $values['prices'] );
			$result .= '<td>';
				$result .= '<span class="dashicons dashicons-trash mphb-pricing-action mphb-pricing-remove-variation" title="' . esc_attr__( 'Remove variation', 'motopress-hotel-booking' ) . '"></span>';
			$result .= '</td>';
		$result .= '</tr>';

		return $result;
	}

	protected function renderPeriod( $name, $value, $atts = '', $class = '' ) {
		return '<input type="number" name="' . esc_attr( $this->getName() . $name ) . '" class="' . esc_attr( 'small-text ' . $class ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( self::MIN_PERIOD ) . '" step="1" ' . $atts . ' />';
	}

	/**
	 * @since 5.0.0
	 *
	 * @param string $inputName Examples: '[prices]', '[extra_adult_prices]'.
	 * @param array $prices
	 * @param bool $isRequireBasePrice Optional. False by default.
	 * @return string
	 */
	protected function renderPrices( $inputName, $prices, $isRequireBasePrice = false ) {
		$result = '';

		for ( $i = 0, $count = count( $this->value['periods'] ); $i < $count; $i++ ) {
			$isBasePrice = ( $i == 0 );
			$isRequired  = $isBasePrice && $isRequireBasePrice;
			$periodPrice = isset( $prices[ $i ] ) ? $prices[ $i ] : '';

			$result .= '<td data-period-index="' . esc_attr( $i ) . '"' . ( $isRequired ? ' class="mphb-required"' : '' ) . '>';
				if ( $isBasePrice ) {
					$result .= $this->renderPrice(
						"{$inputName}[]",
						$periodPrice,
						$isRequired ? 'required="required"' : ''
					);
				} else {
					$result .= $this->renderPrice( "{$inputName}[]", $periodPrice );
				}
			$result .= '</td>';
		}

		return $result;
	}

	protected function renderPrice( $name, $value, $atts = '', $class = '' ) {
		/**
		 * Use text field instead of number to increase the number of digits
		 * after decimal point.
		 *
		 * @see MB-639
		 */
		return '<input type="text" name="' . esc_attr( $this->getName() . $name ) . '" class="' . esc_attr( 'mphb-price-text ' . $class ) . '" value="' . esc_attr( $value ) . '" ' . $atts . ' />';
	}


	protected function renderAdults( $name, $value, $atts = '', $class = '' ) {

		$min = MPHB()->settings()->main()->getMinAdults();

		return '<input type="number" name="' . esc_attr( $this->getName() . $name ) . '" class="' . esc_attr( 'small-text ' . $class ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '" step="1" ' . $atts . ' />';
	}

	protected function renderChildren( $name, $value, $atts = '', $class = '' ) {

		$min = MPHB()->settings()->main()->getMinChildren();

		return '<input type="number" name="' . esc_attr( $this->getName() . $name ) . '" class="' . esc_attr( 'small-text ' . $class ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '" step="1" ' . $atts . ' />';
	}

	/**
	 * @return RoomType|null
	 */
	protected function getEditingRoomType() {
		$postId = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$typeId = ( $postId > 0 ) ? get_post_meta( $postId, 'mphb_room_type_id', true ) : '';

		if ( $typeId !== '' ) {
			return MPHB()->getRoomTypeRepository()->findById( $typeId );
		} else {
			return null;
		}
	}

	public function sanitize( $value ) {
		$pricing = $this->default;

		if ( isset( $value['periods'] ) ) {
			$pricing['periods'] = $this->sanitizePeriods( $value['periods'] );
		}

		if ( isset( $value['base_adults'] ) ) {
			$pricing['base_adults'] = $this->sanitizeAdults( $value['base_adults'] );
		}

		if ( isset( $value['base_children'] ) ) {
			$pricing['base_children'] = $this->sanitizeChildren( $value['base_children'] );
		}

		foreach ( [ 'prices', 'extra_adult_prices', 'extra_child_prices' ] as $pricesParameter ) {
			if ( isset( $value[ $pricesParameter ] ) ) {
				$pricing[ $pricesParameter ] = $this->sanitizePrices( $value[ $pricesParameter ] );
			}
		}

		if ( isset( $value['enable_variations'] ) ) {
			$pricing['enable_variations'] = $this->sanitizeEnableVariations( $value['enable_variations'] );
		}

		if ( ! empty( $value['variations'] ) ) {
			$pricing['variations'] = $this->sanitizeVariations( $value['variations'] );
		}

		$this->checkPricesCount( $pricing['prices'], $pricing['periods'] );
		$this->checkPricesCount( $pricing['extra_adult_prices'], $pricing['periods'] );
		$this->checkPricesCount( $pricing['extra_child_prices'], $pricing['periods'] );

		foreach ( $pricing['variations'] as &$variation ) {
			$this->checkPricesCount( $variation['prices'], $pricing['periods'] );
		}

		unset( $variation );

		if ( $pricing['prices'][0] === '' ) {
			$pricing['prices'][0] = self::MIN_PRICE;
		}

		return $pricing;
	}

	/**
	 * @param string[] $value
	 * @return int[]
	 */
	protected function sanitizePeriods( $value ) {
		$periods = $this->default['periods']; // [1]

		foreach ( $value as $period ) {
			$period = ParseUtils::parseInt( $period );

			if ( $period >= self::MIN_PERIOD ) {
				$periods[] = $period;
			}
		}

		return $periods;
	}

	/**
	 * @param string $value
	 * @return int
	 */
	protected function sanitizeAdults( $value ) {
		$minAdults = MPHB()->settings()->main()->getMinAdults();
		$maxAdults = MPHB()->settings()->main()->getSearchMaxAdults();

		return ParseUtils::parseInt( $value, $minAdults, $maxAdults );
	}

	/**
	 * @param string $value
	 * @return int
	 */
	protected function sanitizeChildren( $value ) {
		$minChildren = MPHB()->settings()->main()->getMinChildren();
		$maxChildren = MPHB()->settings()->main()->getSearchMaxChildren();

		return ParseUtils::parseInt( $value, $minChildren, $maxChildren );
	}

	/**
	 * @param string[] $value
	 * @return array '' is a valid value for a single price.
	 */
	protected function sanitizePrices( $value ) {
		$prices = array();

		foreach ( $value as $price ) {
			// Allow '' as a value
			if ( $price !== '' ) {
				$price = ValidateUtils::validateFloat( $price, self::MIN_PRICE );
			}

			if ( $price !== false || $price === '' ) {
				$prices[] = $price;
			}
		}

		return $prices;
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function sanitizeEnableVariations( $value ) {
		return ValidateUtils::validateBool( $value );
	}

	/**
	 * @param array $value
	 * @return array
	 */
	protected function sanitizeVariations( $value ) {
		$variations = array();

		// Use array_values() to reset numeric indexes
		foreach ( $value as $variation ) {
			$variations[] = array(
				'adults'   => $this->sanitizeAdults( $variation['adults'] ),
				'children' => $this->sanitizeChildren( $variation['children'] ),
				'prices'   => $this->sanitizePrices( $variation['prices'] ),
			);
		}

		return $variations;
	}

	/**
	 * Makes periods array and prices array equal by length.
	 *
	 * @param array $prices
	 * @param array $periods
	 */
	protected function checkPricesCount( &$prices, $periods ) {
		$pricesCount  = count( $prices );
		$periodsCount = count( $periods );

		if ( $pricesCount > $periodsCount ) {
			$prices = array_slice( $prices, 0, $periodsCount );
		} elseif ( $pricesCount < $periodsCount ) {
			$prices = array_merge( $prices, array_fill( $pricesCount, $periodsCount - $pricesCount, '' ) );
		}
	}

}
