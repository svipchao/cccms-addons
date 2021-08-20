<?php
/**
 * 获取插件目录
 *
 * @param string $path
 * @return string
 */
function addons_path($path = '')
{
    return app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR . ($path ? ltrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $path);
}

/**
 * 获得插件列表
 * @return array
 */
function get_addon_list()
{
    $results = scandir(addons_path());

    $list = [];
    foreach ($results as $name) {
        if ($name === '.' or $name === '..') {
            continue;
        }
        if (is_file(addons_path() . $name)) {
            continue;
        }
        $addonDir = addons_path() . $name . DIRECTORY_SEPARATOR;
        if (!is_dir($addonDir)) {
            continue;
        }

        if (!is_file($addonDir . ucfirst($name) . '.php')) {
            continue;
        }

        //这里不采用get_addon_info是因为会有缓存
        //$info = get_addon_info($name);
        $infoFile = $addonDir . 'info.ini';

        if (!is_file($infoFile)) {
            continue;
        }

        $info = parse_ini_file($infoFile, true, INI_SCANNER_TYPED);

        if (!isset($info['name'])) {
            continue;
        }
        //$info['url'] = addon_url($name);
        $list[$name] = $info;
    }
    return $list;
}

function get_addon_config($addonName)
{
    return get_addon_php('config', $addonName);
}

function get_addon_event($addonName)
{
    return get_addon_php('event', $addonName);
}

function get_addon_route($addonName)
{
    return get_addon_php('route', $addonName);
}

function get_addon_php($type, $addonName)
{
    $file = addons_path() . $addonName . DIRECTORY_SEPARATOR . $type . '.php';

    $data = [];
    if (is_file($file)) {
        $data = include $file;
    }

    return $data;
}