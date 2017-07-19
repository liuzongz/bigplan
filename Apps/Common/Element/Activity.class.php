<?php
namespace Common\Element;

use Common\Element\Base\Object;
use Common\Element\Favourable\Favourable;

/**
 * @property-read Favourable $favourable
 * @property-read array $data
 * @property array $config
 */
class Activity extends Object{

    /* @var Goods $goods */
    public $goods;

    private $_data;

    public function getData(){
        return $this->_data;
    }

    public function setData($data, $extend=false){
        if(is_array($data) && $data){
            $this->_data = $data;
        }elseif($data){
            $this->_data = $extend ?
                M()->table('__FAVOURABLE_ACTIVITY__ a')->join('__GOODS_ACTIVITY__ b ON a.act_id = b.act_id')->where(array('b.id'=>(int)$data))->find() :
                M('favourable_activity')->where(array('act_id'=>(int)$data))->find();
        }
        if($this->_data && ($this->_favourable = Element::$serviceLocator->get($this->_data['act_type'], false)) && isset($this->_data['config'])){
            $this->setConfig($this->_data['config']);
        }
        return $this->_data;
    }

    public function __construct($id=0, $data=array(), $config=array()){
        parent::__construct($config);
        $this->setData($data) || $this->setData($id);
    }

    /**
     * 活动数据是否存在,同时保证存在营销工具对应类
     * @return bool
     */
    public function isExist(){
        return $this->_data && $this->_favourable instanceof Favourable;
    }

    /**
     * 活动能否叠加
     * @return null
     */
    public function isAdd(){
        if(!$this->isExist()) return null;
        return $this->_data['is_add'];
    }

    /**
     * 绑定到商品的活动能否自定义配置
     * @return null
     */
    public function isSpecial(){
        if(!$this->isExist()) return null;
        return $this->_data['is_special'];
    }

    /**
     * 判断活动是否在有效期中
     * 未开始,进行中的活动有效
     * @return bool|null
     */
    public function isExpire(){
        if(!$this->isExist()) return null;
        $start  = $this->_data['act_starttime'];
        $end    = $this->_data['act_endtime'] ? $this->_data['act_endtime'] : 4102444800;//2100-01-01
        //过期的判断为无效,未开始和进行中的为有效
        return $end > $start && NOW_TIME < $end;
    }

    /**
     * 活动是否在报名时段
     * @return bool
     */
    public function isSign(){
        $start  = $this->_data['sign_starttime'];
        $end    = $this->_data['sign_endtime'] ? $this->_data['sign_endtime'] : 4102444800;//2100-01-01
        return $this->isExist() && $end > $start && NOW_TIME < $end && NOW_TIME > $start;
    }

    /**
     * 活动权限-商品分类Id
     * @return array|null
     */
    public function getAuthCate(){
        if(!$this->isExist()) return null;
        $cates          = array_column(list_to_tree(M('category')->field('cat_id, parent_id')->select(), 'cat_id', 'parent_id', '_child', 0, array(), true), null, 'cat_id');
        $auth_cates     = array();
        foreach(array_column(M('favourable_activity_rank')->where(array('act_id'=>$this->_data['act_id'], 'type'=>FAR_GOODS_CATE))->select(), 'id') as $cate){
            if(isset($cates[$cate])) $auth_cates = array_merge($auth_cates, array_column(tree_to_list(array($cates[$cate]), 'cat_id'), 'cat_id'));
        }
        return array_combine($auth_cates, $auth_cates);//去重复并主键索引
    }

    /**
     * 活动权限-店铺Id
     * @return array|bool|null
     */
    public function getAuthStore(){
        if(!$this->isExist()) return null;
        return array_column(M()->query("
            SELECT store_id FROM __STORE__ a
            INNER JOIN __FAVOURABLE_ACTIVITY_RANK__ b ON
                ((a.store_id = b.id AND b.type = ".FAR_STORE_SELECT.") OR
                (a.grade_id = b.id AND b.type = ".FAR_STORE_GRADE.")) AND
                b.act_id = {$this->_data['act_id']}
        "), 'store_id', 'store_id');
    }

    /**
     * 活动权限-用户等级Id
     * @return array
     */
    public function getAuthUser(){
        if(!$this->isExist()) return null;
        return array_column(M()->query("
            SELECT rank_id FROM __USER_RANK__ WHERE sort >= (
                SELECT sort
                    FROM __USER_RANK__ a
                    INNER JOIN __FAVOURABLE_ACTIVITY_RANK__ b ON a.rank_id = b.id
                        WHERE b.act_id = {$this->_data['act_id']} AND b.type = ". FAR_USER_RANK ."
                    ORDER BY sort DESC
                    LIMIT 1
            )
        "), 'rank_id', 'rank_id');
    }

    /* @var Favourable $_favourable */
    private $_favourable;

    public function getFavourable(){
        return $this->_favourable;
    }

    /**
     * 配置三级覆盖设置读取
     * @var
     */
    private $_config;

    public function setConfig($config){
        if(!$this->isExist()) return null;
        $return = $this->_favourable->mergerConfig(unserialize($this->_data['act_config']));
        if($this->isSpecial() && $config){
            $return = $this->_favourable->mergerConfig(unserialize($config), $return);
        }
        return $this->_config = $return;
    }

    public function getConfig($config=null){
        if(isset($this->_config) && null === $config){
            return $this->_config;
        }else{
            return $this->setConfig($config);
        }
    }

    /**
     * 获取商品中的活动数据(活动商品列表)
     * @return mixed|null
     */
    public function getDataOfGoods(){
        if(!$this->isExist()) return null;
        return M('goods_activity')->where(array('act_id'=>$this->_data['act_id']))->select();
    }

    /**
     * 获取购物车中的活动数据
     * @return mixed|null
     */
    public function getDataOfCart(){
        if(!$this->isExist()) return null;
        $where['act_id'] = $this->_data['act_id'];
        if($this->goods instanceof Goods)
            $where['a.goods_id'] = $this->goods->goodsId;
        return M()->table('__CART__ a')->join('__GOODS_ACTIVITY__ b ON a.extension_code = b.id AND a.goods_id = b.goods_id')->where($where)->select();
    }

    /**
     * 获取订单中的活动数据
     * @return mixed|null
     */
    public function getDataOfOrder(){
        if(!$this->isExist()) return null;
        $where['act_id'] = $this->_data['act_id'];
        if($this->goods instanceof Goods)
            $where['a.goods_id'] = $this->goods->goodsId;
        return M()->table('__ORDER_GOODS__ a')->join('__GOODS_ACTIVITY__ b ON a.extension_code = b.id AND a.goods_id = b.goods_id')->where($where)->select();
    }

    /**
     * 营销工具相关的特异性视图
     * @param $pos
     * @return null|string
     */
    public function view($pos){
        if(!$this->isExist()) return null;
        $pos = (string)$pos;
        $this->assign('_activity', $this);
        return $this->fetch("{$pos}.{$this->_favourable->name}");
    }

    /**
     * 活动价格实例
     * @return Price|null
     */
    public function price(){
        if(!$this->isExist()) return null;
        return $this->_favourable->price($this);
    }

    /**
     * 能否加入购物车
     * @return bool|null
     */
    public function cart(){
        if(!$this->isExist()) return null;
        return $this->_favourable->cart($this);
    }

    /**
     * 能否支付
     * @return bool|null
     */
    public function pay(){
        if(!$this->isExist()) return null;
        return $this->_favourable->pay($this);
    }

    /**
     * 支付完成回调
     * @param $order
     * @param $goods
     * @param array $level
     * @return array|null
     */
    public function paid($order, $goods, $level=array()){
        if(!$this->isExist()) return null;
        return $this->_favourable->paid($this, $order, $goods, $level);
    }

    /**
     * 能否发货
     * @return bool|null
     */
    public function deliver(){
        if(!$this->isExist()) return null;
        return $this->_favourable->deliver($this);
    }

}


