<?php

// File generated from our OpenAPI spec
namespace MPHB\Stripe\Terminal;

/**
 * A Configurations object represents how features should be configured for terminal readers.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property null|\Stripe\StripeObject $bbpos_wisepos_e
 * @property null|bool $is_account_default Whether this Configuration is the default for your account
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|\Stripe\StripeObject $offline
 * @property null|\Stripe\StripeObject $tipping
 * @property null|\Stripe\StripeObject $verifone_p400
 */
class Configuration extends \MPHB\Stripe\ApiResource
{
    const OBJECT_NAME = 'terminal.configuration';
    use \MPHB\Stripe\ApiOperations\All;
    use \MPHB\Stripe\ApiOperations\Create;
    use \MPHB\Stripe\ApiOperations\Delete;
    use \MPHB\Stripe\ApiOperations\Retrieve;
    use \MPHB\Stripe\ApiOperations\Update;
}
