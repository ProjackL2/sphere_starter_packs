<?php
return [
"PLUGIN_HIDE" => false,
"PLUGIN_ENABLE" => true,
"PLUGIN_NAME" => "Starter Pack",
"PLUGIN_VERSION" => "0.0.2",
"PLUGIN_AUTHOR" => "Projack",
"PLUGIN_GITHUB" => "https://github.com/ProjackL2/sphere_starter_packs",
"PLUGIN_DESCRIPTION" => "Стартовые наборы",
"PLUGIN_ADMIN_PAGE" => "/starter_packs",
"PLUGIN_ADMIN_PAGE_NAME" => "Стартовые наборы",
"PLUGIN_ADMIN_PAGE_ICON" => "fa fa-archive",

"PLUGIN_USER_PAGE" => "/starter_packs",
"PLUGIN_USER_PAGE_NAME" => "Starter packs",
"PLUGIN_USER_PAGE_ICON" => "fa fa-archive",
"PLUGIN_USER_PAGE_ACCESS" => ["user", "admin"],
"PLUGIN_USER_PANEL_SHOW" => ["MAIN_MENU"],

"INCLUDES" => [
    "PLACE_IN_SPACE_MAIN_1" => "sphere_starter_packs/tpl/show.html",
],
];
