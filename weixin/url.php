<?php

return [
    'admin_account/scancodelogin'    => [
        'name'   => '微信扫码登录',
        'vars'   => [
            'id' => [
                'pattern' => '\d+',
                'require' => true
            ]
        ],
        'simple' => true
    ]
];