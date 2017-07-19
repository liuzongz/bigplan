<?php

/**
 * 即牛 - 个人资料
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: ProfileController.class.php 17156 2016-04-20 13:37:47Z keheng $
*/

namespace Wap\Controller;
use Wap\Model;

class ProfileController extends WeixinController {
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();
    }

    public function index(){
        $this->set_form_token();
        $tkd = array('title'=>'','keywords'=>'','discription'=>'');
        $user_info = M('Users')->where('user_id=' . $this->login_info['user_id'])->find();
        $this->assign('user_info', $user_info);
        $this->assign('user_head', array('title'=>'个人资料','backText'=>'首页'));
        if ($url = C('IMG_SERVER')) {
            $upload_server = get_server('IMG_SERVER', '/Uploader/index', ['module'=>'Wap','cat_id'=>3,'_ajax'=>1, 'store_token'=>$this->store_token], 1);
            /*$upload_server = '//' . $url . U('img/Uploader/index',['module'=>'Wap','cat_id'=>3,'_ajax'=>1,'debug'=>'keheng']);*/
        } else {
            $upload_server = U('user_face');
        }
        $this->assign('upload_url', $upload_server);
        $this->assign('tkd', ['title'=>'修改资料']);
        $this->display();
    }

    public function pass(){
        if (IS_POST) {
            $user = I('post.user');
            $pass = I('post.pass','','MD5');
            $newpass = I('post.newpass');
            $newpass1 = I('post.newpass1');

            if (strlen($newpass) < 6 or strlen($newpass) > 18) {
                $this->error('新密码不能小于6位或大于18位！');
            } elseif ($newpass != $newpass1) {
                $this->error('两次密码输入不一致！');
            } else {
                $user_model = new Model\UsersModel();
                if(!$user_model->check_pass($this->user_id,$pass)){
                    $this->error("原密码错误");
                }
                $data = array(
                    'password'  =>  md5($newpass1)
                );
                /*if ($this->user_info['openid'] != ''
                    and $this->user_info['user_name'] != ''     //已填写
                    and $this->user_info['openid'] != $this->user_info['user_name']) {
                    if (md5($pass) != $this->user_info['password']) {
                        $this->error('输入的旧密码不正确！');
                    }
                    $data = array(
                        'password'  =>  md5($newpass1)
                    );
                }*/ /*else {        //未填写
                    $patten = '/[\w\d_]{6,20}/';
                    if ($user != '' and preg_match($patten,$user)) {
                        $res = M('users')->where('user_name="' . $user . '"')->count() ;
                        if ($res > 0) {
                            $this->error('用户名已经存在，请更换用户名再提交！');
                        } else {
                            $data = array(
                                'password'  =>  md5($newpass1),
                                'user_name' =>  $user
                            );
                        }
                    } else {
                        $this->error('用户名必须为6-20位字母、数字或下划线组成！');
                    }
                }*/
                M('users')->where('user_id=' . $this->user_id)->save($data);
                $this->success('用户信息设置成功！',U('User/index'));
            }
        } else {
            #是否设置了用户登录
            if ($this->user_info['openid'] != ''
                and $this->user_info['user_name'] != ''
                and $this->user_info['openid'] != $this->user_info['user_name']) {
                $this->assign('user_is_set', 1);
            }
            $this->assign('tkd', ['title'=>'修改密码']);
            $this->assign('user_info', $this->user_info);
            $this->assign('user_head', array('title'=>'修改密码','backText'=>'首页'));
            $this->display();
        }

    }

    public function email(){
        if(IS_POST){
            $verify = I('post.verify');
            $password = I('post.password','','MD5');
            $email = I('post.email','','trim');
            $user_model = new Model\UsersModel();
            //检查数据
            if(!$this->check_verify($verify)) $this->error("验证码错误");
            if(!$user_model->check_pass($this->user_id,$password)) $this->error("密码错误");
            if(!$user_model->is_email($email)) $this->error("请输入正确的邮箱格式");
            if($user_model->is_exists($email,'email')) $this->error("该邮箱已经被使用");
            //更新邮箱
            $re = $user_model->where('user_id=%d',$this->user_id)->setField(array('email'=>$email));
            if ($re!==false) {
                //来自profile/index的表单
                $result['error'] = 0;
                $result['message'] = "更新成功";
                $result['contents']= "";
            } else {
                $result['error'] = 1;
                $result['message'] = '更新失败！';
            }
            if($this->ajax){
                $this->ajaxReturn($result);
            }else{
                if($result['error']===0){
                    $this->success($result['message'] );
                }else{
                    $this->error($result['message'],U('Profile/email'));
                }
            }
        }else{
            $this->assign('user_head', array('title'=>'修改邮箱','backText'=>'首页'));
            $this->assign('tkd', ['title'=>'修改邮箱']);
            $this->display();
        }

    }

    public function address(){
        $address =  $this->get_address_list($this->user_id);
        foreach ($address as &$v) {
            $v['mobile'] = $this->set_hidden_str($v['mobile'],4);
        }
        $this->assign('address_list',$address);
        $this->assign('address_empty', '<div style="height:100px;line-height:100px;text-align:center;">暂未添加收货地址！</div>');
        $this->assign('user_head', array('title'=>'地址管理','backText'=>'会员首页'));
        $this->assign('tkd', ['title'=>'地址管理']);
        $this->display();
    }

    protected function get_address_list($user_id){
        $res = M('UserAddress')
            ->alias('ua')
            ->field('ua.*,r1.region_name as province_name, r2.region_name as city_name, r3.region_name as district_name')
            ->where('user_id=' . $user_id)
            ->join('left join __REGION__ r1 on ua.province = r1.region_id')
            ->join('left join __REGION__ r2 on ua.city = r2.region_id')
            ->join('left join __REGION__ r3 on ua.district = r3.region_id')
            ->select();
        return $res;
    }

    /**
     * 添加收货地址
     */
    public function add (){
        if (IS_POST) {
            $consignee  = I('post.consignee','','trim');
            $mobile     = I('post.mobile','','trim');
            $email      = I('post.email','','trim');
            $province   = I('post.province',0,'intval');
            $city       = I('post.city',0,'intval');
            $area       = I('post.area',0,'intval');
            $address    = I('post.address','','trim');
            $zipcode    = I('post.zipcode','','trim');
            $best_time  = I('post.best_time','','trim');
            if ($consignee == '') {
                $this->error('收货人姓名不能为空，请填写真实姓名！');
            } else if (!$this->is_phone($mobile)) {
                $this->error('请填写正确的手机号码！');
            } else if (!$this->is_email($email)) {
                $this->error('请填写正确的邮箱！');
            } else if ($province <= 0 or $city <= 0 ) {
                $this->error('请选择您的收货地址！');
            } else if ($address == '') {
                $this->error('请输入详细的收货地址！');
            } else if ($zipcode != '' and !is_numeric($zipcode) and strlen($zipcode) != 6) {
                $this->error('请输入正确的邮编！');
            } else {
                $data = array(
                    'user_id'       =>  $this->user_id,
                    'consignee'     =>  $consignee,
                    'mobile'        =>  $mobile,
                    'email'         =>  $email,
                    'province'      =>  $province,
                    'city'          =>  $city,
                    'district'      =>  $area,
                    'address'       =>  $address,
                    'zipcode'       =>  $zipcode,
                    'best_time'     =>  $best_time,
                );
                if (M('UserAddress')->add($data)) {
                    $this->success('添加完成!',U('address'));
                } else {
                    $this->success('添加失败!');
                }
            }
        } else {
            $id = I('get.id',0,'intval');
            $res = M('UserAddress')->where('address_id=' . $id)->find();
            if ($res) {
                $this->assign('address_info', $res);
            }
            $user_model = new Model\UsersModel();
            $this->assign('best_time', $user_model->get_best_time());
            $this->assign('user_head', array('title'=>'添加收货地址','backText'=>'会员首页'));
            $this->assign('tkd', ['title'=>'添加地址']);
            $this->display();
        }

    }

    /**
     * 修改收货地址
     */
    public function edit_address (){
        if (IS_POST) {
            $address_id = I('post.id',0,'intval');
            $consignee  = I('post.consignee','','trim');
            $mobile     = I('post.mobile','','trim');
            $email      = I('post.email','','trim');
            $province   = I('post.province',0,'intval');
            $city       = I('post.city',0,'intval');
            $area       = I('post.area',0,'intval');
            $address    = I('post.address','','trim');
            $zipcode    = I('post.zipcode','','trim');
            $best_time  = I('post.best_time','','trim');
            if ($consignee == '') {
                $this->error('收货人姓名不能为空，请填写真实姓名！');
            } else if (!$this->is_phone($mobile)) {
                $this->error('请填写正确的手机号码！');
            } else if (!$this->is_email($email)) {
                $this->error('请填写正确的邮箱！');
            } else if ($province <= 0 or $city <= 0 ) {
                $this->error('请选择您的收货地址！');
            } else if ($address == '') {
                $this->error('请输入详细的收货地址！');
            } else if ($zipcode != '' and !is_numeric($zipcode) and strlen($zipcode) != 6) {
                $this->error('请输入正确的邮编！');
            } else {
                $data = array(
                    'consignee'     =>  $consignee,
                    'mobile'        =>  $mobile,
                    'email'         =>  $email,
                    'province'      =>  $province,
                    'city'          =>  $city,
                    'district'      =>  $area,
                    'address'       =>  $address,
                    'zipcode'       =>  $zipcode,
                    'best_time'     =>  $best_time,
                );
                $re =M('UserAddress')->where('user_id=%d and address_id = %d',$this->user_id,$address_id)->save($data);
                if ($re) {
                    $this->success('修改完成',U('address'));
                } else {

                    $this->success('修改失败!');
                }
            }
        } else {
            $id = I('get.id',0,'intval');
            $res = M('UserAddress')->where('address_id=' . $id)->find();
            if ($res) {
                $this->assign('address_info', $res);
            }
            $user_model = new Model\UsersModel();
            $this->assign('best_time', $user_model->get_best_time());
            $this->assign('user_head', array('title'=>'编辑收货地址','backText'=>'会员首页'));
            $this->assign('tkd', ['title'=>'修改地址']);
            $this->display('add');
        }

    }

    public function del(){
        $id = I('get.id',0,'intval');
        $res = M('UserAddress')->where('user_id=' . $this->user_id . ' and address_id=' . $id)->delete();
        if ($res) {
            $this->success('删除成功 ！',U('address'));
        } else {
            $this->error('删除失败！',U('address'));
        }
    }

    /**
     * 修改资料
     *
     */
    public function mod(){
        if(IS_POST){
            $user_model = new Model\UsersModel();
            $user_info = $user_model->where('user_id=%d',$this->login_info['user_id'])->find();
            $nickname = I('post.nickname','',array('htmlspecialchars','trim','stripslashes'));
            $true_name = I('post.true_name','',array('htmlspecialchars','trim','stripslashes'));
            if(!$nickname) $this->error("请设置昵称");
            if($user_model->is_exists($nickname,'nickname')&&$user_info['nickname']!=$nickname){
                $this->error("昵称已被使用");
            }
            $strlen = mb_strlen($nickname,'UTF8');
            if(!$this->is_check_string($nickname)||$strlen<2||$strlen>16){
                $this->error("昵称必须为2-15位中文、字母、数字、下划线组成");
            }



            if($true_name){
                $strlen = mb_strlen($true_name,'UTF8');
                if(!$this->is_chinese($true_name)||$strlen<2||$strlen>10){
                    $this->error("真实姓名必须为2-10位汉字");
                }

            }

            $data = array(
                'nickname'  =>  $nickname,
                'true_name' =>  $true_name ,
                'sex'       =>  I('post.sex',0,'intval'),
                'province'  =>  I('post.province',0,'intval'),
                'city'      =>  I('post.city',0,'intval'),
                'district'  =>  I('post.district',0,'intval'),
            );

            //昵称已经设置昵称就不能更改
            // if($user_info['nickname']) unset($data['nickname']);
            //真实姓名已经设置不允许更改
            if($user_info['true_name']) unset($data['true_name']);

            $re = $user_model->where('user_id=%d',$this->user_id)->save($data);
            if ($re!==false) {
                //来自profile/index的表单
                $this->success("更新成功");
            } else {
                $this->error("更新失败");
            }

        }










    }


    public function user_face(){
        $str = I('post.str','',array('htmlspecialchars','stripslashes'));
        $file = $this->save_str_img($str);
        $user_model = new Model\UsersModel();
        if ($file) {
            if (M('Users')->where('user_id=' . $this->user_id)->save(array('user_avatar' => $file))) {
                $result = $this->result_ajax(0,'上传成功！', $user_model->img_url($file));
            } else {
                $result = $this->result_ajax(3,'上传失败！');
            }
        } else {
            $result = $this->result_ajax(5,'上传失败！', $file);
        }
        $this->ajaxReturn($result);
    }

    //上传到图片服务器后保存地址
    public function ajax_user_face(){
        $str = I('post.str','',array('htmlspecialchars','stripslashes'));
        if (isset($str['savepath']) && isset($str['savename'])) {
            $file = $str['savepath'] . $str['savename'];
            $user_model = new Model\UsersModel();
            if (M('Users')->where('user_id=' . $this->login_info['user_id'])->save(array('user_avatar' => $file))) {
                $result = result_ajax(200,'上传成功！', $user_model->img_url($file));
            } else {
                $result = result_ajax(301,'上传失败！',$file);
            }
        } else {
            $result = result_ajax(302,'上传失败！');
        }
        $this->ajaxReturn($result);
    }
} 