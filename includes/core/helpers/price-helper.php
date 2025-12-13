<?php

namespace MPHB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PriceHelper {

	private function __construct() {}


	/**
	 * @param float  $price
	 * @param array  $atts
	 * @param string $atts['decimal_separator']
	 * @param string $atts['thousand_separator']
	 * @param int    $atts['decimals'] Number of decimals
	 * @param string $atts['currency_position'] Possible values: after, before, after_space, before_space
	 * @param string $atts['currency_symbol']
	 * @param bool   $atts['literal_free'] Use "Free" text instead of 0 price.
	 * @param bool   $atts['trim_zeros'] Trim decimals zeros.
	 * @return string
	 */
	public static function formatPrice( float $price, array $atts = array() ) {

		$defaultAtts = array(
			'decimal_separator'  => MPHB()->settings()->currency()->getPriceDecimalsSeparator(),
			'thousand_separator' => MPHB()->settings()->currency()->getPriceThousandSeparator(),
			'decimals'           => MPHB()->settings()->currency()->getPriceDecimalsCount(),
			'is_truncate_price'  => false,
			'currency_position'  => MPHB()->settings()->currency()->getCurrencyPosition(),
			'currency_symbol'    => MPHB()->settings()->currency()->getCurrencySymbol(),
			'literal_free'       => false,
			'trim_zeros'         => true,
			'period'             => false,
			'period_title'       => '',
			'period_nights'      => 1,
			'as_html'            => true,
		);

		$atts = wp_parse_args( $atts, $defaultAtts );

		$price_and_atts = apply_filters(
			'mphb_format_price_parameters',
			array(
				'price'      => $price,
				'attributes' => $atts,
			)
		);
		$price          = $price_and_atts['price'];
		$atts           = $price_and_atts['attributes'];

		if ( $atts['literal_free'] && $price == 0 ) {

			$formattedPrice = apply_filters( 'mphb_free_literal', _x( 'Free', 'Zero price', 'motopress-hotel-booking' ) );
			$priceClasses[] = 'mphb-price-free';

		} else {

			$negative = $price < 0;
			$price    = abs( $price );

			if ( $atts['is_truncate_price'] ) {

				$priceSuffix = '';

				if ( 900 > $price ) { // 0 - 900

					$price = number_format( $price, $atts['decimals'], $atts['decimal_separator'], $atts['thousand_separator'] );

				} elseif ( 900000 > $price ) { // 0.9k-850k

					$price       = number_format( $price / 1000, 1, $atts['decimal_separator'], $atts['thousand_separator'] );
					$priceSuffix = 'K';

				} elseif ( 900000000 > $price ) { // 0.9m-850m

					$price       = number_format( $price / 1000000, 1, $atts['decimal_separator'], $atts['thousand_separator'] );
					$priceSuffix = 'M';

				} elseif ( 900000000000 > $price ) { // 0.9b-850b

					$price       = number_format( $price / 1000000000, 1, $atts['decimal_separator'], $atts['thousand_separator'] );
					$priceSuffix = 'B';

				} else { // 0.9t+

					$price       = number_format( $price / 1000000000000, 1, $atts['decimal_separator'], $atts['thousand_separator'] );
					$priceSuffix = 'T';
				}

				if ( $atts['trim_zeros'] ) {
					$price = mphb_trim_zeros( $price );
				}

				$price = $price . $priceSuffix;

			} else {

				$price = number_format( $price, $atts['decimals'], $atts['decimal_separator'], $atts['thousand_separator'] );

				if ( $atts['trim_zeros'] ) {
					$price = mphb_trim_zeros( $price );
				}
			}

			$formattedPrice = ( $negative ? '-' : '' ) . $price;

			if ( ! empty( $atts['currency_symbol'] ) ) {

				$priceFormat    = MPHB()->settings()->currency()->getPriceFormat( $atts['currency_symbol'], $atts['currency_position'], $atts['as_html'] );
				$formattedPrice = ( $negative ? '-' : '' ) . sprintf( $priceFormat, $price );
			}
		}

		$priceClasses = array( 'mphb-price' );

		/**
		 * @since 3.9.8
		 *
		 * @param array $priceClasses
		 * @param float $price
		 * @param string $formattedPrice
		 * @param array $atts
		 */
		$priceClasses = apply_filters( 'mphb_price_classes', $priceClasses, $price, $formattedPrice, $atts );

		if ( $atts['as_html'] ) {

			$priceClassesStr = join( ' ', $priceClasses );
			$price           = sprintf( '<span class="%s">%s</span>', esc_attr( $priceClassesStr ), $formattedPrice );

		} else {
			$price = $formattedPrice;
		}

		if ( $atts['period'] ) {

			if ( 1 === $atts['period_nights'] ) {

				// translators: Price per one night. Example: $99 per night
				$priceDescription = _x( 'per night', 'Price per one night. Example: $99 per night', 'motopress-hotel-booking' );

			} else {

				/*
				* Translation will be used with numbers:
				*     21, 31, 41, 51, 61, 71, 81...
				*     2-4, 22-24, 32-34, 42-44, 52-54, 62...
				*     0, 5-19, 100, 1000, 10000...
				*/

				// translators: Price for X nights. Example: $99 for 2 nights, $99 for 21 nights
				$priceDescription = _nx(
					'for %d nights',
					'for %d nights',
					$atts['period_nights'],
					'Price for X nights. Example: $99 for 2 nights, $99 for 21 nights',
					'motopress-hotel-booking'
				);
			}

			$priceDescription = sprintf( $priceDescription, $atts['period_nights'] );
			$priceDescription = apply_filters( 'mphb_price_period_description', $priceDescription, $atts['period_nights'] );

			if ( $atts['as_html'] ) {
				$priceDescription = sprintf( '<span class="mphb-price-period" title="%1$s">%2$s</span>', esc_attr( $atts['period_title'] ), $priceDescription );
			}

			$price = sprintf( '%1$s %2$s', $price, $priceDescription );
		}

		return $price;
	}
}
