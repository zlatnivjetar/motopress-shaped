<?php

namespace MPHB\Entities;

use MPHB\Utils\DateUtils;

class Rate {

	/**
	 *
	 * @var int
	 */
	private $id;

	/**
	 *
	 * @var int
	 */
	private $originalId;

	/**
	 *
	 * @var string
	 */
	private $title;

	/**
	 *
	 * @var string
	 */
	private $description;

	/**
	 *
	 * @var int
	 */
	private $roomTypeId;

	/**
	 *
	 * @var SeasonPrice[]
	 */
	private $seasonPrices;

	/**
	 *
	 * @var bool
	 */
	private $isActive = false;

	/**
	 * Available dates with prices calculated based on MPHB()->reservationRequest().
	 * Get this field with $this->getDatePrices() method only to make sure we do not
	 * break the PriceLabs integration!
	 *
	 * @var array [ date ('Y-m-d') => price (float) ]
	 */
	private $datePrices = array();

	/**
	 *
	 * @param array         $atts Array of atts
	 * @param int           $atts['id'] Id of rate
	 * @param string        $atts['title'] Title of rate
	 * @param string        $atts['description'] Description of rate
	 * @param int           $atts['room_type_id'] Room Type ID
	 * @param SeasonPrice[] $atts['season_prices'] Array of Season Prices.
	 * @param bool          $atts['active'] Is rate available for user choosing.
	 */
	function __construct( array $atts ) {

		$this->id           = $atts['id'];
		$this->originalId   = MPHB()->translation()->getOriginalId( $this->id, MPHB()->postTypes()->rate()->getPostType() );
		$this->title        = $atts['title'];
		$this->description  = $atts['description'];
		$this->roomTypeId   = (int) $atts['room_type_id'];
		$this->seasonPrices = array_reverse( $atts['season_prices'] );
		$this->isActive     = $atts['active'];
	}

	/**
	 *
	 * @return int Id of rate
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function getOriginalId() {
		return $this->originalId;
	}

	/**
	 *
	 * @return string Title
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 *
	 * @return string Description
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 *
	 * @return SeasonPrice[] Array of season prices.
	 */
	public function getSeasonPrices() {
		return $this->seasonPrices;
	}

	/**
	 * @return int original room type id
	 */
	public function getRoomTypeId() {
		return $this->roomTypeId;
	}

	/**
	 * @return bool
	 */
	public function isAvailableForDates( \DateTime $dateFrom, \DateTime $dateTo, $isIncludeLastDate = false ) {

		$checkingDate = clone $dateFrom;
		$checkingDate->setTime( 0, 0, 0 );

		$dateTo = clone $dateTo;
		$dateTo->setTime( 0, 0, 0 );

		if ( $isIncludeLastDate ) {
			$dateTo = $dateTo->modify( '+1 day' );
		}

		while ( $checkingDate < $dateTo ) {

			$checkingDateString = DateUtils::formatDateDB( $checkingDate );

			if ( ! array_key_exists( $checkingDateString, $this->getDatePrices() ) ) {
				return false;
			}

			$checkingDate = $checkingDate->modify( '+1 day' );
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function isExistsFrom( \DateTime $fromDate ) {
		$fromDateString = DateUtils::formatDateDB( $fromDate );

		foreach ( array_keys( $this->getDatePrices() ) as $date ) {
			if ( $date > $fromDateString ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array [ date (string Y-m-d) => price (float) ]
	 */
	public function getDatePrices() {

		if ( empty( $this->datePrices ) ) {

			// Note: $seasonPrices are in reverse order (see array_reverse())
			foreach ( $this->seasonPrices as $seasonPrice ) {
				$this->datePrices = array_merge( $this->datePrices, $seasonPrice->getDatePrices() );
			}
		}

		return $this->datePrices;
	}

	/**
	 *
	 * @return bool
	 */
	public function isActive() {
		return $this->isActive;
	}

	/**
	 *
	 * @return Season[]
	 */
	public function getSeasons() {
		$seasons = array_map(
			function( SeasonPrice $seasonPrice ) {
				return $seasonPrice->getSeason();
			},
			$this->seasonPrices
		);
		return array_filter( $seasons );
	}

	/**
	 * @param \DateTime|null $fromDate
	 * @param \DateTime|null $toDate
	 * @return float
	 */
	public function getMinBasePrice( $fromDate = null, $toDate = null ) {

		$useFilter = false;

		if ( $fromDate && is_a( $fromDate, '\DateTime' ) ) {
			$useFilter = true;
			$fromDate  = $fromDate->format( 'Y-m-d' );
		}

		if ( $toDate && is_a( $toDate, '\DateTime' ) ) {
			$useFilter = true;
			$toDate    = $toDate->format( 'Y-m-d' );
		}

		$datePrices = array();

		if ( $useFilter ) {
			foreach ( $this->getDatePrices() as $date => $price ) {

				if ( $fromDate && $date < $fromDate ) {
					continue;
				}

				if ( $toDate && $date > $toDate ) {
					continue;
				}

				$datePrices[ $date ] = $price;
			}
		} else {
			$datePrices = $this->getDatePrices();
		}

		return ! empty( $datePrices ) ? min( $datePrices ) : 0.0;
	}

	/**
	 * @param \DateTime $checkInDate
	 * @param \DateTime $checkOutDate
	 * @return float
	 *
	 * @since 3.5.0 removed optional parameter $occupancyParams.
	 */
	public function calcPrice( \DateTime $checkInDate, \DateTime $checkOutDate ) {
		return (float) array_sum( $this->getPriceBreakdown( $checkInDate, $checkOutDate ) );
	}

	/**
	 * @param string $checkInDate date in format 'Y-m-d'
	 * @param string $checkOutDate date in format 'Y-m-d'
	 * @return array Array where keys are dates and values are prices
	 *
	 * @since 3.5.0 removed optional parameter $occupancyParams.
	 */
	public function getPriceBreakdown( $checkInDate, $checkOutDate ) {

		$prices = array();

		// we need to recalculate prices each time for price breakdown MPI-11939
		$this->datePrices = array();
		$datePrices = $this->getDatePrices();

		foreach ( DateUtils::createDatePeriod( $checkInDate, $checkOutDate ) as $date ) {
			$dateDB = DateUtils::formatDateDB( $date );
			if ( array_key_exists( $dateDB, $datePrices ) ) {
				$prices[ $dateDB ] = $datePrices[ $dateDB ];
			}
		}

		return $prices;
	}

}
