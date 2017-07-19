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
 * $Id: WeixinController.class.php 17156 2015-12-25 17:30:47Z keheng $
*/
namespace Wap\Controller;
use Wap\Model;
class WeixinController extends BaseController {
    private $wx_doamin = array(
        0=>'https://api.weixin.qq.com/',
        1=>'https://open.weixin.qq.com/',
        2=>'https://mp.weixin.qq.com/',
        3=>'https://api.mch.weixin.qq.com/'
    );
    private $wx_api_url = array(
        0=>'connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s#wechat_redirect',
        1=>'sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code',
        2=>"sns/userinfo?access_token=%s&openid=%s&lang=zh_CN",
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
       13=>'cgi-bin/qrcode/create?access_token=%s'         //获取二维码
    );

    protected $wecha_id;
    protected $token;
    protected $token_api;
    protected $wxuser = array();
    //支付相关参数star
    private $parameters;        //静态链接参数
    private $prepay_id;         //预支付ID
    private $appid;             //公众号appid
    private $mchid;             //受理商ID，身份标识
    private $key;               //商户支付密钥Key。审核通过后，在微信发送的邮件中查看
    private $appsecret;         //JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
    //=======【JSAPI路径设置】===================================
    //获取access_token过程中的跳转uri，通过跳转将code传入jsapi支付页面
    //private $js_api_call_url = 'http://www.jiniu.cc/index.php?m=Wap&c=Weixin&a=jsApiCall';
    //=======【证书路径设置】=====================================
    //证书路径,注意应该填写绝对路径
    private $sllcert_path = '/cacert/apiclient_cert.pem';
    private $sslkey_path = '/cacert/apiclient_key.pem';
    //=======【异步通知url设置】===================================
    //异步通知url，商户根据实际开发过程设定
    //C('url')."admin.php/order/notify_url.html";
    //private $notify_url = 'http://www.jiniu.cc/Wap/Weixin/notify.html';
    //=======【curl超时设置】===================================
    //本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
    private $curl_timeout = 30;
    //支付相关参数stop
    protected function _initialize() {
        parent::_initialize();
        if (strpos($_SERVER['HTTP_HOST'],'weipincn.com')){
            $this->wxuser = array(
                'appcode'       => $this->_CFG['weixin_appcode'],
                'appid'         => $this->_CFG['weixin_appid'],
                'appsecret'     => $this->_CFG['weixin_appkey'],
                'appmchid'      => $this->_CFG['weixin_mchid'],
                'paysignkey'    => $this->_CFG['weixin_paysignkey']
            );
        } else {
            $this->wxuser = array(
                //'appcode'       => 'gh_bb7aa136b9e4',//$this->_CFG['weixin_appcode'],
                //'appid'         => 'wxbb39d35308973c74',//$this->_CFG['weixin_appid'],
                //'appsecret'     => 'd4624c36b6795d1d99dcf0547af5443d',//$this->_CFG['weixin_appkey'],
                //'appmchid'      => $this->_CFG['weixin_mchid'],
                //'paysignkey'    => $this->_CFG['weixin_paysignkey']
                'appcode'       => $this->store_wx['gd_id'],//$this->store_wx['gd_id'],
                'appid'         => $this->store_wx['appid'],//$this->_CFG['weixin_appid'],
                'appsecret'     => $this->store_wx['appsecret'],//$this->_CFG['weixin_appkey'],
                'appmchid'      => $this->store_wx['mchid'],
                'paysignkey'    => $this->store_wx['paysignkey']
            );
        }
    }



    public static function Array2Str($arr) {
        if (is_array($arr)) {
            $result = '';
            $i = 0;
            foreach($arr as $k => $v) {
                if ($i > 0) $result .= '&';
                $result .= $k . '=' . $v;
                $i++;
            }
            return $result;
        } else {
            return false;
        }
    }
    /**
     * 作用：将xml转为array
     * @param $xml
     * @return mixed
     */
    public static function Xml2Array($xml){
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }

    /**
     * 作用：array转xml
     * @param $arr
     * @return string
     */
    public static function Array2Xml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val){
            if (is_numeric($val)){
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }else{
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 对像转为数组
     * @param $obj
     * @return mixed
     */
    public function obj2array($obj){
        $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
        $arr = array();
        foreach ($_arr as $key => $val) {
            $val = (is_array($val)) || is_object($val) ? $this->obj2array($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }
    /**
     * 对像转数组
     * @param $obj
     * @return array
     */
    public function ob2ar($obj) {
        if (is_object($obj)) {
            $obj = (array) $obj;
            $obj = $this->ob2ar($obj);
        } elseif (is_array($obj)) {
            foreach ($obj as $key => $value) {
                $obj[$key] = $this->ob2ar($value);
            }
        }
        return $obj;
    }
    /**
     * 验证Sign
     * @param array $obj
     * @param string $key 加密密匙
     * @param int $is_weixin
     * @param string $sign_text
     * @return bool
     */
    public function test_Sign($obj, $key = '', $is_weixin = 1, $sign_text = 'sign') {
        if (empty($obj) or $obj[$sign_text] == '') return false;
        if (!$key) $key = C('CRYPT_KEY');
        $sign = trim($obj[$sign_text]);
        if (isset($obj[$sign_text])) unset($obj[$sign_text]);
        if ($sign == $this->get_Sign($obj, $key)) {
            if ($is_weixin == 1) {
                if ($obj['appid'] == $this->wxuser['appid'] and $obj['mch_id'] == $this->wxuser['appmchid']) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Sign加密
     * @param $Obj array 项目数组
     * @param string $key 加密密钥
     * @param int $type 加密类型 sha1|md5
     * @return string 加密值
     */
    protected function get_Sign($Obj, $key = '', $type = 0){
        if (!$key) $key = C('CRYPT_KEY');
        foreach ($Obj as $k => $v){
            $Parameters[$k] = $v;
        }
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        $String .= "&key=" . $key;
        $this->logger($String);
        if ($type == 0) {
            $String = md5($String);
        } else {
            $String = sha1($String);
        }
        $result = strtoupper($String);
        return $result;
    }

    protected function wx_login($code, $state) {
        if (!$code or !$state) return false;
        $time = time();
        //$this->token = session('wx_token');
        if (!$this->wx_token) {
            $this->wx_token = $this->access_token($code);
            if (!empty($this->wx_token['openid'])) {
                $this->wx_token['add_time'] = $time;
                session('wx_token', $this->wx_token);
            } else {
                return false;
            }
        }
//print_r($this->token);
        $user = new Model\UsersModel();
        $user_info = $user->get_userinfo('openid="' . $this->token['openid'] . '"');
        if (empty($user_info) or $user_info['user_id'] <= 0) {
            $res = $this->get_wx_userinfo($this->token['access_token'],$this->token['openid']);
            $this->adddUserInfo($this->ob2ar($res));
            $user_info = $user->get_userinfo('openid="' . $this->token['openid'] . '"');
        }
        $this->user_info = $user_info;
        $this->user_id = $user_info['user_id'];
        $user_info['add_time'] = $time;
        $user_info['code'] = $code;
        $user_info['state'] = $state;
        session('login_info_'.$this->store_token, $user_info);
        return true;
    }


    protected function wx_login2($code, $state) {
        if (!$code or !$state) return false;
        $time = time();
        //$this->token = session('wx_token');
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

    protected function wx_login1($url = '',$stime = 1200){
        $time = time();
        session('login_info_'.$this->store_token, null);
        if (empty($url)) $url = U('Index/index');
        if (isset($_GET['code']) && isset($_GET['state']) and $this->is_weixin()) {
            $this->token = $this->access_token(I('code'));
            $this->token['add_time'] = $time;
            session('wx_token', $this->token);//exit(print_r($this->token,1));
            $openid = $this->token['openid'];
            if (!empty($openid)) {
                $user = new Model\UsersModel();
                $user_info = $user->get_userinfo('openid="' . $openid . '"');
                if (empty($user_info) or $user_info['user_id'] <= 0) {
                    $res = $this->get_wx_userinfo($this->token['access_token'],$openid);
                    $user_id = $this->adddUserInfo($this->ob2ar($res));
                    $user_info = $user->get_userinfo('openid="' . $openid . '"');
                }
                $this->user_info = $user_info;
                $this->user_id = $user_info['user_id'];
                $user_info['time'] = $time;
                $user_info['code'] = $_GET['code'];
                session('login_info_'.$this->store_token, $user_info);
                header('location:' . $url);
            } else {
                $this->error( '系统出错,请重新登录！');
            }
        } else {
            $this->Authorize($url);
        }
    }

    /**
     * 作用：格式化参数，签名过程需要使用
     * @param $paraMap
     * @param $urlencode
     * @return string
     */
    protected function formatBizQueryParaMap($paraMap, $urlencode){
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v){
            if($urlencode){
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0){
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }

    /**
     * 生成微信二维码
     * @param string|int $scene_str
     * @param string $action_name 临时:QR_SCENE|永久:QR_LIMIT_STR_SCENE
     * @param int $expire
     * @return bool|mixed
     */
    protected function get_wxuser_qr($scene_str, $action_name = 'QR_SCENE',$expire = 604800){
        if (is_numeric($scene_str)) {
            $scene = array('scene_id'=>(int)$scene_str);
        } else {
            $scene = array('scene_str'=>$scene_str);
        }
        if ($action_name == 'QR_SCENE') $data['expire_seconds'] = $expire;
        $data['action_name']       = $action_name;
        $data['action_info']       = array('scene'=>$scene);
        $api_token = $this->api_token($this->wxuser['appid'],$this->wxuser['appsecret']);
        if (!empty($api_token) and $api_token['access_token']) {
            $url = sprintf($this->get_url(13), $api_token['access_token']);
            $result = $this->obj2array(json_decode( ($this->curlGet($url,'post',json_encode($data))) ));
            if (empty($result) or !isset($result['ticket'])) {
                return false;
            } else {
                return $result;
            }
        } else {
            return false;
        }
    }



    protected function get_api_userinfo($openid){
        $token = session('token_api');
        if (!$token) {
            $token = $this->api_token($this->wxuser['appid'],$this->wxuser['appsecret']);
            session('token_api', $token, $token['expires_in']);
        } else {
            //$this->logger('已获取得token;');
        }
        return $this->api_userinfo($token['access_token'],$openid);
    }

    /**
     * API方式取token
     * cgi-bin/token?grant_type=client_credential&appid=APPID&secret=APPSECRET
     * @param $appid
     * @param $secret
     * @return mixed
     */
    private function api_token($appid,$secret){
        //$url = sprintf($this->get_url(5), $this->wxuser['appid'], $this->wxuser['appsecret']);
        $url = sprintf($this->get_url(4,0), $appid, $secret);
        $this->logger('取token地址：' . $url);
        return json_decode($this->curlGet($url),1);
    }

    /**
     * api方式获取用户信息
     * cgi-bin/user/info?access_token=%s&openid=%s&lang=%s
     * @param $access_token
     * @param $openid
     * @param string $lang
     * @return mixed
     */
    private function api_userinfo($access_token,$openid,$lang = 'zh_CN'){
        $url = sprintf($this->get_url(5,0), $access_token, $openid,$lang);
        return json_decode($this->curlGet($url),1);
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
    }

    /**
     * 获取access_token
     * 'sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code'
     * @param $code
     * @return mixed
     */
    private function access_token($code){
        $rt = $this->curlGet(
            $url = sprintf($this->get_url(1,0),
            $this->wxuser['appid'],
            $this->wxuser['appsecret'],
            $code));//echo $rt . "\n" . "$url\n";
        return json_decode($rt, 1);
    }

    /**
     * 刷新token
     * 'sns/oauth2/refresh_token?appid=%s&grant_type=refresh_token&refresh_token=%s'
     * @param $refresh_token
     * @return mixed
     */
    private function refresh_token($refresh_token){
        $rt = $this->curlGet(sprintf($this->get_url(3),$this->wxuser['appid'], $refresh_token));
        return json_decode($rt, 1);
    }
    protected function token_refresh(){
        $token = session('wx_token');
        if (!empty($token) and $token['refresh_token'] != '') {
            $time = time();
            if (($time - $token['add_time']) > ($token['expires_in'] - 60)) {
                $res = $this->refresh_token($token['refresh_token']);
                if (!empty($res) and $res['access_token'] != '') {
                    $res['add_time'] = $time;
                    session('wx_token', $this->token);
                }
            }
        }
    }

    /**
     * 获取当前用户信息
     * "sns/userinfo?access_token=%s&openid=%s&lang=zh_CN"
     * @param $access_token
     * @param $openid
     * @return mixed
     */
    protected function get_wx_userinfo($access_token,$openid){
        $info_url = sprintf($this->get_url(2,0),$access_token,$openid);
        return json_decode($this->curlGet($info_url ), 1);
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

    /**
     * curl
     * @param $url
     * @param string $method
     * @param string $data
     * @param int $is_cookie
     * @return mixed
     */
    protected function curlGet($url, $method = 'get', $data = '', $is_cookie = 0) {
        $method = strtoupper($method) == 'GET' ? 'GET' : 'POST';
        $data = is_array($data) ? $this->Array2Str($data) : $data;
        $this->logger("CURL发送数据：\n" . $url . "\n" . $method . "\n" . $data);
        $ch = curl_init();
        try {
            $module = strtoupper(MODULE_NAME);
            $header = array("Accept-Charset"=>"UTF-8");
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,10);
            curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_POST, $method == 'POST' ? TRUE : false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); //强制协议为1.0
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect: ')); //头部要送出'Expect: '
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
            curl_setopt($ch, CURLOPT_COOKIE, C('COOKIE_PREFIX') . $module . "=".cookie($module));
            $temp = curl_exec($ch);
            curl_close($ch);
            $this->logger("CURL返回数据：\n" . $temp);
            return $temp;
        } catch (\Think\Exception $e) {
            \Think\Log::record("Curl超时：" .
                "\n错误号：" . curl_errno($ch) .
                "\n错误描述：" . curl_error($ch) .
                "\n提交URL："  . $url .
                "\n提交数据：" . $data .
                "\n提交方式：" . $method );
            curl_close($ch);
            return false;
        }
    }

    /**
     * 微信发送信息
     * @param $openid
     * @param $content
     * @return string
     */
    protected function wx_sendMessage($openid, $content){
        $id = $this->get_rand_str(8,1);
        $data = array(
            'ToUserName'    => $openid,	//开发者微信号
            'FromUserName'  => $this->wxuser['appcode'],	//发送方帐号（一个OpenID）
            'CreateTime'    => time(),	//消息创建时间 （整型）
            'MsgType'       => 'text',	//text
            'Content'       => $content,	//文本消息内容
            'MsgId'         => $id	//消息id，64位整型
        );
        $data = $this->array2xml($data);
        $url = sprintf($this->get_url(12),'');
        $this->curlGet($url,'post',$data);
    }

    public function test_send(){
        $this->debug(session('wx_token'),1);
        //$this->wx_sendMessage('oiUltuGRS_9GYX0vlt2cTWb5eY6M','这是测试消息');
    }

    /**
     * 汇客惠品API接口
     * @param $url
     * @param $data
     * @param $is_return int
     * @return array|mixed
     */
    protected function hkhp_api($url, $data, $is_return = 1){
        $data['appid']      =  $this->api['appid'];
        $data['appsecret']  =  $this->api['appsecret'];
        $data['uid']        =  $this->user_id;
        $data['sign']       =  $this->get_Sign($data);
        //exit($this->hkhp_api_url . $url);
        $hkhp_info = $this->curlGet($this->hkhp_api_url . $url, 'post', $this->Array2Str($data));
        $hkhp_info = $this->ob2ar(json_decode($hkhp_info));//var_dump($hkhp_info);exit('43214312');
        if (intval($hkhp_info['error']) == 202) {       //登录
            $data = array(
                'appid'     =>  $this->api['appid'],
                'uid'       =>  $this->user_id,
                'ref'       =>  urlencode($this->cur_domain . U()),
            );
            $data['sign'] = $this->get_Sign($data);
            header('location:' . $hkhp_info['data'] . '&' . $this->Array2Str($data));
        } elseif (intval($hkhp_info['error']) != 200) {
            if ($is_return){
                return $hkhp_info;
            } else {
                $this->ajaxReturn($hkhp_info);
            }
        } else {
            return $hkhp_info;//防止api出错显示错误信息
        }
    }
}
 