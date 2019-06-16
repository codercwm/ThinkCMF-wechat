<?php
namespace plugins\weixin\controller;
use think\Exception;
use think\Cache;
use think\Db;
use plugins\weixin\lib\WeixinTemplatemsg;

/**
 * 模板消息
 */
class TemplatemsgController extends WeixinBaseController{

    private $appid;
    private $secret;
    public function __construct() {
        parent::__construct();

        $adminId = cmf_get_current_admin_id();//获取后台管理员id，可判断是否登录
        if (!empty($adminId)) {
            $this->assign("admin_id", $adminId);
        }else{
            return $this->redirect(url("admin/Public/login"));
        }

        $config = $this->getPlugin()->getConfig();

        $this->appid = $config['tmpmsg_appid'];
        $this->secret = $config['tmpmsg_secret'];
    }

    public function index(){

    }

    /**
     * 模板消息列表
     * Author: cwm
     * Date: 2019/2/21
     */
    public function templateList(){
        $config = [
            'appid'=> $this->appid,
            'secret' => $this->secret,
        ];
        $weixintemplatemsg_instance = new WeixinTemplatemsg($config);
        $template_list = $weixintemplatemsg_instance->getTemplateList();
        $this->assign('template_list',$template_list);
        return $this->fetch();
    }

    /**
     * 发送模板消息页面
     */
    public function sendMsg(){
        $config = [
            'appid'=> $this->appid,
            'secret' => $this->secret,
        ];
        //消息模板列表
        $weixintemplatemsg_instance = new WeixinTemplatemsg($config);
        $template_list = $weixintemplatemsg_instance->getTemplateList();
        $this->assign('template_list',$template_list);

        //用户列表
        $user_list = Db::name('user')->alias('u')
            ->join('third_party_user t','t.user_id=u.id')
            ->where('app_id',$this->appid)
            ->field('t.openid,t.nickname,u.user_nickname')
            ->select()->toArray();

        //肉菜系统的openid存在user表
        try{
            $user_list2 = Db::name('user')
                ->whereNotNull('openid')
                ->where(['openid'=>['neq','']])
                ->field('openid,store_name as nickname,user_nickname')
                ->select()->toArray();
            $user_list = array_merge($user_list,$user_list2);
        }catch (Exception $e){

        }

        $this->assign('user_list',$user_list);

        return $this->fetch();
    }

    public function send(){

        $req = input();
        $template_id = input('template_id');
        $openids = empty($req['user_openid'])?[]:$req['user_openid'];
        $data = empty($req['template_data'])?[]:$req['template_data'];

        $param = [
            'openids' => $openids,
            'template_id' => $template_id,
            'data' => $data,
        ];

        //发送消息
        $weixin_plugin = new \plugins\weixin\WeixinPlugin();
        $res = false;
        try{
            $res = $weixin_plugin->sendTemplateMsg($param);
        }catch (Exception $e){
            $this->error($e->getMessage());
        }

        if($res){
            $this->success('已发送');
        }
        $this->error('发送失败');
    }

    /**
     * 获取模板内容和关键字
     * Author: cwm
     * Date: 2019/2/22
     */
    public function getTemplateContent(){
        $template_id = input('template_id');
        $config = [
            'appid'=> $this->appid,
            'secret' => $this->secret,
        ];
        //消息模板列表
        $weixintemplatemsg_instance = new WeixinTemplatemsg($config);
        $template = $weixintemplatemsg_instance->getTemplateList()[$template_id];
        $template_content = $template['content'];

        $keywords = $weixintemplatemsg_instance->getTemplateKeywords($template_id);

        $res = [
            'content' => $template_content,
            'keywords' => $keywords,
        ];

        $this->result($res);
    }



}