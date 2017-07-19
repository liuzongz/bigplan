<?php
namespace Sales\Element\Base\Di;

use Sales\Element\Base\Object;
use Sales\Element\Element;
use Closure;

class ServiceLocator extends Object {

    //依据定义生成的组件实例
    private $_elements      = array();

    //纯组件定义
    private $_definitions   = array();

    //清空
    public function clear($id){
        unset($this->_definitions[$id], $this->_elements[$id]);
    }

    //组件定义/实例是否存在
    public function has($id, $checkInstance=false){
        return $checkInstance ? isset($this->_elements[$id]) : isset($this->_definitions[$id]);
    }

    //获取全部组件定义/配置
    public function getElements($returnDefinitions=true){
        return $returnDefinitions ? $this->_definitions : $this->_elements;
    }

    //批量设置组件定义
    public function setElements($elements){
        foreach($elements as $id => $element){
            $this->set($id, $element);
        }
    }

    //单个组件配置
    public function set($id, $definition){
        if($definition === null){
            //null设置删除
            unset($this->_elements[$id], $this->_definitions[$id]);
            return;
        }

        //新的设置之前,删除旧实例
        unset($this->_elements[$id]);

        //配置后在get中调用createObject
        if(is_object($definition) || is_callable($definition, true)){
            $this->_definitions[$id] = $definition;
        }elseif(is_array($definition)){
            if(isset($definition['class'])){
                $this->_definitions[$id] = $definition;
            }else{
                E("组件 {$id} 的定义配置数组必须要有一个 class 元素");
            }
        } else {
            E("组件 {$id} 的配置类型: " . gettype($definition) . "不存在");
        }
    }

    //获取组件实例
    public function get($id, $throwException=true){
        if(isset($this->_elements[$id])){
            //服务定位器全部都是单例
            return $this->_elements[$id];
        }

        if(isset($this->_definitions[$id])){
            $definition = $this->_definitions[$id];
            if(is_object($definition) && !$definition instanceof Closure){
                //主要区分回调,用createObject的扩展回调生成
                return $this->_elements[$id] = $definition;
            }else{
                return $this->_elements[$id] = Element::createObject($definition);
            }
        }elseif($throwException){
            E("未知的组件ID: {$id}");
        }else{
            return null;
        }
    }










    //public function __get(){}
    //public function __isset(){}
}


