<?php

namespace MPHB\Entities;

use MPHB\Utils\DateUtils;
use DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 5.0.0
 */
class RecurrentSeason extends Season {
	/**
	 * @var array [
	 *     [
	 *         start_date => DateTime,
	 *         end_date   => DateTime,
	 *     ],
	 *     ...
	 * ]
	 */
	private $periods = [];

	protected function setupDates() {

		if ( is_null( $this->startDate ) || is_null( $this->endDate ) ) {
			return;
		}

		$this->startDate->setTime( 0, 0 );
		$this->endDate->setTime( 23, 59, 59, 999 );

		$startDate = clone $this->startDate;
		$endDate   = clone $this->endDate;

		// Limit the period to 1 year/12 months
		$startDateAfterYear = DateUtils::cloneModify( $startDate, '+1 year' );

		if ( $endDate > $startDateAfterYear ) {
			$endDate = $startDateAfterYear;
		}

		// Limit the max date to +1 year from the current date
		// or $repeatUntilDate
		$maxDate = new DateTime( '+1 year', DateUtils::getSiteTimeZone() );

		if ( ! is_null( $this->repeatUntilDate ) && $this->repeatUntilDate < $maxDate ) {
			$maxDate = clone $this->repeatUntilDate;
		}

		// Generate periods
		$periodsCount = (int) $maxDate->format( 'Y' ) - (int) $startDate->format( 'Y' ) + 1;
		$periodsCount = max( 0, $periodsCount );

		for ( $i = 1; $i <= $periodsCount; $i++ ) {
			$this->addDatesForPeriod( $startDate, $endDate );

			if ( $endDate == $maxDate ) {
				break;
			}

			$startDate->modify( '+1 year' );
			$endDate->modify( '+1 year' );

			if ( $startDate > $maxDate) {
				break;
			}

			if ( $endDate > $maxDate ) {
				$endDate = clone $maxDate;
			}
		}
	}

	protected function addDatesForPeriod( DateTime $startDate, DateTime $endDate ) {

		parent::addDatesForPeriod( $startDate, $endDate );

		// Save current period
		$this->periods[] = [
			'start_date' => clone $startDate,
			'end_date'   => clone $endDate,
		];
	}

	/**
	 * @param DateTime $date
	 * @return bool
	 */
	public function isDateInSeason( $date ) {
		return $this->isDateInPeriods( $date ) && $this->isAllowedWeekDay( $date );
	}

	/**
	 * @return bool
	 */
	private function isDateInPeriods( DateTime $date ) {
		foreach ( $this->periods as $period ) {
			if ( $date >= $period['start_date'] && $date <= $period['end_date'] ) {
				return true;
			}
		}

		return false;
	}
}
