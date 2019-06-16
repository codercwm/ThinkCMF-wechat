<?php
namespace plugins\weixin\controller; //Demo插件英文名，改成你的插件英文就行了
use think\Db;
class AdminIndexController extends WeixinBaseController {


    public function __construct() {
        parent::__construct();
        $adminId = cmf_get_current_admin_id();//获取后台管理员id，可判断是否登录
        if (!empty($adminId)) {
            $this->assign("admin_id", $adminId);
        }else{
            return $this->redirect(url("admin/Public/login"));
        }
    }

    //自定义插件配置
    public function setting(){
        $plugin_name = $this->getPlugin()->getName();
        $plugin_info = Db::name('plugin')->where('name',$plugin_name)->field('id,config')->findOrEmpty();
        $config = json_decode($plugin_info['config'],true);
        $this->assign('id',$plugin_info['id']);
        $this->assign('config',$config);
        return $this->fetch();
    }

    public function index(){
        $this->assign('links','');
        return $this->fetch();
    }
    //扫码登录说明
    public function scanCodeLogin(){
        return $this->fetch();
    }
    //扫码绑定管理员说明
    public function scanCodeBindUser(){
        return $this->fetch();
    }
    //模板消息说明
    public function tmplmsg(){
        return $this->fetch();
    }
    //扫码支付说明
    public function scanCodePay(){
        return $this->fetch();
    }
    //扫码支付示例
    public function scanCodePayExample(){


        $class = new \plugins\weixin\WeixinPlugin();
        $param = [
            'body' => '示例测试',
            'out_trade_no' => date('YmdHis'),
            'total_fee' => 1,
            'notify_url' => '/admin/test/notify2',
            'product_id' => 1,
        ];
        $imgstr = $class->payQrcode($param);
        $this->assign('imgstr',$imgstr);

        return $this->fetch();
    }


    public function clearCache(){
        $this->cache()->clear();
        $this->success('清除缓存成功');
    }



}