<?php

return [
    /*
    | Public history-check names. Membership follows the same flags as
    | PlatformSufficiencyChecker::configuredCouriers(): Steadfast, Pathao,
    | and Paperfly are always on; RedX and Carrybee follow
    | fraud_check.include_redx / fraud_check.include_carrybee.
    | Display order is the marketing order, not the checker call order.
    */
    'history' => [
        ['name' => 'Pathao', 'flag' => null],
        ['name' => 'Steadfast', 'flag' => null],
        ['name' => 'RedX', 'flag' => 'include_redx'],
        ['name' => 'Paperfly', 'flag' => null],
        ['name' => 'Carrybee', 'flag' => 'include_carrybee'],
    ],

    /*
    | Parcel booking / auto-entry. Static: these four have create-order
    | APIs. Carrybee does not. This list does not follow the history flags.
    */
    'booking' => ['Pathao', 'Steadfast', 'RedX', 'Paperfly'],
];
