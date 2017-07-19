<?php
namespace Sales\Element;

use Sales\Element\Base\Object;

/**
 * @property Activity $Activity
 * @property array $data
 * @property int $projectId
 */
class Project extends Object{
    /**
     * 可配置的商品原始数据
     * @var mixed|null
     */
    private $_data;

    /**@var  Activity $_activity*/
    static private $_activity = null;

    public function __construct($id=0, $data=array(), $config=array()){
        parent::__construct($config);
        $this->setData($data) || $this->setData($id);
    }

    public function getData(){
        return $this->_data;
    }

    public function setData($data){
        if(is_array($data) && $data){
            $this->_data = $data;
        }elseif($data){
            $this->_data = M('favourable_user_activity')->where(array('id'=>(int)$data))->find();
        }
        if($this->_data) $this->getActivity();
        return $this->_data;
    }

    /**
     * 商品ID对应数据是否存在
     * @return bool
     */
    public function isExist(){
        return !!$this->_data;
    }

    public function getActivity(){

        if(!$this->isExist()) return null;
        if(self::$_activity) return self::$_activity;
        /* @var Activity $t */
        $t = Element::createObject('activity', array($this->_data['act_id']));

        //存在性
        if(!$t->isExist()) return null;

        //验证活动时间
        if(!$this->isExpire()) return null;

        //设置复写后的配置
        $t->setConfig($this->_data['config']);

        //设置寄主商品
        $t->project = $this;

        //活动追加
        return self::$_activity = $t;
    }

    public function isExpire(){
        if(!$this->isExist()) return null;
        $start  = $this->_data['act_starttime'];
        $end    = $this->_data['act_endtime'];
        //过期的判断为无效,未开始和进行中的为有效
        return $end > $start && NOW_TIME < $end && NOW_TIME>$start;
    }

    /**
     * 商品Id
     * @return mixed
     */
    public function getProjectId(){
        return $this->_data['id'];
    }
}