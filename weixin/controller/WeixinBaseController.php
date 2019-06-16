<?php
namespace plugins\weixin\controller;
use think\Cache;
use cmf\controller\PluginBaseController;
class WeixinBaseController extends PluginBaseController{
    public $cache;

    public function __construct() {
        parent::__construct();
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
}