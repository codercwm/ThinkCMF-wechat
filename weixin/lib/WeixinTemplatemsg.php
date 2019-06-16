<?php
namespace plugins\weixin\lib;
use think\Cache;
class WeixinTemplatemsg{

    public $appid;
    public $secret;
    public $cache;
    public function __construct($config){
        $this->appid = $config['appid'];
        $this->secret = $config['secret'];

    }

    /**
     * 发送模板消息
     * @param $openid
     * @param $template_id
     * @param $url string 模板跳转链接
     * @param $data array 模板数据
     * @param $miniapp array 小程序数据
     * @return bool
     */
    public function sendTemplateMsg($openid, $template_id, $data=[], $url='')
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $data = [
            "touser" => $openid,
            "template_id" => $template_id,
            "url" => $url, //模板跳转链接

            //[
            //    "appid" => "xiaochengxuappid12345",
            //    "pagepath" => "index?foo=bar"
            //],

            "data" => $data, //模板数据
            //[
            //    "first" =>  [
            //        "value" => "恭喜你购买成功！",
            //        "color" => "#173177"
            //    ],
            //    "keynote1" => [
            //        "value" => "巧克力",
            //        "color" => "#173177"
            //    ],
            //    "remark" => [
            //        "value" => "欢迎再次购买！",
            //        "color" => "#173177"
            //    ]
            //]
        ];
        //注：url和miniprogram都是非必填字段，若都不传则模板无跳转；若都传，会优先跳转至小程序。
        //开发者可根据实际需要选择其中一种跳转方式即可。当用户的微信客户端版本不支持跳小程序时，将会跳转至url

        $url ="https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
        $this->asyncCallUrl($url, $data);
        return true;
        /*$return = $this->wxUrlContents($url, $data);
        if ($return === false) {
            return false;
        }
        return true;*/
    }

    /**
     * 获取用户所有模板消息
     * Author: cwm
     * Date: 2019/2/21
     * @return bool
     */
    public function getTemplateList($refresh=false)
    {
        if(!$refresh){
            $templatelist = $this->cache()->get('templatelist');
            if($templatelist){
                return $templatelist;
            }
        }
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $url ="https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token={$access_token}";
        $result = $this->wxUrlContents($url);
        $templatelist = $result['template_list'];
        $list = [];
        foreach($templatelist as $val){
            $list[$val['template_id']] = $val;
        }
        $this->cache()->set('templatelist',$list);
        return $templatelist;
    }

    /**
     * 获取模板keyword
     * Author: cwm
     * Date: 2019/2/22
     */
    public function getTemplateKeywords($template_id){
        $template_list = $this->getTemplateList();
        if(empty($template_list[$template_id])){
            $template_list = $this->getTemplateList(true);
        }
        if(empty($template_list[$template_id])){
            return false;
        }
        $template = $template_list[$template_id];
        $template_content = $template['content'];
        $p = '/{{(.*?)\.DATA}}/';
        preg_match_all($p,$template_content,$res);
        if(!empty($res[1])){
            return $res[1];
        }
    }

    /**
     * 组装模板数据
     * Author: cwm
     * Date: 2019/2/22
     */
    public function assemData($data,$template_id){
        $template_keywords = $this->getTemplateKeywords($template_id);
        $new_data = [];
        $i = 0;
        foreach($data as $val){
            if(!is_array($val)){
                $val = ['value' => $val];
            }
            if(empty($template_keywords[$i])){
                break;
            }
            $new_data[$template_keywords[$i]] = $val;
            $i++;
        }
        return $new_data;
    }

    /**
     *
     * Author: cwm
     * Date: 2019/2/19
     * @param
     * @param bool $refresh
     * @return mixed|string
     */
    public function getAccessToken()
    {
        $cache = $this->cache();
        $access_token = $cache->get('access_token');
        if(!empty($access_token)){
            //判断是否过了缓存期
            $expire_time = $access_token['expire_time'];
            $access_token = $access_token['access_token'];
            if ($expire_time > time()) {
                return $access_token;
            }
        }

        $access_token_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appid}&secret={$this->secret}";
        $result = $this->wxUrlContents($access_token_url);

        $access_token = $result['access_token'];
        $expires_in = $result['expires_in']-100;
        $expire_time = $expires_in+time();

        $cache->set('access_token',['access_token'=>$access_token,'expire_time'=>$expire_time],$expires_in);

        return $access_token;
    }

    /**
     * 获取缓存实例
     * Author: cwm
     * Date: 2019/2/21
     */
    public function cache(){
        if(empty($this->cache)){
            $cache_config = config('cache');
            $cache_config['prefix'] = 'plugin_weixin';

            $this->cache = new Cache($cache_config);
        }

        return $this->cache;
    }

    /**
     * 接口请求
     * @param string $url
     * @return string
     */
    public function wxUrlContents($url,$post_data = array()){
        $result = $this->urlContents($url,$post_data);
        $result = json_decode($result,true);
        if(!empty($result['errcode'])){
            exception($result['errmsg'],$result['errcode']);
        }
        return $result;
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

    //异步请求url
    public function asyncCallUrl($url,$data)
    {
        $url_info = parse_url($url);
        $host = $url_info['host'];
        $path = $url;
        if('https'==$url_info['scheme']){
            $fp = fsockopen( 'ssl://'.$host , 443, $errno, $errstr, 30);
        }else{
            $fp = fsockopen( $host , 80, $errno, $errstr, 30);
        }
        if(!$fp)
        {
            //如果失败，使用curl方式
            $this->urlContents($url,$data);
        }else{
            if(is_array($data)) $data = json_encode($data);
            $post = $data;//http_build_query($data);
            $len = strlen($post);
            $out = "POST $path HTTP/1.1\r\n";
            $out .= "Host: $host\r\n";
            $out .= "Content-type: application/x-www-form-urlencoded\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Content-Length: $len\r\n";
            $out .= "\r\n";
            $out .= $post."\r\n";
            fwrite($fp, $out);
            sleep(1); //不加这个sleep可能会导致请求发送不完整
        }
    }
}