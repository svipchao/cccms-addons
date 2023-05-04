<?php
declare (strict_types=1);

namespace addons\init\controller;

use PDO;
use cccms\Base;
use cccms\service\ConfigService;

class Index extends Base
{
    public function index()
    {
        // 判断是否安装
        if (file_exists(__DIR__ . '/../data/install.lock')) {
            _result(['code' => 200, 'msg' => '已安装'], 'view');
        }
        _result([], 'index');
    }

    public function doInstall()
    {
        // 判断是否安装
        // !empty(Db::query('show tables like "sys_config"'))
        if (file_exists(__DIR__ . '/../data/install.lock')) {
            _result(['code' => 200, 'msg' => '已安装'], 'json');
        }
        $param = $this->app->request->param();
        try {
            $param['mysqlHostPort'] = $param['mysqlHostPort'] ?: 3306;
            $dsn = config('database.default') . ":host={$param['mysqlHostName']};port={$param['mysqlHostPort']};dbname={$param['mysqlDatabase']}";
            $pdo = new PDO($dsn, $param['mysqlUserName'], $param['mysqlPassWord']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = file_get_contents(__DIR__ . '/../data/install.sql');
            $pdo->query($sql);

            // 将数据库配置写入配置文件
            $sqlInfo = [
                // 自动写入时间戳字段
                // true为自动识别类型 false关闭
                // 字符串则明确指定时间字段类型 支持 int timestamp datetime date
                'auto_timestamp' => true,

                // 时间字段取出后的默认时间格式
                'datetime_format' => false,

                'connections' => [
                    'mysql' => [
                        // 数据库类型
                        'type' => 'mysql',
                        // 服务器地址
                        'hostname' => $param['mysqlHostName'],
                        // 数据库名
                        'database' => $param['mysqlDatabase'],
                        // 用户名
                        'username' => $param['mysqlUserName'],
                        // 密码
                        'password' => $param['mysqlPassWord'],
                        // 端口
                        'hostport' => $param['mysqlHostPort'],
                    ]
                ]
            ];
            // 写入配置文件
            file_put_contents($this->app->getRootPath() . 'cccms/config/database.php', '<?php' . PHP_EOL . 'return ' . var_export($sqlInfo, true) . ';');

            // 初始化配置文件
            ConfigService::instance()->createConfigFile();

            // 生成安装标识文件
            file_put_contents(__DIR__ . '/../data/install.lock', 0);

            _result(['code' => 200, 'msg' => '初始化成功'], 'json');
        } catch (\PDOException $e) {
            _result(['code' => 202, 'msg' => $e->getMessage()], 'json');
        }
    }
}