<?php
namespace Sales\Element\Base\Di;

use ReflectionClass;
use Sales\Element\Base\Object;

class Container extends Object {

    //类,构造函数,反射实例缓存
    private $_reflections       = array();

    //类,构造函数,参数依赖缓存
    private $_dependencies      = array();

    //实例,单例缓存
    private $_singletons        = array();

    //类,完整配置定义
    private $_definitions       = array();

    //类,构造函数参数,默认值缓存
    private $_params            = array();

    //类,构造函数,获取反射实例和参数依赖
    protected function getDependencies($class){
        if(isset($this->_reflections[$class])){
            return [$this->_reflections[$class], $this->_dependencies[$class]];
        }

        $dependencies = [];
        try{
            $reflection = new ReflectionClass($class);
        }catch(\Exception $e){
            E($e->getMessage());
        }

        $constructor = $reflection->getConstructor();
        if($constructor !== null){
            foreach($constructor->getParameters() as $param){
                if($param->isDefaultValueAvailable()){
                    $dependencies[] = $param->getDefaultValue();
                }else{
                    $c = $param->getClass();
                    $dependencies[] = Instance::of($c === null ? null : $c->getName());
                }
            }
        }

        $this->_reflections[$class]     = $reflection;
        $this->_dependencies[$class]    = $dependencies;

        return [$reflection, $dependencies];
    }

    //类,构造函数,参数解依赖实例化
    protected function resolveDependencies($dependencies, $reflection=null){
        /* @var ReflectionClass $reflection */
        foreach ($dependencies as $index => $dependency) {
            if ($dependency instanceof Instance) {
                if ($dependency->id !== null) {
                    //递归
                    $dependencies[$index] = $this->get($dependency->id);
                } elseif ($reflection !== null) {
                    $name = $reflection->getConstructor()->getParameters()[$index]->getName();
                    $class = $reflection->getName();
                    E("初始化{$class}类时,缺少必须的{$name}参数");
                }
            }
        }
        return $dependencies;
    }

    //生成类实例核心函数
    protected function build($class, $params, $config){
        /* @var $reflection ReflectionClass */
        list($reflection, $dependencies) = $this->getDependencies($class);

        foreach($params as $index => $param){
            $dependencies[$index] = $param;
        }

        //解依赖时,会有一个递归调用
        $dependencies = $this->resolveDependencies($dependencies, $reflection);
        if(!$reflection->isInstantiable()){
            E("{$reflection->name}类,无法实例化");
        }
        if(empty($config)){
            return $reflection->newInstanceArgs($dependencies);
        }

        if(!empty($dependencies) && $reflection->implementsInterface('Sales\Element\Base\Configurable')){
            $dependencies[count($dependencies) - 1] = $config;
            return $reflection->newInstanceArgs($dependencies);
        }else{
            $object = $reflection->newInstanceArgs($dependencies);
            foreach($config as $name => $value){
                $object->$name = $value;
            }
            return $object;
        }
    }

    //类,构造函数参数,最新值覆盖默认值
    protected function mergeParams($class, $params){
        if(empty($this->_params[$class])){
            return $params;
        }elseif(empty($params)){
            return $this->_params[$class];
        }else{
            $ps = $this->_params[$class];
            foreach($params as $index => $value){
                $ps[$index] = $value;
            }
            return $ps;
        }
    }

    //获取class参数对应实例
    public function get($class, $params=array(), $config=array()){
        if(isset($this->_singletons[$class])){
            //单例
            return $this->_singletons[$class];
        }elseif(!isset($this->_definitions[$class])){
            return $this->build($class, $params, $config);
        }

        $definition = $this->_definitions[$class];

        if(is_callable($definition, true)){
            $params = $this->resolveDependencies($this->mergeParams($class, $params));
            $object = call_user_func($definition, $this, $params, $config);
        }elseif(is_array($definition)){
            $concrete = $definition['class'];
            unset($definition['class']);

            $config = array_merge($definition, $config);
            $params = $this->mergeParams($class, $params);

            if($concrete === $class){
                $object = $this->build($class, $params, $config);
            }else{
                $object = $this->get($concrete, $params, $config);
            }
        }elseif(is_object($definition)){
            return $this->_singletons[$class] = $definition;
        }else{
            $object = null;
            E('未知的对象配置类型: '. gettype($definition));
        }

        if(array_key_exists($class, $this->_singletons)){
            //单例设置
            $this->_singletons[$class] = $object;
        }

        return $object;
    }











    //规范化set类实例配置
    protected function normalizeDefinition($class, $definition){
        if(empty($definition)){
            return array('class' => $class);
        }elseif(is_string($definition)){
            return array('class' => $definition);
        }elseif(is_callable($definition, true) || is_object($definition)){
            return $definition;
        }elseif(is_array($definition)){
            if(!isset($definition['class'])) {
                if(strpos($class, '\\') !== false){
                    $definition['class'] = $class;
                }else{
                    E('类定义配置数组必须要有一个 "class" 元素');
                }
            }
            return $definition;
        }else{
            E("{$class}类的配置类型: " . gettype($definition) . "不存在");
        }
    }

    //类实例设置
    public function set($class, $definition=array(), array $params=array()){
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);
        $this->_params[$class] = $params;
        unset($this->_singletons[$class]);
        return $this;
    }

    //类实例配置(单例)
    public function setSingleton($class, $definition=array(), array $params=array()){
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);
        $this->_params[$class] = $params;
        $this->_singletons[$class] = null;
        return $this;
    }

    //自定义:
    public function setClasses($classes){
        foreach($classes as $class => $definition){
            $params = isset($definition['params']) ? $definition['params'] : array();
            unset($definition['params']);
            $this->set($class, $definition, $params);
        }
    }








    //定义对象配置是否存在
    public function has($class){
        return isset($this->_definitions[$class]);
    }

    //定义对象配置是否存在(单例)
    public function hasSingleton($class, $checkInstance=false){
        return $checkInstance ? isset($this->_singletons[$class]) : array_key_exists($class, $this->_singletons);
    }

    //定义对象配置清除
    public function clear($class){
        unset($this->_definitions[$class], $this->_singletons[$class]);
    }

    //获取全部定义对象配置
    public function getDefinitions(){
        return $this->_definitions;
    }










    //public function invoke(){}
    //public function resolveCallableDependencies(){}
}


