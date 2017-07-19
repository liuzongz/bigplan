<?php

/**
 * 即牛 - 活动专题页
 * ============================================================================
 * 版权所有 2011-2015 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: ActiveController.class.php 17156 2015-12-11 16:10:47Z keheng $
*/

namespace Wap\Controller;
use Wap\Model;
class ActiveController extends WapController {
    protected $active_name = '';
    protected $active_list = [];
    protected $favourable;
    protected function _initialize(){
        parent::_initialize();
        $this->is_login();
    }

    public function index(){
        $aid = AesDeCrypt(I('get.aid','','trim'));

        $result= curlpost('get_act_info', array_merge(['act_id'=>I('get.aid')], C('API_APPINFO')));

        $this->get_dealer_status();
        $this->user_info['user_rank'] = intval($this->user_info['user_rank']) <= 0 ? 1 : intval($this->user_info['user_rank']);

        if ($result['status'] != 200) {
            $this->error('活动不存在或已过期！');
        }elseif (!isset($this->user_info['is_store']) and !empty($result['data']['auth_user']) and !in_array($this->user_info['user_rank'], $result['data']['auth_user'])) {
            $this->error('您不能参与当前活动！');
        } else {
            $activity = $result['data'];
            $goods_model = new Model\GoodsModel();
            $category_list = $goods_model->get_cate_list($activity['config']['cate']['value']);//$goods_model->get_each_cate_goods('add_time desc',4,2);
            $category_ids = $this->get_array_value($category_list,'cat_id');        //取所有ID备用查询产品
            $category_list = $this->get_array_tree($category_list,'cat_id','parent_id');                 //生成树
            $category_list = $this->get_array_sort($category_list,'sort_order');                    //排序显示

            //接口获取活动所有活动相关的数据
            $all_goods= curlpost('get_act_allGoods', array_merge(['act_id'=>I('get.aid')], C('API_APPINFO')));
            if($all_goods['status'] != 200) $this->error($all_goods['msg']);
            $active_list = $all_goods['data'];

            $goods_ids = array_column($active_list, 'goods_id','goods_id');
            $goods_list = [];
            if(!empty($active_list) and !empty($goods_ids)) {
                $goods_list = $goods_model->get_goods_list_by_IDCATE($goods_ids, $category_ids, 'recommend DESC, ga.id DESC');

            }else{
                $this->error('该活动下暂时未添加产品！');
            }
            $i = 0;  $active_ids = [];

            foreach ($active_list  as $kkk => $vvv) {
                $active_ids[$vvv['goods_id']] = $vvv;
            }

            foreach ($category_list as $kk => $vv) {
                $category_list[$kk]['url'] = U('Gift/fenlei') . '?id=' . $vv['cat_id'] . '&aid=' . AesEnCrypt($aid);
            }

            //获取所有活动相关的商品
            $goods_result= curlpost('get_goods_list', array_merge(['goods_list'=>json_encode($goods_list), 'act_id'=>$aid], C('API_APPINFO')));
            if($goods_result['status'] != 200) $this->error($goods_result['msg']);

            foreach ($goods_list as $k => &$v) {
                if(isset($goods_result['data'][$v['goods_id']])){
                    $goods_activity = $goods_result['data'][$v['goods_id']];
                }else{
                    unset($goods_list[$k]);
                    continue;
                }
                $v['act_type'] = $goods_activity['act_data']['act_type'];
                $v['show_price'] = intval($goods_activity['show_price']);
                $v['url'] = U('goods/index') . '?id=' . $v['goods_id'] .'&aid=' . AesEnCrypt($active_ids[$v['goods_id']]['id']);
                $v['act_data'] = $goods_activity['data'];


                foreach ($category_list as $kk => $vv) {
                    if ($i == 0) {
                        $category_list[$kk]['url'] = U('Gift/fenlei') . '?id=' . $vv['cat_id'] . '&aid=' . AesEnCrypt($aid);
                    }
                    if ($v['cat_id'] == $vv['cat_id'] || in_array($v['cat_id'], $this->get_array_value($vv, 'cat_id'))) {
                        $category_list[$kk]['goods_list'][] = $v;
                    }
                }
            }
            $info = $activity['data'];
            $info['act_id'] = AesEnCrypt($aid);
            $this->assign('info', $info);
            $this->assign('category_list', $category_list);
            $this->assign('cur_page','gift');
            $this->assign('goods_empty', '<li class="goods_empty">该分类下暂无产品！</li>');
            $this->display('Active:index');
        }

    }

    private function get_active_item(){
        return M('favourable')->field('class,name')->select();
    }
} 