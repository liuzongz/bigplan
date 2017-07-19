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
 * $Id: FenleiController.class.php 17156 2015-12-10 10:48:47Z keheng $
*/
namespace Wap\Controller;
use Wap\Model;
class FenleiController extends WapController {
    public function index(){
        $aid = AesDeCrypt(I('get.aid','','trim'));
        $id = I('id',0,'intval');
        $keywords = I('keywords',"",'trim');
        $type = 1;//决定搜索字段
        $is_dealer = $this->get_dealer_status();//决定产品类型
        //$sufs = $this->dealer_suf();
        $goods = new Model\GoodsModel();

        if(!$aid){
            $res = $goods->cat_list_all($is_dealer);#读取分类列表
        }else{
            $is_dealer = 3;
            $res = $goods->cat_list_all($is_dealer, $aid);#读取分类列表
        }
        $cat_list = $this->get_array_tree($res,'cat_id','parent_id');#分类进行分级整理

        //print_r($cat_list);
        if ($id <=0 && !$keywords ) {
            //$res = $this->array_search_multi($cat_list,'cat_id',$id); #搜索相关分类
            //$ids = $this->get_array_value($res,'cat_id');    #搜索所有下级分类ID
            $this->assign('cat_list', $cat_list);
            //$this->assign('user_head',array('title'=>'<a class="navC cur" href="' . U('fenlei/index') . '?'.$sufs[$is_dealer] . '">分类</a><a class="navB" href="' . U('brand/index') . '?' .$sufs[$is_dealer] . '">品牌</a>'));//,'backUrl'=>U('Index/index'),'backText'=>'返回首页'
            $this->assign('user_head',array('title'=>'分类'));//,'backUrl'=>U('Index/index'),'backText'=>'返回首页'
            $this->assign('tkd', array('title'=>'分类' . ' 产品列表','keywords'=>'','discription'=>''));
            $this->assign('cur_page','fenlei');
            $this->display('Fenlei:class');
        } else {
            if ($is_dealer==1) {
                $this->void_user();
                $this->void_store(intval($this->user_id),U('index/index'));
                $this->assign('stock','stock');
            }

            $sort['sort'] = I('get.sort','','trim');
            $sort['order'] = I('get.order','','trim');
            if (!in_array($sort['sort'], array('new','collect','price','sales','credit'))) $sort['sort'] = '';
            if ($sort['order'] != '' and $sort['order'] != 'desc') $sort['order'] = '';
            $this->assign('sort', $sort);
            $cat_info = $this->get_array_search($cat_list,'cat_id',$id,'children_list'); #搜索相关分类
           // if (!$cat_info) header('location:' . U('Fenlei/index'));
            $ids = $goods->get_sub_cat_ids($id);
            $ids[] = $id;
            $this->assign('ids',$ids);
            $goods_list = $goods->goods_list($ids,10,$is_dealer, $this->get_sort_field($sort['sort']), $sort['order'],$keywords,$type,intval($this->user_info['user_rank']));
            foreach ($goods_list['goods_list'] as &$v) {
                $v['goods_price_format'] = price_format($v['shop_price']);
                $v['url'] = U('Goods/index') . '?id=' . $v['goods_id'];
                $v['store_url'] = '#';//U('Store/index?id=' . $v['store_id']);
            }
            $this->assign('goods_btn','点击购买');
            $this->assign("goods_list", $goods_list['goods_list']);
            $this->assign("goods_page", $goods_list['page']);
            $this->assign('goods_page_total', $goods_list['total']);



            //进行搜索时修改标题和empty
            if($keywords){
                $column = $goods->get_search_column($type);
                $title = "搜索 ".$column['name'].' : '.$keywords;
                $empty = "没有找到相关产品！";

            }else{
                if ($cat_info['parent_id'] != 0) {
                    $title = '<a href="' . U('Fenlei/index') . '?id=' . $cat_info['parent_id'] .
                        '">' . $cat_info['parent_name'] . '</a> - ' . $cat_info['cat_name'];
                } else {
                    $title = $cat_info['cat_name'];
                }
                $empty = '该分类下暂无产品!';
            }
            //dump(session('stocking'));
            $user_head = array('title'=>$title,'backUrl'=>$this->back_url,'backText'=>'返回分类');
            $this->assign('godos_empty','<div style="height:5rem;overflow:hidden;text-align:center;line-height:5rem;font-size:.6rem;">'.$empty.'</div>');

            $this->assign('cat_info', $cat_info);
            $this->assign('user_head',$user_head);
            $this->assign('tkd', array('title'=>$res['cat_name'] . ' 产品列表','keywords'=>'','discription'=>''));
            $this->assign('is_fenlei',false);//是否显示分类
            //$this->assign('goods_empty','<li style="padding:20px 0;">此分类下无下级分类或产品</li>');
            $this->assign('cur_page','fenlei');
            $this->display('Fenlei:list');
        }
    }

    /**
     * 取排序字段
     * @param $sort
     * @return string
     */
    private function get_sort_field($sort){
        switch ($sort) {
            case 'new': $result = 'g.is_new';break;
            case 'collect': $result = 'g.click_count';break;
            case 'price': $result = 'g.shop_price';break;
            case 'sales': $result = 'g.sale_count';break;
            //case 'sales': $result = 'g.is_hot';break;
            case 'credit': $result = 'sc.store_credit';break;
            default:$result = '';
        }
        return $result;
    }

    private function index1(){
        $id = I('id',0,'intval');
        $stock = isset($_GET['stock']) ? 1 : 0;
        if ($id <= 0 ) $this->redirect('Index/index');
        $goods = new Model\GoodsModel();
        $res = $goods->cat_list_all();#读取分类列表

        $cat_list = $this->get_array_tree($res,'cat_id','parent_id');#分类进行分级整理
        $res = $this->array_search_multi($cat_list,'cat_id',$id); #搜索相关分类
        $ids = $this->get_array_value($res,'cat_id');    #搜索所有下级分类ID

        if ($id > 0) {
            if ($goods->cat_num('cat_id=' . $id . ' and parent_id=0') > 0) {
                $selected = $id;
            } else {
                $selected = $cat_list[0]['cat_id'];
            }
        } else {
            $selected = $cat_list[0]['cat_id'];
        }

        if ($res['children_list']) {
            $this->assign('is_fenlei',true);
            $this->assign('right_cat_list', $res);
            $this->assign('tkd', array('title'=>$res['cat_name'] . ' 产品分类','keywords'=>'','discription'=>''));
        } else {
            if (!$ids) $ids = $id;
            $selected = $id ;//$res[0]['gc_id'];
            if ($stock) {
                $goods_list = $goods->goods_list($ids,8,1);
            } else {
                $goods_list = $goods->goods_list($ids);
            }

            foreach ($goods_list['goods_list'] as $k => &$v) {
                $v['url'] = U('Goods/index') . '?id=' . $v['goods_id'];
                $v['store_url'] = '#';//U('Store/index?id=' . $v['store_id']);
            }
            $this->assign("goods_list", $goods_list['goods_list']);
            $this->assign("goods_page", $goods_list['page']);
            $this->assign('goods_page_total', $goods_list['total']);
            $this->assign('tkd', array('title'=>$res['cat_name'] . ' 产品列表','keywords'=>'','discription'=>''));
            $this->assign('is_fenlei',false);//是否显示分类
            $this->assign('goods_empty','<li style="padding:20px 0;">此分类下无下级分类或产品</li>');
        }
        $this->assign('cat_list', $cat_list);
        $this->assign('selected', $selected);
        if ($stock) {
            $this->display('Stock:fenlei');
        } else {
            $this->display();
        }
    }

    public function ajax_goods_list(){
        $goods = new Model\GoodsModel();
        //$result = array('error'=>0,'message'=>"",'contents'=>array());
        $id = I('get.ids',0,'intval');
        $ids = $goods->get_sub_cat_ids($id);
        $ids[] = $id;
        $keywords = I('get.keywords',"");
        $is_dealer = session('is_dealer')?:0;
        $sort = $this->get_sort_field(I('get.sort',""));
        $order = I('get.order',"desc");
        $res = $goods->goods_list($ids,10,$is_dealer,$sort ,$order,$keywords);
        if ($res['goods_list']) {
            $str = '';
            //print_r($res);
            foreach ($res['goods_list'] as $v) {
                $this->assign('goods', $v);
                $str .= $this->fetch('Public:goods_list2');
            }
            $result = result_ajax(200,'获取成功！',['pagecount'=>$res['total'],'curpage'=>$res['curpage'],'str'=>$str]);
        } else {
            $result = result_ajax(205,'获取失败！');
        }
        $this->ajaxReturn($result);

    }


}
