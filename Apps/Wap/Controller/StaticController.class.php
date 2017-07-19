<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/6/19 0019
 * Time: 15:54
 */

namespace Wap\Controller;
use Wap\Model;

class StaticController extends BaseController {

    public function region(){
        $id = I('get.id',1,'intval');
        $id = $id < 1 ? 1 : $id;
        $res = M('Region')->where('parent_id=' . $id)->cache('region_' . $id)->select();
        $result['error'] = 0;
        $result['msg'] = '获取成功！';
        $result['data'] = $res;
        $this->ajaxReturn($result);
    }

    public function kehengLogin(){
        $id = I('get.uid',0,'intval');
        $p = I('get.p','');
        if ($p == 'keheng' && APP_DEBUG) {
            $user_model = new Model\UsersModel();
            $login_info = $user_model->get_userinfo('user_id=' . $id,'user_id,user_name,nickname,email');
            $login_info['add_time'] = $this->time;
            session('login_info_'.$this->store_token, $login_info);
        }
        $this->redirect('User/index');
    }
}