<?php
namespace app\index\controller;
use \think\config;
use \think\Log;
use \think\Db;
use \think\Debug;
use \think\Validate;
class Index
{
    public function index(\think\Request $request)
    {
        return get_access_token();
    }
}
