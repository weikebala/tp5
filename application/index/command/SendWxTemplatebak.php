<?php
/**
 * 线程处理情况
 */
namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use app\index\thread\SendWxThread;

class SendWxTemplatebak extends Command
{
    private $messageid = 28;
    private $redisName = 'wxthread';
    private $redis;

    protected function configure()
    {
        $this->setName('sendWxTread')->setDescription('Here is the weixin send template ');
    }

    /**
     * 根据消息 将要发送的粉丝记录 加入redis队列中
     * @param Input $input
     * @param Output $output
     */
    protected function execute(Input $input, Output $output)
    {

        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1','6379',0);
        $this->redis->del($this->redisName);
        $thisMessage = Db::table('pigcms_send_message')->where('id',$this->messageid)->field('token')->find();
        $this->token = $thisMessage['token'];
        $fans=Db::table('pigcms_wechat_group_list')->where('token',$this->token)->field('nickname,openid')->select();
        foreach($fans as $vo){
            $this->redis->lPush($this->redisName, $vo['openid']);
        }
        $this->doQuece($output);
    }
    public function get_access_token($appid,$appsecret){
        $url_get='https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$appsecret;
        $json = https_request($url_get);
        return $json['access_token'];
    }
    /**
     * 处理队列
     */
    private function doQuece(Output $output){
        if($this->redis->lLen($this->redisName)<1){
            return;
        }

        $thisMessage = Db::table('pigcms_send_message')->where('id',$this->messageid)->find();

        #获取微信access_token
        //$access_token = get_access_token();
        $wxuser = Db::table('pigcms_wxuser')->where(array('token'=>$this->token))->find();
        $access_token = $this->get_access_token($wxuser['appid'],$wxuser['appsecret']);

        #获取模板消息
        $messageparam = unserialize($thisMessage['text']);
        $template_id = $messageparam['templateid'];
        $url = $messageparam['link'];
        $dates = date('Y-m-d H:i:s');
        $data = array(
            'first' => array(
                'value' => $messageparam['first'],
                'color' => '#FF2525'
            ),
            'keyword1' => array(
                'value' => $messageparam['keyword1'].'元',
                'color' => '#173177'
            ),
            'keyword2' => array(
                'value' => $messageparam['keyword2'],
                'color' => '#173177'
            ),
            'keyword3' => array(
                'value' => 'meigou',
                'color' => '#173177'
            ),
            'keyword4' => array(
                'value' => $dates,
                'color' => '#173177'
            ),
            'remark' => array(
                'value' => $messageparam['remark'],
                'color' => '#FF2525'
            )
        );
        $senddata = array(
            'template_id'  => $template_id,
            'url'          => $url,
            'data'         => $data
        );

        SendWxThread::$access_token = $access_token;
        SendWxThread::$data = $senddata;
        SendWxThread::$messageid = $this->messageid;
        SendWxThread::$redisName = $this->redisName;

        SendWxThread::$redis = $this->redis;
        //SendWxThread::$ouput = new Output();
        print_r(SendWxThread::$redis);exit;
        $startTime = microtime(true);
        while($this->redis->lLen($this->redisName) > 0){
            $x = 0;
            $work = [];
            for ($x; $x<1; $x++) {
                $openid = $this->redis->lPop($this->redisName);
                if(!empty($openid)){
                    $work[$x] = new SendWxThread($openid);
                }
            }
            foreach($work as $thread){
                $thread->start();
            }
            foreach($work as $thread) {
                $thread->join();
            }
            break;
        }
        $endTime = microtime(true);
        $output->writeln($endTime-$startTime);
    }
    /**
     * 发送https请求
     * @param <string> $url 地址
     * @param <mixed> $post_data post数据
     * @return <mixed>
     */
    function https_request($url, $post_data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if(!empty($post_data)){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        if(is_array($response) && count($response)) {
            return $response;
        } else {
            return false;
        }
    }
    private function curlGet($url){
        $ch = curl_init();
        $header = "Accept-Charset: utf-8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $temp = curl_exec($ch);
        return $temp;
    }
    private  function curlPost($url, $data,$showError=1){
        $ch = curl_init();
        $headers = array('Content-Type:application/json; charset=utf-8','Content-Length: '.strlen($data));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_POSTQUOTE, array());
        curl_setopt($ch, CURLOPT_HTTP200ALIASES, array());
        curl_setopt($ch, CURLOPT_QUOTE, array());

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        $errorno=curl_errno($ch);
        if ($errorno) {
            return array('rt'=>false,'errorno'=>$errorno);
        }else{
            $js=json_decode($tmpInfo,1);
            if (intval($js['errcode']==0)){
                return array('rt'=>true,'errorno'=>0,'media_id'=>$js['media_id'],'msg_id'=>$js['msg_id']);
            }else {
                if ($showError){
                    $this->error('发生了Post错误：错误代码'.$js['errcode'].',微信返回错误信息：'.$js['errmsg']);
                }
            }
        }
    }
    function curl_post($data,$url)
    {
        $host = array('Content-Type:application/json; charset=utf-8','Content-Length: '.strlen($data));
        $ch = curl_init();
        $res= curl_setopt ($ch, CURLOPT_URL,$url);
        var_dump($res);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt ($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_HTTPHEADER,$host);
        $result = curl_exec ($ch);
        curl_close($ch);
        if ($result == NULL) {
            return 0;
        }
        //TMDebugUtils::debugLog($result);
        return $result;
    }
}