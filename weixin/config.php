<?php
return [
    'custom_config' => [// 在后台插件配置表单中的键名 ,会是config[custom_config]，这个键值很特殊，是自定义插件配置的开关
        'title' => '自定义配置处理', // 表单的label标题
        'type'  => 'text',// 表单的类型：text,password,textarea,checkbox,radio,select等
        'value' => '1',// 如果值为1，表示由插件自己处理插件配置，配置入口在 AdminIndex/setting
        'tip'   => '自定义配置处理' //表单的帮助提示
    ],
];