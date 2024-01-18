<?php

namespace Ofey\Logan22\component\plugins\sphere_starter_packs;

use Ofey\Logan22\component\alert\board;
use Ofey\Logan22\component\image\client_icon;
use Ofey\Logan22\component\lang\lang;
use Ofey\Logan22\component\time\time;
use Ofey\Logan22\controller\page\error;
use Ofey\Logan22\model\admin\userlog;
use Ofey\Logan22\model\admin\validation;
use Ofey\Logan22\model\donate\donate;
use Ofey\Logan22\model\db\sql;
use Ofey\Logan22\model\server\server;
use Ofey\Logan22\model\user\auth\auth;
use Ofey\Logan22\model\user\player\player_account;
use Ofey\Logan22\template\tpl;

class starter_packs {
    public function show_starter_packs_draw() {
        $settings = include __DIR__ . "/settings.php";
        if (!$settings['PLUGIN_ENABLE']){
            tpl::addVar("message", "Plugin disabled");
            tpl::display("page/error.html");
            return;
        }

        $server_id = auth::get_default_server();
        if (!$server_id) {
            tpl::addVar("message", "Not Server");
            tpl::display("page/error.html");
            return;
        }

        tpl::displayPlugin("/sphere_starter_packs/tpl/index.html");
    }

    public static function get_starter_packs($server_id) {
        validation::user_protection();

        $settings = include __DIR__ . "/settings.php";
        if (!$settings['PLUGIN_ENABLE']){
           return null;
        }

        $list_of_packs = self::get_packs_from_config($server_id);
        if (empty($list_of_packs)) {
            return null;
        }

        $donate_info = include ('src/config/donate.php');
        $type_discount = self::get_type_discount($donate_info);

        $full_packs_info_draws = [];
        foreach ($list_of_packs as $pack) {
            $product_info = self::donate_item_info($pack['product_id'], $server_id);
            if (!$product_info) {
                error::error404(lang::get_phrase(152));
            }

            $count_discount = self::get_count_discount($donate_info, $product_info);
            $cost_discount = floor($product_info['cost'] * (1 - ($type_discount / 100)) * (1 - ($count_discount / 100)));

            $item_id = $product_info['item_id'];
            $pack_info = client_icon::get_item_info($item_id, false);
            $pack_info['product_id'] = $pack['product_id'];
            $pack_info['cost'] = $product_info['cost'];
            $pack_info['cost_discount'] = $cost_discount;
            $pack_info['items'] = [];
            foreach ($pack['items'] as $item) {
                $item_info = client_icon::get_item_info($item['id'], false);
                $item_info['enchant'] = $item['enchant'] ?? 0;
                $item_info['item_count'] = $item['count'] ?? 1;
                $pack_info['items'][] = $item_info;
            }
            $full_packs_info_draws[] = $pack_info;
        }

        return $full_packs_info_draws;
    }

    public function buy() {
        validation::user_protection();

        $settings = include __DIR__ . "/settings.php";
        if (!$settings['PLUGIN_ENABLE']){
            board::error("Покупка невозможна, плагин отключен");
        }

        if (!auth::get_is_auth()) {
            board::error("Покупка возможна только авторизованным пользователям");
        }

        $user_id = auth::get_id();
        $server_id = $_POST['server_id'] ?? auth::get_default_server();
        $product_id = $_POST['product_id'] ?? board::error("Not valid product id");
        $char_name = $_POST['char_name'] ?? null;

        // spam protection
        $now = time();
        $config = include __DIR__ . "/config.php";
        $last_usage = $_SESSION['last_pack_usage'] ?? 0;
        $cooldown_seconds = $config['cooldown_seconds'];
        if ($now - $last_usage < $cooldown_seconds) {
            board::error("Повторное использование функции возможно только один раз в " . $cooldown_seconds . " секунд");
        }
        $_SESSION['last_pack_usage'] = $now;
        
        $product_info = self::donate_item_info($product_id, $server_id);
        if ($product_info == null) {
            board::error("Покупаемый пак не найден");
        }

        // take discount into account
        $donate_info = include ('src/config/donate.php');
        $type_discount = self::get_type_discount($donate_info);
        $count_discount = self::get_count_discount($donate_info, $product_info);
        $cost_discount = floor($product_info['cost'] * (1 - ($type_discount / 100)) * (1 - ($count_discount / 100)));

        if (auth::get_donate_point() < $cost_discount) {
            board::error("У Вас нехватает денег: " .  auth::get_donate_point() . "/" . $cost_discount);
        }

        // add to player or to account inventory
        $item_id = $product_info['item_id'];
        $item_info = client_icon::get_item_info($item_id, false);

        $message = lang::get_phrase(304);
        if ($char_name != null && !empty($char_name)) {
            $message =  $message . ": " . $item_info['name'] . ". Предмет отправлен на персонажа {$char_name}.";
            self::add_to_player($server_id, $char_name, $item_id);
            userlog::add("donate", 539, [$item_id, 1, $cost_discount, $char_name]);
        } else {
            $message =  $message . ": " . $item_info['name'] . ". Предмет отправлен в инвентарь.";
            self::add_to_inventory($user_id, $server_id, $item_id, "Starter Packs");
            userlog::add("donate", 539, [$item_id, 1, $cost_discount, "TO INVENTORY"]);
        }
        
        self::add_donation_record($user_id, $item_id, $cost_discount, $char_name, $server_id);
        donate::taking_money($cost_discount, $user_id);
        auth::set_donate_point(auth::get_donate_point() - $cost_discount);

        board::alert([
            'type' => 'notice',
            'ok' => true,
            'message' => $message,
            'donate_bonus' => auth::get_donate_point(),
        ]);
    }

    private static function add_to_player($server_id, $char_name, $item_id) {
        $server_info = server::get_server_info($server_id);
        if (!$server_info) {
            board::notice(false, lang::get_phrase(150));
        }

        $player_info = player_account::is_player($server_info, [$char_name]);
        $player_info = $player_info->fetch();

        if (!$player_info) {
            board::notice(false, lang::get_phrase(151, $char_name));
        }

        $player_id = $player_info["player_id"];
        if ($server_info['collection_sql_base_name']::need_logout_player_for_item_add()) {
            if ($player_info["online"]) {
                board::notice(false, lang::get_phrase(153, $char_name));
            }
            donate::add_item_max_val_id($server_info, $player_id, $item_id, 1);
        } else { //Если персонаж может быть в игре для выдачи предмета
            player_account::add_item($server_info, [$player_id, $item_id, 1, 0]);
        }
    }

    private static function add_to_inventory($user_id, $server_id, $item_id, $phrase): void {
        $ins = sql::run("INSERT INTO `bonus` (`user_id`, `server_id`, `item_id`, `count`, `enchant`, `phrase`) VALUES (?, ?, ?, ?, ?, ?)", [
            $user_id, $server_id, $item_id, 1, 0, $phrase,
        ]);
        if (!$ins) {
            error_log("Failed to add to inventory item: user_id=" . $user_id . " server_id=" . $server_id . " item_id=" . $item_id);
        }
    }

    private static function add_donation_record($user_id, $item_id, $cost, $char_name, $server_id) {
        $ins = sql::run("INSERT INTO `donate_history` (`user_id`, `item_id`, `amount`, `cost`, `char_name`, `server_id`, `date`) VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $user_id, $item_id, 1, $cost, $char_name, $server_id, time::mysql(),
        ]);
        if (!$ins) {
            error_log("Failed to add donation history record item: user_id=" . $user_id . " server_id=" . $server_id . " item_id=" . $item_id);
        }
    }

    private static function donate_item_info($item_id, $server_id) {
        return sql::run("SELECT id, item_id, count, cost FROM donate WHERE id = ? AND server_id = ?", [
            $item_id, $server_id,
        ])->fetch();
    }

    private static function get_packs_from_config($server_id) {
        $list_of_packs = [];
        $config = include __DIR__ . "/config.php";
        foreach ($config['list_of_packs'] as $draw) {
            if ($draw['server_id'] == $server_id) {
                $list_of_packs = $draw['packs'];
                break;
            }
        }
        return $list_of_packs;
    }

    private static function get_type_discount($donate_info) {
        if (auth::get_is_auth() && $donate_info['DONATE_DISCOUNT_TYPE_PRODUCT_ENABLE']) {
            return donate::getBonusDiscount(auth::get_id(), $donate_info['discount_product']['table']);
        }
        return 0;
    }

    private static function get_count_discount($donate_info, $product_info) {
        $discount = 0;
        $item_id = $product_info['item_id'];
        if ($donate_info['DONATE_DISCOUNT_COUNT_ENABLE']) {
            $discount_count_product_table = $donate_info["discount_count_product"]['table'] ?? [];
            $discount_count_product_items = $donate_info["discount_count_product"]['items'] ?? [];
            if (in_array($item_id, $discount_count_product_items) or empty($discount_count_product_items)) {
                $discount = self::find_value_for_n(1, $discount_count_product_table) ?? 0;
            }
        }
        return $discount;
    }

    private static function find_value_for_n($inputN, $keyValueObject = 0) {
        if (!is_array($keyValueObject)) {
            return 0;
        }
        $result = null;
        foreach ($keyValueObject as $key => $value) {
            $currentKey = (int)$key;
            if ($currentKey > $inputN) {
                break;
            }
            $result = $value;
        }
        return $result;
    }
}