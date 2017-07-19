<?php

/**
 * 即牛 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2015 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: UserController.class.php 17156 2015-12-31 13:03:47Z keheng $
*/

namespace Wap\Controller;
use Think\Image;
use Think\Upload;
use Wap\Model;
class UserController extends WapController {
    protected function _initialize() {
        parent::_initialize();
        header('Location:'.get_server('PASSPORT_SERVER', '/user/index', ['store_token', $this->store_token]));
        $this->get_back_url();
        $this->set_dealer_status(0);
    }
    public function index(){
        $this->void_user();
        $agency_model = new Model\AgencyModel();
        $user_model = new Model\UsersModel();
        $stock_model = new Model\StockModel();
        //$vip = $user_model->get_vip_name($this->user_info);
        //此页面需要填写昵称
        if(!$this->user_info['nickname']){
            $this->error("请先完善个人资料",U('Profile/index'));
        }

        $order_handle = array(
            array('text'=>'待付款','url'=>U('Order/index?t=pay'),'icon'=>'1'),
            array('text'=>'待收货','url'=>U('Order/index?t=confirm'),'icon'=>'2'),
            array('text'=>'待评价','url'=>U('Order/index?t=comment'),'icon'=>'3'),
            array('text'=>'已退货','url'=>U('Order/index?t=refund'),'icon'=>'4'),
        );
        $this->assign('order_handle',$order_handle);
        $data = array(
            array('name'=>'我的订单','url'=>U('Order/index'),'icon'=>'wodedingdan','color'=>'#CF3A3C'),
            array('name'=>'专属名片','url'=>U('Index/share') . '?rec=' . AesEnCrypt($this->user_id),'icon'=>'mingpian','color'=>'#E54949'),
            array('name'=>'我的账户','url'=>U('Wallet/dynamic'),'icon'=>'qianbao','color'=>'#5CA2E7'),
            //array('name'=>'我的红包','url'=>U('User/voucher'),'icon'=>'hb'),
            //array('name'=>$this->_CFG['integral_name'] . '兑物','url'=>U('Gift/index'),'icon'=>'duihuan'),
            //array('name'=>'今日分享','url'=>U('Share/index'),'icon'=>'fx','handle'=>array(array('name'=>'分享创富','url'=>'','color'=>'red'))),
            //array('name'=>'我的会员','url'=>U('Team/index'),'icon'=>'hy'),
            /*array('name'=>'我的拥金','url'=>U('Order/agency?'),'icon'=>'3'),
            array('name'=>'充值提现','url'=>U('User/agency'),'icon'=>'4'),
            */
        );
        if (!$this->is_weixin) {
//            $data[] = array('name'=>'我的会员','url'=>U('Team/index'),'icon'=>'huiyuan','color'=>'#F4A83A');
            $data[] = array('name'=>'我的KP券','url'=>U('Rebate/index'),'icon'=>'service','color'=>'#5CA2E7','handle'=>array(array('name'=>'激活','url'=>U('Rebate/active'),'color'=>'red'),array('name'=>'查看','url'=>U('Rebate/index'),'color'=>'red')));
            $data[] = array('name'=>'今日分享','url'=>U('Article/slist') . '?id=13&share','icon'=>'fenxiang','color'=>'#FFA401','handle'=>array(array('name'=>'分享创富','url'=>'','color'=>'red')));
            /*if($this->user_info['is_vip'] > 0 or $stock_model->is_store($this->user_id)){
                $data[] = array('name'=>'我的会员','url'=>U('Team/index'),'icon'=>'huiyuan','color'=>'#F4A83A');
            }*/


        }
        /*if ($this->user_id == 176) {
            $data[] = ['name'=>'测试页','url'=>U('get_agent'),'icon'=>'test'];
        }*/
        $menus[] = $data;
        $menus[] = array(
            array('name'=>'修改资料','url'=>U('Profile/index'),'icon'=>'bianji','color'=>'#B1CB2A'),
            //array('name'=>'地址管理','url'=>U('User/address'),'icon'=>'dz'),
            //array('name'=>'登录设置','url'=>U('User/setlogin'),'icon'=>'dl'),
        );
        //$apply = $agency_model->get_apply($this->user_id);
        //if ($apply['state'] == AS_THROUGH) {
        if ($stock_model->is_store($this->user_id)) {
            $menus[] = array(
                //array('name'=>'我的微店','url'=>U('Agency/index'),'icon'=>'wd'),
                array('name'=>'入驻信息','url'=>U('apply/index'),'icon'=>'ruzhuhetong','color'=>'#B1CB2A'),
                array('name'=>'我要进货','url'=>U('Stock/index'),'icon'=>'jinhuozr','color'=>'#B1CB2A'),
            );
            $this->user_info['vip_name'] = '企业主';
        } else {
            if ($this->user_info['is_vip'] > 0) {
                $this->user_info['vip_name'] = '在职员工';
            } else if ($this->user_info['is_vip'] < 0) {
                $this->user_info['vip_name'] = '离职员工';
            } else {
                $this->user_info['vip_name'] = '会员';
            }
            $menus[] = array(
                array('name'=>'我要入驻','url'=>U('apply/index'),'icon'=>'iconfontruzhu','color'=>'#B1CB2A'),
            );
        }
        /*$menus[] = array(
//            array('name'=>'退出会员','url'=>U('User/logout'),'icon'=>'tuichu'),
        );*/
        //获取公告信息
        $this->assign('notice',$this->get_notice());
        $this->user_info['agency'] = $user_model->get_user_agency($this->user_id);
        $this->user_info['user_avatar'] = $user_model->img_url($this->user_info['user_avatar']);
        $this->assign('menus', $menus);
        $this->assign('user_head', array('title'=>'会员中心','backUrl'=>U('Index/index'),'backText'=>'首页'));
        $this->assign('url', array('logout'=>U('User/logout')));
        $this->assign('memberInfo', $this->user_info);//$this->debug($this->user_info,1);
        $this->assign('cur_page','user');
        $this->display();
    }


    public function login(){
        //从cookie中获取用户信息
        if(cookie('login_info') && !session('login_info')){
            session('login_info', $this->get_login_cookie());
		}/*2016/9/14*/
        $login_info = session('login_info');
        if (!$this->is_login($login_info)) {
            $this->assign('action_url', U('User/login_act'));
            $this->assign('verify_url', U('User/Verify'));
            $this->assign('back_act', AesEnCrypt($this->back_url));
            $this->assign('tkd',['title'=>' 会员登录','keywords'=>$this->_CFG['shop_name'],'discription'=>$this->_CFG['shop_name']]);
            $this->display('login1');
        } else {    //已登录跳转用户中心首页
            $this->redirect('User/index');
        }
    }
    //http://www.weipincn.com/Wap/User/wxlogin.html?back_act=tym1XMBzG0oN4az3gleMWU9XcpZrzO/7GcYrziBKBpM=&code=0316a3df54a148e8171ec8069fdf498x&state=49298711
    //http://www.weipincn.com/Wap/User/wxlogin.html?back_act=tym1XMBzG0oN4az3gleMWU9XcpZrzO/7Y2/n0BakDgzZ7TI21lVz5A==&code=031fc0eb5d1f5122af9348de0e571dbF&state=12352432
    /*public function wxlogin() {
        if ($this->is_weixin()) {
            if ($this->wx_login($_GET['code'],$_GET['state'])) {
                header('location:' . $this->back_url);
            } else {
                $this->error('登录失败NO:01',U('Index/index'));
            }
        } else {
            $this->redirect('User/login');
        }
    }*/

    public function oauth(){
        if (isset($_GET['ss_id']) and trim($_GET['ss_id']) != '') {
            header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
            setcookie("WP_AUTH", trim($_GET['ss_id']), time() + 3600, "/", ".weipincn.com");
        }
    }

    public function login_act(){
        if (IS_POST) {
            $user = I('post.email','','trim');
            $pass = I('post.password','','trim');
            $veri = I('post.verify');
            //$back = I('post.back_act');
            if (strlen($user) < 6) {
                $this->error('请输入正确的用户名！');
            } else {
                $user_model = new Model\UsersModel();
                $res = $user_model->get_userinfo('user_name="' . $user . '" or email="' . $user . '" or mobile_phone="' . $user . '"','user_id,openid,user_name,nickname,password,last_login,last_time,last_ip,visit_count,email');
                if ($res) {
                    if ($res['password'] != md5($pass)) {
                        $this->error('用户或密码输入错误，请重新输入！NO.1');
                    /*} elseif ($res['openid'] != '' and $res['openid'] != $this->wx_token['openid']) {
                        $this->error('该用户与当前微信登录用户不匹配，请用与当前微信匹配用户登录！');*/
                    } else {
                        $data = array(
                            'visit_count'       => $res['visit_count'] + 1,
                            'last_login'         => $this->time,
                            'last_ip'           => get_client_ip(),
                        );
                        if ($this->wx_token['openid'] != '' and $this->store_wx['gd_id'] != '') {
                            if (!$user_model->get_userwx_info($res['user_id'],$this->wx_token['openid'],$this->store_wx['gd_id'])) {
                                $user_model->add_userwx_info($res['user_id'],$this->wx_token['openid'],$this->store_wx['gd_id']);
                            }
                        }
                        M('users')->where('user_id=' . $res['user_id'])->save($data);
                        $user_info = array(
                            'user_id'           =>  $res['user_id'],
                            'user_name'         =>  $res['user_name'],
                            'nickname'          =>  $res['nickname'],
                            'openid'            =>  $this->wx_token['openid'],
                            'email'             =>  $res['email'],
                            'add_time'          =>  $this->time
                        );
                        session('login_info', $user_info);
                        $this->set_login_cookie($user_info);/*2016/9/14*/
                        header('location:' . $this->back_url);
                        //$this->success('登录成功！',$this->back_url);
                    }
                } else {
                    $this->error('用户或密码输入错误，请重新输入！NO.2');
                }
            }
        } else {
            if(cookie('login_info') && !session('login_info')){
                session('login_info', $this->get_login_cookie());
            }
            $login_info = session('login_info');
            if (!$this->is_login($login_info)) {
                //header('location:' . U('index/index'));
                $this->assign('action_url', U());
                $this->assign('verify_url', U('User/Verify'));
                $this->assign('back_act', AesEnCrypt($this->back_url));
                $this->assign('tkd',['title'=>$this->_CFG['shop_name'] . ' 会员登录','keywords'=>$this->_CFG['shop_name'],'discription'=>$this->_CFG['shop_name']]);
                $this->display('login1');
            } else {    //已登录跳转用户中心首页
                $this->redirect('User/index');
            }
        }
    }
    //http://www.weipincn.com/Wap/User/wxlogin.html?back_act=tym1XMBzG0oN4az3gleMWU9XcpZrzO/7GcYrziBKBpM=&code=0316a3df54a148e8171ec8069fdf498x&state=49298711
    //http://www.weipincn.com/Wap/User/wxlogin.html?back_act=tym1XMBzG0oN4az3gleMWU9XcpZrzO/7Y2/n0BakDgzZ7TI21lVz5A==&code=031fc0eb5d1f5122af9348de0e571dbF&state=12352432
    /*public function wxlogin() {
        if ($this->is_weixin()) {
            if ($this->wx_login($_GET['code'],$_GET['state'])) {
                header('location:' . $this->back_url);
            } else {
                $this->error('登录失败NO:01',U('Index/index'));
            }
        } else {
            $this->redirect('User/login');
        }
    }*/
/*
    private  function oauth(){
        if (isset($_GET['ss_id']) and trim($_GET['ss_id']) != '') {
            header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
            setcookie("WP_AUTH", trim($_GET['ss_id']), time() + 3600, "/", ".weipincn.com");
        }
    }*/


    /**
     * 获取登录状态
     */
    protected function get_login_status(){
        if (!$login_info_sess = session('login_info')) {
            $login_info_cook = cookie('login_info');
            $login_info = unserialize(AesDeCrypt($login_info_cook));
            if ($login_info_cook and $login_info and $this->is_login($login_info)) {
                $arr['add_time'] = $this->time;
                session('login_info', $login_info);
                //$this->debug($this->user_info);
            } else {

            }
        } else {
            //$this->debug($login_info_sess);
        }
    }

    /**
     * 设置登录cookie
     * @param $login_info
     * @param int $time
     */
    protected  function set_login_cookie($login_info, $time = 604800){
        $str =  AesEnCrypt(serialize($login_info));
        cookie('login_info',$str,$time);
        $data = [
            'user_id'       =>  $login_info['user_id'],
            'user_name'     =>  $login_info['user_name'],
            'add_time'    =>  $login_info['add_time'],
            'login_module'  =>  'Wap'
        ];
        cookie(strtoupper(MODULE_NAME), RsaEnCrypt(json_encode($data)));
    }

    /**
     * 获取登录cookie
     * @return mixed
     */
    protected function get_login_cookie(){
        $str = cookie('login_info');
        $arr = [];
        if ($str) {
            $arr = unserialize(AesDeCrypt($str));
            if(!is_array($arr)) return false;
            $arr['add_time'] = $this->time;
        }
        return $arr;
    }

    /*
     * 使用cookie登录
     */
    protected function login_by_cookie(){
        //print_r($_COOKIE);
        //session中有登录信息，或者cookie中没有登录信息,中断
        if(session('login_info')||!cookie('login_info')) return ;
        if($login_info = $this->get_login_cookie()){
            $user_id = $login_info['user_id'];
            //查看是否有此用户
            $count = M('users')->where('user_id=%d',$user_id)->count();
            if(!$count) return ;
            //设置登录信息
            session('login_info',$login_info);
        }
    }




    /**
     * 密码找回页
     */
    public function reset_ways(){
        $this->assign('user_head', array('title'=>'密码找回 - STEP 1','backUrl'=>U('User/index'),'backText'=>'首页'));
        $this->display('resetStep1');
    }

    /**
     * 邮箱找回
     */
    public function reset_by_email(){
        $user_model = new Model\UsersModel();
        $step = I('get.step',1,'intval');
        if ($step == 2) {
            if (IS_POST) {
                $email = I('post.account','','trim');
                $verify = I('post.number');
                if (!$user_model->is_email($email)) {
                    $this->error('邮箱输入错误，请重新输入！');
                } else if (!$this->check_verify($verify)) {
                    $this->error('验证码输入错误，请重新输入！');
                } else {
                    $user_info = $user_model->get_userinfo('email="' . $email . '"');
                    if (!empty($user_info) and $user_info['user_id'] > 0) {
                        $code = $this->get_rand_str(8,1);
                        $verify_id = $user_model->set_verification($email,$code);
                        if ($res = $this->SendEmail($email,2)) {
                            $this->assign('user_head', array('title'=>'密码找回 - STEP 2','backUrl'=>U('User/index'),'backText'=>'首页'));
                            $this->assign('info',array('email'=>$email));
                            $this->assign('verify_id', $verify_id);
                            $this->display('resetByEmailStep2');
                        } else {
                            $this->error('邮件发送失败，请稍候再试！');
                        }
                    } else {
                        $this->error('该邮箱地址尚未在本站注册！');
                    }
                }

            } else {
                $this->redirect('User/reset_by_email');
            }
        }else if ($step == 3){
            $email          = I('post.email','','trim');
            $verify         = I('post.verify');
            $password       = I('post.password');
            $reppassword    = I('post.reppassword');
            $verify_id      = I('post.verify_id',0,'intval');
            if (!$user_model->is_email($email)) {
                $this->error('邮箱输入错误，请重新输入！');
            } else if (strlen($password) < 6 or strlen($password) > 18) {
                $this->error('密码必须为6－18位');
            } else if ($password != $reppassword) {
                $this->error('确认密码必须与密码保持一致');
            } else if (!$user_model->authcode_check($email,$verify)) {
                $this->error('邮箱验证码验证错误');
            } else {
                $user_info = $user_model->get_userinfo('email="' . $email . '"');
                if (!empty($user_info) and $user_info['user_id'] > 0) {
                    $user_model->where('user_id=%d',$user_info['user_id'])->save(array('password'=> md5($reppassword)));
                    $this->success('密码修改成功！',U('User/index'));
                } else {
                    $this->error('该邮箱地址尚未在本站注册！');
                }
            }
        } else {
             $this->assign('user_head', array('title'=>'密码找回 - STEP 1','backUrl'=>U('User/index'),'backText'=>'首页'));
            $this->display('resetByEmailStep1');
        }
    }

    /**
     * 手机找回
     */
    public function reset_by_phone(){
        $user_model = new Model\UsersModel();
        $step = I('get.step',1,'intval');
        switch ($step){
            case 1:
                $this->assign('user_head', array('title'=>'密码找回 - STEP 1','backUrl'=>U('User/index'),'backText'=>'首页'));
                $this->display('resetByPhoneStep1');
                break;
            case 2:
                if(IS_POST){
                    $verify_code = I('post.number');
                    $phone = I('post.account','','trim');
                    if(!$this->check_verify($verify_code))  $this->error("验证码输入不正确");
                    if(!$this->is_phone($phone))    $this->error("请输入正确的手机号码！");
                    $res = $this->SendSms($phone,433);
                    if ($res['error'] == 0) {
                        $this->assign('user_head', array('title'=>'密码找回 - STEP 2','backUrl'=>U('User/index'),'backText'=>'首页'));
                        $this->assign('info',array('phone'=>$phone));
                        $this->display('resetByPhoneStep2');
                    } else {
                        $this->error('短信发送失败，请与管理员联系！');
                    }
                }else{
                    $this->redirect('User/reset_by_phone');
                }
                break;
            case 3:
                $phone = I('post.phone','','trim');
                $verify         = I('post.verify');
                $password       = I('post.password');
                $reppassword    = I('post.reppassword');
                //$verify_id      = I('post.verify_id',0,'intval');
                if (!$this->is_phone($phone)) {
                    $this->error('手机输入错误，请重新输入！');
                } else if (strlen($password) < 6 or strlen($password) > 18) {
                    $this->error('密码必须为6－18位');
                } else if ($password != $reppassword) {
                    $this->error('确认密码必须与密码保持一致');
                } else if (!$user_model->authcode_check($phone,$verify)) {
                    $this->error('验证码输入错误或已经过期！');
                } else {
                    $user_info = $user_model->get_userinfo('mobile_phone="' .$phone . '"');
                    if (!empty($user_info) and $user_info['user_id'] > 0) {
                        $user_model->where('user_id=%d',$user_info['user_id'])->save(array('password'=> md5($reppassword)));
                        $this->success('密码修改成功！',U('User/index'));
                    } else {
                        $this->error('该手机尚未在本站注册！');
                    }
                }
                break;
            default:
                $this->assign('user_head', array('title'=>'密码找回 - STEP 1','backUrl'=>U('User/index'),'backText'=>'首页'));
                $this->display('resetByPhoneStep1');
                break;
        }
    }

    public function logout(){
        session('login_info',null);
        cookie('login_info',null);/*2016/9/14*/
        $this->redirect('Index/index');
    }

    public function register(){
        //$recom = get_encrypt_str('rec','get',1);
        $recom['uid'] = AesDeCrypt(I('get.rec','','trim'));;
        if ($recom['uid'] > 0) {
            $user_model = new Model\UsersModel();
            $recom_info = $user_model->get_userinfo('user_id=' . $recom['uid']);
            if ($recom_info) {
                if ($this->is_email($recom_info['user_name'])) {
                    $reco_name = $this->set_hidden_str($recom_info['user_name'],2);
                } elseif ($this->is_phone($recom_info['user_name'])) {
                    $reco_name = $this->set_hidden_str($recom_info['user_name'],4);
                } else {
                    $reco_name = $this->set_hidden_str($recom_info['user_name']);
                }
                $this->assign('rec_info', array('reco_id'=>AesEnCrypt($recom_info['user_id']),'reco_name'=>$reco_name));
            }
        }
        $this->assign('url',array('register'=>U('User/register_act')));
        $this->assign('back_act',AesEnCrypt($this->back_url));
        $this->assign('user_head', array('title'=>'注册会员','backUrl'=>U('Index/index'),'backText'=>'首页'));
        $this->assign('tkd',['title'=>$this->_CFG['shop_name'] . ' 会员注册','keywords'=>$this->_CFG['shop_name'],'discription'=>$this->_CFG['shop_name']]);
        $this->display('register1');
    }

    public function register_act(){
        $user_name = I('post.user_name','','stripslashes');
        $email          = I('post.email');
        $password       = I('post.password','','stripslashes');
        $reppassword    = I('post.reppassword','','stripslashes');
        $number         = I('post.number');
        $verify         = I('post.verify');
//        $rec            = get_encrypt_str('rec','post');//intval($this->DeCrypt1(I('post.rec')));            //推荐人
        $rec['uid']            = AesDeCrypt(I('post.rec','','trim'));//intval($this->DeCrypt1(I('post.rec')));            //推荐人
        $user_model = new Model\UsersModel();
        if (!$user_model->authcode_check($email,$number)) {
            $this->error('手机/邮箱验证码验证错误');
        }
        else if(!$user_model->is_username($user_name)){
            $this->error("请输入正确的用户名");
        }
        else if($user_model->is_exists($user_name,'user_name')){
            $this->error("用户名已存在，请更换用户名");
        }
        else if (!$user_model->is_email($email) and !$user_model->is_mobile_num($email)) {
            $this->error('请输入正确的邮箱或手机号码');
        }
        else if ($user_model->is_exists($email,'email') || $user_model->is_exists($email,'mobile_phone')){
            $this->error('该邮箱或手机号码已存在');
        }
        else if (strlen($password) < 6 or strlen($password) > 18) {
            $this->error('密码必须为6－18位');
        }
        else if ($password != $reppassword) {
            $this->error('确认密码必须与密码保持一致');
            /*} else if (!$this->check_verify($verify)) {
                $this->error('验证码输入错误，请重新输入！');*/
        }

        else {
            $uid = 0;
            if ($rec['uid'] > 0) {
                $reco_info = $user_model->get_userinfo('user_id=' . $rec['uid']);
                if ($reco_info) {$uid = $rec['uid'];}
            }
            $res = $user_model->check_user($email);
            $hkhp_user = $this->apireg(0,$email);       //查询汇客是否存在此用户
            if ($res['error'] <= 0 or $hkhp_user['data']['user_exists'] > 0) {
                if ($id = $user_model->add_user(array('user'=>$email, 'pass'=> $password,'user_name'=>$user_name),$uid)) {
                    $this->apireg(1,$email,$password, $id);  //汇客注册此用户
                    $user_model->regist_giving($id);//注册赠送
                    $this->success('注册成功！', U('login') . '?back_act=' . AesEnCrypt($this->back_url));
                } else {
                    $this->error('注册失败！');
                }
            } else {
                $this->error('该用户已经存在！');
            }
        }
    }

    private function apireg($type, $user, $pass = '', $author_id = 0){
        $data = [
            'type'      =>  $type,        //默认为0，查询   1 注册
            'user'      =>  stripslashes($user),
            'pass'      =>  stripslashes($pass),
            'ip'        =>  get_client_ip(),
            'author_id' =>  stripslashes($author_id),
        ];
        $this->user_id = 33;        //固定接口用户,修改将注册时出错
        return $this->hkhp_api('apireg.html',$data,1);
    }

    public function verify(){
        $Verify =     new \Think\Verify();
        $Verify->fontSize = 30;
        $Verify->length   = 4;
        $Verify->useNoise = false;
        $Verify->entry();
    }

    // 检测输入的验证码是否正确，$code为用户输入的验证码字符串
    protected function check_verify($code, $id = ''){
        $verify = new \Think\Verify();
        return $verify->check($code, $id);
    }

    /**
     * 发送验证码接口
     * 手机邮箱自动区分
     */
    public function sendmsg(){
        $user = I('post.user','','trim');
        $verify = I('post.verify','');
//        if (!$this->check_verify($verify)) {
//            $result = $this->result_ajax(1,'验证码输入不正确');
//        } else {
            if ($this->is_email($user)) {
                if ($res = $this->SendEmail($user,1)) {
                    $result = $this->result_ajax(0,'邮件发送成功，请查收！');
                } else {
                    $result = $this->result_ajax(5,'邮件发送失败，请与管理员联系！');
                }
            } elseif ($this->is_phone($user)) {
                $res = $this->SendSms($user,407);
                if ($res['error'] == 0) {
                    $result = $this->result_ajax(0,'短信发送成功，请查收！');
                } else {
                    $result = $this->result_ajax(3,'短信发送失败，请与管理员联系！',array('message'=>$res['msg']));
                }
            } else {
                $result = $this->result_ajax(2,'用户名必须为手机或邮箱！');
            }
//        }
        $this->ajaxReturn($result);
    }


    public function share(){
        $this->void_user();
        header('location:' . U('Index/share') . '?rec=' . AesEnCrypt($this->user_id));
        if ($this->user_info['user_rank'] <= -1) {
            $user_rank = M('UserRank')
                ->where('special_rank=0 and rank_id !=' . intval($this->user_info['user_rank']))
                ->order('sort')
                ->find();
            $this->assign('rank', $user_rank);
            //$this->assign('rank_text', '您必须成为v1用户才可以进行分享!');
            $this->display('User:share1');
        } else {
            $user_model = new Model\UsersModel();
            $qr = $user_model->user_qr_get($this->user_id);
            $time = time();//exit( $qr['add_time'] + $qr['expire_seconds'] . '=' . $time);

            if (empty($qr)) {
                if ($this->is_weixin()) {
                    $info = $this->get_share_qr($this->user_id);
                } else {
                    $info = '';
                }
            } else if (($time - $qr['add_time']) > $qr['expire_seconds'] ) {
                @unlink(__ROOT__ . $qr['img_address']);
                $info = $this->get_share_qr($this->user_id, $qr);
            } else {
                $info = array_merge($qr, $this->user_info);
            }
            $this->assign('user_head', array('title'=>'','backUrl'=>U('User/index')));
            $this->assign('info', $info);
            $this->display('User:share');
        }

    }

    protected function get_share_qr($user_id, $QR_info = array()){
        $time = time();
        $user_model = new Model\UsersModel();
        if ($QR_info && $QR_info['id'] > 0) {
            //$user_model->user_qr_save($QR_info['id'], $QR_info['img_address']);
            $qr_md5 = $QR_info['md5'];
            $file = $QR_info['img_address'];
            $qr_id = $QR_info['id'];
        } else {
            $qr_md5 = md5($this->get_rand_str(22,2) . $time);
            $file = $this->get_save_path(2) . $time . '_' . $qr_md5 . '.png';//生成文件名
            $qr_id = $user_model->user_qr_add($user_id, $qr_md5, $file);//保存到数据库
        }
        $info = $this->get_wxuser_qr($qr_id);//读取QR信息
        if ($info) {
            $this->save_net_file($file,'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $info['ticket']);
            $user_model->user_qr_save($qr_id, $info);
        } else {
            exit('没有');
        }
        $info['md5'] = $qr_md5;
        $info['user_id'] = $this->user_id;
        $info['id'] = $qr_id;
        $info['img_address'] = $file;
        $info['add_time'] = $time;
        $info = array_merge($info, $this->user_info);
        return $info;
    }

    protected  function share1(){
        $this->void_user();
        $user_model = new Model\UsersModel();
        $qr = $user_model->user_qr_get($this->user_id);
        $info = null;
        if (empty($qr)) {
            $time = time();
            $qr_md5 = md5($this->get_rand_str(22,2) . $time);
            $file = $this->get_save_path(2) . $time . '_' . $qr_md5 . '.png';
            $this->create_qr($this->cur_domain . '/q/' . $qr_md5, $file);
            $qr_id = $user_model->user_qr_add($this->user_id, $qr_md5, $file);
            if ($this->is_weixin()) {
                $info = $this->get_wxuser_qr($qr_id);
                $user_model->user_qr_save($qr_id, $info);
                $info['md5'] = $qr_md5;
                $info['user_id'] = $this->user_id;
                $info['id'] = $qr_id;
                $info['img_address'] = $file;
                $info['add_time'] = $time;
                $info = array_merge($info, $this->user_info);
            }
        } else if ($qr['url'] == '') {
            if ($this->is_weixin()) {
                $info = $this->get_wxuser_qr($qr['id']);
                $user_model->user_qr_save($qr['id'], $info);
                $info = array_merge($qr,$info);
            }
        } else {
            $info = $qr;
        }
        //$this->debug($info,1);
        $this->assign('info', $info);
        $this->display('User:share');

    }


    public function get_agent(){
        //print_r($_SERVER['HTTP_USER_AGENT']);
    }

    public function yongjin(){
        $this->void_user();
        $this->display();
    }

    public function voucher(){       #我的代金券
        $this->void_user();
        $this->show('完善中...');
    }

    public function Kpchit(){       #我的代金券
        $this->void_user();
        $this->show('完善中...');
    }

    public function address(){      #地址管理
        $this->void_user();
        $this->show('完善中...');
    }

    public function setlogin(){      #登录设置
        $this->void_user();
        $this->show('完善中...');
    }

    public function record_wxpay(){
        $data = I('data');
        $this->logger($data);
    }

    /**
     * @param int $id 公告id
     * @param int $get_content 是否获取公告内容
     * @return mixed
     */
    public function get_notice($id=0,$get_content=0){
        //字段
        $field = array('id','endtime','starttime','title');
        if($get_content) $field[] = 'content';

        //条件
        $map = array();
        $map['is_show'] = array('eq',1);
        $ctime = time();
        $map['starttime'] = array('elt',$ctime);
        $map['endtime'] = array('egt',$ctime);

        if($id){
            $map['id'] = array('eq',$id);
            $notice = M('notice')->where($map)->find();
        }else{
            $notice = M('notice')->where($map)->order('is_top desc,addtime desc')->select();
        }

        return $notice;
    }

    public function notice(){
        $id = I('get.id',0,'intval');
        if($id<=0) $this->redirect('index');
        $info =  $this->get_notice($id,1);
        if(!$info)  $this->redirect('index');
        $this->assign('info',$info);
        $this->assign('user_head', array('title'=>'公告','backUrl'=>U('index'),'backText'=>'个人中心'));
        $this->display();

    }


}


 
