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
 * $Id: StockController.class.php 17156 2016-06-28 09:25:47Z keheng $
*/

namespace Wap\Controller;
use Wap\Model;
class StockController extends WapController {
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();
        $this->void_store($this->user_id);
        $this->set_dealer_status(1);
    }

    public function index(){
        header('location:' . U('Active/index') . '?aid=' . AesEnCrypt(70));
        cookie('last_page',U('index'));
        //读取首页banner
        $banner_list = $this->get_banner();
        $this->assign('banner_list', $banner_list);

        //读取顶级分类
        $goods_model = new Model\GoodsModel();
        $category_list = $goods_model->get_each_cate_goods('add_time desc',4,1);
        $this->assign('category_list', $category_list);
        $this->assign('goods_empty', '<li class="goods_empty">该分类下暂无产品！</li>');
        //读取最新产品
        $new = $this->get_goods('new',4);
        $this->assign('new_goods', $new['list']);
        //读取最热产品
        $hot = $this->get_goods('hot',6);
        $this->assign('hot_goods', $hot['list']);
        //读以公告信息
        $notice = $this->get_notice();
        $this->assign('notice', $notice);

        $this->display();
    }

    private function get_notice(){
        return [];
    }

    private function get_banner(){
        return [];
    }

    /**
     * 获取最新或最热产品
     * @param string $type
     * @param int $max
     * @return array
     */

    private function get_goods($type = 'new', $max = 10) {
        $m = D('goods');
        $where = ' is_delete=0 and is_on_sale=1 and is_check=0 and is_dealer=1 ';
        if (!in_array($type,['new','hot'])) {
            $type = 'new';
        }
        $where .= ' and is_' . $type . '=1 ';
        $list = $m->alias('g')
            ->field('g.*,si.store_label,b.brand_name,b.brand_logo')
            ->where($where)
            ->join('LEFT JOIN __STORE__ s ON g.store_id=s.store_id')
            ->join('LEFT JOIN __STORE_IMAGES__ si ON g.store_id=si.store_id')
            ->join('LEFT JOIN __BRAND__ b ON g.brand_id= b.brand_id')
            ->limit($max)
            ->order('goods_id desc')
            ->select();
        foreach ($list as &$v) {
            $v['goods_thumb'] = $m->img_url($v['goods_thumb']);
            $v['url']   = U('Goods/index') . '?id=' . $v['goods_id'] . '&stock';
        }
        return array('list'=>$list);
    }

    public function fenlei(){
        $url = U('Fenlei/index',$_GET).'?stock';
        header('location:'.$url,true,301);
//        $_GET['id'] = 1;
//        if(I('get.id')){
//            echo 'test';
//            $c = A("Fenlei");
//            $c->index();
//            $this->display("fenlei:list");
//        }
////        $id = I('id',1,'intval');
////        if ($id <= 0 ) $this->redirect('index');
//        $goods = new Model\GoodsModel();
//        $res = $goods->cat_list_all();#读取分类列表
//
////
//      $cat_list = $this->get_array_tree($res,'cat_id','parent_id');#分类进行分级整理
//        dump($cat_list);
////        $res = $this->get_array_search($cat_list,['cat_id'=>$id,'parent_id'=>0],'and'); #搜索相关分类
////        $ids = $this->get_array_value($res,'cat_id');    #搜索所有下级分类ID
////        if (!$res) $this->redirect('index');
////
////        $goods_list = $goods->goods_list($ids,8,1);
////        foreach ($goods_list['goods_list'] as &$v) {
////            $v['url'] = U('Goods/index') . '?id=' . $v['goods_id'] . '&stock';
////            $v['store_url'] = '#';//U('Store/index?id=' . $v['store_id']);
////            $v['stock_price']   = sprintf('%1.2f',$v['stock_price']);
////            $v['goods_name']   = msubstr($v['goods_name'],0,10);
////        }
////        if ($this->ajax) {
////            $this->echo_ajax(0,'获取成功',$goods_list);
////        } else {
////            //print_r($goods_list);
////            $this->assign('cat_info', $res);
////            $this->assign("goods_list", $goods_list['goods_list']);
////            $this->assign("goods_page", $goods_list['page']);
////            $this->assign('goods_page_total', $goods_list['total']);
////            $this->assign('tkd', array('title'=>$res['cat_name'] . ' 产品列表','keywords'=>'','discription'=>''));
////            $this->assign('is_fenlei',false);//是否显示分类
////            $this->assign('goods_empty','<li style="padding:20px 0;width:auto;min-height:450px;float:none;">此分类下无下级分类或产品</li>');
////            $this->display();
////        }
//        $this->display();
    }

    private  function test(){
        $arr = $this->testArr();
        $s = $this->get_array_search($arr,['cat_id'=>1,'parent_id'=>0],'and','children_list');
        var_dump($s);exit;
    }




    private function testArr(){
        return Array (
            8 => Array(
                'cat_id' => '1',
                'cat_name' => '美妆',
                'keywords' => '',
                'cat_desc' => '',
                'parent_id' => '0',
                'sort_order' => '50',
                'template_file' => '',
                'measure_unit' => '',
                'show_in_nav' => '1',
                'style' => '',
                'is_show' => '1',
                'grade' => '0',
                'filter_attr' => '',
                'cat_logo' => 'Uploads/category/mz.jpg',
                'stock_name' => '美妆&彩妆',
                'stock_icon' => '',
                'stock_banner' => '',
                'goods_count' => '4',
                'url' => '/Wap/Fenlei/index/id/1.html',
                'cat_log' => '',
                'children_list' => Array (
                    0 => Array (
                        'cat_id' => '4',
                        'cat_name' => '清洁',
                        'keywords' => '',
                        'cat_desc' => '',
                        'parent_id' => '1',
                        'sort_order' => '50',
                        'template_file' => '',
                        'measure_unit' => '',
                        'show_in_nav' => '0',
                        'style' => '',
                        'is_show' => '1',
                        'grade' => '0',
                        'filter_attr' => '',
                        'cat_logo' => 'Uploads/category/qj.jpg',
                        'stock_name' => '',
                        'stock_icon' => '',
                        'stock_banner' => '',
                        'goods_count' => '5',
                        'url' => '/Wap/Fenlei/index/id/4.html',
                        'cat_log' => '',
                    ),

                    1 => Array(
                        'cat_id' => '5',
                        'cat_name' => '爽肤',
                        'keywords' => '',
                        'cat_desc' => '',
                        'parent_id' => '1',
                        'sort_order' => '50',
                        'template_file' => '',
                        'measure_unit' => '',
                        'show_in_nav' => '0',
                        'style' => '',
                        'is_show' => '1',
                        'grade' => '0',
                        'filter_attr' => '',
                        'cat_logo' => 'Uploads/category/sf.jpg',
                        'stock_name' => '',
                        'stock_icon' => '',
                        'stock_banner' => '',
                        'goods_count' => '6',
                        'url' => '/Wap/Fenlei/index/id/5.html',
                        'cat_log' => '',
                    ),

                    2 => Array(
                        'cat_id' => '16',
                        'cat_name' => '防护',
                        'keywords' => '',
                        'cat_desc' => '',
                        'parent_id' => '1',
                        'sort_order' => '50',
                        'template_file' => '',
                        'measure_unit' => '',
                        'show_in_nav' => '0',
                        'style' => '',
                        'is_show' => '1',
                        'grade' => '0',
                        'filter_attr' => '',
                        'cat_logo' => 'Uploads/category/fh.jpg',
                        'stock_name' => '',
                        'stock_icon' => '',
                        'stock_banner' => '',
                        'goods_count' => '3',
                        'url' => '/Wap/Fenlei/index/id/16.html',
                        'cat_log' => '',
                    ),

                    3 => Array (
                        'cat_id' => '2',
                        'cat_name' => '改善',
                        'keywords' => '',
                        'cat_desc' => '',
                        'parent_id' => '1',
                        'sort_order' => '50',
                        'template_file' => '',
                        'measure_unit' => '',
                        'show_in_nav' => '0',
                        'style' => '',
                        'is_show' => '1',
                        'grade' => '0',
                        'filter_attr' => '',
                        'cat_logo' => 'Uploads/category/gs.jpg',
                        'stock_name' => '',
                        'stock_icon' => '',
                        'stock_banner' => '',
                        'goods_count' => '12',
                        'url' => '/Wap/Fenlei/index/id/2.html',
                        'cat_log' => '',
                    ),
                    4 => Array(
                        'cat_id' => '3',
                        'cat_name' => '彩妆',
                        'keywords' => '',
                        'cat_desc' => '',
                        'parent_id' => '1',
                        'sort_order' => '50',
                        'template_file' => '',
                        'measure_unit' => '',
                        'show_in_nav' => '0',
                        'style' => '',
                        'is_show' => '1',
                        'grade' => '0',
                        'filter_attr' => '',
                        'cat_logo' => 'Uploads/category/cz.jpg',
                        'stock_name' => '',
                        'stock_icon' => '',
                        'stock_banner' => '',
                        'goods_count' => '9',
                        'url' => '/Wap/Fenlei/index/id/3.html',
                        'cat_log' => '',
                    )
                )
            ),
            9 => Array(
                'cat_id' => '6',
                'cat_name' => '面膜',
                'keywords' => '',
                'cat_desc' => '',
                'parent_id' => '0',
                'sort_order' => '50',
                'template_file' => '',
                'measure_unit' => '',
                'show_in_nav' => '1',
                'style' => '',
                'is_show' => '1',
                'grade' => '0',
                'filter_attr' => '',
                'cat_logo' => 'Uploads/category/mm.jpg',
                'stock_name' => '面膜&护肤',
                'stock_icon' => '',
                'stock_banner' => '',
                'goods_count' => '4',
                'url' => '/Wap/Fenlei/index/id/6.html',
                'cat_log' => '',
            ),
            10 => Array(
                'cat_id' => '12',
                'cat_name' => '母婴',
                'keywords' => '',
                'cat_desc' => '',
                'parent_id' => '0',
                'sort_order' => '50',
                'template_file' => '',
                'measure_unit' => '',
                'show_in_nav' => '1',
                'style' => '',
                'is_show' => '1',
                'grade' => '0',
                'filter_attr' => '',
                'cat_logo' => 'Uploads/category/my.jpg',
                'stock_name' => '母婴&护理',
                'stock_icon' => '',
                'stock_banner' => '',
                'goods_count' => '0',
                'url' => '/Wap/Fenlei/index/id/12.html',
                'cat_log' => '',
                'children_list' => Array(
                    0 => Array(
                        'cat_id' => '14',
                        'cat_name' => '尿不湿',
                        'keywords' => '',
                        'cat_desc' => '',
                        'parent_id' => '12',
                        'sort_order' => '50',
                        'template_file' => '',
                        'measure_unit' => '',
                        'show_in_nav' => '0',
                        'style' => '',
                        'is_show' => '1',
                        'grade' => '0',
                        'filter_attr' => '',
                        'cat_logo' => 'Uploads/category/nbs.jpg',
                        'stock_name' => '',
                        'stock_icon' => '',
                        'stock_banner' => '',
                        'goods_count' => '9',
                        'url' => '/Wap/Fenlei/index/id/14.html',
                        'cat_log' => '',
                    ),
                    1 => Array(
                        'cat_id' => '15',
                        'cat_name' => '奶粉',
                        'keywords' => '',
                        'cat_desc' => '',
                        'parent_id' => '12',
                        'sort_order' => '50',
                        'template_file' => '',
                        'measure_unit' => '',
                        'show_in_nav' => '0',
                        'style' => '',
                        'is_show' => '1',
                        'grade' => '0',
                        'filter_attr' => '',
                        'cat_logo' => 'Uploads/category/nf.jpg',
                        'stock_name' => '',
                        'stock_icon' => '',
                        'stock_banner' => '',
                        'goods_count' => '10',
                        'url' => '/Wap/Fenlei/index/id/15.html',
                        'cat_log' => '',
                    ),
                    2 => Array (
                        'cat_id' => '13',
                        'cat_name' => '奶瓶',
                        'keywords' => '',
                        'cat_desc' => '',
                        'parent_id' => '12',
                        'sort_order' => '50',
                        'template_file' => '',
                        'measure_unit' => '',
                        'show_in_nav' => '0',
                        'style' => '',
                        'is_show' => '1',
                        'grade' => '0',
                        'filter_attr' => '',
                        'cat_logo' => 'Uploads/category/np.jpg',
                        'stock_name' => '…',
                    )
            )
    )
        );
    }
} 