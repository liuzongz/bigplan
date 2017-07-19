<?php
namespace Sales\Controller;
use Think\Controller;
use Sales\Model;
abstract class BaseController extends Controller {
    protected $login_info = array();
    protected $is_weixin = false;
    protected $user_id = 0;
    protected $store_id = '';
    protected $ajax = 0;
    protected $user_info = array();
    protected $back_url = '';
    protected $cur_url = '';
    protected $cur_domain = '';
    protected $time = 0;

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
        $this->store_id = I('request.' . C('store_token_name'),0, 'intval');
        $this->time = time();
        $this->login_info = session("login_info_{$this->store_id}");
        $this->assign('user_info', $this->user_info = $this->login_info);

        $this->cur_url      = get_cur_url(1);
        $this->cur_domain   = get_cur_domain(1);
        $this->back_url     = get_back_url(U('User/index'));//设置回跳url
        //$this->get_cur_url();
        $this->assign('is_weixin', $this->is_weixin = $this->is_weixin());  //设置微信
        //加入自定义语言变量
        L(include MODULE_PATH.'/Common/lang.php');
    }

    /**
     * 取当前域名和当前URL
     */
    private function get_cur_url(){
        $scheme = $_SERVER["REQUEST_SCHEME"];
        $scheme = $scheme != '' ? $scheme . '://' : $scheme;
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") {
            $agreem = 'http://';
        } else {
            $agreem = 'https://';
        }
        $agreement = ($scheme == '' ? $agreem : $scheme)  ;
        $this->cur_url = $agreement . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        $this->cur_domain = $agreement . $_SERVER['HTTP_HOST'];
    }

    /**
     * 发送手机短信
     * @param $mobile int
     * @param $msg_id string
     * @return array
     */
    protected function SendSms($mobile, $msg_id){
        $m = new Model\UsersModel();
        $rnd = $this->get_rand_str(6,1);
        $m->authcode_add($mobile,$rnd,2);
        $tpl = C("SMS_TPL");
        if (!$tpl[$msg_id]) {
            $this->result_ajax(1,'模板ID不存在！');
        } else {
            $body = sprintf($tpl[$msg_id],$rnd);
            $result = $this->Sms($mobile, array('key'=>$msg_id,'body'=>$body));
            if ($result['Code'] == 0) {
                return $this->result_ajax(0,'短信发送成功！');
            } else {
                return $this->result_ajax(2,$result['Message']);
            }
        }
    }

    /**
     * 生成随机字符口串
     * @param int $length
     * @param int $str_type
     * @return string
     */
    protected function get_rand_str($length = 8,$str_type = 0){
        $chars[0] = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
        $chars[1] = '0123456789';
        $chars[2] = '0123456789abcdefghijklmnopqrstuvwxyz';
        $password = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $password .= $chars[$str_type][ mt_rand(0, strlen($chars[$str_type]) - 1) ];
        }
        return $password;
    }

    /**
     * 返回固定格式的json
     * @param int $error
     * @param string $msg
     * @param array $data
     * @param array $extends
     * @return array
     */
    protected function result_ajax($error = 0,$msg = '',$data = [], $extends = []){
        $result = ['error'=>$error,'msg'=> $msg, 'data'=> $data];
        if ($extends) {
            $result = array_merge($result, $extends);
        }
        return $result;
    }

    /**
     * 发送短信（单条）
     * @param int  $mobile 手机号码
     * @param array $tpl 内容
     * @param int $rType 响应数据类型 int $rType  0 Json 类型，1 xml 类型
     * @param string $mToken
     * @param string $extno
     * @return array|bool|float|int|mixed|stdClass|string
     */
    private function Sms($mobile, $tpl, $rType = 0,$mToken = "",$extno = ""){
        $sms_info = array(
            'host'  =>  C('SMS_HOST'),
            'port'  =>  C('SMS_PORT'),
            'uri'   =>  C('SMS_URI'),
            'user'  =>  C('SMS_USER'),
            'pass'  =>  C('SMS_PASS'),
        );
        $url = "http://{$sms_info['host']}:{$sms_info['port']}{$sms_info['uri']}";
        //$str = "account={$sms_info['user']}&token={$sms_info['pass']}&tempid={$tpl_id}&mobile={$mobile}&content={$body}&type={$rType}&mToken={$mToken}&extno={$extno}";
        $str = array(
            'account'   =>  $sms_info['user'],
            'token'     =>  $sms_info['pass'],
            'tempid'    =>  $tpl['key'],
            'mobile'    =>  $mobile,
            'content'   =>  $tpl['body'],
            'type'      =>  $rType,
            'mToken'    =>  $mToken,
            'extno'     =>  $extno,
        );
        $str = $this->Array2Str($str);
        $res = $this->curlGet($url,'post',$str);
        $data = array(
            'user_id'   =>  intval($this->user_info['user_id']),
            'send_url'  =>  $url,
            'send_str'  =>  $str,
            'get_str'   =>  $res,
            'add_time'  =>  $this->time
        );
        M('send_sms_record')->add($data);
        return json_decode($res, true);
    }

    /**
     * 是否是手机号码
     *
     * @param string $phone 手机号码
     * @return boolean
     */
    protected function is_phone($phone) {
        if (strlen ( $phone ) != 11 || ! preg_match ( '/^1[3|4|5|8|7][0-9]\d{4,8}$/', $phone )) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 数组转换字符串
     * @param string $arr
     * @return boolean
     */
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
     * curl
     * @param $url
     * @param string $method
     * @param string $data
     * @param int $is_raw
     * @return mixed
     */
    protected function curlGet($url, $method = 'get', $data = '', $is_raw = 0) {
        $method = strtoupper($method) == 'GET' ? 'GET' : 'POST';
        $data = is_array($data) ? $this->Array2Str($data) : $data;
        $this->logger("CURL发送数据：\n" . $url . "\n" . $method . "\n" . $data);
        $ch = curl_init();
        try {
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
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); //强制协议为1.0
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect: ')); //头部要送出'Expect: '
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
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

    protected function logger($log_content) {
        /*if (isset($_SERVER['HTTP_APPNAME'])) {   //SAE
            sae_set_display_errors(false);
            sae_debug($log_content);
            sae_set_display_errors(true);
        } else */
        if (APP_DEBUG) { //LOCAL
            $max_size = 50000;
            $log_filename = "log.xml";
            if (file_exists($log_filename) and ( abs(filesize($log_filename)) > $max_size)) {
                unlink($log_filename);
            }
            if (is_array($log_content)) {
                $log_content = print_r($log_content,1);
            }
            file_put_contents($log_filename, "====" . date('Y-m-d H:i:s') . "=======\n" . $log_content . "\r\n\n", FILE_APPEND);
        } else {

        }
    }

    /**
     * 验证用户登录跳转
     * @param string $url
     * @param int $sess_time
     * @return bool
     */
     protected function void_user($store_id, $url){
         void_user($store_id, $url);
     }


    /**
     * 验证用户登录跳转
     * @param string $url
     * @param int $sess_time
     * @return bool
     */
    protected function void_user1( $url = '', $sess_time = 3600){
        $time = time();
        //$this->login_info = session('login_info');
        $this->login_info =  session('login_info_' . $this->store_id);
        if ($this->is_login()){
            if ($time - $this->login_info['add_time'] < $sess_time) {
                session('login_info_' . $this->store_id . '.add_time',$time);
                return true;
            } else {
                session('login_info_' . $this->store_id,null);
                return false;
            }
        } else {
            if(!$url){
                $url = $this->cur_url;
            }
            $url = get_server('PASSPORT_SERVER', '/User/login',
                [
                    'back_act'  =>   AesEnCrypt($this->cur_domain . U('index/access_token') . '?back_act=' . AesEnCrypt($url)),
                    'back_module'    =>   AesEnCrypt(strtoupper(MODULE_NAME)),
                ], 1);
            if ($this->ajax) {
                /*$result['message'] = '请先登录！';
                $result['error']   = 2;
                $result['contents']['url'] = $url;*/
                $result = result_ajax(301,'请先登录！', ['url'=>$url]);
                $this->ajaxReturn($result);
            } else {
                header('Location: ' . $url);
            }
        }
    }

    protected function is_login($store_id = ''){
        $login_sess = !empty($login_sess) ? $login_sess : session('login_info_' . $store_id);
        if ($login_sess and $login_sess['user_id'] > 0) {
            $this->login_info = $login_sess;
            $this->login_info['add_time']   = $this->time;
            session('login_info_' . $store_id . '.add_time',$this->time);
            return true;
        }else{
            return false;
        }

    }

    /**
     * 验证用户是否登录
     * @param $login_sess array 信息
     * @return bool
     */
    protected function is_login1($login_sess = array()){
        $login_sess = !empty($login_sess) ? $login_sess : session('login_sales');
        if ($login_sess and $login_sess['user_id'] > 0) {
            $user_info = M('users')
                ->alias('u')
                //->join('LEFT JOIN __USERS__ u1 ON u.parent_id=u1.user_id')
               // ->join('LEFT JOIN __USER_RANK__ ur ON u.user_rank=ur.rank_id')
               // ->field('u.*,if(u1.user_name = "",u1.nickname,u1.user_name) as parent_name,ur.rank_name')
                ->where('u.user_id=' . intval($login_sess['user_id']))
                ->find();
            if ($user_info) {
                $user_info['pay_points'] = intval($user_info['pay_points']);
                $user_info['user_avatar'] = img_url($user_info['user_avatar']);
                $this->user_info = $user_info;
                $this->user_id = $user_info['user_id'];
                return true;
            } else {
                session('login_sales',null);
                return false;
            }
        } else {
            session('login_sales',null);
            return false;
        }
    }

    /**
     * 取返回url
     */
    protected function get_back_url(){
        if (isset($_REQUEST['back_act']) and trim($_REQUEST['back_act']) != '') {
            $back_url = AesDeCrypt(trim($_REQUEST['back_act']));
        } else {
            if (isset($_SERVER['HTTP_REFERER']) and $_SERVER['HTTP_REFERER'] != '') {
                $back_url = $_SERVER['HTTP_REFERER'];
            } else {
                $back_url = U('User/index');
            }
        }
        $this->assign('back_act', $back_url);
        $this->back_url = $back_url;
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
     * 设置提交表单用的token
     * @param string $key
     * @return string
     */
    protected function set_form_token($key = 'form_token'){
        $str = md5($this->get_rand_str(8));
        session($key, $str);
        $this->assign($key, $str);
        return $str;
    }

    /**
     * 效验证token
     * @param string $key
     * @return bool
     */
    protected function check_form_token($key = 'form_token') {
        $get_key = trim(session($key));
        $ses_key = trim(I('post.' . $key,'','trim'));
        session($key,null);
        if (empty($get_key) or empty($ses_key) or $get_key != $ses_key) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 表单令牌验证
     * @param $key
     */
    protected function check_token($key = 'form_token'){
        if(!$this->check_form_token($key)){
            $result['error'] = 9;
            $result['message'] = '请不要重复提交！';
            $result['contents'] = null;
            if ($this->ajax) {
                $this->ajaxReturn($result);
            } else {
                if ($result['error']) {
                    $this->error($result['message']);
                } else {
                    $this->success($result['message']);
                }

            }
        };
    }

    /**
     * 判断是否微信浏览器登录
     * @return bool
     */
    protected function is_weixin(){
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
            return true;
        }
        return false;
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
            'login_time'    =>  time(),
            'login_module'  =>  'sales'
        ];
        cookie(strtoupper(MODULE_NAME), RsaEnCrypt(json_encode($data)));
    }

    /**
     * 发送手机验证码
     * @param string $cnname
     * @param int    $user_id
     * @param string $pair
     * @param int    $content
     * @return array
     */
    protected function send_authcode($pair, $cnname='jiniu', $user_id=0, $content=1){
        \Think\Log::record('URL:' . get_server('API_SERVER') . '/Sms/sendMsg.html');
        \Think\Log::record(print_r(['cnname'=>$cnname, 'user_id'=>$user_id, 'pair'=>$pair, 'content'=>$content],1));
        $result = curlGet(get_server('API_SERVER').'/Sms/sendMsg.html','post', ['cnname'=>$cnname, 'user_id'=>$user_id, 'pair'=>$pair, 'content'=>$content]);
        return json_decode($result, true);
    }

    /**
     * 验证手机/邮箱验证码
     * @param string $pair
     * @param string $token
     * @param string $verify
     * @return bool
     */
    protected function check_authcode($pair, $verify, $token){
        \Think\Log::record('URL:' . get_server('API_SERVER') . '/Sms/sendMsg.html');
        \Think\Log::record(print_r(['pair'=>$pair, 'verify'=>$verify, 'token'=>$token],1));
        $result = curlGet(get_server('API_SERVER').'/Sms/checkMsg.html','post', ['pair'=>$pair, 'verify'=>$verify, 'token'=>$token]);
        $result = json_decode($result, true);
        return isset($result['error']) && $result['error'] == 200;
    }

}


