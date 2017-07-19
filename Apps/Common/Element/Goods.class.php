<?php
namespace Common\Element;

use Common\Element\Base\Object;

/**
 * @property-read double $price
 * @property-read int $goodsId
 * @property array $data
 */
class Goods extends Object{

    /**
     * 可配置的商品原始数据
     * @var mixed|null
     */
    private $_data;

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
            $this->_data = M('goods')->where(array('goods_id'=>(int)$data))->find();
        }
        self::$_activityList = null;
        return $this->_data;
    }

    /**
     * 商品ID对应数据是否存在
     * @return bool
     */
    public function isExist(){
        return !!$this->_data;
    }

    static private $_activityList = null;

    /**
     * 获取当前商品的活动列表
     * 已过滤活动权限,已过期活动
     * @return array|null
     */
    public function getActivityList(){
        if(!$this->isExist()) return null;
        if(!empty(self::$_activityList[$this->_data['goods_id']])) return self::$_activityList[$this->_data['goods_id']];
        $activity   = array();
        foreach(M('goods_activity')->where(array('goods_id'=>$this->_data['goods_id']))->select() as $each){
            /* @var Activity $t */
            $t = Element::createObject('activity', array($each['act_id']));

            //存在性
            if(!$t->isExist()) continue;

//            //分类权限
//            $auth_cate = $t->getAuthCate();
//            if($auth_cate && !in_array($this->_data['cat_id'], $auth_cate)) continue;
//
//            //店铺权限
//            $auth_store = $t->getAuthStore();
//            if($auth_store && !in_array($this->_data['store_id'], $auth_store)) continue;

            //验证活动时间
            if(!$t->isExpire()) continue;

            //设置复写后的配置
            $t->setConfig($each['config']);

            //设置寄主商品
            $t->goods = $this;

            //不能叠加的活动,清空之前的活动
            if(!$t->isAdd()) $activity = array();

            //活动追加
            $activity[$each['act_id']] = $t;
        }
        return self::$_activityList[$this->_data['goods_id']] = $activity;
    }

    /**
     * 商品店铺价格
     * @return mixed
     */
    public function getPrice(){
        return $this->_data['shop_price'];
    }

    /**
     * 商品Id
     * @return mixed
     */
    public function getGoodsId(){
        return $this->_data['goods_id'];
    }

}


