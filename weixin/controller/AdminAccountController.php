<?php
namespace plugins\weixin\controller;
use plugins\weixin\validate\AdminAccountValidate;
use think\Exception;
use think\Cache;
use think\Db;
use plugins\weixin\lib\WeixinUserinfo;

/**
 * 后台管理员账号管理类
 */
class AdminAccountController extends WeixinBaseController{
    public function index(){

    }

    /**
     * 获取二维码页面
     * Author: cwm
     * Date: 2019/2/20
     */
    private function qrcodeUrl(){
        $config = $this->getPlugin()->getConfig();

        $appid = $config['login_appid'];
        $secret = $config['login_secret'];
        $config = [
            'appid'=> $appid,
            'secret' => $secret,
        ];
        //登录成功后用于验证用户信息的key
        $veriy_key = md5(uniqid());
        $weixinuserinfo_instance = new WeixinUserinfo($config);
        //微信扫码后的跳转地址
        //        $redirect_uri = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].cmf_plugin_url('Weixin://AdminAccount/scanCodeReturn',['verify_key'=>$veriy_key]);
        $redirect_uri = cmf_plugin_url('Weixin://AdminAccount/scanCodeReturn',['verify_key'=>$veriy_key]);
        //获取二维码url
        $url = $weixinuserinfo_instance->getCodeUrl($redirect_uri);
        require 'plugins/weixin/lib/vendor/phpqrcode.php';
        $qrcode_str = \QRcode::base64($url);
        $this->assign('qrcode_str',$qrcode_str);
        $this->assign('verify_key',$veriy_key);
        if(function_exists('cl')){
            cl($url);
        }
    }

    /**
     * 扫码登录，获取二维码
     * Author: cwm
     * Date: 2019/1/30
     */
    public function scanCodeLogin(){
        $admin_id = session('ADMIN_ID');
        if (!empty($admin_id)) {//已经登录
            header('Location: '.url("admin/Index/index"));
            exit;
        }
        $this->qrcodeUrl();
        return $this->fetch();
    }

    /**
     * 扫码成功进行登录操作
     * Author: cwm
     */
    public function scanCodeCheckLogin(){
        //登录成功后用于验证用户信息的key
        $verify_key = input('post.verify_key');
        if(empty($verify_key)){
            $this->error('登录失败，二维码已过期，请刷新页面重试');
        }

        if (hook_one('admin_custom_login_open')) {
            $this->error('您已经通过插件自定义后台登录！');
        }

        //从缓存中获取用户信息
        $wx_user_info = $this->cache()->get($verify_key);
        if(empty($wx_user_info)||empty($wx_user_info['openid'])){
            $this->error('');
        }
        Db::startTrans();
        $do_login = $this->doLogin($wx_user_info);

        if(1==$do_login['code']){
            Db::commit();
            //登入成功页面跳转
            $result = $do_login['data'];
            session('ADMIN_ID', $result["id"]);

            $name = empty($result["user_login"])?$result["nickname"]:$result["user_login"];
            session('name', $name);

            $token                     = cmf_generate_user_token($result["id"], 'web');
            if (!empty($token)) {
                session('token', $token);
            }

            cookie("admin_username", $name, 3600 * 24 * 30);
            session("__LOGIN_BY_CMF_ADMIN_PW__", null);
            $this->success('登录成功，正在跳转...', url("admin/Index/index"));
        }else{
            Db::rollback();
            $this->error($do_login['msg']);
        }

    }

    private function doLogin($wx_user_info){
        $result = Db::name('user')->alias('u')->join('third_party_user t','t.user_id=u.id')
            ->where(['t.openid'=>$wx_user_info['openid']])
            ->field('u.id,u.user_type,u.user_status,u.user_login,t.nickname,t.login_times,t.status')
            ->find();

        if (!empty($result) && $result['user_type'] == 1 ) {
            $groups = Db::name('RoleUser')
                ->alias("a")
                ->join('__ROLE__ b', 'a.role_id =b.id')
                ->where(["user_id" => $result["id"], "status" => 1])
                ->value("role_id");
            if ($result["id"] != 1 && (empty($result['user_status']) || empty($result['status']))) {
                return ['code'=>0,'msg'=>lang('USE_DISABLED')];
            }

            $update_user = [
                'last_login_ip' => get_client_ip(0, true),
                'last_login_time' => request()->time(),
                'avatar' => $wx_user_info['headimgurl'],
            ];
            $update_user_result = Db::name('user')->where(['id'=>$result['id']])->update($update_user);
            $update_third = [
                'last_login_ip' => get_client_ip(0, true),
                'last_login_time' => time(),
                'expire_time' => $wx_user_info['expires_in']+time()-100,
                'login_times' => $result['login_times']+1,
                'nickname' => $wx_user_info['nickname'],
                'last_login_ip' => get_client_ip(0, true),
                'access_token' => $wx_user_info['access_token'],
                'openid' => $wx_user_info['openid'],
            ];
            $update_third_result = Db::name('third_party_user')->where(['user_id'=>$result['id']])->update($update_third);

            if((false!==$update_user_result)&&(false!==$update_third_result)){

            }else{
                return ['code'=>0,'msg'=>lang('LOGIN_FAIL')];
            }

            return ['code'=>1,'msg'=>'登录成功','data'=>$result];
        } else {
            return ['code'=>0,'msg'=>'此微信未绑定账号，请联系管理员'];
        }
    }

    /**
     * 微信扫码后跳转地址
     * Author: cwm
     * Date: 2019/2/19
     */
    public function scanCodeReturn(){
        //扫码成功后用于验证用户信息的key
        $verify_key = input('verify_key');
        if(empty($verify_key)){
            $this->assign('status',0);
            $this->assign('msg','操作失败');
            $this->assign('desc','verify_key不能为空');
        }else{
            $config = $this->getPlugin()->getConfig();

            $appid = $config['login_appid'];
            $secret = $config['login_secret'];
            $config = [
                'appid'=> $appid,
                'secret' => $secret,
            ];
            $weixinuserinfo_instance = new WeixinUserinfo($config);
            try{
                //获取用户信息
                $wx_user_info = $weixinuserinfo_instance->getUserInfo();

                //用户信息存到缓存中，供浏览器获取，过期时间几秒就够了
                $this->cache()->set($verify_key,$wx_user_info,10);

                $this->assign('status',1);
                $this->assign('msg','扫码成功');
                $this->assign('desc','请继续在浏览器上进行操作');
            }catch (Exception $e){
                $errmsg = $e->getMessage();
                $this->assign('status',0);
                $this->assign('msg','操作失败');
                $this->assign('desc',$errmsg);
            }
        }
        return $this->fetch();
    }

    /**
     * 扫码绑定微信，显示二维码页面
     * Author: cwm
     * Date: 2019/2/20
     */
    public function scanCodeBindUser(){
        $this->qrcodeUrl();
        $user_id = input('user_id',0);//用户id，（要绑定哪个账号）
        $this->assign('user_id',$user_id);
        return $this->fetch();
    }

    /**
     * 扫码绑定微信，检查扫码结果
     * Author: cwm
     * Date: 2019/2/20
     */
    public function scanCodeCheckBind(){
        //登录成功后用于验证用户信息的key
        $verify_key = input('post.verify_key');
        $user_id = input('user_id',0);//用户id，（要绑定哪个账号）
        if(empty($verify_key)){
            $this->error('添加失败，二维码已过期，请刷新页面重试');
        }

        //从缓存中获取用户信息
        $wx_user_info = $this->cache()->get($verify_key);
        if(empty($wx_user_info)||empty($wx_user_info['openid'])){
            $this->error('');
        }
        $bind = $this->bindUser($wx_user_info,$user_id);
        $user_id = $bind['user_id'];
        $binding = $bind['binding'];//把这个也带过去，用于分辨这个微信以前是否已经绑定过
        if($user_id){
            $this->success('操作成功，正在跳转...', url("admin/User/edit",['id'=>$user_id,'binding'=>$binding]));
        }else{
            $this->error('操作失败，请联系管理员');
        }
    }

    /**
     * 扫码绑定微信，写入数据
     * Author: cwm
     * Date: 2019/2/20
     */
    public function bindUser($wx_user_info,$user_id=0){
        Db::startTrans();
        //检查是否存在
        $third_user_exists = Db::name('third_party_user')->where(['openid'=>$wx_user_info['openid']])->field('id as third_id,user_id')->find();

        //检查user表的id是否为空，如果为空即表示没有关联user表或已经删除此条数据，把数据删除掉，
        $user_exists = Db::name('user')->where(['id'=>$third_user_exists['user_id']])->value('id');
        //        dump($user_exists);die;
        if(empty($user_exists)){
            Db::name('third_party_user')->where(['id'=>$third_user_exists['third_id']])->delete();
            $third_user_exists = false;
        }

        if(empty($third_user_exists)){
            $time = time();
            //参数$user_id不为空表示是从已存在的账号中绑定微信，不需要在user表创建数据
            if(empty($user_id)){
                $insert_user = [
                    'create_time' => $time,
                    'user_nickname' => $wx_user_info['nickname'],
                ];
                $user_id = Db::name('user')->insertGetId($insert_user);
                $third_id = false;
            }else{
                $third_id = Db::name('third_party_user')->where(['user_id'=>$user_id])->value('id');
            }
            $insert_third = [
                'user_id' => $user_id,
                'create_time' => $time,
                'app_id' => $this->getPlugin()->getConfig()['login_appid'],
                'openid' => $wx_user_info['openid'],
                'union_id' => isset($wx_user_info['unionid'])?$wx_user_info['unionid']:'',
            ];
            if($third_id){
                $insert_third['id'] = $third_id;
                Db::name('third_party_user')->update($insert_third);
            }else{
                $third_id = Db::name('third_party_user')->insertGetId($insert_third);
            }
            $binding = 0;//告诉它这个微信是否已经绑定过
        }else{
            $user_id = $third_user_exists['user_id'];
            $third_id = $third_user_exists['third_id'];
            $binding = 1;//告诉它这个微信是否已经绑定过
        }

        $update_user = [
            'id' => $user_id,
            'sex' => $wx_user_info['sex'],
            'avatar' => $wx_user_info['headimgurl'],
        ];
        $update_third = [
            'id' => $third_id,
            'nickname' => $wx_user_info['nickname'],
        ];

        $update_user_res = Db::name('user')->update($update_user);
        $update_third = Db::name('third_party_user')->update($update_third);

        if($user_id&&$user_id&&(false!==$update_user_res)&&(false!==$update_third)){
            Db::commit();
            return ['user_id'=>$user_id,'binding'=>$binding];
        }else{
            Db::rollback();
            return false;
        }

    }


}