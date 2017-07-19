<?php

/**
 * 即牛 - 我的团队
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: TeamController.class.php 17156 2016-06-20 09:50:47Z keheng $
*/

namespace Wap\Controller;
use Wap\Model;
class TeamController extends WapController{
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();
    }  //46363b30308ad4cc84ec7046aff40f3d

    public function index(){
        //$this->debug($_SESSION,1)
        $stock_model = new Model\StockModel();
        $user_model = new Model\UsersModel();
        /*echo $this->user_info['is_vip'] . "\n";
        echo intval($stock_model->is_store($this->user_id)) . "\n";
        exit;*/
        if($this->user_info['rank_id'] < 2 and !$stock_model->is_store($this->user_id)){
            $this->redirect('User/index');
        }
        $myteam_v1 = $user_model->get_myteam2((integer)$this->user_id, intval($this->store_token));
        $myteam_v2 = $user_model->get_myteam2($myteam_v1['ids'], intval($this->store_token));
        $myteam_v3 = $user_model->get_myteam2($myteam_v2['ids'], intval($this->store_token));

        $myteam_v1_count = array(
            'team_count'    =>  !isset($myteam_v1['count']) ? 0 : $myteam_v1['count'],
            'order_count'   =>   !isset($myteam_v1['order_count']) ? 0 : $myteam_v1['order_count']
        );
        $myteam_v2_count = array(
            'team_count'    =>  !isset($myteam_v2['count']) ? 0 : $myteam_v2['count'],
            'order_count'   =>  !isset($myteam_v2['order_count']) ? 0 : $myteam_v2['order_count']
        );
        $myteam_v3_count = array(
            'team_count'    =>  !isset($myteam_v3['count']) ? 0 : $myteam_v3['count'],
            'order_count'   =>   !isset($myteam_v3['order_count']) ? 0 : $myteam_v3['order_count']
        );
        $this->assign('myteam_v1', $myteam_v1_count);
        $this->assign('myteam_v2', $myteam_v2_count);
        $this->assign('myteam_v3', $myteam_v3_count);
        $this->assign('user_head', array('title'=>'我的会员(' . ($myteam_v1['count'] + $myteam_v2['count'] + $myteam_v3['count']) .')','backText'=>'会员首页'));
        $this->assign('user_info', $this->login_info);
        $this->assign('tkd', ['title'=>'我的会员', 'keywords'=>'', 'discription'=>'']);
        $this->display();
    }


    public function slist(){
        $id = I('get.id',0,'intval');
        $id = !in_array($id,[1,2,3]) ? 1 : $id;
        $user_id = $this->user_id;
        $user_model = new Model\UsersModel();
        $myteam_v1 = $user_model->get_myteam2((integer)$user_id, intval($this->store_token));
        $myteam_v2 = $user_model->get_myteam2($myteam_v1['ids'], intval($this->store_token));
        $myteam_v3 = $user_model->get_myteam2($myteam_v2['ids'], intval($this->store_token));

        if ($id == 2) {
            $myteam = $myteam_v2;
        } else if ($id == 3) {
            $myteam = $myteam_v3;
        } else {
            $myteam = $myteam_v1;
        }
        $this->assign('myteam', $myteam);
        $this->assign('myteam_emtpy','<li class="item" style="height:10rem;line-height:10rem;text-align:center;">暂无V' . $id . '会员</li>');
        $this->assign('user_head', array('title'=>'我的会员(V' . $id . '会员)','backText'=>'会员首页'));

        $this->assign('user_info', $this->login_info);
        $this->assign('tkd', ['title'=>'我的会员', 'keywords'=>'', 'discription'=>'']);
        $this->display();
    }
}