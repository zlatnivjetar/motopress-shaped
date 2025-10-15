<?php

namespace MPHB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This facade must contain all methods for working with rates
 * and prices that are called from outside the core (from templates,
 * shortcodes, Gutenberg blocks, Ajax commands, REST API controllers,
 * other plugins and themes).
 */
class PricesCoreAPIFacade extends AbstractCoreAPIFacade {


	protected function getHookNamesForClearAllCache(): array {
		return array(
			'save_post_' . MPHB()->postTypes()->room()->getPostType(),
			'save_post_' . MPHB()->postTypes()->roomType()->getPostType(),
			'save_post_' . MPHB()->postTypes()->rate()->getPostType(),
			'save_post_' . MPHB()->postTypes()->season()->getPostType(),
			'update_option_mphb_min_stay_length',
			'update_option_mphb_max_stay_length',
			'update_option_mphb_do_not_apply_booking_rules_for_admin',
		);
	}

	/**
	 * RATES SEARCH
	 */

	/**
	 * @param string $languageCode = 'current' or some language code.
	 *                               If empty then returns rate without translation.
	 * @return \MPHB\Entities\Rate|null
	 */
	public function getRateById( int $rateId, string $languageCode = '' ) {

		if ( ! empty( $languageCode ) ) {

			if ( 'current' === $languageCode ) {

				$languageCode = '';
			}

			$rateId = apply_filters( '_mphb_translate_post_id', $rateId );
		}

		$rate = MPHB()->getRateRepository()->findById( $rateId );

		return apply_filters( 'mphb_get_rate_by_id', $rate, $rateId, $languageCode );
	}

	/**
	 * @return array [ date (string Y-m-d), ... ]
	 */
	public function getDatesWithRatesByRoomTypeId( int $roomTypeOriginalId ) {

		$cacheDataId = 'getDatesWithRatesByRoomTypeId' . $roomTypeOriginalId;
		$result      = $this->getCachedData( $cacheDataId );

		if ( static::CACHED_DATA_NOT_FOUND === $result ) {

			$rates = $this->getActiveRatesByRoomTypeId( $roomTypeOriginalId );

			$result = array();

			foreach ( $rates as $rate ) {

				$result = array_merge( $result, array_keys( $rate->getDatePrices() ) );
			}

			$result = apply_filters( 'mphb_get_dates_rates_for_room_type', $result, $roomTypeOriginalId );

			$this->setCachedData( $cacheDataId, '', $result );
		}

		return $result;
	}

	/**
	 * @return \MPHB\Entities\Rate[]
	 */
	public function getActiveRatesByRoomTypeId( int $roomTypeOriginalId ) {

		$cacheDataId = 'getActiveRatesByRoomTypeId' . $roomTypeOriginalId;
		$result      = $this->getCachedData( $cacheDataId );

		if ( static::CACHED_DATA_NOT_FOUND === $result ) {

			$result = MPHB()->getRateRepository()->findAllActiveByRoomType( $roomTypeOriginalId );

			$result = apply_filters( 'mphb_get_room_type_active_rates', $result, $roomTypeOriginalId );

			$this->setCachedData( $cacheDataId, '', $result );
		}

		return $result;
	}

	/**
	 * @return \MPHB\Entities\Rate[]
	 */
	public function getActiveRates( int $roomTypeIdOnAnyLanguage, \DateTime $startDate, \DateTime $endDate, bool $isGetOnDefaultLanguage = true ) {

		$rateArgs = array(
			'check_in_date'  => $startDate,
			'check_out_date' => $endDate,
			'mphb_language'  => 'original',
		);

		if ( $isGetOnDefaultLanguage ) {

			$rateArgs['mphb_language'] = 'original';
		}

		$rates =  MPHB()->getRateRepository()->findAllActiveByRoomType(
			$roomTypeIdOnAnyLanguage,
			$rateArgs
		);

		return apply_filters( 'mphb_get_active_rates', $rates, $roomTypeIdOnAnyLanguage, $startDate, $endDate, $isGetOnDefaultLanguage );
	}


	public function isRoomTypeHasActiveRate( int $roomTypeIdOnAnyLanguage, \DateTime $startDate, \DateTime $endDate ): bool {

		$isRoomTypeHasActiveRate =  MPHB()->getRateRepository()->isExistsForRoomType(
			$roomTypeIdOnAnyLanguage,
			array(
				'check_in_date'  => $startDate,
				'check_out_date' => $endDate,
			)
		);

		return apply_filters( 'mphb_is_room_type_has_active_rates', $isRoomTypeHasActiveRate, $roomTypeIdOnAnyLanguage, $startDate, $endDate );
	}

	/**
	 * @return \MPHB\Entities\Rate
	 */
	public function duplicateRate( \MPHB\Entities\Rate $rate ) {
		return MPHB()->getRateRepository()->duplicate( $rate );
	}

	/**
	 * @param \MPHB\Entities\Rate $rate Pass the rate by reference, otherwise
	 *     you'll get <code>null</code> instead of ID when creating a rate via
	 *     the REST API. (See task [MPI-11948])
	 */
	public function saveRate( \MPHB\Entities\Rate &$rate ) {
		return MPHB()->getRateRepository()->save( $rate );
	}

	/**
	 * PRICES CALCULATION
	 */

	/**
	 * @return float room type minimal price for min days stay with taxes and fees
	 * @throws Exception if booking is not allowed for given date
	 */
	public function getRoomTypeMinBasePriceForDate( int $roomTypeOriginalId, \DateTime $startDate ) {

		return mphb_get_room_type_base_price( $roomTypeOriginalId, $startDate, $startDate );
	}

	/**
	 * @param array $atts with:
	 * 'decimal_separator' => string,
	 * 'thousand_separator' => string,
	 * 'decimals' => int, Number of decimals
	 * 'is_truncate_price' => bool, false by default
	 * 'currency_position' => string, Possible values: after, before, after_space, before_space
	 * 'currency_symbol' => string,
	 * 'literal_free' => bool, Use "Free" text instead of 0 price.
	 * 'trim_zeros' => bool, true by default
	 * 'period' => bool,
	 * 'period_title' => '',
	 * 'period_nights' => 1,
	 * 'as_html' => bool, true by default
	 */
	public function formatPrice( float $price, array $atts = array() ) {
		return PriceHelper::formatPrice( $price, $atts );
	}
}
