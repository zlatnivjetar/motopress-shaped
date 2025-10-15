<?php

namespace MPHB\Admin\ManageCPTPages;

use MPHB\PostTypes\SeasonCPT;
use MPHB\Utils\DateUtils;

class SeasonManageCPTPage extends ManageCPTPage {

	public function __construct( $postType, $atts = array() ) {
		parent::__construct( $postType, $atts );

		$this->description = __( 'Seasons are real periods of time, dates or days that come with different prices for accommodations. E.g. Winter 2018 ($120 per night), Christmas ($150 per night).', 'motopress-hotel-booking' );

		add_filter( 'request', array( $this, 'filterCustomOrderBy' ) );
	}

	public function filterColumns( $columns ) {
		$customColumns = array(
			'start_date'    => esc_html__( 'Start', 'motopress-hotel-booking' ),
			'end_date'      => esc_html__( 'End', 'motopress-hotel-booking' ),
			'days'          => esc_html__( 'Days', 'motopress-hotel-booking' ),
			'repeat_period' => esc_html__( 'Repeat', 'motopress-hotel-booking' ),
		);
		$offset        = array_search( 'date', array_keys( $columns ) ); // Set custom columns position before "DATE" column
		$columns       = array_slice( $columns, 0, $offset, true ) + $customColumns + array_slice( $columns, $offset, count( $columns ) - 1, true );

		unset( $columns['date'] );

		return $columns;
	}

	public function filterSortableColumns( $columns ) {
		$columns['start_date'] = 'mphb_start_date';
		$columns['end_date'] = 'mphb_end_date';

		return $columns;
	}

	/**
	 * @since 5.0.0
	 *
	 * @access protected
	 *
	 * @param array $vars
	 * @return array
	 */
	public function filterCustomOrderBy( $vars ) {
		if ( ! $this->isCurrentPage() ) {
			return $vars;
		}

		if ( empty( $vars['orderby'] ) ) {
			// Default sort order
			$vars['meta_key'] = 'mphb_start_date';
			$vars['orderby']  = 'meta_value';
			$vars['order']    = 'DESC';

		} elseif ( $vars['orderby'] == 'mphb_start_date' || $vars['orderby'] == 'mphb_end_date' ) {
			$vars['meta_key'] = $vars['orderby'];
			$vars['orderby']  = 'meta_value';
		}

		return $vars;
	}

	public function renderColumns( $column, $postId ) {
		$season = MPHB()->getSeasonRepository()->findById( $postId );
		switch ( $column ) {
			case 'start_date':
				$startDate = $season->getStartDate();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $startDate ? \MPHB\Utils\DateUtils::formatDateWPFront( $startDate ) : static::EMPTY_VALUE_PLACEHOLDER;
				break;
			case 'end_date':
				$endDate = $season->getEndDate();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $endDate ? \MPHB\Utils\DateUtils::formatDateWPFront( $endDate ) : static::EMPTY_VALUE_PLACEHOLDER;
				break;
			case 'days':
				$days = $season->getDays();
				if ( empty( $days ) ) {
					esc_html_e( 'None', 'motopress-hotel-booking' );
				} elseif ( count( $days ) === 7 ) {
					esc_html_e( 'All', 'motopress-hotel-booking' );
				} else {
					echo esc_html( join( ', ', array_map( array( '\MPHB\Utils\DateUtils', 'getDayByKey' ), $days ) ) );
				}
				break;
			case 'repeat_period':
				switch ( $season->getRepeatPeriod() ) {
					case SeasonCPT::REPEAT_PERIOD_YEAR:
						if ( is_null( $season->getRepeatUntilDate() ) ) {
							esc_html_e( 'Annually', 'motopress-hotel-booking' );
						} else {
							echo sprintf(
								// translators: %s: A date string such as "December 31, 2025".
								esc_html__( 'Annually until %s', 'motopress-hotel-booking' ),
								DateUtils::formatDateWPFront( $season->getRepeatUntilDate() )
							);
						}
						break;

					default:
						echo esc_html( self::EMPTY_VALUE_PLACEHOLDER );
						break;
				}
				break;
		}
	}

}
