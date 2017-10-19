<?php
/**
 * Created by PhpStorm.
 * User: lenovo
 * Date: 2017/10/19
 * Time: 9:22
 */
namespace app\index\thread;
use think\Db;
use think\console\Output;
class SendWxThread extends \Thread {
    /**
     * 能访问  字符串 或数组
     * @var
     */
    static $data;
    static $access_token;
    static $messageid;
    static $redisName;
    /**
     * @var 不能访问 对象
     */
    static $redis;
    static $ouput;

    private $openid;
    public function __construct($openid){
        $this->openid = $openid;
    }

    public function run(){print_r(self::$redis);exit;
        //$output = new Output();
        $data = self::$data;
        $data['touser'] = $this->openid;
        $rt = https_request('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.self::$access_token,json_encode($data,JSON_UNESCAPED_UNICODE));
        //$output->writeln(json_encode($rt));

        self::$ouput->writeln(json_encode($rt));
        if($rt==false){
            self::$redis->lPush(self::$redisName, $this->openid);
        }else{
            if($rt['errmsg'] == 'ok'){
                //Db::table('pigcms_send_message')->where('id',self::$messageid)->lock(true)->setInc('reachcount');
            }elseif($rt['errcode'] == '40001'){//token失效重新获取access_token
                self::$redis->lPush(self::$redisName, $this->openid);
                self::$access_token = get_access_token();
            }elseif($rt['errcode'] == '-1'){
                self::$redis->lPush(self::$redisName, $this->openid);
                sleep(3);
            }
        }
    }
}