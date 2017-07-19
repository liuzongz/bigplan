<?php
namespace Passport\Controller;

use Passport\Model\UsersModel;
use Passport\Model\StockModel;


class UserController extends WechatController {

    private  $organization = array('教育培训','餐饮美食','消费购物','生活服务','休闲娱乐','美容美发','婚前影楼',
            '运动健身','汽车美容','家装设计','旅游景区','其他');


    protected function _initialize(){
        parent::_initialize();
    }
    /**
     * 汇客惠品的用户中心样式
     */
    public function index(){
        $store_id = $this->store_id;
        $this->void_user($store_id);
        $store_info = M('user_relevance')->where(array('user_id'=>$this->user_info['user_id']))->find();
        //get_server('WAP_SERVER', '/Profile/index', ['store_id'=>$store_id], 1);
        $user_model = new UsersModel();
        $stock_model = new StockModel();
        /*if(!$this->user_info['nickname']){
            $this->error("请先完善个人资料", get_server('WAP_SERVER', '/Profile/index', ['store_token'=>$store_id], 1));
        }*/
        $order_handle = array(
            array('text'=>'待付款',
                'url'=>get_server('WAP_SERVER', '/Order/index', ['store_token'=>$store_id, 't'=>'pay'], 1), 'icon'=>'1'),
            array('text'=>'待收货',
                'url'=>get_server('WAP_SERVER', '/Order/index', ['store_token'=>$store_id, 't'=>'confirm'], 1), 'icon'=>'2'),
            array('text'=>'待评价',
                'url'=>get_server('WAP_SERVER', '/Order/index', ['store_token'=>$store_id, 't'=>'comment'], 1), 'icon'=>'3'),
            array('text'=>'已退货',
                'url'=>get_server('WAP_SERVER', '/Order/index', ['store_token'=>$store_id, 't'=>'refund'], 1), 'icon'=>'4'),
        );
        $menus[] = array(
            array('name'=>'我的订单',
                'url' => get_server('WAP_SERVER', '/Order/index', ['store_token'=>$store_id], 1), 'icon'=>'wodedingdan','color'=>'#CF3A3C'),
            array('name'=>'我的账户',
                'url' => get_server('WAP_SERVER', '/Wallet/dynamic', ['store_token'=>$store_id], 1), 'icon'=>'qianbao','color'=>'#5CA2E7'),
        );
        $this->assign('order_handle', $order_handle);

        if (!$this->is_weixin) {
            $data[] = array('name'=>'今日分享',
                'url'=>get_server('WAP_SERVER', '/Article/slist', ['store_token'=>$store_id, 'id'=>13], 1),'icon'=>'fenxiang', 'color'=>'#FFA401', 'handle'=>array(array('name'=>'分享创富', 'url'=>'','color'=>'red')));
            /* $data[] = array('name'=>'我的会员',
                     'url'=>get_server('WAP_SERVER', '/Team/index', '', 1),'icon'=>'huiyuan','color'=>'#F4A83A');*/
        }
        if(!$this->is_weixin){
            $menus[] = array(
                array('name'=>'修改密码',
                    'url'=>get_server('PASSPORT_SERVER', '/user/modify', ['store_token'=>$store_id], 1),'icon'=>'mima01','color'=>'#B1CB2A'),
            );
        }
        $menus[] = array(
            array('name'=>'修改资料',
                'url'=>get_server('WAP_SERVER', '/Profile/index', ['store_token'=>$store_id], 1),'icon'=>'bianji','color'=>'#B1CB2A'),
        );
        if($store_id != C('jzc_store')){                          //商家个人中心
            if($store_info['rank_id'] > 1){
                $data[] = array('name'=>'我的会员',
                    'url'=>get_server('WAP_SERVER', '/Team/index', ['store_token'=>$store_id], 1),'icon'=>'huiyuan','color'=>'#F4A83A');
            }
            $data[] =  array('name'=>'专属名片',
                'url' => get_server('PASSPORT_SERVER', '/Index/share', ['store_token'=>$store_id, 'rec'=>AesEnCrypt($this->user_id)], 1), 'icon'=>'mingpian', 'color'=>'#E54949');
            if($stock_model->is_store($this->user_id)){         //已经入住
                $data[] = array(
                    array('name'=>'入驻信息',
                        'url'=>get_server('WAP_SERVER', '/apply/index', ['store_token'=>$store_id], 1),'icon'=>'ruzhuhetong','color'=>'#B1CB2A'),
                    array('name'=>'我要进货',
                        'url'=>get_server('WAP_SERVER', '/Stock/index', ['store_token'=>$store_id], 1),'icon'=>'jinhuozr','color'=>'#B1CB2A'),
                );
                $this->user_info['vip_name'] = '企业主';
            }else{
                if ($this->user_info['is_vip'] > 0) {
                    $this->user_info['vip_name'] = '在职员工';
                } else if ($this->user_info['is_vip'] < 0) {
                    $this->user_info['vip_name'] = '离职员工';
                } else {
                    $this->user_info['vip_name'] = '会员';
                }
                /*$data[] = array('name'=>'我要入驻',
                    'url'=>get_server('WAP_SERVER', '/apply/index', ['store_token'=>$store_id], 1),'icon'=>'iconfontruzhu','color'=>'#B1CB2A');*/
            }
        }else{                                  //即牛个人中心
            $menus[] = array(
                array('name'=>'邀请有礼',
                    'url'=>get_server('WAP_SERVER', '/Index/invite', ['store_token'=>$store_id, 'user_id'=>AesEnCrypt($this->login_info['user_id'])]),'icon'=>'libao01','color'=>'#B1CB2A'),
            );
            $menus[] = array(
                array('name'=>'我的活动',
                    'url'=>get_server('SALES_SERVER', '/User/active', ['store_token'=>$store_id], 1),'icon'=>'huodong01','color'=>'#B1CB2A'),
            );
            $menus[] = array(
                array('name'=>'我的传单','url'=>'','icon'=>'chuandan01','color'=>'#B1CB2A'),
            );
            $menus[] = array(
                array('name'=>'常见问题',
                    'url'=>'','icon'=>'wenti01','color'=>'#B1CB2A'),
            );
        }
        $menus[] = $data;

        if (isset($_GET['debug']) and $_GET['debug'] == 'netbum') {
            $this->login_info['user_name'] =  $this->login_info['user_id'];
            // print_R($this->wxuser);
            print_R($this->login_info);
        }
        //获取公告信息
        $this->assign('notice',$this->get_notice());
        $this->user_info['agency'] = $user_model->get_user_agency($this->user_id);
        $this->user_info['user_avatar'] = $user_model->img_url($this->user_info['user_avatar']);
        $this->assign('menus', $menus);
        //get_server('PASSPORT_SERVER', 'user/index', ['store_id'=>$store_id], 1)
        $this->assign('user_head', array('backUrl'=>get_server('WAP_SERVER', '/Store/init', ['store_token'=>$store_id,'id'=>$store_id], 1),'title'=>'会员中心','backText'=>'首页'));
        $this->assign('tkd', ['title'=>'会员中心']);
        $this->assign('url', array('logout'=>get_server('PASSPORT_SERVER', 'User/logout', ['store_token'=>$store_id], 1)));
        $this->assign('memberInfo', $this->user_info);//$this->debug($this->user_info,1);
        $this->assign('cur_page','user');
        $this->assign('store_id',$store_id);
        $this->assign('jzc_store',C('jzc_store'));
        $this->assign('is_weixin', $this->is_weixin);
        $this->display();
    }

    /**
     * 用户注册(短信平台问题 暂时弃用)
     */
    public function register(){
        C('TOKEN_ON', true);
        if(IS_POST)
        {
            $user_model = new UsersModel();
            $nickname       = urldecode(I('post.nickname','','stripslashes'));//机构名称
            $user_areainfo  = urldecode(I('post.user_areainfo'));//行业
            $phone          = I('post.phone');   //手机号
            $number         = I('post.number');  //验证码
            $province       = intval(I('post.province'));//省份
            $city           = intval(I('post.city'));//城市
            $district       = intval(I('post.district'));//区
            $password       = I('post.password','','stripslashes');//密码
            $sms_token         = I('post.sms_token');  //验证码
            /*if (!$this->check_authcode($phone,$number,$sms_token))
            {
                $result = $this->result_ajax(1,'手机/邮箱验证码验证错误！');
            }
            else */if ($user_model->is_exists($phone,'user_name'))
            {
                $result = $this->result_ajax(2,'用户名已存在，请更换用户名');
            }
            else if (strlen($password) < 6 or strlen($password) > 18)
            {
                $result = $this->result_ajax(3,'密码必须为6－18位');
            } else
            {
                $uid = 0;
                if ($id = $user_model->add_user(array('user'=>$phone,'nickname'=>$nickname,'user_areainfo'=>$user_areainfo,'pass'=> $password,
                    'user_name'=>$phone,'province'=>$province,'city'=>$city,'district'=>$district),$uid)) {
                    $result = $this->result_ajax(200,'注册成功！',['url'=>U('User/login') . '?back_act=' . AesEnCrypt($this->back_url)]);
                } else
                {
                    $result = $this->result_ajax(4,'注册失败');
                }
            }
            $this->ajaxReturn($result);
        } else
        {
            $this->assign('back_act', AesEnCrypt($this->back_url));
            $this->assign('organization',$this->organization);
            $this->display();
        }
    }

    /**
     * 发送验证码
     */
    public function sendmsg(){
        C('TOKEN_ON', true);
        $user = I('post.user','','trim');
        if($this->checkhash($_POST))
        {        //令牌验证
            if ($this->is_phone($user))
            {
                $res = $this->send_authcode($user);
                if ($res['error'] == 200)
                {
                    $result = $this->result_ajax(0,'短信发送成功，请查收！', $res['data']);
                } else
                {
                    $result = $this->result_ajax(3,'短信发送失败，请与管理员联系！',array('message'=>$res['msg']));
                }
            } else
            {
                $result = $this->result_ajax(2,'请输入正确的手机号');
            }
        } else
        {
                $result = $result = $this->result_ajax(1,'请不要重复提交,或刷新后再试！');
        }
        $this->ajaxReturn($result);
    }

    /**
     * 登录
     */
    public function login(){
        C('TOKEN_ON', true);
        if (IS_POST)
        {
            $user = I('post.phone','','trim');
            $pass = I('post.password','','trim');
            if($this->checkhash($_POST))
            {           //令牌验证
                if(strlen($user) < 6)
                {
                     $result = $this->result_ajax(1,'请输入正确的用户名！');
                } else
                {
                    $user_model = new UsersModel();
                    $res = $user_model->get_userinfo('user_name="' . $user . '"','user_id,user_name,nickname,password,last_login,last_time,last_ip,visit_count,user_money');
                    if ($res)
                    {
                        if($res['password'] != md5($pass))
                        {
                            $result = $this->result_ajax(3,'用户或密码输入错误，请重新输入！NO.1');
                        } else
                        {
                            $time = time();
                            $data = array(
                                'visit_count'       => $res['visit_count'] + 1,
                                'last_login'        => $time,
                                'last_ip'           => get_client_ip(),
                            );
                            M('users')->where(array('user_id'=>$res['user_id']))->save($data);
                            $res['add_time'] = $time;
                            $res['login_time'] = $time;
                            session('login_info_' . $this->store_id, $res);
                            $data = [
                                'user_id'       =>  $res['user_id'],
                                'user_name'     =>  $res['user_name'],
                                'login_time'    =>  $res['last_login'],
                                'login_module'  =>  'sales',
                                'add_time'      =>  $time
                            ];
                            cookie($this->back_module, RsaEnCrypt(json_encode($data)));
                            //$this->set_login_cookie($user_info);/*2016/9/14*/
                            //设置用户微信信息
                            $this->set_wxInfo($res['user_id']);
                            $back_url = $this->login_url_back();
                            $result = $this->result_ajax(200,'登录成功！',['url'=>$back_url]);
                         }
                    } else
                    {
                        $result = $this->result_ajax(0,'用户或密码输入错误，请重新输入！NO.1');
                    }
                }
            } else
            {
                $result = $this->result_ajax(1,'请不要重复提交！');
            }
            $this->ajaxReturn($result);
        } else
        {
            if(session('login_info_' . $this->store_id))
            {
                $back_url = $this->login_url_back();
                header("Location:$back_url");
            } else
            {
                $back_url = stripos($this->back_url, 'modify') || stripos($this->back_url, 'register') ? $this->cur_domain.U('User/index') : $this->back_url;
                $back_module = $this->back_module ? $this->back_module : MODULE_NAME;
                $this->assign('back_act', AesEnCrypt($back_url));
                $this->assign('back_module', AesEnCrypt($back_module));
                $this->display();
            }
        }
    }

    /**
     * 登出
     * @param string $ref_url
     */
    public function logout($ref_url=''){
        session('login_info', null);//当前服务器登出
        //session('wx_token', null);//当前服务器登出
        cookie('login_info', null);//图片服务器登出
        session('wx_token', null); //token
        cookie(strtoupper(MODULE_NAME), null);//图片服务器登出
        $this->redirect('user/login');
    }

    /**
     * 修改密码
     */
    public function modify(){
        C('TOKEN_ON', true);

        $this->void_user(session('store_id'));
        if(IS_POST)
        {
            $user = I('post.phone','','trim');
            $oldpass = I('post.oldpass', '', 'trim');
            $password1 = I('post.password','','trim');
            $password2 = I('post.password2','','trim');
            $verify = I('post.verify','','trim');
            $sms_token         = I('post.sms_token', '', 'trim');  //验证码
            $user_model = new UsersModel();
            $user_info = $user_model->get_userinfo('user_id="' .$this->login_info['user_id'] . '"');
            /*if (!$this->check_authcode($user,$verify,$sms_token))
            {
                $result = $this->result_ajax(1,'手机验证码验证错误！');
            } else */if(!$user_model->is_exists($user,'user_name'))
            {
                $result = $this->result_ajax(2,'该用户不存在！');
            } else if(  md5($oldpass) != $user_info['password'])
            {
                $result = $this->result_ajax(3, '原密码错误');
            }else if(  $password1 != $password2)
            {
                $result = $this->result_ajax(3, '两次密码不一致！');
            }else if(  $oldpass == $password2)
            {
                 $result = $this->result_ajax(3, '新密码与旧密码不能一致！');
            }else if(strlen($password1) < 6 or strlen($password1) > 18)
            {
                $result = $this->result_ajax(3,'密码必须为6－18位');
            } else {
                if (!empty($user_info) and $user_info['user_id'] > 0) {
                    $user_model->where('user_id=%d',$user_info['user_id'])->save(array('password'=> md5($password1)));
                    $result = $this->result_ajax(0,get_server('SALES_SERVER', '/user/index', '', 1));
                }
            }
            $this->ajaxReturn($result);
        }else
        {
            $this->display();
        }
    }

    /**
     * 获取地区联动信息(省市区)
     */
    public function region(){
        $id = I('get.id',1,'intval');
        $id = $id < 1 ? 1 : $id;
        $res = M('Region')->where('parent_id=' . $id)->cache('region_' . $id)->select();
        $result['error'] = 0;
        $result['msg'] = '获取成功！';
        $result['data'] = $res;
        $this->ajaxReturn($result);
    }

    /**
     * 表单令牌验证
     * @param $post
     * @return bool
     */
    private function checkhash($post){
         $model = new UsersModel();
         return $model->autoCheckToken($post);
    }

    /**
     * 商家岛的个人中心样式
     */
    public function index_sjd(){
        $this->void_user(session('store_id'));
        $this->assign('myActive', get_server('SALES_SERVER', '/User/active', '', 1));
        $this->assign('user_info', $this->login_info);
        $this->display();
    }


    /**
     * 位置微信用户信息
     * @param $user_id
     * @return bool|mixed
     */
    private function set_wxInfo($user_id){
        $where = array(
            'a.user_id' =>  $user_id,
            'b.openid'  =>  $this->wx_token['openid'],
            'b.gd_id'   =>  $this->wxuser['appcode'],
        );
        $user_info = M('Users')->alias('a')
                    ->join(' JOIN __USER_WX__ b ON a.user_id=b.user_id')
                    ->where($where)
                    ->find();
        if(!$user_info && isset($this->wx_token['openid']) && isset($this->wxuser['appcode'])){
            $wx_data = array(
                'user_id'   => $user_id,
                'openid'    => $this->wx_token['openid'],
                'gd_id'     => $this->wxuser['appcode'],
                'add_time'  => time(),
                'add_ip'    => get_client_ip()
            );
           return M('user_wx')->add($wx_data);
        }
        return true;
    }

    /**
     * 测试微信登录 测试方法
     */
    public function wx_login(){
        $appid = $this->wxuser['appid'];
        $url1 = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=http%3A%2F%2Fnba.bluewebgame.com%2Foauth_response.php&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect";
        /*$wx_info = $this->wx_token;
        $info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$wx_info['access_token'].'&openid='.$wx_info['openid'].'⟨=zh_CN';
        $info = json_decode(file_get_contents($info_url));*/
        echo 456;exit;
    }


    /**
     * 测试微信注册 测试方法
     * @param $user_id
     * @return bool|mixed
     */
    private function wx_reg($user_id){
        $where = array(
            'a.user_id' =>  $user_id,
            'b.openid'  =>  $this->wx_token['openid'],
            'b.gd_id'   =>  $this->wxuser['appcode'],
        );
        $user_info = M('Users')->alias('a')
            ->join(' JOIN __USER_WX__ b ON a.user_id=b.user_id')
            ->where($where)
            ->find();
        if(!$user_info && isset($this->wx_token['openid']) && isset($this->wxuser['appcode'])){
            $wx_data = array(
                'user_id'   => $user_id,
                'openid'    => $this->wx_token['openid'],
                'gd_id'     => $this->wxuser['appcode'],
                'add_time'  => time(),
                'add_ip'    => get_client_ip()
            );
            return M('user_wx')->add($wx_data);
        }
        return true;
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

    /**微信验证token
     * @return bool
     */
    public function checkSignature() {
        //define("TOKEN", "100111");
        $signature = I("signature");
        $timestamp = I("timestamp");
        $token = I("token");
        $nonce = I("nonce");
        //$token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 测试数据专用
     */
    public function test(){
        $wx_info = session('wx_token');
        $wx_info =  session('wx_token');
        $access_token2 = session('access_token');
        $url = sprintf($this->get_url(5,0), $access_token2, $this->wxuser['appid']);
        $rt = $this->curlGet($url);
        print_R($rt);exit;
    }

}
