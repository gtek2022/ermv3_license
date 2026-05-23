<?php

return [
    'default' => 'main',
    'connections' => [
        'main' => [
            'salt'   => env('APP_KEY', 'gemilang-salt'),
            'length' => 10,
        ],
    ],
];
