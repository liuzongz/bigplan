<?php
namespace Passport\Controller;
use Passport\Model;
class WechatController extends BaseController {

    protected $token_info = array();
    protected $wx_token = array();
    protected $access_token = '';
    protected $wx_ticket = '';
    protected $parent_id = '';
    protected $store_url = '';

    protected $wxuser = "";

    private $wx_doamin = array(
        0=>'https://api.weixin.qq.com/',
        1=>'https://open.weixin.qq.com/',
        2=>'https://mp.weixin.qq.com/',
        3=>'https://api.mch.weixin.qq.com/'
    );

    private $wx_api_url = array(
        0=>'connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s#wechat_redirect',
        1=>'sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code',
        2=>'sns/userinfo?access_token=%s&openid=%s&lang=zh_CN',
        3=>'sns/oauth2/refresh_token?appid=%s&grant_type=refresh_token&refresh_token=%s',
        4=>'cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s',
        5=>'cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN',
        6=>'pay/unifiedorder',           //统一下单（支付）
        7=>'pay/orderquery',             //查询订单
        8=>'pay/closeorder',             //关闭订单
        9=>'secapi/pay/refund',             //申请退款
        10=>'pay/refundquery',             //查询退款
        11=>'pay/downloadbill',             //下载对账单
        12=>'cgi-bin/message/custom/send?access_token=%s',   //向用户发送消息
        13=>'cgi-bin/qrcode/create?access_token=%s',         //获取二维码
        14=>'cgi-bin/ticket/getticket?type=jsapi&access_token=%s', //获取微信js接口ticket
        15=>'cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s' //获取微信js接口token
    );
    protected function _initialize(){
        parent::_initialize();
        $this->parent_id = I('request.rec',0,'AesDeCrypt')?I('request.rec',0,'AesDeCrypt'):session('parent_id');
        $store_id = I('request.store_token',0, 'intval');
        $this->wxuser = $this->get_config($store_id);
        debug(print_r($this->wxuser,1));
        session('store_id', $this->store_id = $this->wxuser['store_id']);
        /*if(intval($this->wxuser['store_id']) <= 0){
            echo "<script>alert('店铺token错误！！！')</script>";
            exit;
        }*/
        $this->assign('is_weixin', $this->is_weixin = $this->is_weixin());  //设置微信
        $this->wx_token = session('wx_token' . $this->store_id);
        $this->access_token = session('access_token' . $this->store_id);
        //$this->wx_ticket = session('wx_ticket' . $this->store_id);
        /*unset($_SESSION['wp_']);exit;*/
        if($this->is_weixin && !$this->is_login($this->store_id)){
            $this->wxlogin_log();
            //$this->wxlogin();
            //$this->setTicket();
        }
        $this->setTicket();
    }

    /**
     * 获取access_token
     * 'sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code'
     * @param $code
     * @return mixed
     */
    private function access_token($code){
        $url = sprintf($this->get_url(1,0), $this->wxuser['appid'],$this->wxuser['appsecret'], $code);
        $rt = $this->curlGet($url);
        return json_decode($rt, 1);
    }

    /**
     * 获取access_token
     * 'sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code'
     * @param $code
     * @return mixed
     */
    private function wx_userinfo($access_token){
        $url = sprintf($this->get_url(2,0), $access_token, $this->wxuser['appid']);
        $rt = $this->curlGet($url);
        return json_decode($rt, 1);
    }

    /**
     * 微信注册 并登陆
     */
    public function wxlogin_log(){
        //在微信里面且没有token
        //if ($this->is_weixin and !$this->is_login($this->store_id)) {
        if (1) {
            debug('wxlogin_log:' . __LINE__);
            debug($this->wxuser);
            if ($result = $this->wxlogin_reg($_GET['code'],$_GET['state'])) {  // 通过返回的code和state交互微信 获取openID  access_token
                $back_url = $this->login_url_back();
                header("Location:$back_url");
            }else{
                $url = $this->cur_url;  // 第一次交互微信  返回带有code和state
                session('parent_id', $this->parent_id);
                //session('store_id', $this->wxuser['store_id']);
                $this->Authorize($url, 0);
            }
        }else{
            if(isset($this->wx_token['openid'])){
                session('wx_token' . $this->store_id,$this->wx_token);
                $where['a.openid'] = $this->wx_token['openid'];
                $where['a.gd_id'] = $this->wxuser['appcode'];
                $user_wx = M('user_wx')->alias('a')->join(' JOIN __USERS__ b ON a.user_id=b.user_id')->where($where)->find();
                if($user_wx){
                    $store_info = M('user_relevance')->where(array('user_id'=>$user_wx['user_id']))->find();
                    $user_wx['store_token'] = $store_info['store_id'];
                    $user_wx['add_time'] = $this->time;
                    $user_wx['user_rele'] = $store_info;
                    session('login_info_' . $store_info['store_id'], $user_wx);
                    //session('login_info', $user_wx);
                    //session('store_id', $store_info['store_id']);
                    $this->set_login_cookie($user_wx);
                    //$img_url = get_server('IMG_SERVER', '/uploader/login', '', 1);
                }else{

                }
                $back_url = $this->login_url_back();
                header("Location:$back_url");
            }
        }
    }

    /**
     * 微信注册 登录
     * @param $code
     * @param $state
     * @return bool
     */
    public function  wxlogin_reg($code, $state){
        if (!$code or !$state) return false;
        $time = time();
        $this->wx_token = $this->access_token($code);  //获取token
        if (!empty($this->wx_token['openid'])) {  // 根据openid查询用户是否存在
            $this->wx_token['add_time'] = $time;
            session('wx_token' . $this->store_id, $this->wx_token);
            $where['a.openid'] = $this->wx_token['openid'];
            $where['a.gd_id'] = $this->wxuser['appcode'];
            $user_wx = M('user_wx')->alias('a')->join(' JOIN __USERS__ b ON a.user_id=b.user_id')->where($where)->find();
            if($user_wx){    //存在:登录
                $this->set_login_info($this->store_id, $user_wx['user_id'], $user_wx['openid']);
            }else{          // 不存在:重新交互微信 获取授权
                $result = $this->wx_addusers($this->wx_token['access_token']);
                if ($result['error'] == 200) {
                    $this->set_login_info($this->store_id,$result['data']['user_id'],$result['data']['openid']);
                } else {
                    echo '用户注册失败！';
                }
            }
        } else {
            echo '未获取登录信息' . print_r($this->wx_token,1);
        }
        return true;
    }

    /**
     * 设置登录信息
     * @param $store_id
     * @param $user_id
     * @param $openid
     */
    private function set_login_info($store_id, $user_id, $openid){
        $data = [
            'user_id'       =>  $user_id,
            'openid'        =>  $openid,
            'login_time'    =>  $this->time,         //登录时间
            'add_time'      =>  $this->time          //最后更新时间
        ];
        session('login_info_' . $store_id, $data);
    }

    /**微信获取用户信息注册
     * @param $access_token
     */
    public function wx_addusers($access_token){
        $result = $this->wx_userinfo($access_token);
        $add_user = array(
            'openid'        => $result['openid'],
            'nickname'      => $result['nickname'],
            'sex'           => $result['sex'],
            'city'          => $result['city'],
            'province'      => $result['province'],
            'headimgurl'    => $result['headimgurl'],
            'user_name'     => C('wx_prefix') . get_rand_str(8, 2),
        );
        $uid = 0;
        $user_model = new Model\UsersModel();
        $insertId = $user_model->add_user($add_user, $uid);
        //TODO:未判断用户名唯一  后期解决
        if($insertId){
            //微信绑定关系
            $wx_info = array(
                'user_id'  =>  $insertId,
                'openid'   =>  $this->wx_token['openid'],
                'gd_id'    =>  $this->wxuser['appcode'],
                'add_time' =>  $this->time,
                'add_ip'   =>  get_client_ip(),
            );
            M('user_wx')->add($wx_info);
            $parent_id = intval(session('parent_id'));
            if($parent_id > 0 ){
                $parent_info = M('user_relevance')->where(array('user_id'=>$parent_id))->find();
                $store_id = $parent_info['store_id'];
            }else{
                $store_id = intval(session('store_id'));
            }

            //上级绑定关系
            $rele_info = array(
                'parent_id'    =>  $parent_id,
                'user_id'      =>  $insertId,
                'store_id'     =>  $store_id,

            );
            M('user_relevance')->add($rele_info);
            //TODO:写入日志
            //$user_info = $user_model->where(array('user_id'=>$insertId))->find();
            //$this->wxlogin_log();
            return result_ajax(200,'',array_merge($result,['user_id'=>$insertId]));
        } else {
            return result_ajax(400,'');
        }
    }


    /**
     * 微信自动授权
     */
    /*public function wxlogin() {
        if ($this->is_weixin and !$this->wx_token) {
            if ($result = $this->wx_login2($_GET['code'],$_GET['state'])) {
                $back_url = $this->login_url_back();
                header("Location:$back_url");
            } else {
                $url = $this->cur_url;
                $this->Authorize($url, 1);
            }
        }else{
            //设置微信自动登录
            if(isset($this->wx_token['openid'])){
                session('wx_token',$this->wx_token);
                $where['a.openid'] = $this->wx_token['openid'];
                $where['a.gd_id'] = $this->wxuser['appcode'];
                $user_wx = M('user_wx')->alias('a')->join(' JOIN __USERS__ b ON a.user_id=b.user_id')->where($where)->find();
                if($user_wx){
                    $user_wx['add_time'] = $this->time;
                    session('login_info', $user_wx);
                    $this->set_login_cookie($user_wx);
                }else{
                    $this->wx_addusers($this->wx_token['access_token']);
                }
                $back_url = $this->login_url_back();
                header("Location:$back_url");
            }
        }
    }*/
    /*protected function wx_login2($code, $state) {
        if (!$code or !$state) return false;
        $time = time();
        if (!$this->wx_token) {
            $this->wx_token = $this->access_token($code);
            if (!empty($this->wx_token['openid'])) {
                $this->wx_token['add_time'] = $time;
                session('wx_token', $this->wx_token);
                $where['a.openid'] = $this->wx_token['openid'];
                $where['a.gd_id'] = $this->wxuser['appcode'];
                $user_wx = M('user_wx')->alias('a')->join(' JOIN __USERS__ b ON a.user_id=b.user_id')->where($where)->find();
                if($user_wx){
                    $user_wx['add_time'] = $this->time;
                    session('login_info', $user_wx);
                }else{

                }
            } else {

            }
        }
        return true;
    }*/

    /**
     * 跳转认证页面
     * 'connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code
     * &scope=snsapi_userinfo&state=123#wechat_redirect'
     * @param string $Response_url
     * @param int $scope
     * @return mixed
     */
    protected function Authorize($Response_url = '', $scope = 0){
        debug('Authorize:' . __LINE__);
        debug($this->wxuser);
        $str = $scope == 0 ? 'snsapi_userinfo' : 'snsapi_base';
        $url = $Response_url == '' ? $this->cur_url : $Response_url;
        $url = sprintf($this->get_url(0,1), $this->wxuser['appid'] ,urlencode($url), $str,$this->get_rand_str(8,1));
        debug('Authorize:' . __LINE__);
        debug($url);
        header('Location:' . $url);
        return;
    }


    /**
     * 获取access_token
     * 'cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s'
     * @return mixed
     */
    private function getAccessToken(){
        $url = sprintf($this->get_url(15,0), $this->wxuser['appid'],$this->wxuser['appsecret']);
        $rt = $this->curlGet($url);
        $res = json_decode($rt, 1);
        return $res['access_token'];
    }

    /**
     * 获取ticket
     * 'sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code'
     * @param $access_token
     * @return mixed
     */
    private function getJsapiTicket($access_token){
        $url = sprintf($this->get_url(14,0), $access_token);
        $result = json_decode(curlGet($url), 1);

        return $result['ticket'];
    }

    /**
     * 组合成URL
     * @param $file_id
     * @param int $domain   0：api   1:open   2:mp   3:pay
     * @return string
     */
    protected function get_url($file_id, $domain = 0){
        if ($domain > count($this->wx_doamin) - 1 or $domain < 0) $domain = 0;
        return $this->wx_doamin[$domain] . $this->wx_api_url[$file_id];
    }


    //设置微信js接口所需的Ticket
    public function setTicket(){
        if(!$this->wx_ticket) {
            $access_token = !$this->access_token ? $this->getAccessToken() : $this->access_token;
            $result = $this->getJsapiTicket($access_token);
            $this->wx_ticket = $result;
            if ($access_token) session('access_token' . $this->store_id, $access_token);
            if ($result) session('wx_ticket' . $this->store_id, $result);
        }
        //设置签名参数
        $noncestr = md5($this->get_rand_str(8));
        $string = "jsapi_ticket={$this->wx_ticket}&noncestr={$noncestr}&timestamp={$this->time}&url={$this->cur_url}";
        $signature = sha1($string);

        //设置前台所需的数据
        $signPackage = array(
            "appId"     => $this->wxuser['appid'],
            "nonceStr"  => $noncestr,
            "timestamp" => $this->time,
            "url"       => $this->cur_url,
            "signature" => $signature,
            "rawString" => $string
        );

        $this->assign('signPackage', $signPackage);
    }

    /**
     * 格式化日期
     * @param $month
     * @return mixed
     */
    protected function format_month($month){
        if (strlen($month) == 1) {
            $month = '0' . $month;
        }
        return str_replace(
            array('01','02','03','04','05','06','07','08','09','10','11','12'),
            array('一月','二月','三月','四月','五月','六月','七月','八月','九月','十月','十一','十二'),
            $month
        );
    }


    /**
     * 登陆返回原地址
     * @return string
     */
    public function login_url_back(){
        $query = [
            C("VAR_SESSION_ID") =>  AesEnCrypt(session_id()),
        ];
        $url_back = parse_url($this->back_url);
        $url_cur = parse_url($this->cur_domain);

        if($url_back['host'] != $url_cur['host']){
            $back_url = get_query($this->back_url) . http_build_query($query);
        }else{
            $back_url = $this->back_url;
        }
        $back_url = check_storeid($back_url, session('store_id'));
        return $back_url;
    }

    /**
     * 获取微信配置参数
     */
    public function get_config($store_id){
        if($store_id){
            $return = M('store_wx')->where(array('store_id'=>$store_id))->find();
            if(empty($return)){
                $return = C('weixin_config');
                $return['store_id'] = C('jzc_store');
                return $return;
            } else {
                $return['appcode'] = $return['gd_id'];
                $return['appmchid'] = $return['mchid'];
                return $return;
            }
        }else{
           $return = C('weixin_config');
            $return['store_id'] = C('jzc_store');
            return $return;
        }
    }
}