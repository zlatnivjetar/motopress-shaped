<?php

namespace MPHB\Crons;

use DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 5.0.0
 */
class CheckLicenseStatusCron extends AbstractCron {
	public function doCronJob() {
		if ( empty( MPHB()->settings()->license()->getLicenseKey() ) ) {
			// Nothing to check
			return;
		}

		list( 'status' => $status, 'expires' => $expires ) = MPHB()->settings()->license()->getLicenseStatus();

		$dateNow = new DateTime();

		if ( $expires == 'lifetime' || $dateNow < $expires ) {
			return;
		}

		if ( $status == 'valid' ) {
			MPHB()->settings()->license()->checkLicense();
		}
	}
}
