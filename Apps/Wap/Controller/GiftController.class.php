<?php

/**
 * 即牛 - 微币兑换
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: GiftController.class.php 17156 2016-07-16 14:27:47Z keheng $
*/
namespace Wap\Controller;
use Wap\Model;

class GiftController extends WeixinController {
    protected function _initialize(){
        parent::_initialize();
        //$this->set_dealer_status(0);
    }

    public function index(){
        //读取首页banner
        //header('location:' . U('Active/index') . '?aid='.urlencode(encrypt(77,'aid-')));
        header('location:' . U('Active/index') . '?aid=' . AesEnCrypt(108));
        $banner_list = $this->get_banner();
        $this->assign('banner_list', $banner_list);
        //读取顶级分类
        $goods_model = new Model\GoodsModel();
        $category_list = $goods_model->get_each_cate_goods('add_time desc',4,2);//dump($category_list); exit();
        $this->assign('cur_page','gift');
        $this->assign('category_list', $category_list);
        $this->assign('goods_empty', '<li class="goods_empty">该分类下暂无产品！</li>');
        /*//读取最新产品
        $new = $this->get_goods('new',4);
        $this->assign('new_goods', $new['list']);
        //读取最热产品
        $hot = $this->get_goods('hot',6);
        $this->assign('hot_goods', $hot['list']);
        //读以公告信息
        $notice = $this->get_notice();
        $this->assign('notice', $notice);*/

        $this->display();
    }

    public function fenlei(){
        $aid = AesDeCrypt(I('get.aid','','trim'));
        $id = I('id',0,'intval');
        $keywords = I('keywords',"",'trim');
        $type = 1;
        $this->is_login();
//        $goods_model = new Model\GoodsModel();
//        $res = $goods_model->cat_list_all();#读取分类列表
//        $cat_list = $this->get_array_tree($res,'cat_id','parent_id');#分类进行分级整理
//        dump($cat_list);exit;
        $result= curlpost('get_act_info', array_merge(['act_id'=>I('get.aid')], C('API_APPINFO')));
        $this->user_info['user_rank'] = intval($this->user_info['user_rank']) <= 0 ? 1 : intval($this->user_info['user_rank']);

        $cat_info = array();
        if ($result['status'] != 200) {
            $this->error('活动不存在或已过期！');
        }elseif (!isset($this->user_info['is_store']) and !empty($result['data']['auth_user']) and !in_array($this->user_info['user_rank'], $result['data']['auth_user'])) {
            $this->error('您不能参与当前活动！');
        } else {
            $activity = $result['data'];
            $goods_model = new Model\GoodsModel();
            $cat_list = $goods_model->get_cate_list($activity['config']['cate']['value']);
            $cat_list = $this->get_array_tree($cat_list,'cat_id','parent_id');                 //生成树
            $cat_list = $this->get_array_sort($cat_list,'sort_order');                    //排序显示

            //排序
            $sort['sort'] = I('get.sort','','trim');
            $sort['order'] = I('get.order','','trim');
            if (!in_array($sort['sort'], array('new','collect','price','sales','credit','integral'))) $sort['sort'] = '';
            if ($sort['order'] != '' and $sort['order'] != 'desc') $sort['order'] = '';
            $this->assign('sort', $sort);

            $cat_info = $this->get_array_search($cat_list,'cat_id',$id,'children_list'); #搜索相关分类
            $ids = $goods_model->get_sub_cat_ids($id);
            $ids[] = $id;
        }

        $this->assign('info', $result['data']);
        $this->assign('aid', AesEnCrypt($aid));
        $this->assign('goods_btn','点击购买');
        $this->assign('godos_empty','<div style="height:5rem;overflow:hidden;text-align:center;line-height:5rem;font-size:.6rem;">该分类下暂无产品！</div>');
        if($keywords){
            $column = $goods_model->get_search_column($type);
            $title = "搜索 ".$column['name'].' : '.$keywords;
            $empty = "没有找到相关产品！";

        } else{
            if($cat_info['parent_id'] != 0) {
                $title = '<a href="' . U('Fenlei/index') . '?id=' . $cat_info['parent_id'] .
                    '">' . $cat_info['parent_name'] . '</a> - ' . $cat_info['cat_name'];
            } else {
                $title = $cat_info['cat_name'];
            }
            $empty = "该分类下暂无产品!";
        }
        $this->assign('goods_empty','<div style="height:5rem;overflow:hidden;text-align:center;line-height:5rem;font-size:.6rem;">'.$empty.'</div>');
        $user_head = array('title'=>$title,'backUrl'=>$this->back_url,'backText'=>'返回分类');
        $this->assign('cat_info', $cat_info);
        $this->assign('user_head',$user_head);
        $this->assign('tkd', array('title'=>$cat_info['cat_name'] . ' 产品列表','keywords'=>'','discription'=>''));
        $this->assign('is_fenlei',false);//是否显示分类
        $this->assign('cur_page','fenlei');
        $this->display('Gift:list');
    }

    public function get_goods_list(){
        $aid = AesDeCrypt(I('get.aid','','trim'));
        $id = I('get.id',0,'intval');
        $keywords = I('get.keywords','','trim');
        $type = 1;

        $goods_model = new Model\GoodsModel();

        $result= curlpost('get_act_info', array_merge(['act_id'=>I('get.aid')], C('API_APPINFO')));

        $this->user_info['uer_rank'] = intval($this->user_info['uer_rank']) <= 0 ? 1 : intval($this->user_info['uer_rank']);
        if ($result['status'] != 200) {
            $result = result_ajax(301,'活动不存在或已过期');
        } elseif (!empty($result['data']['auth_user']) and !in_array($this->user_info['uer_rank'], $result['data']['auth_user'])) {
            $result = result_ajax(301,'无权限的活动！');
        } else {
            //排序
            $sort['sort'] = I('get.sort', '', 'trim');
            $sort['order'] = I('get.order', '', 'trim');
            if (!in_array($sort['sort'], array('new', 'collect', 'price', 'sales', 'credit', 'integral'))) $sort['sort'] = '';
            if ($sort['order'] == '' and $sort['order'] != 'asc') $sort['order'] = 'desc';
            $this->assign('sort', $sort);
            $ids = $goods_model->get_sub_cat_ids($id);
            $ids[] = $id;
            $goods_list = $goods_model->act_goods_list($ids, $aid,$this->get_sort_field($sort['sort']), $sort['order'], $keywords, $type);

            $goods_result= curlpost('get_goods_list', array_merge(['goods_list'=>json_encode($goods_list['goods_list']), 'act_id'=>$aid], C('API_APPINFO')));
            if($goods_result['status'] != 200) $this->ajaxReturn(result_ajax(302,$goods_result['msg']));
            foreach ($goods_list['goods_list'] as $k => &$v) {
                $goods_act = $goods_result['data'][$v['goods_id']];
                $v['show_price'] = $goods_act['show_price'];
                $v['url'] = U('goods/index') . '?id=' . $v['goods_id'] . '&aid=' . AesEnCrypt($v['aid']);
            }
            if(isset($goods_list['goods_list'])){
                $this->assign('info', $result['data']);
                $this->assign('goods_list', $goods_list['goods_list']);
                $result = result_ajax(200,'获取成功',['str'=>$this->fetch('Public:goods_list3'),'pagecount'=>$goods_list['total'],'curpage'=>$goods_list['curpage']]);
            }else{
                $result = result_ajax(201,'当前分类没有产品！');
            }
        }

        $this->ajaxReturn($result);
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
            case 'sales': $result = 'g.is_hot';break;
            case 'credit': $result = 'sc.store_credit';break;
            case 'integral':$result = 'g.integral';break;
            default:$result = '';
        }
        return $result;
    }


    protected function get_giftgoods(){
        $goods_model = new Model\GoodsModel();
        $list = $goods_model->goods_list(0,10,2);
    }

    protected function get_banner(){
        return [];
    }
} 