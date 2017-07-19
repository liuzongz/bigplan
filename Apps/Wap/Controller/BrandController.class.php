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
 * $Id: BrandController.class.php 17156 2015-12-11 16:10:47Z keheng $
*/

namespace Wap\Controller;
use Wap\Model;
class BrandController extends WapController {

    public function index(){
        $sufs = $this->dealer_suf();
        $is_dealer = $this->get_dealer_status();
        $goods = new Model\GoodsModel();
        $brand_list = $goods->get_brand();
        $cate_list = array();
        foreach ($brand_list as &$v) {
            $v['brand_logo'] = $goods->img_url($v['brand_logo']);
            $v['brand_figure'] = $goods->img_url($v['brand_figure']);
            if ($v['cat_id'] != 0) {
                $cate_list[$v['cat_id']]['cat_name'] = $v['cat_name'];
                $cate_list[$v['cat_id']]['cat_id'] = $v['cat_id'];
                $cate_list[$v['cat_id']]['cat_img'] = $goods->img_url($v['cat_img']);
                $cate_list[$v['cat_id']]['url'] = U() . '?id=' . $v['cat_id'];
                $cate_list[$v['cat_id']]['list'][] = $v;
            }
        }
        $this->assign('cur_page','fenlei');
        $this->assign('cat_list', $cate_list);

        $this->assign('user_head',array('title'=>'<a class="navC" href="' . U('fenlei/index') . '?'.$sufs[$is_dealer] . '">分类</a><a class="navB cur" href="' . U('brand/index')  . '?'.$sufs[$is_dealer] . '">品牌</a>'));
        $this->assign('tkd', array('title'=>'品牌' . ' 产品列表','keywords'=>'','discription'=>''));
        $this->display('index1');
    }

    private  function index1(){
        $goods = new Model\GoodsModel();//D('Goods');
        //$this->active_index();          //有活动则跳转活动首页
        $res = $goods->cat_list_all();#读取分类列表
        $cat_list = $this->get_array_tree($res,'cat_id','parent_id');#分类进行分级整理
        $this->assign('cat_list', $cat_list);
        $brand = $goods->get_brand();//$this->debug($brand,1);
        $this->assign('brand_list',$brand);
        $this->assign('brand_empty','<li class="" style="padding:50px 0;">暂未添加品牌！</li>');
        //$this->assign('brand_page_total', $brand['total']);
        $this->assign('ajax_url',U('Index/ajax_get_store'));
        $this->assign('is_index',1);
        $this->display('index1');
        //print_r($this->get_st
    }

    public function info(){
        $id = I('get.id',0,'intval');
        if ($id <= 0) {
            $this->redirect('index');
        } else {
            $goods_model = new Model\GoodsModel();
            $brand_info = $goods_model->get_brand_info($id);
            if ($brand_info) {
                $res = $goods_model->cat_list_all();#读取分类列表
                $cat_list = $this->get_array_tree($res,'cat_id','parent_id');#分类进行分级整理
                $this->assign('cat_list', $cat_list);

                $this->assign('brand_info', $brand_info);
                $goods_list = $goods_model->get_brand_goods($id);
                foreach ($goods_list as $k => &$v) {
                    $v['brand_logo'] = $brand_info['brand_logo'];
                    $v['brand_name'] = $brand_info['brand_name'];
                    $v['url'] = U('Goods/index') . '?id=' . $v['goods_id'];
                }
                $this->assign('goods_list', $goods_list);
                $this->assign('goods_empty', '<li class="" style="padding:50px 0;">该品牌下暂无产品！</li>');
                $this->display();
            } else {
                $this->redirect('index');
            }
        }
    }

    protected function get_store_into($id){
        if ($id <= 0 ) return false;
        return M('store')->where("store_id=" . $id);
    }
}