<?php

/**
 * 即牛 - ${readme}
 * ============================================================================
 * 版权所有 2011-2015 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: GoodsController.class.php 17156 2015-12-11 16:10:47Z keheng $
*/

namespace Wap\Controller;
use Wap\Model;
class GoodsController extends WapController {
    protected function _initialize(){
        parent::_initialize();
        $this->is_login();
    }

    public function index(){
        $aid = AesDeCrypt(I('get.aid','','trim'));
        $goods_id = I('get.id',0,'intval');  //产品ID
        $goods_condition['g.goods_id'] = $goods_id;
        $goods_model = new Model\GoodsModel();
        if($aid) $goods_condition['ga.id'] = $aid;
        $goods_info = $goods_model->get_GoodsInfo($goods_condition);
        if ($goods_id <= 0 or !$goods_info) {
            $this->error('无此产品或产品已下架！');
        } elseif (!$aid and intval($goods_info['user_rank']) > 0 and empty($this->user_info)) {        //判断有没有商品权限
            $this->error('此商品暂时未开放购买权限！');
        } elseif (!$aid and !empty($this->user_info) and $goods_info['user_rank'] > $this->user_info['user_rank']){
            $this->error('此商品暂时未放购买权限！');
        } else {//

            if($aid){ //如果商品有活动就从营销工具读数据

                $result= curlpost('get_goods_act', array_merge(['goods_info'=>json_encode($goods_info), 'aid'=>AesEnCrypt($goods_info['aid'])], C('API_APPINFO')));
                if($result['status'] != 200) $this->error('活动错误！');
                $result = $result['data'];

                if((empty($result['auth_user'])) || (isset($act) && isset($this->user_info['is_store'])) || in_array($this->user_info['user_rank'],$result['auth_user'])) {
                    $goods_info['act_html'] = $result['act_html'];
                    $goods_info['act_button'] = $result['act_button'];
                    $goods_info['aid'] = AesEnCrypt($goods_info['aid']);
                }else{
                    $this->error('无此活动或无开放权限参与活动！');
                }
            }else{
                $goods_info['aid'] = '';
            }

            $goods_info['is_real'] = $goods_model->is_real_goods($goods_info['cat_id']);
            $goods_info['store_name']   = $goods_info['store_id'] <= 0 ? $this->_CFG['shop_name'] : $goods_info['store_name'];
            $goods_info['click_count']  = intval($goods_info['click_count']);
            $goods_info['sale_count']   = intval($goods_info['sale_count']);
            $goods_info['goods_thumb']  = img_url($goods_info['goods_thumb']);
            $goods_info['goods_img']    = img_url($goods_info['goods_img']);
            $goods_info['original_img'] = img_url($goods_info['original_img']);
            $goods_info['brand_logo']   = img_url($goods_info['brand_logo']);
            $goods_info['goods_desc']   = ReplaceImgUrl($goods_info['goods_desc']);
            //$goods_info['act_config']   = $actData->getData();

            $this->assign('goods_info', $goods_info);

            /*设置表单令牌*/
            $this->set_form_token();

            /*读取相册*/
            $gallery = $goods_model->get_GoodsGallery($goods_id);
            $this->assign('gallery', $gallery);

            /*读取属性*/
            $attr = $this->get_goods_attr($goods_id, $goods_model);//R('Public/get_goods_attr',array($id));
            $this->assign('attr',$attr);//print_r($attr);
            $this->assign('pro_empty','<tr><td class="e" style="border:0;">暂无属性</td></tr>');

            //读取评论
            $com = $this->get_comments($goods_id);//R('Public/get_comments',array($id));
            $this->assign('com_list',$com);//print_r($com);
            $this->assign('com_list_empty','<div class="list no" style="text-align:center;height:3rem;line-height:3rem;">暂未收到评论！</div>');

            //读取推荐产品
            $recommend = $goods_model->get_rand_act_goods(2,1800, $this->store_token);
            $this->assign('goods_recommend', $recommend);

//            dump($recommend);exit;
//            $this->assign('ship_line',$this->get_ship_line());


            /*if ($is_stock == 1) {
                $url['addtocart_url'] .= '&stock=1';
                $url['get_attr_url'] .= '&stock';
            }
            //首页设置
            switch ($is_dealer){
                case 0: $index = U('Index/index'); break;
                case 1: $index = U('Stock/index'); break;
                case 2: $index = U('Gift/index'); break;
                default:$index = U('Index/index');
            }*/

            //$this->assign('index',$index);
            //页头标题
            $this->assign('user_head', array('title'=>'产品详细'));
            $url = array(
                'get_attr_url' =>  U('Goods/get_attr_price') . '?_ajax=1',
                'addtocart_url' =>  U('Cart/addtocart') . '?_ajax=1' . ($this->debug ? '&debug=' . $this->debug : ''),
                'curr_url'      =>  AesEnCrypt(U('Goods/index') . '?id=' . $goods_id),
                //'return_url'    =>  U('User/login') . '?back_act=' . AesEnCrypt(U() . '?id=' . $goods_id)
                'return_url'    => get_server('PASSPORT_SERVER', '/User/login',
                    [
                        'back_act'  =>   AesEnCrypt($this->cur_domain . U('index/access_token') . '?back_act=' . AesEnCrypt($this->cur_url)),
                        'back_module'    =>   AesEnCrypt(strtoupper(MODULE_NAME)),
                        C('store_token_name') => $this->store_token
                    ], 1)
            );
            $this->assign('url', $url);

            $tkd = array(
                'title'         =>  $goods_info['goods_name'],
                'keywords'      =>  '',
                'discription'   =>  ''
            );
            $this->assign('tkd', $tkd);
            $this->assign('goods_empty', '<div class="shopClass">暂没有推荐商品！</div>');
            $this->display('index2');
        }
    }

    public function index1(){
        /*重新设置session */
        if(isset($_GET['normal'])) $this->set_dealer_status(GOODS_DEFAULT);//普通商品
        if(isset($_GET['stock'])) $this->set_dealer_status(GOODS_STOCK);//旗舰商品
        if(isset($_GET['gift'])) $this->set_dealer_status(GOODS_INTEGRAL);//积分商品
        if(!isset($_GET['normal'])&&!isset($_GET['stock'])&&!isset($_GET['gift'])) $this->set_dealer_status(GOODS_DEFAULT);
        $is_dealer = $this->get_dealer_status();
        $goods_id = I('get.id',0,'intval');
        $goods = new Model\GoodsModel();
        $goods_info = $goods->get_GoodsInfoBYid($goods_id);
        if (!$goods_info) {
            $this->error('无此产品或产品已下架！',U('Index/index'));
            //return 404;
        }
        $is_stock = $this->check_stock($goods_info);
        $this->assign('is_stock', $is_stock);
        $time = time();
        if ($goods_info['is_promote'] and $time > $goods_info['promote_start_date'] and $time < $goods_info['promote_end_date']) {
            $goods_info['is_promote'] = 1;
        } else {
            $goods_info['is_promote'] = 0;
        }
        $goods_info['store_name'] = $goods_info['store_id'] <= 0 ? $this->_CFG['shop_name'] : $goods_info['store_name'];
        $goods_info['click_count'] = intval($goods_info['click_count']);
        $goods_info['shop_price'] = intval($goods_info['shop_price']);
        $goods_info['sale_count'] = intval($goods_info['sale_count']);
        $goods_info['goods_thumb'] = $goods->img_url($goods_info['goods_thumb']);
        $goods_info['goods_img'] = $goods->img_url($goods_info['goods_img']);
        $goods_info['original_img'] = $goods->img_url($goods_info['original_img']);
        $goods_info['brand_logo'] = $goods->img_url($goods_info['brand_logo']);
        $this->assign('goods_info', $goods_info);
        /*设置表单令牌*/
        $this->set_form_token();

        /*读取相册*/
        $gallery = $goods->get_GoodsGallery($goods_id);
        $this->assign('gallery', $gallery);

        /*读取属性*/
        $attr = $this->get_goods_attr($goods_id, $goods);//R('Public/get_goods_attr',array($id));
        $this->assign('attr',$attr);//print_r($attr);
        $this->assign('pro_empty','<tr><td class="e" style="border:0;">暂无属性</td></tr>');

        //读取评论
        $com = $this->get_comments($goods_id);//R('Public/get_comments',array($id));
        $this->assign('com_list',$com);//print_r($com);
        $this->assign('com_list_empty','<div class="list no" style="text-align:center;height:3rem;line-height:3rem;">暂未收到评论！</div>');

        //读取推荐产品
        $recommend = $this->get_recommend_goods();
        $this->assign('goods_recommend', $recommend);
        $this->assign('ship_line',$this->get_ship_line());
        //页头标题
        $this->assign('user_head', array('title'=>'产品详细','backUrl'=>'','backText'=>'首页'));
        $url = array(
            'get_attr_url' =>  U('Goods/get_attr_price') . '?ajax=1',
            'addtocart_url' =>  U('Cart/addtocart') . '?ajax=1' . ($this->debug ? '&debug=' . $this->debug : '') . '&store_token='.$this->store_token,
            'curr_url'      =>  AesEnCrypt(U('Goods/index') . '?id=' . $goods_id . '&store_token='.$this->store_token),
            'return_url'    =>  U('User/login') . '?back_act=' . AesEnCrypt(U() . '?id=' . $goods_id) . '&store_token='.$this->store_token
        );
        if ($is_stock == 1) {
            $url['addtocart_url'] .= '&stock=1';
            $url['get_attr_url'] .= '&stock';
        }
        //首页设置
        switch ($is_dealer){
            case 0: $index = U('Index/index'); break;
            case 1: $index = U('Stock/index'); break;
            case 2: $index = U('Gift/index'); break;
            default:$index = U('Index/index');
        }
        $this->assign('index',$index);

        $this->assign('url', $url);

        //html head
        $tkd = array(
            'title'         =>  $goods_info['goods_name'],
            'keywords'      =>  '',
            'discription'   =>  ''
        );
        $this->set_form_token();
        $this->assign('tkd', $tkd);
        $this->display('index2');
    }

    /**
     * 获取推荐
     * @param
     * @return array
     */
    protected function get_recommend_goods(){
        $goods_model = new Model\GoodsModel();
        if(!S('goods_list')) {
            $where = [
                'start_time' =>  ['ELT',$this->time],
                'end_time'   =>  ['EGT',$this->time],
            ];
            $goods = M('GoodsActivity')->alias('ga')
                ->field('ga.*,g.*')
                ->where('act_id IN ' . M('FavourableActivity')
                        ->field('act_id')
                        ->where($where)
                        ->buildSql())
                ->join('LEFT JOIN __GOODS__ g ON g.goods_id=ga.goods_id')
                ->group()->limit(2)->order('rand()')
                ->cache('goods_list', 1000)->select();
        }

        foreach (S('goods_list') as $k => $v){
            $goods[$k] = $v;
            $goods[$k]['goods_thumb']   = $goods_model->img_url($v['goods_thumb']);
            $goods[$k]['url']           = U('Goods/index') . '?id=' . $v['goods_id'];
            $goods[$k]['brand_logo']    = $goods_model->img_url($v['brand_logo']);
        }
        return $goods;
    }

    /**
     * 取产品属性价格
     */
    public function get_attr_price() {
        $goods_id       = I('post.goods_id', 0, 'intval');
        $spec           = I('post.spec','');
        $goods_model    = new Model\GoodsModel();
        $act_id         = AesDeCrypt(I('post.act_id','','trim'));

        $goods_condition = 'g.goods_id=' . $goods_id;
        if($act_id) $goods_condition .= ' and ga.act_id=' . $act_id;
        $goods_info = $goods_model->get_GoodsInfo($goods_condition);
        if ($goods_info) {
            $is_stock = $this->check_stock($goods_info);//exit($is_stock . '');
            $test_spec = $goods_model->check_spec($goods_id,$spec);
            if ($test_spec['error'] > 0) {
                $result = $test_spec;
            } else {

                $price = $goods_model->get_attr_price($spec);//print_r($price);exit($price.'');



                //$actData = Element::createObject('goods', array($goods_info['goods_id'], $goods_info));
                //if($act_id and isset($actData->activityList[$act_id])){ //如果商品有活动就从营销工具读数据
                $actData= curlpost('get_goods_act', array_merge(['goods_info'=>json_encode($goods_info), 'aid'=>AesEnCrypt($goods_info['aid'])], C('API_APPINFO')));
                if($act_id and $actData['status'] == 200){
                    $goods_info['shop_price'] = $actData['data']['RMB'];
                    $goods_info['integral'] = $actData['data']['WB'];

                    if ($goods_info['integral']) {
                        $price =  price_format($price + $goods_info['integral'],0,1) . $this->_CFG['integral_name'];
                    } else {
                        $price =  price_format($price + $goods_info['shop_price'],1,1);
                    }

                } elseif(intval($goods_info['deposit_price']) > 0){
                    $price =  '¥' . price_format($goods_info['deposit_price']);
                } else{
                    $price =  '¥' . price_format($price + $goods_info['shop_price']);
                }


                $result['error'] = 0;
                $result['message'] = '获取成功！';
                $result['data']['price'] = $price;
                $result['data']['is_stock'] = $is_stock;
                /*$result['data']['ss'] = $goods_model->getLastSql();*/
            }
        } else {
            $result['error'] = 1;
            $result['message'] = '产品不存在！';
            //$result['data'] = $goods_model->getLastSql();
        }
        $this->ajaxReturn($result);
    }

    /**
     * 根据产品类型获取价格
     * @param $goods_info
     * @return int
     */
    private function check_stock(&$goods_info){
        //旗舰商品shop_price价格为0，不允许访问
        if($goods_info['is_dealer'] == GOODS_STOCK and $goods_info['shop_price']==0 and !isset($_GET['stock'])){
           $this->redirect('Index/index');
        }
        if ($goods_info['is_dealer'] == GOODS_STOCK and isset($_GET['stock'])) {
            $this->void_user();
            $this->void_store(intval($this->user_id), U('index/index'));
            $goods_info['market_price'] = $goods_info['shop_price'];
            $goods_info['shop_price'] = $goods_info['stock_price'];
            $is_stock = GOODS_STOCK;
        } else if ($goods_info['is_dealer'] == GOODS_INTEGRAL){
            $goods_info['market_price'] = intval($goods_info['shop_price']);
            $goods_info['shop_price'] = intval($goods_info['stock_price']);
            $is_stock = GOODS_INTEGRAL;
        } else {
            $goods_info['is_dealer'] = GOODS_DEFAULT;
            $is_stock = GOODS_DEFAULT;
        }
        return $is_stock;
    }

    /**
     * 取产品属性
     * @param $goods_id
     * @param $model
     * @return mixed
     */
    protected function get_goods_attr($goods_id, $model){
        $grp = $model->get_goods_type($goods_id);//R('Dao/get_goods_type',array($goods_id));
        if (!empty($grp))  {
            $groups = explode("\n", strtr($grp, "\r", ''));
        }
        $res = $model->get_goods_attr($goods_id);//R('Dao/get_goods_attr',array($goods_id));
        /* 获得商品的规格 */
        $arr['pro'] = array();     // 属性
        $arr['spe'] = array();     // 规格
        $arr['lnk'] = array();     // 关联的属性
        foreach ($res AS $row) {
            $row['attr_value'] = str_replace("\n", '<br />', $row['attr_value']);
            if ($row['attr_type'] == 0)  {
                $group = (isset($groups[$row['attr_group']])) ? $groups[$row['attr_group']] :'商品属性';
                $arr['pro'][$group][$row['attr_id']]['name']  = $row['attr_name'];
                $arr['pro'][$group][$row['attr_id']]['value'] = $row['attr_value'];
                $arr['pro'][$group][$row['attr_id']]['id'] = $row['attr_id'];
            } else {
                $arr['spe'][$row['attr_id']]['attr_type']   = $row['attr_type'];
                $arr['spe'][$row['attr_id']]['name']        = $row['attr_name'];
                $arr['spe'][$row['attr_id']]['id']          = $row['attr_id'];
                $arr['spe'][$row['attr_id']]['values'][] = array(
                    'label'        => $row['attr_value'],
                    'price'        => $row['attr_price'],
                    //'format_price' => price_format(abs($row['attr_price']), false),
                    'id'           => $row['goods_attr_id'],
                    //'url'          => U('Goods/index?id=' . $goods_id) . '?attr='
                );
            }
            if ($row['is_linked'] == 1) {
                /* 如果该属性需要关联，先保存下来 */
                $arr['lnk'][$row['attr_id']]['name']  = $row['attr_name'];
                $arr['lnk'][$row['attr_id']]['value'] = $row['attr_value'];
            }
        }

        //$this->debug($arr['spe'],1);
        $i = 0;
        $get_attr = explode('|', I('attr'));
        foreach ($arr['spe'] as $k => $v) {
            if ($v['attr_type'] == 2) { #多选
                if (isset($get_attr[$i]) and $get_attr[$i] != '') {
                    if (is_int($get_attr[$i])) {
                        $ss = array($get_attr[$i]);
                    } else {
                        $ss = explode('.',$get_attr[$i]);
                    }
                    $selected = false;
                    foreach ($v['values'] as $kk => $vv) {
                        $arr['spe'][$k]['values'][$kk]['url'] = U('Goods/index?id=' . $goods_id) . '?attr=';
                        foreach ($ss as $vvv) {
                            if ($vvv == $vv['id']) {
                                $arr['spe'][$k]['values'][$kk]['selected'] = 1;
                                $selected = true;
                                break;
                            }
                        }

                    }
                    if (!$selected) {
                        $arr['spe'][$k]['values'][0]['selected'] = 1;
                    }
                }
            } else {                    #单选
                $selected = false;
                foreach ($v['values'] as $kk => $vv) {
                    $arr['spe'][$k]['values'][$kk]['url'] = 1;
                    if ($get_attr[$i] == $vv['id']) {
                        //$arr['spe'][$k]['values'][$kk]['selected'] = U('Goods/index?id=' . $goods_id) . '?attr=';
                        $selected = true;
                        break;
                    }
                }
                if (!$selected) {
                    $arr['spe'][$k]['values'][0]['selected'] = 1;
                }
            }
            $i++;
         }
        //$this->debug($arr['spe'],1);
        return $arr;
    }


    /**
     * 取评论
     * @param $id
     * @param int $comment_type
     * @return mixed
     */
    protected function get_comments($id, $comment_type = 0){
        //'select count(*) as totle,sum(case when comment_rank=5 then 1 else 0 end) as five from lk_comment where comment_type=0 and id_value=200 '
        $c = D('comment');
        $com['score'] = $c
            ->field('count(*) as totle,sum(case when comment_rank=5 then 1 else 0 end) as good,sum(case when comment_rank=4 then 1 else 0 end) as kind,sum(case when comment_rank<4 then 1 else 0 end) as bad')
            ->where('comment_type='. $comment_type . ' and parent_id = 0 and id_value=' . $id)
            ->find();
        $com['list'] = $c->alias('c')
                ->field('c.*,u.user_name,u.user_avatar')
                ->where('status=1 and id_value=' . $id)
                ->join('LEFT JOIN __USERS__ u ON c.user_id=u.user_id')
                ->order('add_time desc')->select();

        foreach ($com['list'] as $k => $v){
            $com['list'][$k]['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
            $com['list'][$k]['user_name'] = $this->set_hidden_str($v['user_name']);//R('Com/set_hidden_str',array($v['user_name']));
            $com['list'][$k]['ip_address'] = $this->set_hidden_str($v['ip_address'],3);//R('Com/set_hidden_str',array($v['ip_address'],3));
            $com['list'][$k]['email'] = $this->set_hidden_str($v['email'],2);//R('Com/set_hidden_str',array($v['email'],2));
            if ($v['comment_rank'] <= 1){
                $score_img = 'bad';
            } else if ($v['comment_rank'] == 2){
                $score_img = 'kind';
            } else {
                $score_img = 'good';
            }
            $com['list'][$k]['score_img'] = $score_img;
        }
        $com['list'] = $this->get_array_tree($com['list'],'comment_id','parent_id');//R('Com/get_array_tree',array($com['list'],'comment_id','parent_id'));
        return $com;
    }
    //获取配送路线
    public function get_ship_line(){
        $ip = get_client_ip();
        $api = "http://ip.taobao.com/service/getIpInfo.php?ip=".$ip;
        $data = json_decode($this->curlGet($api),true);
        if($data['code']===0){
            $carea = $data['data']['city'];
        }

        $sarea = $this->_CFG['shop_city']<=0?""
            : M('region')->where('region_id=%d',$this->_CFG['shop_city'])->getField('region_name');
        if($sarea&&$carea){
            return '配送路线：' .$sarea.'  至  '.$carea;
        }else{
            return false;
        }
//        $line = array(
//            'shop_area'=>$sarea,
//            'client_area'=>$carea,
//        );

    }


}