<?php
namespace plugins\weixin\lib\vendor\wxpay\log;

interface ILogHandler
{
    public function write($msg);

}