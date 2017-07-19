<?php
namespace Sales\Element\Base;

use Think\View;
use Think\Think;
use Sales\Element\Element;

class Object implements Configurable {

    //内部错误信息
    public $error;

    /* @var View $_view */
    private $_view;

    public function __construct($config=array()){

        $this->_view  = Think::instance('Think\View');

        if (!empty($config)) {
            Element::configure($this, $config);
        }
        $this->init();
    }

    //视图方法与普通模版完全一致,仅模版位置定位到Sales/Element/View下
    public function fetch($templateFile='',$content='',$prefix=''){
        $default = C('VIEW_PATH');
        $default = $default ? $default : '';
        C('VIEW_PATH',  MODULE_PATH.'Element/View/'.C('CURRENT_MODULE').'/');
        $content = $this->_view->fetch(T($templateFile), $content, $prefix);
        C('VIEW_PATH', $default ? $default : '');
        return $content;
    }

    public function display($templateFile='', $content='', $prefix=''){
        echo $this->fetch($templateFile, $content, $prefix);
    }

    public function assign($name, $value=''){
        $this->_view->assign($name, $value);
        return $this->_view;
    }









    protected function init(){}

    public function __get($name){
        $getter = 'get' . $name;
        if(method_exists($this, $getter)){
            return $this->$getter();
        }elseif(method_exists($this, 'set' . $name)){
            E('无法获取只写属性: ' . get_class($this) . '::' . $name);
        }else{
            E('无法获取属性: ' . get_class($this) . '::' . $name);
        }
    }

    public function __set($name, $value){
        $setter = 'set' . $name;
        if(method_exists($this, $setter)){
            $this->$setter($value);
        }elseif(method_exists($this, 'get' . $name)){
            E('无法设置只读属性: ' . get_class($this) . '::' . $name);
        }else{
            E('无法设置属性: ' . get_class($this) . '::' . $name);
        }
    }

    public function __isset($name){
        $getter = 'get' . $name;
        if(method_exists($this, $getter)){
            return $this->$getter() !== null;
        }else{
            return false;
        }
    }

    public function __unset($name){
        $setter = 'set' . $name;
        if(method_exists($this, $setter)){
            $this->$setter(null);
        }elseif (method_exists($this, 'get' . $name)) {
            E('无法设置只读属性: ' . get_class($this) . '::' . $name);
        }
    }

    public function __call($name, $params){
        E('调用不存在方法: ' . get_class($this) . "::$name()");
    }

    public static function className(){
        return get_called_class();
    }










    //public function hasProperty(){}
    //public function canGetProperty(){}
    //public function canSetProperty(){}
    //public function hasMethod(){}
}


