<?php
namespace Sales\Controller;

use Sales\Model\UsersActivityModel;

class CenterController extends WechatController {

    public function index(){
        $this->display();
    }

    public function active(){
        $model = new UsersActivityModel();
        $this->assign('user_head',['title' => '我的活动']);
        $this->display('Center:active_list');
    }

    public function get_act_list(){
        $model = new UsersActivityModel();
        $info = $model->get_act_list($this->login_info['user_id'], 'all');
        if(!$info){
            $result = $this->result_ajax('301', '获取失败！');
        }else{
            $result = $this->result_ajax('200', '获取成功！', $info);
        }

        $this->ajaxReturn($result, '', true);
    }
}