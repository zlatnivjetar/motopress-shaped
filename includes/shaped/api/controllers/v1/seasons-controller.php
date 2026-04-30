<?php
/**
 * Shaped dashboard seasons controller.
 *
 * @package MPHB\Shaped\Api
 */

namespace MPHB\Shaped\Api\Controllers\V1;

use MPHB\Advanced\Api\ApiHelper;
use MPHB\Entities\RecurrentSeason;
use MPHB\Entities\Season;
use MPHB\PostTypes\SeasonCPT;
use MPHB\Shaped\Api\Authentication;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class SeasonsController extends WP_REST_Controller {

	protected $namespace = 'shaped/v1';
	protected $rest_base  = 'dashboard/seasons';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => 'Unique identifier for the resource.',
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	public function get_items_permissions_check( $request ) {
		return Authentication::authorize( $request, 'read' );
	}

	public function create_item_permissions_check( $request ) {
		return Authentication::authorize( $request, 'create' );
	}

	public function update_item_permissions_check( $request ) {
		$season = $this->get_season_entity( (int) $request['id'] );

		if ( ! $season ) {
			return true;
		}

		return Authentication::authorize( $request, 'edit', $season->getId() );
	}

	public function get_items( $request ) {
		$seasons = MPHB()->getSeasonRepository()->findAll(
			array(
				'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
			)
		);

		usort(
			$seasons,
			function( $left, $right ) {
				$leftStart  = $left->getStartDate() ? $left->getStartDate()->format( 'Y-m-d' ) : '';
				$rightStart = $right->getStartDate() ? $right->getStartDate()->format( 'Y-m-d' ) : '';

				if ( $leftStart === $rightStart ) {
					return strcasecmp( $left->getTitle(), $right->getTitle() );
				}

				return strcmp( $leftStart, $rightStart );
			}
		);

		$usageMap = $this->get_rate_usage_map();
		$items    = array();

		foreach ( $seasons as $season ) {
			$items[] = $this->format_season( $season, $usageMap );
		}

		return rest_ensure_response(
			array(
				'seasons'      => $items,
				'generated_at' => current_time( 'c' ),
			)
		);
	}

	public function create_item( $request ) {
		$validated = $this->validate_request( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$season = $this->build_entity_from_payload( $validated, null, true );
		$seasonId = MPHB()->getSeasonRepository()->save( $season );
		if ( ! $seasonId ) {
			return new WP_Error( 'shaped_season_save_failed', 'The season could not be saved.', array( 'status' => 400 ) );
		}

		$savedSeason = $this->get_season_entity( $seasonId, true );
		if ( ! $savedSeason ) {
			return new WP_Error( 'shaped_season_save_failed', 'The season could not be saved.', array( 'status' => 400 ) );
		}

		return $this->respond_with_season( $savedSeason, 201 );
	}

	public function update_item( $request ) {
		$season = $this->get_season_entity( (int) $request['id'], true );
		if ( ! $season ) {
			return new WP_Error( 'shaped_season_not_found', 'Season not found.', array( 'status' => 404 ) );
		}

		$validated = $this->validate_request( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$updatedSeason = $this->build_entity_from_payload( $validated, $season, false );
		$seasonId      = MPHB()->getSeasonRepository()->save( $updatedSeason );
		if ( ! $seasonId ) {
			return new WP_Error( 'shaped_season_save_failed', 'The season could not be saved.', array( 'status' => 400 ) );
		}

		$savedSeason = $this->get_season_entity( $seasonId, true );
		if ( ! $savedSeason ) {
			return new WP_Error( 'shaped_season_save_failed', 'The season could not be saved.', array( 'status' => 400 ) );
		}

		return $this->respond_with_season( $savedSeason, 200 );
	}

	private function respond_with_season( $season, $status ) {
		$response = rest_ensure_response(
			array(
				'season'     => $this->format_season( $season, $this->get_rate_usage_map() ),
				'updated_at' => current_time( 'c' ),
			)
		);
		$response->set_status( $status );

		return $response;
	}

	private function validate_request( WP_REST_Request $request ) {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( '' === trim( $title ) ) {
			return new WP_Error( 'shaped_season_invalid_title', 'Title is required.', array( 'status' => 400 ) );
		}

		$startDate = $this->validate_date( $request->get_param( 'start_date' ), 'start_date' );
		if ( is_wp_error( $startDate ) ) {
			return $startDate;
		}

		$endDate = $this->validate_date( $request->get_param( 'end_date' ), 'end_date' );
		if ( is_wp_error( $endDate ) ) {
			return $endDate;
		}

		if ( strcmp( $startDate->format( 'Y-m-d' ), $endDate->format( 'Y-m-d' ) ) > 0 ) {
			return new WP_Error( 'shaped_season_invalid_range', 'Start date must be earlier than or equal to end date.', array( 'status' => 400 ) );
		}

		$days = $this->validate_days( $request->get_param( 'days' ) );
		if ( is_wp_error( $days ) ) {
			return $days;
		}

		$repeatPeriod = $request->get_param( 'repeat_period' );
		if ( null === $repeatPeriod || '' === $repeatPeriod ) {
			$repeatPeriod = SeasonCPT::REPEAT_PERIOD_DEFAULT;
		}

		if ( ! in_array( $repeatPeriod, array( SeasonCPT::REPEAT_PERIOD_NONE, SeasonCPT::REPEAT_PERIOD_YEAR ), true ) ) {
			return new WP_Error( 'shaped_season_invalid_repeat', 'Repeat period must be either none or year.', array( 'status' => 400 ) );
		}

		return array(
			'title'         => $title,
			'start_date'    => $startDate,
			'end_date'      => $endDate,
			'days'          => $days,
			'repeat_period' => $repeatPeriod,
		);
	}

	private function validate_date( $value, $field ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return new WP_Error( 'shaped_season_invalid_date', sprintf( '%s is required.', $field ), array( 'status' => 400 ) );
		}

		try {
			$date = ApiHelper::prepareDateRequest( $value );
		} catch ( \Exception $e ) {
			return new WP_Error( 'shaped_season_invalid_date', sprintf( '%s must be formatted as YYYY-MM-DD.', $field ), array( 'status' => 400 ) );
		}

		if ( $date->format( 'Y-m-d' ) !== $value ) {
			return new WP_Error( 'shaped_season_invalid_date', sprintf( '%s must be formatted as YYYY-MM-DD.', $field ), array( 'status' => 400 ) );
		}

		return $date;
	}

	private function validate_days( $value ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'shaped_season_invalid_days', 'Days must be provided as an array.', array( 'status' => 400 ) );
		}

		$days = array();
		foreach ( $value as $day ) {
			if ( filter_var( $day, FILTER_VALIDATE_INT ) === false ) {
				return new WP_Error( 'shaped_season_invalid_days', 'Days must contain only integers.', array( 'status' => 400 ) );
			}

			$day = (int) $day;
			if ( $day < 0 || $day > 6 ) {
				return new WP_Error( 'shaped_season_invalid_days', 'Days must be integers between 0 and 6.', array( 'status' => 400 ) );
			}

			$days[] = $day;
		}

		$days = array_values( array_unique( $days ) );
		sort( $days, SORT_NUMERIC );

		if ( empty( $days ) ) {
			return new WP_Error( 'shaped_season_invalid_days', 'At least one day is required.', array( 'status' => 400 ) );
		}

		return $days;
	}

	private function build_entity_from_payload( array $payload, $existingSeason = null, $isCreate = false ) {
		$repeatUntilDate = null;
		$description     = '';
		$seasonId        = 0;

		if ( $existingSeason ) {
			$seasonId    = (int) $existingSeason->getId();
			$description = (string) $existingSeason->getDescription();

			if ( SeasonCPT::REPEAT_PERIOD_YEAR === $payload['repeat_period'] && ! $isCreate ) {
				$repeatUntilDate = $existingSeason->getRepeatUntilDate();
				if ( $repeatUntilDate instanceof \DateTime ) {
					$repeatUntilDate = clone $repeatUntilDate;
				}
			}
		}

		$seasonArgs = array(
			'id'                => $seasonId,
			'title'             => $payload['title'],
			'description'       => $description,
			'start_date'        => $payload['start_date'],
			'end_date'          => $payload['end_date'],
			'days'              => $payload['days'],
			'repeat_period'     => $payload['repeat_period'],
			'repeat_until_date' => $isCreate ? null : ( SeasonCPT::REPEAT_PERIOD_NONE === $payload['repeat_period'] ? null : $repeatUntilDate ),
		);

		if ( SeasonCPT::REPEAT_PERIOD_YEAR === $payload['repeat_period'] ) {
			return new RecurrentSeason( $seasonArgs );
		}

		return new Season( $seasonArgs );
	}

	private function get_season_entity( $seasonId, $force = false ) {
		return MPHB()->getSeasonRepository()->findById( (int) $seasonId, $force );
	}

	private function format_season( $season, array $usageMap ) {
		return array(
			'id'                => (int) $season->getId(),
			'title'             => (string) $season->getTitle(),
			'description'       => (string) $season->getDescription(),
			'start_date'        => $season->getStartDate() ? $season->getStartDate()->format( 'Y-m-d' ) : null,
			'end_date'          => $season->getEndDate() ? $season->getEndDate()->format( 'Y-m-d' ) : null,
			'days'              => array_map( 'intval', $season->getDays() ),
			'repeat_period'     => $season->getRepeatPeriod() ? $season->getRepeatPeriod() : SeasonCPT::REPEAT_PERIOD_DEFAULT,
			'repeat_until_date' => $season->getRepeatUntilDate() ? $season->getRepeatUntilDate()->format( 'Y-m-d' ) : null,
			'is_recurring'      => $season->isRecurring(),
			'used_by_rates'     => isset( $usageMap[ $season->getId() ] ) ? (int) $usageMap[ $season->getId() ] : 0,
		);
	}

	private function get_rate_usage_map() {
		$usageMap = array();
		$rateIds  = get_posts(
			array(
				'post_type'        => 'mphb_rate',
				'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);

		foreach ( $rateIds as $rateId ) {
			$seasonPrices = get_post_meta( $rateId, 'mphb_season_prices', true );
			if ( empty( $seasonPrices ) || ! is_array( $seasonPrices ) ) {
				continue;
			}

			$matchedSeasonIds = array();
			foreach ( $seasonPrices as $seasonPrice ) {
				if ( ! is_array( $seasonPrice ) || ! isset( $seasonPrice['season'] ) ) {
					continue;
				}

				$seasonId = (int) $seasonPrice['season'];
				if ( $seasonId > 0 ) {
					$matchedSeasonIds[ $seasonId ] = true;
				}
			}

			foreach ( array_keys( $matchedSeasonIds ) as $seasonId ) {
				if ( ! isset( $usageMap[ $seasonId ] ) ) {
					$usageMap[ $seasonId ] = 0;
				}

				$usageMap[ $seasonId ]++;
			}
		}

		return $usageMap;
	}
}
