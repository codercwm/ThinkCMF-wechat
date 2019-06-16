<?php
namespace plugins\weixin\controller;

use think\db;
use plugins\weixin\lib\vendor\wxpay\log\Log;
use plugins\weixin\PayNotify;
use app\admin\model\PayInfoModel;
use app\admin\model\BusinessCardOrdersModel;
use app\admin\model\BusinessCardTemplateModel;
use app\admin\model\BusinessCardModel;

class WxNotifyController extends PayNotify
{
    public function index(){}

    public function handleOrder($data){
        Log::DEBUG("来了handleOrder:");
    }
}
