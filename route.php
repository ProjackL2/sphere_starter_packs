<?php
use Ofey\Logan22\component\plugins\sphere_starter_packs\starter_packs;
$routes = [
    [
        "method"  => "GET",
        "pattern" => "/starter_packs",
        "file"    => "starter_packs.php",
        "call"    => function() {
            (new starter_packs())->show_starter_packs_draw();
        },
    ],
    [
        "method"  => "POST",
        "pattern" => "/starter_packs/buy",
        "file"    => "starter_packs.php",
        "call"    => function() {
            (new starter_packs())->buy();
        },
    ],
];
