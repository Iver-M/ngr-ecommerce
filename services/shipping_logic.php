<?php
// shipping_logic.php

$shipping_options = [
    'standard' => [
        'name' => 'Standard Freight',
        'eta' => '7-14 Business Days',
        'costs' => [
            'NCR' => 500.00,
            'ILOCOS REGION' => 1500.00,
            'CAGAYAN VALLEY' => 1600.00,
            'CENTRAL LUZON' => 800.00,
            'CALABARZON' => 750.00,
            'MIMAROPA REGION' => 1800.00,
            'BICOL REGION' => 1700.00,
            'CORDILLERA ADMINISTRATIVE REGION' => 1650.00,
            'WESTERN VISAYAS' => 2500.00,
            'CENTRAL VISAYAS' => 2600.00,
            'EASTERN VISAYAS' => 2750.00,
            'ZAMBOANGA PENINSULA' => 3000.00,
            'NORTHERN MINDANAO' => 3100.00,
            'DAVAO REGION' => 3200.00,
            'SOCCSKSARGEN' => 3300.00,
            'CARAGA' => 3400.00,
            'BANGSAMORO AUTONOMOUS REGION IN MUSLIM MINDANAO' => 3500.00,
            'default' => 2000.00
        ]
    ],
    'express' => [
        'name' => 'Express Freight',
        'eta' => '3-7 Business Days',
        'costs' => [
            'NCR' => 850.00,
            'ILOCOS REGION' => 2200.00,
            'CAGAYAN VALLEY' => 2400.00,
            'CENTRAL LUZON' => 1200.00,
            'CALABARZON' => 1100.00,
            'MIMAROPA REGION' => 2500.00,
            'BICOL REGION' => 2400.00,
            'CORDILLERA ADMINISTRATIVE REGION' => 2350.00,
            'WESTERN VISAYAS' => 3500.00,
            'CENTRAL VISAYAS' => 3600.00,
            'EASTERN VISAYAS' => 3750.00,
            'ZAMBOANGA PENINSULA' => 4000.00,
            'NORTHERN MINDANAO' => 4100.00,
            'DAVAO REGION' => 4200.00,
            'SOCCSKSARGEN' => 4300.00,
            'CARAGA' => 4400.00,
            'BANGSAMORO AUTONOMOUS REGION IN MUSLIM MINDANAO' => 4500.00,
            'default' => 3000.00
        ]
    ]
];

function get_shipping_cost($region, $courier, $options_array) {
    $region_upper = strtoupper(trim($region));
    $costs = $options_array[$courier]['costs'] ?? [];
    foreach ($costs as $key => $value) {
        if (strtoupper($key) === $region_upper) {
            return $value;
        }
    }
    return $costs['default'] ?? 0;
}