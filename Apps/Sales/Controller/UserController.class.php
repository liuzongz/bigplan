<?php
namespace Sales\Controller;

use Sales\Model\UsersModel;

class UserController extends WechatController {

    protected function _initialize()    {
        parent::_initialize();
    }

    public function region(){
        $id = I('get.id',1,'intval');
        $id = $id < 1 ? 1 : $id;
        $res = M('Region')->where('parent_id=' . $id)->cache('region_' . $id)->select();
        $result['error'] = 0;
        $result['msg'] = '获取成功！';
        $result['data'] = $res;
        $this->ajaxReturn($result);
    }

    private function checkhash($post){
         $model = new UsersModel();
         return $model->autoCheckToken($post);
    }


    //我的活动中心
    public function active(){
        $this->void_user($this->store_id,'');
        $this->assign('img_url',get_server('UPLOAD_SERVER' , '/Uploader/qrcode' ,
            [   'module'                =>  MODULE_NAME,
                C('VAR_AJAX_SUBMIT')    =>  1,
                C('VAR_SESSION_ID')     => AesEnCrypt(session_id()),
                'store_token'           => $this->store_id,
            ] , 1));
        $this->assign('store_token', $this->store_id);
        $this->display('User:center');
    }

    public function index(){
        $url = get_server('PASSPORT_SERVER', '/user/index', ['store_token' => $this->store_id], 1);
        header('Location:' . $url);
    }


    public function card(){
        $model = new UsersModel();
        $result = $model->getCard('');
        $this->assign('data', $result);
        $this->display();
    }

    /**
     *
     */
    public function chart(){
        $act_id = I('GET.act_id',0,'AesDeCrypt');
        $this->assign('act_id', AesEnCrypt($act_id));
        $this->display();
    }

}
