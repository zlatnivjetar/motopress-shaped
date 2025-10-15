<?php

namespace MPHB\Crons;

class CronManager {

	const INTERVAL_PENDING_USER_APPROVAL = 'mphb_pending_user_approval';
	const INTERVAL_PENDING_PAYMENT       = 'mphb_pending_payment';
	const INTERVAL_AUTODELETE_SYNC_LOGS  = 'mphb_ical_auto_delete';

	const INTERVAL_QUARTER_AN_HOUR = 'mphb_15m';
	const INTERVAL_HALF_AN_HOUR    = 'mphb_30m';
	/** @since 5.0.0 */
	const INTERVAL_WEEKLY          = 'mphb_weekly';

	// Default WordPress intervals
	const INTERVAL_DAILY       = 'daily';
	const INTERVAL_TWICE_DAILY = 'twicedaily';
	const INTERVAL_HOURLY      = 'hourly';

	/**
	 * @var Cron[]
	 */
	private $crons = array();

	public function __construct() {

		add_filter( 'cron_schedules', array( $this, 'createCronIntervals' ) );

		$this->initCrons();

		// schedule all necessary crons
		// MPHB\Libraries\WP_SessionManager starts its own cron

		$this->getCron( 'check_license_status' )->schedule();

		$this->rescheduleAutoSynchronizationCrons();
	}

	/**
	 * @since 3.6.1 added new cron - DeleteOldSyncLogsCron.
	 * @since 5.0.0 added new cron - CheckLicenseStatusCron.
	 */
	public function initCrons() {

		$crons = array(
			new AbandonBookingPendingUserCron(
				'abandon_booking_pending_user',
				self::INTERVAL_PENDING_USER_APPROVAL
			),
			new AbandonBookingPendingPaymentCron(
				'abandon_booking_pending_payment',
				self::INTERVAL_PENDING_PAYMENT
			),
			new AbandonPaymentPendingCron(
				'abandon_payment_pending',
				self::INTERVAL_PENDING_PAYMENT
			),
			new CheckLicenseStatusCron(
				'check_license_status',
				self::INTERVAL_WEEKLY
			),
			new IcalAutoSynchronizationCron(
				'ical_auto_synchronization',
				get_option( 'mphb_ical_auto_sync_interval', self::INTERVAL_DAILY )
			),
			new DeleteOldSyncLogsCron(
				'ical_auto_delete',
				self::INTERVAL_AUTODELETE_SYNC_LOGS
			),
		);

		foreach ( $crons as $cron ) {
			$this->addCron( $cron );
		}
	}

	public function addCron( AbstractCron $cron ): void {
		$this->crons[ $cron->getId() ] = $cron;
	}

	public function getCron( string $id ): ?AbstractCron {
		return isset( $this->crons[ $id ] ) ? $this->crons[ $id ] : null;
	}

	/**
	 * @param array $schedules
	 * @return array
	 *
	 * @since 3.6.1 added new interval - "Interval for automatic cleaning of synchronization logs".
	 * @since 3.6.1 added new interval - "Quarter an Hour".
	 * @since 3.6.1 added new interval - "Half an Hour".
	 */
	public function createCronIntervals( $schedules ) {

		$schedules[ self::INTERVAL_QUARTER_AN_HOUR ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Quarter an Hour', 'motopress-hotel-booking' ),
		);

		$schedules[ self::INTERVAL_HALF_AN_HOUR ] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Half an Hour', 'motopress-hotel-booking' ),
		);

		$schedules[ self::INTERVAL_PENDING_USER_APPROVAL ] = array(
			'interval' => MPHB()->settings()->main()->getUserApprovalTime() * MINUTE_IN_SECONDS,
			'display'  => __( 'User Approval Time setted in Hotel Booking Settings', 'motopress-hotel-booking' ),
		);

		$schedules[ self::INTERVAL_PENDING_PAYMENT ] = array(
			'interval' => MPHB()->settings()->payment()->getPendingTime() * MINUTE_IN_SECONDS,
			'display'  => __( 'Pending Payment Time set in Hotel Booking Settings', 'motopress-hotel-booking' ),
		);

		$schedules[ self::INTERVAL_AUTODELETE_SYNC_LOGS ] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Interval for automatic cleaning of synchronization logs.', 'motopress-hotel-booking' ),
		);

		$schedules[ self::INTERVAL_WEEKLY ] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => esc_html__( 'Once a week', 'motopress-hotel-booking' ),
		);

		return $schedules;
	}

	public function rescheduleAutoSynchronizationCrons( bool $isForceDisableAutoSyncCrons = false ) {

		$isAutoSyncEnabled  = $isForceDisableAutoSyncCrons || (bool) get_option( 'mphb_ical_auto_sync_enable', false );

		$autoSyncCron = $this->getCron( 'ical_auto_synchronization' );

		if ( ! $isAutoSyncEnabled ) {

			$this->getCron( 'ical_auto_delete' )->unschedule();
			$autoSyncCron->unschedule();

			delete_option( 'mphb_ical_auto_sync_previous_clock' );
			delete_option( 'mphb_ical_auto_sync_previous_interval' );
			delete_option( 'mphb_ical_auto_sync_worked_once' );

			return;
		}

		if ( 'never' !== MPHB()->settings()->main()->deleteSyncLogsOlderThan() ) {

			$this->getCron( 'ical_auto_delete' )->schedule();
		}

		
		// time in 12-hour or 24-hour format: "08:15 pm" or "20:15"
		$autoSyncTime     = get_option( 'mphb_ical_auto_sync_clock', '01:00' );
		$autoSyncInterval = get_option( 'mphb_ical_auto_sync_interval', self::INTERVAL_DAILY );

		$previousClock    = get_option( 'mphb_ical_auto_sync_previous_clock', false );
		$previousInterval = get_option( 'mphb_ical_auto_sync_previous_interval', false );

		$clockChanged    = ( $previousClock === false || $autoSyncTime != $previousClock );
		$intervalChanged = ( $previousInterval === false || $autoSyncInterval != $previousInterval );
		$syncWorkedOnce  = (bool) get_option( 'mphb_ical_auto_sync_worked_once', false );

		if ( ! $clockChanged && ! $intervalChanged && $autoSyncCron->isScheduled() ) {
			// No changes made to settings
			return;
		}

		if ( $clockChanged ) {
			$scheduledTimestamp = \MPHB\Utils\DateUtils::nextTimestampWithTime( $autoSyncTime );

		} else { // if ( $intervalChanged )
			$scheduledTimestamp = wp_next_scheduled( $autoSyncCron->getAction() );

			// Wait less, only if the process was started (worked at least once)
			if ( $scheduledTimestamp !== false && $syncWorkedOnce ) {
				$schedules    = wp_get_schedules();
				$intervalTime = $schedules[ $autoSyncInterval ]['interval'];
				$currentTime  = time();
				$waitTime     = $scheduledTimestamp - $currentTime;
				if ( $waitTime > $intervalTime ) {
					$scheduledTimestamp = $currentTime + $intervalTime;
				}
			} else {
				$scheduledTimestamp = \MPHB\Utils\DateUtils::nextTimestampWithTime( $autoSyncTime );
			}
		}

		$autoSyncCron->unschedule();
		$autoSyncCron->setInterval( $autoSyncInterval );
		$autoSyncCron->scheduleAt( $scheduledTimestamp );

		update_option( 'mphb_ical_auto_sync_previous_clock', $autoSyncTime, 'no' );
		update_option( 'mphb_ical_auto_sync_previous_interval', $autoSyncInterval, 'no' );
	}

	public function do_on_plugin_deactivation() {

		if ( ! empty( $this->crons ) ) {

			foreach ( $this->crons as $cron ) {

				if ( 'ical_auto_synchronization' === $cron->getId() ) {
					$this->rescheduleAutoSynchronizationCrons( true );
				}
					
				$cron->unschedule();
			}
		}

		wp_clear_scheduled_hook( 'mphb_wp_session_garbage_collection' );
	}
}
