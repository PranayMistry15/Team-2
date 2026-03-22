<?php
// Validation rule constraints kept it in a php tho I think json would've been better
return [
    'user' => [
        'name' => ['min' => 2, 'max' => 100],
        'email' => ['min' => 5, 'max' => 100],
        'password' => ['min' => 6, 'max' => 128],
    ],
    'checkout' => [
        'address' => ['min' => 5, 'max' => 200],
        'city' => ['min' => 2, 'max' => 100],
        'postal_code' => ['min' => 3, 'max' => 12],
        'payment_method' => ['min' => 2, 'max' => 20],
    ],
    'review' => [
        'title' => ['min' => 3, 'max' => 120],
        'comment' => ['min' => 5, 'max' => 1000],
        'rating' => ['min' => 1, 'max' => 5],
    ],
    'service_review' => [
        'comment' => ['min' => 5, 'max' => 1000],
        'rating' => ['min' => 1, 'max' => 5],
    ],
    'product' => [
        'name' => ['min' => 2, 'max' => 200],
        'brand' => ['min' => 2, 'max' => 50],
        'cpu' => ['min' => 0, 'max' => 100],
        'ram' => ['min' => 0, 'max' => 50],
        'storage' => ['min' => 0, 'max' => 100],
        'gpu' => ['min' => 0, 'max' => 100],
        'screen_size' => ['min' => 0, 'max' => 20],
        'resolution' => ['min' => 0, 'max' => 50],
        'battery' => ['min' => 0, 'max' => 50],
        'weight' => ['min' => 0, 'max' => 20],
        'os' => ['min' => 0, 'max' => 50],
        'stock' => ['min' => 0, 'max' => 100000],
    ],
];
