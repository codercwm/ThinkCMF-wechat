<?php

namespace plugins\weixin;
use cmf\lib\Plugin;
use plugins\weixin\lib\vendor\wxpay\log\CLogFileHandler;
use plugins\weixin\lib\vendor\wxpay\log\Log;
use plugins\weixin\lib\vendor\wxpay\NativePay;
use plugins\weixin\lib\vendor\wxpay\WxPayData\WxPayUnifiedOrder;

class WeixinPlugin extends Plugin{
    public $info = array(
        'name'        => 'Weixin',//Demo插件英文名，改成你的插件英文就行了
        'title'       => '微信插件',
        'description' => '包含微信登录、模板消息、公众号管理、微信支付等功能',
        'status'      => 1,
        'author'      => 'cwm',
        'version'     => '1.0'
    );

    public $hasAdmin = 1;//插件是否有后台管理界面

    // 插件安装
    public function install()
    {
        return true;//安装成功返回true，失败false
    }

    // 插件卸载
    public function uninstall()
    {
        return true;//卸载成功返回true，失败false
    }

    //我也不知道这个是啥
    public function run(){}

    /**
     * 发送微信模板消息
     * Author: cwm
     * Date: 2019/2/22
     */
    public function sendTemplateMsg($param){
        $openids = $param['openids'];
        $url = empty($param['url'])?'':$param['url'];
        $template_id = $param['template_id'];
        $data = $param['data'];
        if(empty($openids)){
            return false;
        }
        $config = $this->getConfig();
        $config = [
            'appid' => $config['tmpmsg_appid'],
            'secret' => $config['tmpmsg_secret'],
        ];

        $weixintemplatemsg_instance = new \plugins\weixin\lib\WeixinTemplatemsg($config);
        //组装数据
        $data = $weixintemplatemsg_instance->assemData($data,$template_id);

        if(is_array($openids)){
            foreach($openids as $openid){
                //发送消息
                $send = $weixintemplatemsg_instance->sendTemplateMsg($openid,$template_id,$data,$url);
            }
        }else{
            $openid = $openids;
            //发送消息
            $send = $weixintemplatemsg_instance->sendTemplateMsg($openid,$template_id,$data,$url);
        }

        return $send;

    }

    /**
     * 流程：
     * 1、调用统一下单，取得code_url，生成二维码
     * 2、用户扫描二维码，进行支付
     * 3、支付完成之后，微信服务器会通知支付成功
     * 4、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
     */
    public function payQrcode($param){
        //日志初始化
        $log_dir = './plugins/weixin/pay_logs/scan_code/';
        if(!is_dir($log_dir)){
            mkdir($log_dir,0777,true);
        }
        $log = new CLogFileHandler($log_dir.date('Y-m-d').'.log');
        Log::Init($log);
        $notify = new NativePay();

        $input = new WxPayUnifiedOrder();
        $input->SetBody($param['body']);//简要描述
        $input->SetOut_trade_no($param['out_trade_no']);//订单号
        $input->SetTotal_fee($param['total_fee']);//价格单位分
        $host = request()->server('REQUEST_SCHEME').'://'.request()->host();
        $param['notify_url'] = $host.'/'.trim($param['notify_url'],'/');
        $input->SetNotify_url($param['notify_url']);//异步回调地址
        $input->SetProduct_id($param['product_id']);//商品id，trade_type=NATIVE时，此参数必传

        if(isset($param['time_start'])){
            $input->SetTime_start($param['time_start']);//订单开始时间，yyyyMMddHHmmss
        }
        if(isset($param['time_expire'])){
            $input->SetTime_expire($param['time_expire']);//订单失效时间，yyyyMMddHHmmss
        }
        if(isset($param['goods_tag'])){
            $input->SetGoods_tag($param['goods_tag']);//商品标记
        }
        if(isset($param['attach'])){
            $input->SetAttach($param['attach']);//附加数据
        }
        $input->SetTrade_type("NATIVE");//扫码支付填NATIVE
        $result = $notify->GetPayUrl($input);
        if(!isset($result["code_url"])){
            return false;
        }
        $url = $result["code_url"];

        require 'plugins/weixin/lib/vendor/phpqrcode.php';
        $imgstr = \QRcode::base64($url,'L',5.5);

        return $imgstr;
    }

}