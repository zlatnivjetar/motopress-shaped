<?php
/**
 * @package MPHB\Advanced\Api
 * @since 4.1.0
 */

namespace MPHB\Advanced\Api\Data;

use MPHB\Entities\Season;
use MPHB\PostTypes\SeasonCPT;

class SeasonData extends AbstractPostData {

	const DAYS_VOCABULARY = array(
		'0' => 'sunday',
		'1' => 'monday',
		'2' => 'tuesday',
		'3' => 'wednesday',
		'4' => 'thursday',
		'5' => 'friday',
		'6' => 'saturday',
	);

	/**
	 * @var Season
	 */
	public $entity;

	/**
	 * @var string
	 */
	private $dateFormat;

	public function __construct( $entity ) {
		parent::__construct( $entity );
		$this->dateFormat = MPHB()->settings()->dateTime()->getDateTransferFormat();
	}

	public static function getRepository() {
		return MPHB()->getSeasonRepository();
	}

	public static function getProperties() {
		return array(
			'id'                => array(
				'description' => 'Unique identifier for the resource.',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit', 'embed' ),
				'readonly'    => true,
			),
			'title'             => array(
				'description' => 'Title.',
				'type'        => 'string',
				'context'     => array( 'view', 'edit', 'embed' ),
				'required'    => true,
			),
			'description'       => array(
				'description' => 'Description.',
				'type'        => 'string',
				'context'     => array( 'view', 'edit', 'embed' ),
			),
			'start_date'        => array(
				'description' => 'Start date.',
				'type'        => 'string',
				'format'      => 'date',
				'context'     => array( 'view', 'edit', 'embed' ),
				'required'    => true,
			),
			'end_date'          => array(
				'description' => 'End date.',
				'type'        => 'string',
				'format'      => 'date',
				'context'     => array( 'view', 'edit', 'embed' ),
				'required'    => true,
			),
			'days'              => array(
				'description' => 'Days.',
				'type'        => 'array',
				'context'     => array( 'view', 'edit', 'embed' ),
				'items'       => array(
					'type' => 'string',
					'enum' => self::DAYS_VOCABULARY,
				),
				'required'    => true,
			),
			'dates'             => array(
				'description' => 'Dates.',
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type'   => 'string',
					'format' => 'date',
				),
			),
			'repeat_period'     => array(
				'description' => 'Repeat period.',
				'type'        => 'string',
				'enum'        => array(
					SeasonCPT::REPEAT_PERIOD_NONE,
					SeasonCPT::REPEAT_PERIOD_YEAR,
				),
				'context'     => array( 'view', 'edit', 'embed' ),
			),
			'repeat_until_date' => array(
				'description' => 'Repeat until date.',
				'type'        => 'string',
				'format'      => 'date',
				'context'     => array( 'view', 'edit', 'embed' ),
			),
		);
	}

	protected function getStartDate() {
		$startDate = $this->entity->getStartDate();
		if ( ! is_a( $startDate, 'DateTime' ) ) {
			return null;
		}

		return $startDate->format( $this->dateFormat );
	}

	protected function setStartDate( $date ) {
		$this->start_date = $date;
	}

	protected function getEndDate() {
		$endDate = $this->entity->getEndDate();
		if ( ! is_a( $endDate, 'DateTime' ) ) {
			return null;
		}

		return $endDate->format( $this->dateFormat );
	}

	protected function setEndDate( $date ) {
		$this->end_date = $date;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return string|null
	 */
	protected function getRepeatUntilDate() {
		$repeatUntilDate = $this->entity->getRepeatUntilDate();

		if ( ! is_a( $repeatUntilDate, 'DateTime' ) ) {
			return null;
		}

		return $repeatUntilDate->format( $this->dateFormat );
	}

	/**
	 * @since 5.0.0
	 *
	 * @param string $date
	 */
	protected function setRepeatUntilDate( $date ) {
		$this->repeat_until_date = $date;
	}

	protected function getDays() {
		if ( isset( $this->days ) ) {
			return $this->days;
		}
		$days       = array();
		$dayIndexes = $this->entity->getDays();
		foreach ( $dayIndexes as $dayIndex ) {
			$days[] = self::DAYS_VOCABULARY[ $dayIndex ];
		}

		return $days;
	}

	private function getDaysIndexFormat() {
		$daysIndexFormat = array();
		$dayIndexes      = array_flip( self::DAYS_VOCABULARY );
		$days            = $this->getDays();
		if ( ! count( $days ) ) {
			return $daysIndexFormat;
		}
		foreach ( $days as $day ) {
			$daysIndexFormat[] = $dayIndexes[ $day ];
		}
		$daysIndexFormat = array_unique( $daysIndexFormat );
		sort( $daysIndexFormat, SORT_NUMERIC );

		return $daysIndexFormat;
	}

	protected function getDates() {
		$dates = $this->entity->getDates();
		foreach ( $dates as $key => $date ) {
			$dates[ $key ] = $date->format( $this->dateFormat );
		}

		return array_values( $dates );
	}

	private function setDataToEntity() {
		$atts   = array(
			'id' => $this->id,
		);
		$fields = static::getWritableFieldKeys();
		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'start_date':
				case 'end_date':
					$atts[ $field ] = \DateTime::createFromFormat( $this->dateFormat, $this->{$field} );
					break;
				case 'days':
					$atts[ $field ] = $this->getDaysIndexFormat();
					break;
				case 'repeat_until_date':
					if ( ! empty( $this->{$field} ) ) {
						$atts[ $field ] = \DateTime::createFromFormat( $this->dateFormat, $this->{$field} );
					}
					break;
				default:
					$atts[ $field ] = $this->{$field};
			}
		}
		$this->entity = new Season( $atts );
	}

	public function save() {
		$this->setDataToEntity();
		parent::save();
	}
}
