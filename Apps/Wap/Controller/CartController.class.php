<?php

namespace Wap\Controller;
use Wap\Model;

class CartController extends WapController {
    protected function _initialize(){
        parent::_initialize();
    }

    public function index(){
        $this->void_user();
        $this->get_dealer_status();
        $flow = new Model\CartModel();

        //清空购物车虚拟商品
        $flow->del_cat_real($this->user_id);

        $total = $flow->cart_list2($this->user_id);

        foreach ($total['list'] as $key => &$value){

//            $value['is_valid'] = true;//判断购物车商品是否失效
//            if($value['is_delete'] != '0' or $value['is_on_sale'] != '1' or $value['is_check'] != '0'){
//                $value['is_valid'] = false;
//            }

            $value['market_price'] = price_format($value['market_price']);
            $value['goods_thumb'] = $flow->img_url($value['goods_thumb']);
            $total['store_list'][$value['store_id']]['store_id'] = $value['store_id'];
            $total['store_list'][$value['store_id']]['store_name'] = $value['store_name'];
            if ($value['is_selected']) $total['store_list'][$value['store_id']]['is_selected'] += 1;
            $total['store_list'][$value['store_id']]['list'][] = $value;

        }

        $tkd = [
            'title'         =>  ' 购物车',
            'keywords'      =>  '',
            'discription'   =>  ''
        ];

        $this->assign('tkd', $tkd);
        $this->assign('total', $total);
        if(!$total['list']){
            $goodsModel = new Model\GoodsModel();
            $goods_list = $goodsModel->get_rand_act_goods(2, 300, $this->wx_store_id);
            $this->assign('goods_list',$goods_list);
        }

        $this->assign('cur_page','cart');
        $this->assign('user_head',['title' => '购物车']);
        //$this->assign('user_head', $user_haed);
        //$this->assign('flow_type', $flow_type);
        $this->assign('del_flow_pro_addr',U('Cart/drop_goods'));        //删除购物车产品URL
        $this->assign('chage_goods_num',U('Cart/chage_goods_num'));     //修改数量URL
        $this->assign('checked_cart',U('Cart/checked_cart'));           //选中产品URL
        $this->assign('checkout_addr',U('Checkout/index'));              //订单提交页面

        $this->set_form_token();

        $this->display();
    }

    public function addtocart(){
//        $this->check_token();
        $this->void_user();
        $act_id = AesDeCrypt(I('post.act_id','','trim'));  //解密活动id
        $goods_id   = I('goods_id', 0,'intval');
        $spec       = I('spec','','trim');
        $quick      = I('request.quick',0,'intval');
        $number     = I('request.number',0,'intval');
        $parent     = I('request.parent',0,'intval');
        $back_url   = I('post.back_url','','trim');
        $buynow     = I('post.buynow',0,'intval');
        $back_url = !empty($back_url) ? AesEnCrypt($back_url) : '';

        $result = $this->result;

        if ($number <= 0){
            $result['error'] = 1;
            $result['message'] = '请选择产品数量！';
        }else if ($goods_id <= 0){
            $result['error'] = 2;
            $result['message'] = '无效产品ID！';
        }else{
            $goods = new Model\GoodsModel();
            $goods->setUid($this->user_id);
            //$arr = D('goods')->where("goods_id='$goods_id' and is_delete=0 and is_on_sale=1")->field('is_offline_sale,goods_number')->find();
            $goods_condition = 'g.goods_id=' . $goods_id;
            if($act_id) $goods_condition .= ' and ga.act_id=' . $act_id;
            $arr = $goods->get_GoodsInfo($goods_condition);
            if($act_id){
                $apiData= curlpost('get_goods_act', array_merge(['goods_info'=>json_encode($arr), 'aid'=>AesEnCrypt($act_id)], C('API_APPINFO')));

                $aid = 0;
                if($apiData['status'] != 200) {
                    $result['error'] = 4;
                    $result['message'] = '商品活动已结束或暂未开放购买权限！';
                    $this->ajaxReturn($result);
                }elseif(!$apiData['data']['is_cart']){
                    $result['error'] = 3;
                    $result['message'] = '加入购物车失败！';
                    $this->ajaxReturn($result);
                }else{
                    $aid = $arr['gid'];
                }
            }
            if($arr === false or $arr['is_offline_sale'] == 1){
                $result['error']   = 4;
                $result['message'] = '产品不存在或者非线上销售产品或未开放销售！';
            }else if ($number > $arr['goods_number'] ){
                $data['url'] = U('Cart/Add_booking', array('id'=>$goods_id,'spec'=>$spec));
                $result['error']   = 5;
                $result['message'] = '产品库存不足！';
                $result['content'] = $data;
            }else if(!$act_id and $arr['user_rank'] > $this->user_info['user_rank']){
                $result['error']   = 6;
                $result['message'] = '此商品暂时还未开放购买权限！';
            }else {
                $res = $goods->get_goods_attr($goods_id,1);
                $res = $this->get_array_sort($res,'attr_id');//print_r($res);
                $spec_arr_str = [];
                if ($res) {
                    $spec_arr = explode(',',$spec);
                    $get_spec_array = [];
                    $attr_id_1 = [];        //必选项集合
                    $is_mast_check = false;
                    foreach ($res as $v) {      //获取ID及是否存在必选项未选择
                        $get_spec_array[] = $v['goods_attr_id'];

                        if(in_array($v['goods_attr_id'],$spec_arr)){//产品属性名称
                            $spec_arr_str[] = $v['attr_name'].'：'.$v['attr_value'];
                        }
                        if ($v['attr_type'] == 1 ) {  // and !in_array($v['goods_attr_id'], $spec_arr)
                            $attr_id_1[$v['attr_id']][] = $v['goods_attr_id'];
                        }
                    }
                    //判断必选项是否有没有选择的项
                    foreach($spec_arr as $vv) {
                        foreach($attr_id_1 as $k => $v) {
                            if (in_array($vv, $v)) {
                                unset($attr_id_1[$k]);
                            }
                        }
                    }
                    if (!empty($attr_id_1)) {
                        $result['error']            = 7;
                        $result['message']          = '有必选属性未选择,请重新选择属性！';
                        $result['data'] = $res;
                    } else {
                        $spec_ok = 0;
                        foreach ($spec_arr as $v){      //检查是否存在无效属性被选择。
                            if (!in_array($v,$get_spec_array)){
                                $spec_ok = 1;
                                break;
                            }
                        }
                        if ($spec_ok != 0){
                            $result['error']   = 8;
                            $result['message'] = '产品属性非法！';
                        }
                    }
                } else {
                    $spec_arr = array();
                }
                if ($result['error'] == 0) {
//                    //来自U('Good/index')的表单令牌验证
//                    $this->check_token();

                    $s = $goods->addto_cart($goods_id, $number, $spec_arr, $spec_arr_str, $parent, $buynow, $act_id, $aid);
                    if ($s['error'] == 0){
                        $data['numb'] = D('cart')->where('user_id=' . $this->user_id)->getfield('count(goods_number)');
                        $data['cart_url'] = U('Cart/index');
                        $data['one_step_buy'] = C('one_step_buy');
                        $data['confirm_type'] = !empty($this->_CFG['confirm_type']) ? $this->_CFG['confirm_type'] : 2;
                        $data['buynow_url'] = U('Checkout/index') . '?id=' . $s['data'];// . '&flow_type=' . $this->set_form_token();
                        $result['error'] = 0;
                        $result['message'] = '加入购物车成功！';
                        $result['contents'] = $data;
                    }else{
                        $result['error'] = 9;
                        $result['message'] = $s['msg'];
                    }
                }
            }
        }
        if ($this->ajax) {
            $this->ajaxReturn($result);
        } else {
            if ($result['error']) {
                $this->error($result['message']);
            } else {
                $this->success($result['message']);
            }

        }
    }

    function drop_goods(){
        $user_id = intval(session('user_id'));
        $ssid = session($this->ssid_name);
        //$sess = $this->_Public->getSessionIDorUserID();
        $goods = I('post.goods');
        $result = array('error' => 0, 'message' => '', 'content' => array());
        $goods_arr = explode(',',$goods);
        $goods_ok = 0;
        $m = D('cart');
        foreach ($goods_arr as $v){
            if (!is_numeric($v)){
                $goods_ok = 1;
                break;
            }
        }
        if ($goods === false || $goods_ok != 0){
            $result['error'] = 1;
            $result['message'] = '删除失败！';
        } else {
            $where = "user_id={$this->user_id} and rec_id in ($goods)";
            $arr = $m->where($where)->getfield('count(*)');
            if ($arr != count($goods_arr)){
                $result['error'] = 2;
                $result['message'] = '选中的产品中存在空的ID！';
            } else {
                if ($m->where($where)->delete()){
                    //删除相应的购物车属性
                    D('cart_attr')->where('rec_id in (' . $goods . ')')->delete();
                    $result['error'] = 0;
                    $result['message'] = '删除成功！';
                    $result['content']['goods'] = $goods;
                } else {
                    $result['error'] = 3;
                    $result['message'] = '删除失败！';
                }
            }
        }
        $this->ajaxReturn($result);
    }

    function chage_goods_num(){
        $id = intval(I('post.id'));
        $num = intval(I('post.num'));
        $ssid = session($this->ssid_name);
        $user_id = intval($this->user_id);
        $result = array('error' => 0, 'message' => '', 'content' => array());
        //$sess = $this->_Public->getSessionIDorUserID();
        if ($num > 500 or $num < 1){ $num = 1;}
        $where = "user_id={$this->user_id} and rec_id  = $id";
        $m = D('cart');
        $n = $m->where($where)->find();
        if (!$n){
            $result['error'] = 1;
            $result['message'] = '无效购物车物品！';
        } else {
            $max_num = D('Goods')->where('goods_id=' . $n['goods_id'])->getfield('goods_number');
            if ($num > $max_num) {
                $result['error'] = 5;
                $result['message'] = '产品数量不能大于产品库存！';
            } else if ($m->where($where)->save(array('goods_number'=>$num))){
                $arr = $m->where("user_id={$this->user_id}" . ' and is_selected=1')->select();
                $total = array();
                foreach ($arr as $v){
                    $total['integral'] += $v['goods_number'] * $v['integral'];
                    $total['goods_price'] += $v['goods_number'] * $v['goods_price'];
                    $total['goods_number'] += $v['goods_number'];
                }

                $total['goods_price_formated'] = '￥' . price_format($total['goods_price']);


                $result['content'] = $total;
                $result['error'] = 0;
                $result['message'] = '修改成功！';
                //$result['content']['goods_number'] = $num;
            } else {
                $result['error'] = 2;
                $result['message'] = '修改失败！';
                //$result['sql'] = $m->getlastsql();
            }
        }
        $this->ajaxReturn($result);
    }

    function checked_cart(){
        $goods = I('post.goods');
        $result = array('error' => 0, 'message' => '', 'content' => array('goods_number'=>0,'goods_price'=>0));
        $m = new Model\CartModel();
        $is_gift = $m->is_gift();
        $where = "user_id={$this->login_info['user_id']}";//$this->_Public->getSessionIDorUserID();

        if ($goods == false or $goods == 'false'){   //不选则全部取消
            $m->where($where . ' and is_selected=1')->save(array('is_selected'=>0));
            $result['error'] = 0;
            $result['message'] = '修改成功！';
        } else {
            $goods_arr = explode(',', $goods);
            $num = 0;
            foreach ($goods_arr as $v){
                if (!is_numeric($v)) {
                    $num = 1;
                    break;
                }
            }
            if ($num != 0){
                $result['error'] = 1;
                $result['message'] = '产品属性非法！';
            } else {
                $a = $m->where($where . ' and is_selected=1')->save(array('is_selected'=>0));
                $b = $m->where($where . ' and rec_id in (' . $goods . ')')->save(array('is_selected'=>1));//保存选择
                $arr = $m->where($where . ' and is_selected=1')->select();
                $total = array();
                $total['integral_price'] = 0;
                $total['goods_price'] = 0;
                $total['goods_number'] = 0;

                $goods_model = new Model\GoodsModel();

                //$goods = Element::createObject('goods', array(''));

                foreach ($arr as $v){

                    $integral = 0;
                    if ($v['extension_code'] != '') {

                        $aid = M('GoodsActivity')->where(array('id'=>$v['extension_code']))->getField('act_id');
                        //$goods->setData($v);

                        //请求接口活动数据
                        $apiData= curlpost('get_goods_act', array_merge(['goods_id'=>$v['goods_id'], 'aid'=>AesEnCrypt($aid)], C('API_APPINFO')));

                        if($apiData['status'] == 200){
                            $integral = $apiData['data']['WB'];
                            if ($integral > 0) {
                                $integral += $goods_model->get_attr_price(explode(',', $v['goods_attr_id']));
                            }
                        }
//                        if (isset($goods->activityList[$aid])) {
//                            $goods_act = $goods->activityList[$aid];
//                            $integral = $goods_act->price()->WB;
//                            if ($integral > 0) {
//                                $integral += $goods_model->get_attr_price(explode(',', $v['goods_attr_id']));
//                            }
//                        }
                    }

                    $total['integral_price'] += $v['goods_number'] * $integral;
                    $total['goods_price'] += $v['goods_number'] * $v['goods_price'];
                    $total['goods_number'] += $v['goods_number'];
                }

                $result['content'] = $total;
                $result['error'] = 0;
                $result['message'] = '修改成功！';
            }
        }
        $result['content']['flow_type'] = session('flow_type');
        $result['content']['integral_formated'] = intval($result['content']['integral_price']) > 0 ? intval($result['content']['integral_price']) . ' ' . $this->_CFG['integral_name'] : '';//$m->price_format
        $result['content']['goods_price_formated'] = $result['content']['integral_formated'];
        $result['content']['goods_price_formated'] .= intval($result['content']['goods_price']) > 0 ? price_format($result['content']['goods_price']) . "元" : price_format(0) . "元";
        $this->ajaxReturn($result);
    }
}
