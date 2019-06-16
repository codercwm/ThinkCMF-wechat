<?php
namespace plugins\weixin\lib;
use think\Cache;

class WeixinUserinfo{
    public $appid;
    public $secret;
    public function __construct($config){
        $this->appid = $config['appid'];
        $this->secret = $config['secret'];
    }
    //构造要请求的参数数组，无需改动
    public function getCodeUrl($redirect_uri){
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri=".urlencode($redirect_uri)."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect";
        return $url;
    }

    /**
     * 获取用户信息
     * Author: cwm
     * Date: 2019/2/19
     */
    public function getUserInfo(){
        $result = $this->getAccessToken();
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$result['access_token']."&openid=".$result['openid'];
        $user_info =  $this->urlContents($url);
        $user_info = json_decode($user_info,true);
        if(!empty($user_info['errcode'])){
            exception($user_info['errmsg'],$user_info['errcode']);
        }
        $user_info = array_merge($result,$user_info);
        return $user_info;
    }

    /**
     *
     * Author: cwm
     * Date: 2019/2/19
     * @param
     * @param bool $refresh
     * @return mixed|string
     */
    public function getAccessToken($refresh=false){

        $code = input('get.code');
        if(!empty($code)){
            $access_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this->appid.'&secret='.$this->secret.'&code='.$code.'&grant_type=authorization_code';
            $result = $this->urlContents($access_token_url);
            $result = json_decode($result,true);
            if(!empty($result['errcode'])){
                exception($result['errmsg'],$result['errcode']);
            }
            return $result;
        }else{
            exception('code不能为空',0);
        }
    }

    /**
     * 接口请求
     * @param string $url
     * @return string
     */
    public function urlContents($url,$post_data = array()){
        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_URL, $url);
        if(!empty($post_data)) {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            if(is_array($post_data)) $post_data = json_encode($post_data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

}
