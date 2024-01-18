<?php

namespace Ofey\Logan22\component\plugins\sphere_starter_packs;

use Ofey\Logan22\component\plugins\sphere_starter_packs\starter_packs;
use Ofey\Logan22\model\user\auth\auth;
use Ofey\Logan22\model\admin\validation;

class custom_twig {
    public function get_starter_packs() {
        return starter_packs::get_starter_packs(auth::get_default_server());
    }
}