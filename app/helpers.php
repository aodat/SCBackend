<?php

function currency_exchange($amount, $from, $to = 'USD')
{
    $rates = [
        'JOD' => 1.41,
        'SAR' => 0.27,
    ];
    return $rates[$from] * $amount;
}