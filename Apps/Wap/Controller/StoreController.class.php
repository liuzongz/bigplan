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
 * $Id: StoreController.class.php 17156 2015-12-11 16:10:47Z keheng $
*/

namespace Wap\Controller;
use Wap\Model;
class StoreController extends WapController {
    protected function _initialize(){
        parent::_initialize();
        $this->is_login();
    }

    public function init(){
        $id = I('get.id',0,'intval');
        if ($id > 0) {
            $goods_model = new Model\StockModel();//D('Goods');
            $store_info = $goods_model->get_store_info(['s.store_id'=>$id]);
            if ($store_info['store_state'] != STORE_OPEN) {
                $this->error('店铺暂不能开启！');
            } else {
                session('store_info', $store_info);
                session('store_token', $id);
                $this->redirect('info?id=' . $id . '&store_token=' . $id);
            }
        } else {
            $this->error('店铺打开失败！');
        }
    }

    public function info(){
        $id = I('get.id','0', 'intval');
        if($id > 0){
            $stock_model = new Model\StockModel();
            $store_info = $stock_model->get_store_info(['s.store_id'=>$id]);
            $cate_list = $stock_model->get_store_cat($id);       //读取店铺自主分类
            $cate_list = $this->get_array_tree($cate_list,'id','pid');#分类进行分级整
            $this->assign('cate_list', $cate_list);
            $this->assign('store_info', $store_info);
            $template = $this->fetch('', $store_info['tem_content']);
            $this->assign('template', $template);
            $this->assign('tkd', array('title'=>$store_info['store_name'],'keywords'=>$store_info['keywords'],'discription'=>$store_info['discription']));
            if($id == 95){
                $this->display('Store:template1');
            }else{
                $this->display('Store:template');
            }

        }else{
            $this->error('店铺被关闭或未开通');
        }
    }

    public function get_goods_list(){
        $id = I('get.id', 0, 'intval');
        $cate = I('get.cate', 0, 'intval');
        $data_type = I('get.type', 0, 'intval');
        $goods_model = new Model\GoodsModel();
        $stock_model = new Model\StockModel();
        $store_info = $stock_model->get_store_info(['s.store_id'=>$id]);
        $cate_list = $stock_model->get_store_cat($id);       //读取店铺自主分类
        $this->assign('store_info', $store_info);
        if($id && $cate){//带分类ID返回改分类下的商品
            $ids = [];
            foreach($cate_list as $key => $v){//取当前分类下的子级分类
                if($v['pid'] == $cate || $v['id'] == $cate) {
                    $ids[] = $v['id'];
                }else{
                    unset($cate_list[$key]);
                }
            }
            $store_goods = $goods_model->getStoreGoodsForCat($id,$ids,20, intval($this->user_info['user_rank']));
            if($store_goods['str']){
                if($data_type){
                    //返回普通商品列表
                    $this->assign('goods_list', $store_goods['str']);
                    $store_goods['str'] = '<div class="comBack"><ul class="comCp">'.$this->fetch('Public:goods_list3').'</ul></div>';
                    $result = result_ajax(200, '获取成功', $store_goods);
                }else{
                    foreach ($cate_list as &$v) {
                        foreach ($store_goods['str'] as $vv) {
                            if ($vv['seller_cate_id'] == $v['id']) {
                                $v['goods_list'][] = $vv;
                            }
                        }
                        $v['cate_img'] = img_url($v['cate_img']);
                    }
                    $this->assign('cate_list', $cate_list);
                    $this->assign('goods_list', $store_goods['str']);
                    $store_goods['str'] = $this->fetch('', $store_info['tem_goods']);
                    //按照返回分类商品列表
                    $result = result_ajax(200, '获取成功', $store_goods);
                }
            }else{
                $result = result_ajax(200,'',['str'=>'', 'pagecount'=>1, 'curpage'=>1]);
            }
        }elseif ($id && !$cate){//不带分类ID返回所有分类
            $cate_list = $this->get_array_tree($cate_list,'id','pid');#分类进行分级整
            $ids = [];
            foreach ($cate_list as $k => $v) {
                $cate_list[$k]['ids'] = $this->get_array_value($v,'id');
                $ids = array_merge($ids,$cate[$k]['ids']);
            }
            $store_goods = $goods_model->getStoreGoodsForCat($id, $ids, 20, intval($this->user_info['user_rank']));
            foreach ($cate_list as &$v) {
                foreach ($store_goods['str'] as $vv) {
                    if (in_array($vv['seller_cate_id'],$v['ids'])) {
                        $v['goods_list'][] = $vv;
                    }
                }
            }
            if($store_goods['str']) {
                $this->assign('cate_list', $cate_list);
                $str = $this->fetch('', $store_info['tem_goods']);

                $result = result_ajax(200, '获取成功', $str);
            }else{
                $result = result_ajax(200, '', ['str'=>'', 'pagecount'=>1, 'curpage'=>1]);
            }
        } else{
            $result = result_ajax(301, '获取失败！', '');
        }
        $this->ajaxReturn($result);
    }

    //获取全部商品
    public function get_all_goods(){
        $id = I('get.id', 0, 'intval');
        $goods_model = new Model\GoodsModel();
        if($id) {//带分类ID返回改分类下的商品
            $store_goods = $goods_model->getStoreGoodsForCat($id, 0, 1, intval($this->user_info['user_rank']));
            if($store_goods){
                $result = result_ajax(200,'获取成功!', $store_goods);
            }else{
                $result = result_ajax(303, '没有获取到商品信息!');
            }
        }else{
            $result = result_ajax(301, '缺少请求参数！');
        }

        $this->ajaxReturn($result);
    }

    public function get_cate_goods(){
        $id = I('get.id', 0, 'intval');
        $cate = I('get.cate', 0, 'intval');
        $goods_model = new Model\GoodsModel();
        $stock_model = new Model\StockModel();
        $cate_list = $stock_model->get_store_cat($id);       //读取店铺自主分类
        if($id) {//带分类ID返回改分类下的商品
            $ids = [];
            foreach($cate_list as $key => $v){//取当前分类下的子级分类
                if($v['pid'] == $cate || $v['id'] == $cate) {
                    $ids[] = $v['id'];
                }else{
                    unset($cate_list[$key]);
                }
            }
            $store_goods = $goods_model->getStoreGoodsForCat($id, $ids, 10, intval($this->user_info['user_rank']));
            if($store_goods){
                $result = result_ajax(200,'获取成功!', $store_goods);
            }else{
                $result = result_ajax(303, '没有获取到商品信息!');
            }
        }else{
            $result = result_ajax(301, '缺少请求参数！');
        }

        $this->ajaxReturn($result);
    }

    public function index(){
        $goods = new Model\GoodsModel();//D('Goods');
        //$this->active_index();          //有活动则跳转活动首页
      //  $res = $goods->cat_list_all();#读取分类列表
//        $cat_list = $this->get_array_tree($res,'cat_id','parent_id');#分类进行分级整理
//        $this->assign('cat_list', $cat_list);
        $store = $goods->get_store(I('keywords',""));
        $store_goods = $goods->get_store_goods1($store['ids'],3,intval($this->user_info['user_rank']));
        foreach ($store['list'] as &$v) {
            foreach ($store_goods as $vv) {
                if ($v['store_id'] == $vv['store_id']) {
                    $v['goods_list'][] = $vv;
                }
            }
        }
        if($this->ajax){
            if ($store['list']) {
                $result['error'] = 0;
                $result['message'] = "获取成功";
                $result['contents']['goods_list'] = $store['list'];
            } else {
                $result['error'] = 1;
                $result['message'] = '获取失败！';
            }
            $this->ajaxReturn($result);exit();
        }
        $this->assign('store_list',$store['list']);//print_r($store);
        $this->assign('store_empty', '<div class="shopClass">暂没有入驻店铺</div>');
        $this->assign('store_page_total', $store['total']);
        $this->assign('ajax_url',U('Index/ajax_get_store'));
        $this->assign('is_store',1);
        $this->assign('user_head',array('title'=>$this->fetch('Public:search'),'backUrl'=>U('Index/index'),'backText'=>'返回首页'));
        $this->assign('tkd', array('title'=>'店铺列表','keywords'=>'','discription'=>''));
        $this->display('index1');
    }

    public function info1(){
        $id = I('id', 0, 'intval');
        $stock_model = new Model\StockModel();
        if ($id <= 0) header('location:' . U('index'));
        $store_info = $stock_model->get_store_info('s.store_id=' . $id);
        if (!$store_info) {
            $this->error('店铺被关闭或未开通');
        } else {
            $this->assign("is_weixin", $this->is_weixin);
            $this->assign('store_info', $store_info);
            $this->assign('user_head',array('title'=>$store_info['store_name'],'backUrl'=>U('Index/index'),'backText'=>'返回首页'));
            $this->assign('tkd', array('title'=>$store_info['store_name'],'keywords'=>'','discription'=>''));
            $this->display('info1');
        }
    }

    public function get_index(){
        $id = I('get.id',0,'intval');
        $stock_model = new Model\StockModel();
        $store_info = $stock_model->get_store_info(['s.store_id'=>$id]);
        if (!$store_info) {
            $result = result_ajax(400,'店铺不存在');
        } else {
            $cate = $stock_model->get_store_cat($id);       //读取店铺自主分类
            $cate = $this->get_array_tree($cate,'id','pid');#分类进行分级整
            $ids = [];
            foreach ($cate as $k => $v) {
                $cate[$k]['ids'] = $this->get_array_value($v,'id');
                $ids = array_merge($ids,$cate[$k]['ids']);
            }

            $goods = new Model\GoodsModel();
            //$store = $goods->get_store(I('keywords',""));
            $store_goods = $goods->getStoreGoodsForCat($id, $ids, 20, intval($this->user_info['user_rank']));

            if(empty($store_goods['str'])){
                $store_goods_info = $goods->get_store_goods($id, 20,intval($this->user_info['user_rank']));
                $goods_ids = array_rand($store_goods_info['goods_list'],6);
                foreach ($goods_ids as $gid){
                    $store_goods['str'][] = $store_goods_info['goods_list'][$gid];
                }
            }else{
                foreach ($cate as &$v) {
                    foreach ($store_goods['str'] as $vv) {
                        //echo $vv['seller_cate_id'] . '=' . print_r($v['ids'],1) . "\n\n";
                        if (in_array($vv['seller_cate_id'],$v['ids'])) {
                            $v['goods_list'][] = $vv;
                        }
                    }
                }
            }
            if($store_goods['str']) {
                $this->assign('store_goods', $store_goods['str']);
                $this->assign('cate_list', $cate);
                $str = $this->fetch('Store:info_list');
                $result = result_ajax(200, '获取成功', $str);
            }else{
                $result = result_ajax(200, '', '<div class="page_msg">分类下暂无产品！</div>');
            }
        }
        $this->ajaxReturn($result);
    }

    public function introduce(){
        $id = I('get.id', 0, 'intval');
        $stock_model = new Model\StockModel();
        $store_info = $stock_model->get_store_info(['s.store_id'=>$id]);
        if ($id <= 0 or !$store_info) {
            $result = result_ajax(400,'店铺不存在');
        } else {
            if ($store_info['store_desc']) {
                $result = result_ajax(200,'',$store_info['store_desc']);
            } else {
                $result = result_ajax(200,'','<div class="page_msg">店主很懒，什么都没有留下！</div>');
            }
        }
        $this->ajaxReturn($result);
    }

    public function fenlei(){
        $id = I('get.id', 0,'intval');
        $stock_model = new Model\StockModel();
        $store_cate_info = $stock_model->get_store_category_info($id);
        if ($id <= 0 or !$store_cate_info) {
            $this->redirect('index');
        } else {
            $this->assign('id', $id);
            $this->assign('user_head',['title'=>$store_cate_info['cat_name']]);
            $this->display();
        }
    }

    public function get_store_list(){
        $id = I('get.id', 0,'intval');
        $stock_model = new Model\StockModel();
        $store_cate_info = $stock_model->get_store_category();
        if ($id <= 0 or !$store_cate_info) {
            $result = result_ajax(300,'暂时没有店铺信息');
        } else {
            $location = session('location');
            $this->assign('location', $location);//设置位置
            $point_list = return_square_point($location['lng'], $location['lat']);
            $goods_model = new Model\GoodsModel();
            $store = $goods_model->get_nearby_store($point_list);//根据经纬度获取附件的店铺
            $store_goods = $goods_model->get_store_act_goods($store['ids'], array(0, 1), 5);


            //$goods =  Element\Element::createObject('goods',['']);
            //设置店铺下面的商品
            foreach ($store['list'] as &$list) {
                foreach ($store_goods as $vvv) {
                    //$goods->setData($vvv);
                    if ($list['store_id'] == $vvv['store_id'] ) {

                        //$goods_act = $goods->activityList[$vvv['act_id']];
                        $vvv['url'] = U('Goods/index') . '?id=' . $vvv['goods_id'] . '&aid=' . AesEnCrypt($vvv['aid']);
                        //$vvv['goods_price_format'] = (string)$goods_act->price();
                        $list['goods_list'][] = $vvv;
                    }
                }
            }

            /*if ($this->ajax) {
                if ($store['list']) {
                    $result['error'] = 0;
                    $result['msg'] = "获取成功";
                    $result['contents']['goods_list'] = $store['list'];
                } else {
                    $result['error'] = 1;
                    $result['msg'] = '获取失败！';
                }
                $this->ajaxReturn($result);
            }*/
            $this->assign('store_empty','<div class="page_msg">暂时没有店铺信息</div>');
            $this->assign('store_list', $store['list']);
            $str = $this->fetch('Index:store');
            $result = result_ajax(200,'',$str);
        }
        $this->ajaxReturn($result);
    }

    public function gift(){
        $id = I('id', 0, 'intval');
        $goods = new Model\GoodsModel();//D('Goods');
        $stock_model = new Model\StockModel();
        if ($id <= 0) header('location:' . U('index'));
        $this->assign('store_info', $store_info = $stock_model->get_store_info('s.store_id=' . $id));
        $this->assign('store_goods', $store_goods = $goods->get_store_goods($id,2));
        $this->assign('goods_btn','点击购买');
        //print_r($store_info);
        /*print_r($store_goods);*/
        $this->assign('user_head',array('title'=>$store_info['store_name'],'backUrl'=>U('Index/index'),'backText'=>'返回首页'));
        $this->assign('tkd', array('title'=>'店铺' . $store_info['store_name'],'keywords'=>'','discription'=>''));
        $this->assign('goods_empty','<div style="height:5rem;overflow:hidden;text-align:center;line-height:5rem;font-size:.6rem;">暂无积分产品！</div>');
        if($this->ajax){
            if ($store_goods) {
                $result['error'] = 0;
                $result['message'] = "获取成功";
                $result['contents']['goods_list'] = $store_goods;
            } else {
                $result['error'] = 1;
                $result['message'] = '获取失败！';
            }
            $this->ajaxReturn($result);exit();
        }
        $this->display('info1');
    }


    public function goodsList()  {
        $id = I('get.id', 0, 'intval');
        $goods = new Model\GoodsModel();//D('Goods');
        $stock_model = new Model\StockModel();
        $store_info = $stock_model->get_store_info('s.store_id=' . $id);

        if ($id <= 0 or !$store_info) {
            $result = result_ajax(301,'店铺不存在或者已经关闭');
        } else {

            $store_goods = $goods->get_store_goods($id, 20,intval($this->user_info['user_rank']));
            $this->assign('store_goods',$store_goods['goods_list'] );
            $this->assign('store_info',$store_info );
            if($this->ajax){
                $result['error'] = 0;
                $result['message'] = "获取成功";
                $result['contents'] = $store_goods;
            }else{
                if($store_goods['goods_list']) {
                    $result = result_ajax(200, '', $this->fetch('Store:goods_list'));
                }else{
                    $result = result_ajax(200, '', '<div class="page_msg">分类下暂无产品！</div>');
                }
            }

        }
        $this->ajaxReturn($result);

    }

    public function storeInfo(){
        $id = I('id', 0, 'intval');
//        $goods = new Model\GoodsModel();//D('Goods');
        $stock_model = new Model\StockModel();
        if ($id <= 0) header('location:' . U('index'));

        $store_info = $stock_model->get_store_info('s.store_id=' . $id);

        $this->assign('user_head',array('title'=>$store_info['store_name'],'backUrl'=>U('Index/index'),'backText'=>'返回首页'));
        $this->assign('store_info', $store_info);
        $this->display('info');
    }

    public function active(){
        $id = I('get.id', 0, 'intval');
        $stock_model = new Model\StockModel();
        $goods = new Model\GoodsModel();
        if ($id <= 0) header('location:' . U('index'));

        $store_info = $stock_model->get_store_info('s.store_id=' . $id);

        if ($id <= 0 or !$store_info) {
            $result = result_ajax(301,'店铺不存在或者已经关闭');
        } else {
            $store_goods = $goods->get_store_act_goods($id, array(0,1,2), 8,'goods_id','store_id', intval($this->user_info['user_rank']));
            $this->assign('store_goods',$store_goods['goods_list'] );
            $this->assign('store_info',$store_info );
            if($this->ajax){
                $result['error'] = 0;
                $result['message'] = "获取成功";
                $result['contents'] = $store_goods;
            }else{
                if($store_goods['goods_list']){
                    $result = result_ajax(200,'',$this->fetch('Store:active'));
                }else{
                    $result = result_ajax(200, '', '<div class="page_msg">分类下暂无产品！</div>');
                }
            }
        }
        $this->ajaxReturn($result);
    }

}
