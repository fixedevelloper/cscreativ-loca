<?php

return [
    'greeting' => 'Hello :name,',
    'signature' => 'If you have any questions, please contact your property manager.',

    'invitation' => [
        'subject' => 'Welcome to :app: create your password',
        'intro' => 'An account has been created for you on :app. To access it, choose your password.',
        'action' => 'Create my password',
        'expiry' => 'This link is valid for :hours hours.',
        'ignore' => 'If you were not expecting this message, you can ignore it.',
    ],

    'rent_overdue' => [
        'subject' => [
            'j3' => 'Reminder: rent is due (:property)',
            'j7' => 'Reminder: rent still unpaid (:property)',
            'j15' => 'Formal notice: unpaid rent (:property)',
        ],
        'j3' => 'This is a reminder that the rent for ":property" for :month is due and remains unpaid.',
        'j7' => 'The rent for ":property" for :month has still not been paid (:days days late).',
        'j15' => 'Despite our previous reminders, the rent for ":property" for :month remains unpaid, :days days late.',
        'amount' => 'Amount due: :amount.',
        'amount_with_penalty' => 'Amount due (rent + late fee): :amount.',
        'please_pay' => 'Please make the payment as soon as possible.',
        'please_settle' => 'Please settle your account as soon as possible.',
        'formal_notice' => 'Failing payment within 8 days, debt recovery proceedings may be initiated.',
        'sms' => ':app: rent :month unpaid for ":property". Amount due: :amount XAF. Please pay as soon as possible.',
    ],
];
