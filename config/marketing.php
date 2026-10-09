<?php

return [
    'currency' => 'NGN',
    'contact_email' => env('MARKETING_CONTACT_EMAIL', 'hello@ziwait.test'),

    // PLACEHOLDER social proof from the design mock-up. These are NOT real figures or customers.
    // Keep MARKETING_SOCIAL_PROOF=false in production until real numbers and consented quotes replace them.
    'social_proof' => (bool) env('MARKETING_SOCIAL_PROOF', false),

    'stats' => [
        ['500+', 'Businesses'],
        ['50K+', 'Orders processed'],
        ['99.9%', 'Uptime'],
    ],

    'testimonials' => [
        ['quote' => 'ZIWAIT has made it so easy to manage our menu and get orders. Our sales have increased significantly!', 'name' => 'Tunde Adebayo', 'role' => 'Restaurant Owner'],
        ['quote' => 'The subscription and payment system is seamless. We love the advertising features too!', 'name' => 'Ada Nwosu', 'role' => 'Hotel Manager'],
        ['quote' => 'Excellent platform and amazing support. ZIWAIT is a game changer for business.', 'name' => 'Ibrahim Musa', 'role' => 'Cafe Owner'],
    ],

    // Bullet points per plan code; the price itself always comes from the plan_prices table.
    'plan_features' => [
        'starter' => ['1 branch', 'Digital menu and QR code', 'Live order alerts', 'Email support'],
        'professional' => ['Up to 5 branches', 'Branch-specific pricing', 'Team roles and permissions', 'Priority email support'],
        'business' => ['Up to 10 branches', 'Everything in Professional', 'Targeted advertising placements', 'Priority email support'],
    ],
    'popular_plan' => 'professional',
];
