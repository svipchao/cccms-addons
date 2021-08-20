<?php
declare (strict_types=1);

namespace cccms\addons;

use think\Service;
use think\facade\Event;

class Library extends Service
{
    // 初始化服务
    public function boot()
    {
        Event::subscribe('cccms\addons\Addons');
    }
}