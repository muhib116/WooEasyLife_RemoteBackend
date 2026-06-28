<?php

return [
    'methods' => [
        [
            'payment_partner' => 'bKash',
            'account' => env('PAYMENT_BKASH_ACCOUNT', '01770-989591'),
            'note' => 'Use bKash "Send Money" and include the Transaction ID in your payment request.',
            'steps' => [
                'Open the bKash app or dial *247#.',
                'Choose "Send Money".',
                'Enter the bKash account number shown below.',
                'Send the total subscription amount.',
                'Copy the Transaction ID and paste it into the payment request form.',
            ],
        ],
        [
            'payment_partner' => 'Rocket',
            'account' => env('PAYMENT_ROCKET_ACCOUNT', '01770-989591-9'),
            'note' => 'Use Rocket "Send Money" and include the Transaction ID in your payment request.',
            'steps' => [
                'Open the Rocket app or dial *322#.',
                'Choose "Send Money".',
                'Enter the Rocket account number shown below.',
                'Send the total subscription amount.',
                'Copy the Transaction ID and paste it into the payment request form.',
            ],
        ],
    ],
];
