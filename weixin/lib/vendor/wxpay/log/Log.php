<?php
namespace plugins\weixin\lib\vendor\wxpay\log;

class Log
{
    private $handler = null;
    private $level = 15;

    private static $instance = null;

    private function __construct(){}

    private function __clone(){}

    public static function Init($handler = null,$level = 15)
    {
        if(!self::$instance instanceof self)
        {
            self::$instance = new self();
            self::$instance->__setHandle($handler);
            self::$instance->__setLevel($level);
        }
        return self::$instance;
    }


    private function __setHandle($handler){
        $this->handler = $handler;
    }

    private function __setLevel($level)
    {
        $this->level = $level;
    }

    public static function DEBUG($msg)
    {
        self::$instance->write(1, $msg);
    }

    public static function WARN($msg)
    {
        self::$instance->write(4, $msg);
    }

    public static function ERROR($msg,$backtrace=true)
    {

        $stack = "\r\n";
        $stack .= '    '.$msg."\r\n";
        if($backtrace){
            $debugInfo = debug_backtrace();
            foreach($debugInfo as $key => $val){
                $stack .=  '    ';
                if(array_key_exists("file", $val)){
                    $stack .= "file:" . $val["file"].',';
                }
                if(array_key_exists("line", $val)){
                    $stack .= "line:" . $val["line"].',';
                }
                if(array_key_exists("function", $val)){
                    $stack .= "function:" . $val["function"];
                }
                $stack .= "\r\n";
            }
        }

        self::$instance->write(8, $stack);
    }

    public static function INFO($msg)
    {
        self::$instance->write(2, $msg);
    }

    private function getLevelStr($level)
    {
        switch ($level)
        {
            case 1:
                return 'debug';
                break;
            case 2:
                return 'info';
                break;
            case 4:
                return 'warn';
                break;
            case 8:
                return 'error';
                break;
            default:

        }
    }

    protected function write($level,$msg)
    {
        if(($level & $this->level) == $level )
        {
            $stack = '['.date('Y-m-d H:i:s').']['.$this->getLevelStr($level).']';
            $stack .= "\r\n";
            $stack .= '    '.$msg."\r\n";
            $this->handler->write($stack);
        }
    }
}
