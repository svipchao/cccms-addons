<?php
declare(strict_types=1);

use think\facade\Config;
use think\facade\Event;
use think\facade\Route;
use think\helper\{
    Str, Arr
};

if (!function_exists('_getEnCode')) {
    /**
     * 获取返回编码类型 (view,json,jsonp,xml)
     * PS:  第一个为默认编码类型
     *      view 类型请自行阅读 common.php->_result()
     *      前后端分离开发模式不需要用到 view
     * @param string $enCode 默认值
     * @return string
     */
    function _getEnCode(string $enCode = 'view'): string
    {
        return strtolower(request()->param('encode/s', $enCode));
    }
}

use think\Response;

if (!function_exists('_result')) {
    /**
     * 返回数据
     * @param array $data 参数
     * @param string $type 输出类型(view,json,jsonp,xml)
     * @param array $header 设置响应的头信息
     */
    function _result(array $data = [], string $type = '', array $header = []): Response
    {
        $data = array_merge([
            'msg' => $data['msg'] ?? 'success',
            'code' => $data['code'] ?? 200,
            'data' => $data['data'] ?? [],
            'url' => $data['url'] ?? '/'
        ], $data);
        if (in_array(strtolower($type), ['json', 'jsonp', 'xml'])) {
            $response = Response::create($data, $type, (int)$data['code']);
        } else {
            // 处理视图
            $addonsName = app()->request->param('addon'); // 插件名
            if ($type === 'view') {
                $htmlName = 'result.html'; // 模版文件名
//                $htmlName = config('cccms.view.resultPath'); // 模版文件名
            } elseif (!empty($addonsName)) {
                $ds = DIRECTORY_SEPARATOR; // DIRECTORY_SEPARATOR
                $suffix = '.' . config('view.view_suffix'); // 模板后缀

                // 判断模版目录层级
                if (count(explode('/', $type)) === 1) {
                    $htmlPath = app()->request->controller() . $ds . $type;
                } else {
                    $htmlPath = $type;
                }
                $htmlName = root_path() . 'addons' . $ds . $addonsName . $ds . config('view.view_dir_name') . $ds . $htmlPath . $suffix;
            } else {
                $htmlName = $type; // 模版文件名
            }
            if (empty($htmlName)) {
                $response = Response::create('异常模版不存在', 'html', 404);
            } else {
                $response = Response::create($htmlName, 'view', (int)$data['code'])->assign($data);
            }
        }
        throw new \think\exception\HttpResponseException($response->header($header));
    }
}

\think\Console::starting(function (\think\Console $console) {
    $console->addCommands([
        'addons:config' => '\\think\\addons\\command\\SendConfig'
    ]);
});

// 插件类库自动载入
spl_autoload_register(function ($class) {

    $class = ltrim($class, '\\');

    $dir = app()->getRootPath();
    $namespace = 'addons';

    if (strpos($class, $namespace) === 0) {
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $namespace . $path;

        if (file_exists($dir)) {
            include $dir;
            return true;
        }

        return false;
    }

    return false;

});

if (!function_exists('hook')) {
    /**
     * 处理插件钩子
     * @param string $event 钩子名称
     * @param array|null $params 传入参数
     * @param bool $once 是否只返回一个结果
     * @return mixed
     */
    function hook($event, $params = null, bool $once = false)
    {
        $result = Event::trigger($event, $params, $once);

        return join('', $result);
    }
}

if (!function_exists('get_addons_info')) {
    /**
     * 读取插件的基础信息
     * @param string $name 插件名
     * @return array
     */
    function get_addons_info($name)
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }

        return $addon->getInfo();
    }
}

if (!function_exists('get_addons_instance')) {
    /**
     * 获取插件的单例
     * @param string $name 插件名
     * @return mixed|null
     */
    function get_addons_instance($name)
    {
        static $_addons = [];
        if (isset($_addons[$name])) {
            return $_addons[$name];
        }
        $class = get_addons_class($name);
        if (class_exists($class)) {
            $_addons[$name] = new $class(app());

            return $_addons[$name];
        } else {
            return null;
        }
    }
}

if (!function_exists('get_addons_class')) {
    /**
     * 获取插件类的类名
     * @param string $name 插件名
     * @param string $type 返回命名空间类型
     * @param string|null $class 当前类名
     * @return string
     */
    function get_addons_class(string $name, string $type = 'hook', string $class = null): string
    {
        $name = trim($name);
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);

            $class[count($class) - 1] = Str::studly(end($class));
            $class = implode('\\', $class);
        } else {
            $class = Str::studly(is_null($class) ? $name : $class);
        }
        switch ($type) {
            case 'controller':
                $namespace = '\\addons\\' . $name . '\\controller\\' . $class;
                break;
            default:
                $namespace = '\\addons\\' . $name . '\\Plugin';
        }

        return class_exists($namespace) ? $namespace : '';
    }
}

if (!function_exists('addons_url')) {
    /**
     * 插件显示内容里生成访问插件的url
     * @param $url
     * @param array $param
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return bool|string
     */
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        $request = app('request');
        if (empty($url)) {
            // 生成 url 模板变量
            $addons = $request->addon;
            $controller = $request->controller();
            $controller = str_replace('/', '.', $controller);
            $action = $request->action();
        } else {
            $url = Str::studly($url);
            $url = parse_url($url);
            if (isset($url['scheme'])) {
                $addons = strtolower($url['scheme']);
                $controller = $url['host'];
                $action = trim($url['path'], '/');
            } else {
                $route = explode('/', $url['path']);
                $addons = $request->addon;
                $action = array_pop($route);
                $controller = array_pop($route) ?: $request->controller();
            }
            $controller = Str::snake((string)$controller);

            /* 解析URL带的参数 */
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
                $param = array_merge($query, $param);
            }
        }

        return Route::buildUrl("@addons/{$addons}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}

