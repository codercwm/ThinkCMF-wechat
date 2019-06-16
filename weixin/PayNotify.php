<?php

namespace plugins\weixin;
use plugins\weixin\lib\vendor\wxpay\WxPayNotify;
use plugins\weixin\lib\vendor\wxpay\WxPayData\WxPayOrderQuery;
use plugins\weixin\lib\vendor\wxpay\WxPayConfig;
use plugins\weixin\lib\vendor\wxpay\WxPayApi;
use plugins\weixin\lib\vendor\wxpay\log\Log;
use plugins\weixin\lib\vendor\wxpay\log\CLogFileHandler;
class PayNotify extends WxPayNotify{
    public function __construct() {

        //日志初始化
        $log_dir = './plugins/weixin/pay_logs/pay_notify/';
        if(!is_dir($log_dir)){
            mkdir($log_dir,0777,true);
        }
        $log = new CLogFileHandler($log_dir.date('Y-m-d').'.log');
        Log::Init($log);

        $this->config = new WxPayConfig();

        $this->Handle($this->config,false);
    }

    private $config;

    //查询订单
    public function queryOrder($transaction_id)
    {
        $input = new WxPayOrderQuery();
        $input->SetTransaction_id($transaction_id);

        $result = WxPayApi::orderQuery($this->config, $input);
//        Log::DEBUG("query:" . json_encode($result));
        if(array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS")
        {
            return true;
        }
        return false;
    }

    /**
     *
     * 回包前的回调方法
     * 业务可以继承该方法，打印日志方便定位
     * @param string $xmlData 返回的xml参数
     *
     **/
    public function LogAfterProcess($xmlData)
    {
        Log::ERROR("call back， return xml:" . $xmlData);
        return;
    }

    //重写回调处理函数
    /**
     * @param WxPayNotifyResults $data 回调解释出的参数
     * @param WxPayConfigInterface $config
     * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    public function NotifyProcess($objData, $config, &$msg)
    {
        $data = $objData->GetValues();
        //TODO 1、进行参数校验
        if(!array_key_exists("return_code", $data)
            ||(array_key_exists("return_code", $data) && $data['return_code'] != "SUCCESS")) {
            //TODO失败,不是支付成功的通知
            //如果有需要可以做失败时候的一些清理处理，并且做一些监控
            $msg = "异常异常";
            return false;
        }
        if(!array_key_exists("transaction_id", $data)){
            $msg = "输入参数不正确";
            return false;
        }

        //TODO 2、进行签名验证
        try {
            $checkResult = $objData->CheckSign($this->config);
            if($checkResult == false){
                //签名错误
                Log::ERROR("签名错误...");
                return false;
            }
        } catch(Exception $e) {
            Log::ERROR($e->getMessage());
        }

        //TODO 3、处理业务逻辑
//        Log::DEBUG("call back:" . json_encode($data));
        $notfiyOutput = array();


        //查询订单，判断订单真实性
        if(!$this->queryOrder($data["transaction_id"])){
            $msg = "订单查询失败";
            return false;
        }

        //子类中定义这个方法进行道德处理
        $this->handleOrder($data);

        return true;
    }
}