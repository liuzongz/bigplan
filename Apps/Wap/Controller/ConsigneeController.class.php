<?php
namespace Wap\Controller;
use Wap\Model\CartModel;

class ConsigneeController extends WapController {
    function index($uri = ''){
        $this->CheckLogin();
        $user_id = $this->user_id;

        $me = new CartModel();//D('Flow');
        $Public = D('Public');

        $consignee = $me->get_consignee($user_id);//取所有收货地址
        $this->assign('consignee_list', $consignee);
        $this->assign('consign_empty', '<li class="empty" style="line-height:3rem;text-align:center;">暂无收货地址，请点击上面添加收货地址添加</li>');

        $user_info = $Public->get_user_info($user_id);//取用户默认收货地址
        $this->assign('address_id', $user_info['address_id']);

        $region_list = $Public->get_region_info();//城市列表
        $this->assign('region_list', $region_list);

        $besttimelist = $Public->get_best_time_list();
        $this->assign('besttimelist', $besttimelist);

        $url['select_address']  = U('Checkout/index?address_id=');        //选择地址后返回的地址
        $url['get_consignee']   = U('Consignee/get_consignee');         //ajax获取收货信息地址
        $url['get_region']      = U('Consignee/get_region');              //ajax获取城市信息
        $url['add_consignee']   = U('Consignee/add_consignee');        //ajax添加或修改收货地址
        $url['set_consignee']   = U('Consignee/set_consignee');        //ajax设置默认收货地址
        $url['del_consignee']   = U('Consignee/del_consignee');         //ajax删除收货地址
        $this->assign('url', $url);

        $this->assign('address_box_frame', true);//读取地址列表是否有外框
        $this->assign('user_head', array('title'=>'收货地址管理','backUrl'=>U('Index/index'),'backText'=>'首页'));
        $this->display('Flow/consignee');
    }

    function get_consignee($id = 0){
        $this->CheckLogin(1);
        $user_id = $this->user_id;

        $result = array('error'=>0,'message'=>'','content'=>'');
        if ($id == 0){
            $result['error'] = 1;
            $result['message'] = '错误';
        } else {
            $user_id = session('user_id');  //检查登录状态
            if ($user_id <= 0){
                $result['error'] = 2;
                $result['message'] = '请先登录';
                $result['content']['url'] = U('User/login?uri=');
            } else {
                $f = D('Public');
                $consi = D('Flow')->get_consignee($user_id,$id);
                if ($consi) {
                    //$consi['countrylist']     = $f->get_region_info();
                    $consi['provincelist']     = $f->get_region_info($consi['country']);
                    $consi['citylist']         = $f->get_region_info($consi['province']);
                    $consi['districtlist']      = $f->get_region_info($consi['city']);
                    $result['error']            = 0;
                    $result['message']          = '';
                    $result['content']          = $consi;
                } else {
                    $result['error'] = 3;
                    $result['message'] = '未找到信息';
                }
            }
        }
        $this->ajaxReturn($result);
    }

    function get_region($id){
        $result = array('error'=>0,'message'=>'','content'=>'');
        if (!is_numeric($id)){
            $result['error'] = 0;
            $result['message'] = '错误！';
        } else {
            $f = D('Public');
            $arr = $f->get_region_info($id);
            if ($arr) {
                $result['error'] = 0;
                $result['message'] = '！';
                $result['centent'] = $arr;
            } else {
                $result['error'] = 0;
                $result['message'] = '错误2！';
            }
        }
        $this->ajaxReturn($result);
    }

    function add_consignee(){
        $this->CheckLogin(1);
        $user_id = $this->user_id;

        //$user_id    = session('user_id');
        $id         = intval(I('post.id'));
        $nick       = I('post.nick');
        $prov       = intval(I('post.prov'));
        $city       = intval(I('post.city'));
        $dist       = intval(I('post.dist'));
        $addr       = I('post.addr');
        $email      = I('post.email');
        $tel        = I('post.tel');
        $zipcode    = intval(I('post.zipcode'));
        $building   = I('post.building');
        $best       = intval(I('post.best'));
        $mobile     = I('post.mobile');
        $me = D('Public');

        $result = array('error'=>0,'message'=>'','content'=>'');
        if ($user_id <= 0){
            $result['error'] = 11;
            $result['message'] = '请先登录后再添加收货地址！';
            $result['content']['url'] = U('User/login?uri=Flow/consignee');
        } else if ($nick == '') {
            $result['error'] = 1;
            $result['message'] = '收货人姓名不能为空，请输入收货人姓名！';
        } else if ($prov > 0 and $me->get_region_text($prov) == ''){
            $result['error'] = 2;
            $result['message'] = '请选择收货省份！';
        } else if ($city > 0 and $me->get_region_text($city) == ''){
            $result['error'] = 3;
            $result['message'] = '请选择收货城市！';
            /* } else if ($me->get_region_text($dist) == ''){
                 $result['error'] = 4;
                 $result['message'] = '请选择收货区或县！';*/
        } else if ($addr == ''){
            $result['error'] = 5;
            $result['message'] = '请输入收货详细地址！';
        } else if ($email != '' and !is_email($email)){
            $result['error'] = 6;
            $result['message'] = '请输入正确的邮箱地址！';
        } else if ($mobile != '' and !is_mobile_num($mobile)){
            $result['error'] = 7;
            $result['message'] = '请输入正确的手机号码！';
        } else if ($tel != '' and !is_tel($tel)){
            $result['error'] = 8;
            $result['message'] = '电话号码输入不正确，请输入区号 + 减号（-） + 电话号码！';
        } else if ($tel == '' and $mobile == ''){
            $result['error'] = 9;
            $result['message'] = '联系电话和手机号码最少要输入一个！';
        } else {

            $data = array(
                'consignee'     =>  $nick,
                'country'       =>  0,
                'province'      =>  $prov,
                'city'          =>  $city,
                'district'      =>  $dist,
                'address'       =>  $addr,
                'email'         =>  $email,
                'tel'           =>  $tel,
                'mobile'        =>  $mobile,
                'zipcode'       =>  $zipcode,
                'sign_building' =>  $building,
                'best_time'     =>  $best,
                'user_id'       =>  $user_id
            );
            $m = D('user_address');
            if ($id > 0){
                $res = $m->where('address_id=' . $id)->save($data);
            } else {
                $res = $m->add($data);
            }
            //echo $res;
            if ($res === false){
                $result['error'] = 10;
                $result['message'] = '失败！';
            } else {
                $result['error'] = 0;
                $result['message'] = '';
            }

        }
        $this->ajaxReturn($result);
    }

    function set_consignee($id){
        $this->CheckLogin(1);
        $user_id = $this->user_id;

        $id = intval($id);
        //$user_id = session('user_id');
        $result = array('error'=>0,'message'=>'','content'=>'');
        if ($id <= 0){
            $result['error'] = 1;
            $result['message'] = '设置错误！';
        } else {
            $arr = D('Flow')->get_consignee($user_id, $id);
            if ($id <= 0 || !$arr) {
                $result['error'] = 2;
                $result['message'] = '信息错误！';
                $result['content']['url'] = U('User/login?uri=Flow/consignee');
            } else {
                $m = D('users')->where('user_id=' . $user_id)->save(array('address_id'=>$id));
                if ($m) {
                    $result['error'] = 0;
                    $result['message'] = '修改成功！';
                } else {
                    $result['error'] = 0;
                    $result['message'] = '修改失败！';
                }
            }
        }
        $this->ajaxReturn($result);
    }

    function del_consignee($id = 0){
        $this->CheckLogin(1);
        $user_id = $this->user_id;
        $id = intval(I('post.id'));
        $result = array('error'=>0,'message'=>'','content'=>'');
        if ($id <= 0){
            $result['error'] = 1;
            $result['message'] = '删除地址时出现错误！';
        } else {
            $arr = D('Flow')->get_consignee($user_id, $id);
            if (!$arr) {
                $result['error'] = 2;
                $result['message'] = '信息错误！';
                $result['content']['url'] = U('User/login?uri=Flow/consignee');
            } else {
                $m = D('users')->where('user_id=' . $user_id)->delete();
                if ($m) {
                    $result['error'] = 0;
                    $result['message'] = '删除成功！';
                } else {
                    $result['error'] = 0;
                    $result['message'] = '删除失败！';
                }
            }
        }
        $this->ajaxReturn($result);
    }
}