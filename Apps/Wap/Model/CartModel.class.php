<?php

/**
 * 即牛 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: CartModel.class.php 17156 2016-01-05 09:59:47Z keheng $
*/

namespace Wap\Model;
use Think\Model;
class CartModel extends BaseModel {
    function get_consignee($user_id, $address_id = false){
        if ( !$user_id ){
            return false;
        } else {
            $m = M('user_address');
            $m->alias('ua')
                ->field('ua.*,r1.region_name as province_name,r2.region_name as city_name,r3.region_name as country_name,r4.region_name as district_name')
                ->join('LEFT JOIN __USERS__ u on u.user_id=ua.user_id')
                ->join('LEFT JOIN __REGION__ r1 on r1.region_id=ua.province')
                ->join('LEFT JOIN __REGION__ r2 on r2.region_id=ua.city')
                ->join('LEFT JOIN __REGION__ r3 on r3.region_id=ua.country')
                ->join('LEFT JOIN __REGION__ r4 on r4.region_id=ua.district');
            if ($address_id == 1){
                $m->where('u.user_id=' . $user_id . ' and ua.address_id=u.address_id');
                $arr = $m->find();
            } else if ($address_id > 1) {
                $m->where('u.user_id=' . $user_id . ' and ua.address_id=' . $address_id);
                $arr = $m->find();
            } else {
                $m->where('u.user_id=' . $user_id);
                $arr = $m->select();
            }
            if ($arr) {
                return $arr;
            } else {
                return false;
            }
        }
    }


    /**
     * 购物车提交的购物车ID验证
     * @param $user_id int
     * @param $rec_id array
     * @return array
     */
    public function is_cart_goods($user_id, $rec_id){
        $result = array();
        $cart_list = $this->cart_list2($user_id, $rec_id);
        if (empty($rec_id) or !is_array($rec_id)) {
            $result['error'] = 1;
            $result['message'] = '产品ID不存在！1';
        } else if (!$cart_list['list'] or count($cart_list['list']) <= 0) {
            $result['error'] = 2;
            $result['message'] = '未发现购物车内钩选产品';
            $result['url']  =   U('cart/index');
        } else {
            $sort_rec = asort($rec_id);
            $sort_ids = asort($cart_list['total']['ids']);
            $diff = array_diff($rec_id, $cart_list['total']['ids']);
            if (!empty($diff)) {
                $result['error'] = 3;
                $result['message'] = '产品ID异常！';
            } else {
                $store_id = 0;
                $is_this_store = 0;
                foreach ($cart_list['list'] as $k => $v) {
                    if ($k == 0) {
                        $store_id = $v['store_id'];
                    } elseif ($store_id != $v['store_id']){
                        $is_this_store = 1;
                        break;
                    }
                }
                if ($is_this_store) {
                    $result['error'] = 4;
                    $result['message'] = '不能同时购买不同店铺的产品，如果需要请分批购买！';
                } else {
                    $cart_list['total']['store_id'] = $store_id;
                    $result['error'] = 0;
                    $result['message'] = '产品ID正常！';
                    $result['data'] = $cart_list;
                }
            }
        }
        return $result;
    }

    function cart_list($user_id){
        if ( !$user_id ){
            return false;
        } else {
            $total = array('countAmt'=>0,'countNum'=>0,'list'=>'');
            $is_selected = 0;
            $cart_list = M('cart')
                ->alias('c')
                ->where('user_id=' . $user_id . ' and is_selected=1')
                ->field('c.*,g.goods_thumb')
                ->join('LEFT JOIN __GOODS__ g on g.goods_id=c.goods_id')
                ->select();
            if ($cart_list){
                foreach ($cart_list as $k => $v){
                    $data = D('cart_attr')
                        ->alias('ca')
                        ->field('ca.cart_attr_id,ca.goods_attr_id,ca.rec_id,ga.attr_value,a.attr_name')
                        ->join('LEFT JOIN __GOODS_ATTR__ ga on ga.goods_attr_id = ca.goods_attr_id')
                        ->join('LEFT JOIN __ATTRIBUTE__ a on a.attr_id = ga.attr_id')
                        ->where('ca.rec_id=' . $v['rec_id'])
                        ->select();
                    $cart_list[$k]['attr_list'] = $data;
                    $cart_list[$k]['url'] = U('Goods/index?id=' . $v['goods_id']);
                    if ($v['is_selected']){
                        $total['goods_price'] += $v['goods_number'] * $v['goods_price'];
                        $total['goods_number'] += $v['goods_number'];
                    } else {
                        $is_selected = 1;
                    }
                }
                $total['list'] = $cart_list;
                $total['goods_price_formated'] = $this->price_forma($total['countAmt']) ;

                return $total;
            } else {
                return false;
            }
        }
    }

    /**
     * 获取购物车产品
     * @param $user_id
     * @param array $rec_id
     * @param bool $select  是否已选产品
     * @return array|bool
     */
    function cart_list1($user_id, $rec_id = array(), $select = false){
        $is_gift = $this->is_gift();
        $where = ' c.user_id=' . $user_id;
        if (!empty($rec_id)) {
            $where .= ' and ' . $this->db_create_in($rec_id,'c.rec_id');
        }
        if ($select == true){
            $where .= ' and c.is_selected = 1 ';
        }
        if ($is_gift) {
            $where .= ' and (c.extension_code="gift" or c.extension_code= 4)';
        } else {
            $where .= ' and c.extension_code=""';
        }
        $is_selected = 0;
        $result = array('total'=>array(),'list'=>array());
        $total = array(
            'real_goods_count'      =>  0,
            'goods_number'          =>  0,
            'saving'                =>  0,
            'goods_price'           =>  0,
            'market_price'          =>  0,
            'deposit_price'         =>  0,//定金总额
            'integral'              =>  0,
        );
        //$m = M('cart');
        $cart_list = $this      //读取购物车列表
            ->alias('c')
            ->where($where)
            ->field('c.*,g.goods_thumb,g.deposit_price,g.shipping_type,g.shipping_cont,g.shipping_auto,g.goods_weight,s.store_name,si.store_label,c.integral')
            ->join('LEFT JOIN __GOODS__ g on g.goods_id=c.goods_id')
            ->join('LEFT JOIN __STORE__ s on c.store_id=s.store_id')
            ->join('LEFT JOIN __STORE_IMAGES__ si on c.store_id=si.store_id')
            ->group('c.rec_id')
            ->order('c.rec_id desc,c.store_id')
            ->select();
        //print_r($cart_list);
        if ($cart_list){
            $ids = array();
            foreach ($cart_list as $k => &$v){
                $ids[] = $v['rec_id'];
                $v['url']           = U('Goods/index?id=' . $v['goods_id']);
                if ($is_gift) {
                    $v['url'] .= '?gift';
                }
                $v['goods_thumb'] = $this->img_url($v['goods_thumb']);
                $v['integral_format'] = sprintf('%6.0f',$v['integral']) . ' ' . $this->_CFG['integral_name'] ;
                $v['goods_price_format'] = $this->price_format($v['goods_price']) ;

                $v['store_name'] = $v['store_id'] == 0 ? $this->_CFG['shop_name'] : $v['store_name'] ;

                if ($v['is_selected'] or !empty($rec_id)){
                    $total['goods_price']  += $v['goods_number'] * $v['goods_price'];
                    $total['market_price'] += $v['goods_number'] * $v['market_price'];
                    $total['deposit_price']+= $v['goods_number'] * $v['deposit_price'];//定金总额
                    $total['goods_number'] += $v['goods_number'];
                    $total['integral']     += $v['goods_number'] * $v['integral'];
                } else {
                    $is_selected = 1;
                }
                if ($v['is_real'] == 1) $total['real_goods_count']++;
            }

            $in = $this->db_create_in($ids,'ca.rec_id');
            $attribute = M('cart_attr')     //读取购物车产品属性
                ->alias('ca')
                ->field('ca.cart_attr_id,ca.goods_attr_id,ca.rec_id,ga.attr_value,a.attr_name')
                ->join('LEFT JOIN __GOODS_ATTR__ ga on ga.goods_attr_id = ca.goods_attr_id')
                ->join('LEFT JOIN __ATTRIBUTE__ a on a.attr_id = ga.attr_id')
                ->where($in)
                ->order('ca.rec_id')
                ->select();
            $store_id = 0;
            foreach ($cart_list as $k => &$v){
                $data = array();
                if ( $k == 0 ) $store_id = $v['store_id'];
                foreach ($attribute as $kk => $vv){
                    if ($vv['rec_id'] == $v['rec_id']) {
                        $data[$vv['attr_name']][] = $vv;
                        unset($attribute[$kk]);
                    }
                }
                $v['attr_list'] = $data;
            }//print_r($cart_list);
            $total['is_selected'] = $is_selected;
            $total['ids'] = $ids;
            $total['store_id'] = $store_id;
            $total['integral_format'] = intval($total['integral']) . ' ' . $this->_CFG['integral_name'] ;
            $total['goods_price_format'] = $this->price_format($total['goods_price']) ;
            $total['deposit_price_format'] = $this->price_format($total['deposit_price']);
            $total['flow_type'] = session('flow_type');

            $result['list'] = $cart_list;
            $result['total'] = $total;
            return $result;
        } else {
            return false;
        }
    }

    /**
     * 获取购物车产品
     * @param $user_id
     * @return array|bool
     */
    function cart_list2($user_id, $rec_id = array(), $select = false){
        $total = array('countAmt'=>0,'countNum'=>0,'list'=>'');
        $is_selected = 0;


        $where = ' c.user_id=' . $user_id . ' and g.is_delete=0 and g.is_on_sale=1  and g.is_check=0 ';
        if (!empty($rec_id)) {
            $where .= ' and ' . $this->db_create_in($rec_id,'c.rec_id');
        }
        if ($select == true){
            $where .= ' and c.is_selected = 1 ';
        }

        $is_selected = 0;
        $result = array('total'=>array(),'list'=>array());
        $total = array(
            'real_goods_count'      =>  0,
            'goods_number'          =>  0,
            'saving'                =>  0,
            'goods_price'           =>  0,
            'market_price'          =>  0,
            'deposit_price'         =>  0,//定金总额
            'integral'              =>  0,
        );

        $cart_list = $this
            ->alias('c')
            ->where($where )
            ->field('c.*,g.goods_thumb,g.deposit_price,g.shipping_type,g.shipping_cont,g.shipping_auto,
                        g.goods_weight,g.is_delete,g.is_on_sale,g.is_check,s.store_name,si.store_label,c.integral,cg.is_real')
            ->join('LEFT JOIN __GOODS__ g on g.goods_id=c.goods_id')
//            ->join('LEFT JOIN __GOODS_ACTIVITY__ ga on ga.goods_id=g.goods_id')
//            ->join('LEFT JOIN __FAVOURABLE_ACTIVITY__ fa on ga.act_id=fa.act_id')
            ->join('LEFT JOIN __CATEGORY__ cg on g.cat_id=cg.cat_id')
            ->join('LEFT JOIN __STORE__ s on c.store_id=s.store_id')
            ->join('LEFT JOIN __STORE_IMAGES__ si on c.store_id=si.store_id')
            ->group('c.rec_id')
            ->order('c.rec_id desc,c.store_id')
            ->select();

        if ($cart_list){
            $ids = array();
            $goods_model = new GoodsModel();


            foreach ($cart_list as $k => &$v){
                $ids[] = $v['rec_id'];
                $v['url']           = U('Goods/index') . '?id=' . $v['goods_id'] . '&store_token=' . $this->store_token;

                $v['goods_thumb'] = $this->img_url($v['goods_thumb']);
                $v['integral_format'] = sprintf('%6.0f',$v['integral']) . ' ' . $this->_CFG['integral_name'] ;
                $v['goods_price_format'] = '￥'.price_format($v['goods_price']) .'元';

//                $v['shop_price'] = $v['goods_price'];
                if ($v['extension_code'] != '') {
                    $v['url'] .= '&aid=' . AesEnCrypt($v['extension_code']);

                    $aid = M('GoodsActivity')->where(array('id'=>$v['extension_code']))->getField('act_id');

                    $result= curlpost('get_goods_act', array_merge(['goods_info'=>json_encode($v), 'aid'=>AesEnCrypt($aid)], C('API_APPINFO')));

                    if($result['status'] == 200){
                        $goods_act = $result['data'];
                        $v['integral'] = $goods_act['WB'];
//                        $v['goods_price_format'] = (string)$goods_act->price();
                        $v['act_name'] = $goods_act['data']['act_name'];
                        if($v['integral'] > 0){
                            $v['integral'] += $goods_model->get_attr_price(explode(',', $v['goods_attr_id']));
                            $v['goods_price_format'] = intval($v['goods_price']) > 0 ? '￥'. price_format($v['goods_price']) .'元 ' : '';
                            $v['goods_price_format'] .= $v['integral'] . $this->_CFG['integral_name'];
                        }
                    }
                }

                $v['store_name'] = $v['store_id'] == 0 ? $this->_CFG['shop_name'] : $v['store_name'] ;

                if ($v['is_selected'] or !empty($rec_id)){
                    $total['goods_price']  += $v['goods_number'] * $v['goods_price'];
                    $total['market_price'] += $v['goods_number'] * $v['market_price'];
                    $total['deposit_price']+= $v['goods_number'] * $v['deposit_price'];//定金总额
                    $total['goods_number'] += $v['goods_number'];
                    $total['integral']     += $v['goods_number'] * $v['integral'];
                } else {
                    $is_selected = 1;
                }
                if ($v['is_real'] == 1) $total['real_goods_count']++;
            }

            $in = $this->db_create_in($ids,'ca.rec_id');
            $attribute = M('cart_attr')     //读取购物车产品属性
            ->alias('ca')
                ->field('ca.cart_attr_id,ca.goods_attr_id,ca.rec_id,ga.attr_value,a.attr_name')
                ->join('LEFT JOIN __GOODS_ATTR__ ga on ga.goods_attr_id = ca.goods_attr_id')
                ->join('LEFT JOIN __ATTRIBUTE__ a on a.attr_id = ga.attr_id')
                ->where($in)
                ->order('ca.rec_id')
                ->select();
            $store_id = 0;
            foreach ($cart_list as $k => &$v){
                $data = array();
                if ( $k == 0 ) $store_id = $v['store_id'];
                foreach ($attribute as $kk => $vv){
                    if ($vv['rec_id'] == $v['rec_id']) {
                        $data[$vv['attr_name']][] = $vv;
                        unset($attribute[$kk]);
                    }
                }
                $v['attr_list'] = $data;
            }
            $total['is_selected'] = $is_selected;
            $total['ids'] = $ids;
            $total['store_id'] = $store_id;
            $total['integral_format'] = intval($total['integral']) > 0 ?intval($total['integral']) . ' ' . $this->_CFG['integral_name']: '';
            $total['goods_price_format'] = intval($total['goods_price']) > 0?'￥'.price_format($total['goods_price'])."元":'';
            $total['goods_price_format'] .= $total['integral_format'];
            $total['deposit_price_format'] = price_format($total['deposit_price']);
            $total['flow_type'] = session('flow_type');

            $result['list'] = $cart_list;
            $result['total'] = $total;
            return $result;
        } else {
            return false;
        }
    }

    /**
     * 获取购物车产品
     * @param $user_id
     * @return array|bool
     */
    function cart_list222($user_id, $rec_id = array(), $select = false){
        $total = array('countAmt'=>0,'countNum'=>0,'list'=>'');
        $is_selected = 0;


        $where = ' c.user_id=' . $user_id . ' and g.is_delete=0 and g.is_on_sale=1  and g.is_check=0 ';
        if (!empty($rec_id)) {
            $where .= ' and ' . $this->db_create_in($rec_id,'c.rec_id');
        }
        if ($select == true){
            $where .= ' and c.is_selected = 1 ';
        }

        $is_selected = 0;
        $result = array('total'=>array(),'list'=>array());
        $total = array(
            'real_goods_count'      =>  0,
            'goods_number'          =>  0,
            'saving'                =>  0,
            'goods_price'           =>  0,
            'market_price'          =>  0,
            'deposit_price'         =>  0,//定金总额
            'integral'              =>  0,
        );

        $cart_list = $this
            ->alias('c')
            ->where($where )
            ->field('c.*,g.goods_thumb,g.deposit_price,g.shipping_type,g.shipping_cont,g.shipping_auto,
                        g.goods_weight,g.is_delete,g.is_on_sale,g.is_check,s.store_name,si.store_label,c.integral,cg.is_real')
            ->join('LEFT JOIN __GOODS__ g on g.goods_id=c.goods_id')
//            ->join('LEFT JOIN __GOODS_ACTIVITY__ ga on ga.goods_id=g.goods_id')
//            ->join('LEFT JOIN __FAVOURABLE_ACTIVITY__ fa on ga.act_id=fa.act_id')
            ->join('LEFT JOIN __CATEGORY__ cg on g.cat_id=cg.cat_id')
            ->join('LEFT JOIN __STORE__ s on c.store_id=s.store_id')
            ->join('LEFT JOIN __STORE_IMAGES__ si on c.store_id=si.store_id')
            ->group('c.rec_id')
            ->order('c.rec_id desc,c.store_id')
            ->select();

        if ($cart_list){
            $ids = array();
            $goods_model = new GoodsModel();

            //$goods = Element::createObject('goods', array(''));

            foreach ($cart_list as $k => &$v){
                $ids[] = $v['rec_id'];
                $v['url']           = U('Goods/index') . '?id=' . $v['goods_id'];

                $v['goods_thumb'] = $this->img_url($v['goods_thumb']);
                $v['integral_format'] = sprintf('%6.0f',$v['integral']) . ' ' . $this->_CFG['integral_name'] ;
                $v['goods_price_format'] = '￥'.price_format($v['goods_price']) .'元';

//                $v['shop_price'] = $v['goods_price'];
                if ($v['extension_code'] != '') {
                    $v['url'] .= '&aid=' . AesEnCrypt($v['extension_code']);

                    $aid = M('GoodsActivity')->where(array('id'=>$v['extension_code']))->getField('act_id');
                    $goods->setData($v);
                    /* @var Activity $goods_act */
                    if(isset($goods->activityList[$aid])){
                        $goods_act = $goods->activityList[$aid];
//                        $v['goods_price'] = $goods_act->price()->RMB;

                        $v['integral'] = $goods_act->price()->WB;
//                        $v['goods_price_format'] = (string)$goods_act->price();
                        $v['act_name'] = $goods_act->data['act_name'];
                        if($v['integral'] > 0){
                            $v['integral'] += $goods_model->get_attr_price(explode(',', $v['goods_attr_id']));
                            $v['goods_price_format'] = intval($v['goods_price']) > 0 ? '￥'. price_format($v['goods_price']) .'元 ' : '';
                            $v['goods_price_format'] .= $v['integral'] . $this->_CFG['integral_name'];
                        }
                    }
                }

                $v['store_name'] = $v['store_id'] == 0 ? $this->_CFG['shop_name'] : $v['store_name'] ;

                if ($v['is_selected'] or !empty($rec_id)){
                    $total['goods_price']  += $v['goods_number'] * $v['goods_price'];
                    $total['market_price'] += $v['goods_number'] * $v['market_price'];
                    $total['deposit_price']+= $v['goods_number'] * $v['deposit_price'];//定金总额
                    $total['goods_number'] += $v['goods_number'];
                    $total['integral']     += $v['goods_number'] * $v['integral'];
                } else {
                    $is_selected = 1;
                }
                if ($v['is_real'] == 1) $total['real_goods_count']++;
            }

            $in = $this->db_create_in($ids,'ca.rec_id');
            $attribute = M('cart_attr')     //读取购物车产品属性
            ->alias('ca')
                ->field('ca.cart_attr_id,ca.goods_attr_id,ca.rec_id,ga.attr_value,a.attr_name')
                ->join('LEFT JOIN __GOODS_ATTR__ ga on ga.goods_attr_id = ca.goods_attr_id')
                ->join('LEFT JOIN __ATTRIBUTE__ a on a.attr_id = ga.attr_id')
                ->where($in)
                ->order('ca.rec_id')
                ->select();
            $store_id = 0;
            foreach ($cart_list as $k => &$v){
                $data = array();
                if ( $k == 0 ) $store_id = $v['store_id'];
                foreach ($attribute as $kk => $vv){
                    if ($vv['rec_id'] == $v['rec_id']) {
                        $data[$vv['attr_name']][] = $vv;
                        unset($attribute[$kk]);
                    }
                }
                $v['attr_list'] = $data;
            }
            $total['is_selected'] = $is_selected;
            $total['ids'] = $ids;
            $total['store_id'] = $store_id;
            $total['integral_format'] = intval($total['integral']) > 0 ?intval($total['integral']) . ' ' . $this->_CFG['integral_name']: '';
            $total['goods_price_format'] = intval($total['goods_price']) > 0?'￥'.price_format($total['goods_price'])."元":'';
            $total['goods_price_format'] .= $total['integral_format'];
            $total['deposit_price_format'] = price_format($total['deposit_price']);
            $total['flow_type'] = session('flow_type');

            $result['list'] = $cart_list;
            $result['total'] = $total;
            return $result;
        } else {
            return false;
        }
    }

    /**
     * 清空购物车
     * @param $ids array
     * @param $user_id int
     * @param int $type 类型：默认普通商品
     */
    public function clear_cart($ids, $user_id, $type = CART_GENERAL_GOODS) {
        /*$sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE session_id = '" . SESS_ID . "' AND rec_type = '$type'";
        $GLOBALS['db']->query($sql);*/
        M('cart')->where("rec_type = '$type' and user_id={$user_id} and " . $this->db_create_in($ids,'rec_id'))->delete();
    }

    /**
     * 获取购物车内免运费产品的数量
     * @param $user_id
     * @param array $rec
     * @return mixed
     */
    public function get_cart_count($user_id,$rec = array()) {
        return  M('cart')
            ->where("`extension_code` != 'package_buy' AND `is_shipping` = 0 and user_id={$user_id} and " . $this->db_create_in($rec, 'rec_id'))
            ->count();
    }

    //清空购物虚拟商品
    public function del_cat_real($user_id)
    {
        $where['user_id'] = $user_id;
        $goods = M('cart')
            ->alias('c')
                ->field('c.*,cg.is_real')
                ->join('LEFT JOIN __GOODS__ g on g.goods_id=c.goods_id')
                ->join('LEFT JOIN __CATEGORY__ cg on g.cat_id=cg.cat_id')
            ->where($where)
            ->select();
        $ids = [];
        foreach ($goods as $v){
            if(intval($v['is_real'])){
                $ids[] = $v['rec_id'];
            }
        }
        if(!empty($ids)) {
            $map['rec_id'] = ['in', $ids];
            D('cart')->where($map)->delete();
            D('cart_attr')->where($map)->delete();
            return true;
        }
        return false;
    }
}