<?php
namespace Sales\Controller;

abstract class WechatController extends BaseController {

    protected $token_info = array();
    protected $wx_token = array();
    protected $access_token = '';
    protected $wx_ticket = '';


    protected $wxuser = '';

   /* protected $wxuser = array(
        //'appcode'       => 'gh_643c38bb1b24',
        'appid'         => 'wxb5d1d38fe642be5a',
        //'appid'         => 'wx80ac0245c629eb56',
       'appsecret'     => 'ba839cf1a2dc4290af4a8932d8c7c850',
        //'appsecret'     => '5ed77d331a0b13d628d913ae49101809',
        'appmchid'      => '1232514802',
        'paysignkey'    => 'Jiuzichunjiniu140814081408140814'
    );*/

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
        5=>'cgi-bin/user/info?access_token=%s&openid=%s&lang=%s',
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
        //$this->wxuser = C('weixin_config');
        $this->wxuser = array(            // //即牛公众号
            'appcode'       => 'gh_c3ea06a7a528',
            'appid'         => 'wxb5d1d38fe642be5a',
            'appsecret'     => 'a7b49779fdc6a49d43849aed8449d089',
            'appmchid'      => '1232514802',
            'paysignkey'    => 'Jiuzichunjiniu140814081408140814'
       );

        $this->assign('user_info', $this->user_info = $this->login_info);
        $this->assign('is_weixin', $this->is_weixin = $this->is_weixin());  //设置微信
        $this->wx_token = session('wx_token');
        $this->access_token = session('access_token');
        $this->wx_ticket = session('wx_ticket');
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
     * 微信自动授权
     */
    public function wxlogin() {
        if ($this->is_weixin and !$this->wx_token) {
            if ($result = $this->wx_login2($_GET['code'],$_GET['state'])) {
                header('location:' . $this->cur_url);
            } else {
                //$url = $this->cur_domain . U() . '?back_act=' . AesEnCrypt($this->cur_url);
                $url = $this->cur_url;
                $this->Authorize($url, 1);
            }
        }else{
            //设置微信自动登录
            if(!$this->login_info && isset($this->wx_token['openid']) && isset($this->wxuser['appcode'])){
                $where['a.openid'] = $this->wx_token['openid'];
                $where['a.gd_id'] = $this->wxuser['appcode'];
                $user_wx = M('user_wx')->alias('a')->join(' JOIN __USERS__ b ON a.user_id=b.user_id')->where($where)->find();
                if($user_wx){
                    $user_wx['add_time'] = $this->time;
                    session('login_sales', $user_wx);
                    $this->set_login_cookie($user_wx);
                }
            }
        }
    }

   protected function wx_login2($code, $state) {
        if (!$code or !$state) return false;
        $time = time();
        if (!$this->wx_token) {
            $this->wx_token = $this->access_token($code);
            if (!empty($this->wx_token['openid'])) {
                $this->wx_token['add_time'] = $time;
                session('wx_token', $this->wx_token);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * 跳转认证页面
     * 'connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code
     * &scope=snsapi_userinfo&state=123#wechat_redirect'
     * @param string $Response_url
     * @param int $scope
     * @return mixed
     */
   protected function Authorize($Response_url = '', $scope = 0){
        $str = $scope == 0 ? 'snsapi_userinfo' : 'snsapi_base';
        $url = $Response_url == '' ? $this->cur_url : $Response_url;
        $url = sprintf($this->get_url(0,1), $this->wxuser['appid'] ,urlencode($url), $str,$this->get_rand_str(8,1));
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
            if ($access_token) session('access_token', $access_token);
            if ($result) session('wx_ticket', $result);
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
}