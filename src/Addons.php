<?php

namespace cccms\addons;

use think\facade\Config;
use think\facade\Event;
use think\facade\Route;

/**
 * 插件初始化
 */
class Addons
{
    public function handle()
    {
        $addonList = get_addon_list();

        foreach ($addonList as $addon) {
            if ($addon['state'] == 1) {
                $this->load_config($addon['name']);
                $this->load_route($addon['name']);
                $this->load_event($addon['name']);
            }
        }

        Event::trigger('AddonsInit');
    }

    public function load_config($addonName)
    {
        $config = get_addon_config($addonName);

        if ($config) {
            Config::set([$addonName => $config], 'addons');
        }
    }

    public function load_route($addonName)
    {
        $route = get_addon_route($addonName);
        Route::rule("addons/$addonName", "\addons\\$addonName\controller\Index@index");
        Route::rule("addons/$addonName/[:controller]", "\addons\\$addonName\controller\:controller@index");
        Route::rule("addons/$addonName/[:controller]/[:action]", "\addons\\$addonName\controller\:controller@:action");
    }

    public function load_event($addonName)
    {
        $event = get_addon_event($addonName);
        Event::listenEvents($event);
    }
}