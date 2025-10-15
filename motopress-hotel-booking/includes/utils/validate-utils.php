<?php

namespace MPHB\Utils;

class ValidateUtils {

	/**
	 * @since 5.0.0
	 *
	 * @param mixed $value
	 * @param float|null $min
	 * @param float|null $max
	 * @return float|false
	 */
	public static function validateFloat( $value, $min = null, $max = null ) {
		$options = array();

		if ( ! is_null( $min ) ) {
			$options['min_range'] = $min;
		}

		if ( ! is_null( $max ) ) {
			$options['max_range'] = $max;
		}

		if ( ! empty( $options ) ) {
			return filter_var( $value, FILTER_VALIDATE_FLOAT, array( 'options' => $options ) );
		} else {
			return filter_var( $value, FILTER_VALIDATE_FLOAT );
		}
	}

	/**
	 *
	 * @param mixed $value
	 * @param int   $min Optional.
	 * @param int   $max Optional.
	 * @return int|false Validated number or FALSE if the filter fails.
	 */
	public static function validateInt( $value, $min = null, $max = null ) {
		$options = array();

		if ( isset( $min ) ) {
			$options['min_range'] = $min;
		}

		if ( isset( $max ) ) {
			$options['max_range'] = $max;
		}

		if ( ! empty( $options ) ) {
			$options = array(
				'options' => $options,
			);
		}

		return ! empty( $options ) ? filter_var( $value, FILTER_VALIDATE_INT, $options ) : filter_var( $value, FILTER_VALIDATE_INT );
	}

	/**
	 * @deprecated 5.0.0
	 *
	 * @see ParseUtils::parseInt()
	 */
	public static function parseInt( $value, $min = null, $max = null ) {
		return ParseUtils::parseInt( $value, $min, $max );
	}

	/**
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function validateBool( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * @param string $value
	 *
	 * @return int[]
	 */
	public static function validateCommaSeparatedIds( $value ) {
		$values = explode( ',', $value );
		return self::validateIds( $values );
	}

	/**
	 * @param array $values
	 *
	 * @return int[]
	 */
	public static function validateIds( $values ) {
		$ids = array();

		foreach ( $values as $id ) {
			$ids[] = self::validateInt( $id, 0 );
		}

		$ids = array_filter( $ids );

		return $ids;
	}

	public static function validateRelation( $value ) {
		$value = strtoupper( $value );
		return ( $value == 'OR' || $value == 'AND' ? $value : 'OR' );
	}

	public static function validateOrder( $value ) {
		$value = strtoupper( $value );
		return ( $value == 'DESC' || $value == 'ASC' ? $value : 'DESC' );
	}

	/**
	 *
	 * @param bool $value
	 * @return bool
	 */
	public static function isNotEqualFalse( $value ) {
		return $value !== false;
	}

	/**
	 * @param mixed $value
	 * @return int|false
	 *
	 * @since 3.8.3
	 */
	public static function validateAdults( $value ) {
		$minAdults = MPHB()->settings()->main()->getMinAdults();
		$maxAdults = MPHB()->settings()->main()->getSearchMaxAdults();

		return self::validateInt( $value, $minAdults, $maxAdults );
	}

	/**
	 * @param mixed $value
	 * @return int|false
	 *
	 * @since 3.8.3
	 */
	public static function validateChildren( $value ) {
		$minChildren = MPHB()->settings()->main()->getMinChildren();
		$maxChildren = MPHB()->settings()->main()->getSearchMaxChildren();

		return self::validateInt( $value, $minChildren, $maxChildren );
	}

}
