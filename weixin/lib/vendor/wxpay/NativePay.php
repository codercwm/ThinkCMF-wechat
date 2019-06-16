<?php
namespace plugins\weixin\lib\vendor\wxpay;
use plugins\weixin\lib\vendor\wxpay\WxPayData\WxPayBizPayUrl;
use think\Exception;
use plugins\weixin\lib\vendor\wxpay\log\Log;
class NativePay{
    /**
     *
     * 生成扫描支付URL,模式一（不再使用）
     * @param BizPayUrlInput $bizUrlInfo
     */
    public function GetPrePayUrl($productId)
    {
        $biz = new WxPayBizPayUrl();
        $biz->SetProduct_id($productId);
        try{
            $config = new WxPayConfig();
            $values = WxPayApi::bizpayurl($config, $biz);
        } catch(Exception $e) {
            Log::ERROR($e->getMessage());
        }
        $url = "weixin://wxpay/bizpayurl?" . $this->ToUrlParams($values);
        return $url;
    }

    /**
     *
     * 参数数组转换为url参数
     * @param array $urlObj
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            $buff .= $k . "=" . $v . "&";
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     *
     * 生成直接支付url，支付url有效期为2小时,模式二
     * @param UnifiedOrderInput $input
     */
    public function GetPayUrl($input)
    {
        if($input->GetTrade_type() == "NATIVE")
        {
            try{
                $config = new WxPayConfig();
                $result = WxPayApi::unifiedOrder($config, $input);
                if('FAIL'==$result['return_code']){
                    Log::ERROR($result['return_msg']);
                    return false;
                }
                return $result;
            } catch(Exception $e) {
                Log::ERROR($e->getMessage());
            }
        }

        return false;
    }
}