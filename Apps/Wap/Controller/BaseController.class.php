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
 * $Id: BaseController.class.php 17156 2015-12-25 17:26:47Z keheng $
 */

namespace Wap\Controller;
use Think\Controller;
use Think\Log;
use Think\Upload;
use Wap\Model;
class BaseController extends Controller {
    protected $cur_url = '';
    protected $cur_domain = '';
    protected $user_info = array();
    protected $user_id = 0;
    protected $ssid = '';
    protected $ajax = 0;
    protected $crypt;
    protected $crypt_key;
    protected $back_url = '';
    protected $time = 0;
    protected $is_weixin = false;
    protected $debug = false;
    protected $wx_token;        //微信授权信息
    protected $store_wx;        //店铺微信信息
    protected $login_info;      //登录信息
    protected $position_info;      //位置信息
    protected $wx_pay = false;          //店铺微信支付是否开启
    protected $wx_store_id = 0;     //微信里面设置店铺id
    protected $store_token = 0;     //微信里面设置店铺访问令牌

    public $ssid_name = 'WP_SSID';
    public $_CFG;
    public $result = array(
        'error'     =>  0,
        'message'   =>  '',
        'contents'  =>  array()
    );

    protected function _initialize() {
        //设置店铺信息
        $this->assign('is_weixin', $this->is_weixin = $this->is_weixin());
        $store_token = I('get.store_token', 0, 'intval');
        $this->store_token = $store_token ? $store_token : session('store_token');
        session('store_token', $this->store_token);
        $this->assign('store_token', $this->store_token);

        $goods_model = new Model\GoodsModel();
        $this->_CFG = $goods_model->Loadconfig();
        $this->crypt_key = md5(C('CRYPT_KEY'));
        $this->time = time();
        $this->assign('_CFG', $this->_CFG);
        $this->cur_domain = get_cur_domain(1);
        $this->cur_url = get_cur_url(1);
        $this->back_url = get_back_url(U('User/index'));
        $this->ajax = isset($_REQUEST[C('VAR_AJAX_SUBMIT')]) ? intval($_REQUEST[C('VAR_AJAX_SUBMIT')]) : 0;
        $this->wx_token = session('wx_token');
        $this->store_wx = session('store_info');
        $this->login_info = session('login_info_'.$this->store_token);
        $this->user_id = $this->login_info['user_id'];
        $this->position_info = session('position_info');
        //cookie登录
        $this->login_by_cookie();
        //url过滤
        $this->I_filter();
        //$this->is_store_wx();
    }


    /**
     * 获取登录状态
     */
    protected function get_login_status(){
        if (!$login_info_sess = $this->login_info) {
            $login_info_cook = cookie('login_info');
            $login_info = unserialize(AesDeCrypt($login_info_cook));
            if ($login_info_cook and $login_info and $this->is_login($login_info)) {
                $arr['add_time'] = $this->time;
                session('login_info_'.$this->store_token, $login_info);
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
            $arr['add_time'] = time();
        }
        return $arr;
    }

    /*
     * 使用cookie登录
     */
    protected function login_by_cookie(){
       //print_r($_COOKIE);
        //session中有登录信息，或者cookie中没有登录信息,中断
        if($this->login_info||!cookie('login_info')) return ;
        if($login_info = $this->get_login_cookie()){
            $user_id = $login_info['user_id'];
            //查看是否有此用户
            $count = M('users')->where('user_id=%d',$user_id)->count();
            if(!$count) return ;
            //设置登录信息
            session('login_info_' . $this->store_token,$login_info);
        }
    }


    /**
     * 保留小数位
     * @param $float
     * @param int $f
     * @return string
     */
    protected function get_float($float, $f = 2) {
        return sprintf("%.2f",substr(sprintf("%.3f", $float), 0, -$f));
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
     * 发送邮件
     * @param string $email
     * @param int $tpl
     * @param array $data
     * @return bool
     */
    protected function SendEmail($email, $tpl, $data = array()){
        $m = new Model\UsersModel();
        $rnd = $this->get_rand_str(6,1);
        $m->authcode_add($email,$rnd,1);
        $this->assign('code', array('email'=>$email,'verify'=>$rnd));
        switch($tpl) {
            case '1':
                $title = $this->_CFG['shop_name'] . '会员邮箱验证';
                $body = $this->fetch('Public:Email_sendcode');
                break;
            case '2':
                $title = $this->_CFG['shop_name'] . '找回密码验证';
                $body = $this->fetch('Mail.reset_pass');
                break;
            case '3':
                $title = '';
                $body = '';
                break;
            default:
                $title = '';
                $body = '';
        }
        return $this->Email($email,$title,$body);
    }

    /**
     * 发送邮件
     * @param $mailto
     * @param $subject
     * @param $body
     * @param int $is_html
     * @return bool
     */
    private function Email($mailto, $subject, $body, $is_html = 1){
        $email_obj = new \Vendor\Email\Email();
        $data = array(
            'mailto'    =>  $mailto, //收件人
            'subject'   =>  $subject,    //邮件标题
            'body'      =>  $body,    //邮件正文内容
        );
        return $email_obj->send($data);
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
     * 输出json
     * @param int $error
     * @param string $msg
     * @param array $data
     */
    protected function echo_ajax($error = 0,$msg = '',$data = []){
        exit(json_encode($this->result_ajax($error,$msg,$data)));
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
     * 获取统一格式文件名
     * @param $ext
     * @return string
     */
    protected function get_save_name($ext){
        return time() . '_' . md5($this->get_rand_str(8)) . '.' . $ext;
    }

    /**
     * 获取存储路径
     * @param $id
     * @return mixed|string
     */
    protected function get_save_path($id){
        $dir = $this->get_uploadimg_dir($id);
        $dir = C("UP_PATH") . $dir . '/' . date('Y-m',time()) . '/';
        @mkdir($dir = iconv("UTF-8", "GBK", $dir),0777,true);
        return $dir;
    }

    /**
     * 生成二维码
     * @param $data
     * @param $file
     * @param string $level
     * @param int $size
     */
    protected function create_qr($data, $file, $level = 'L', $size = 10) {
        create_qr($data, $file, $level, $size);
    }

    /**
     * 保存网络图片到本地
     * @param $file
     * @param $url
     * @return int
     */
    protected function save_net_file($file,$url){
        $this->logger( $file . "\n" . $url);
        return @file_put_contents($file, $this->curlGet($url));
    }

    /**
     * 上传文件
     * @param $dir_id
     * @param int $size
     * @param array $mime
     * @return array|bool|string
     */
    protected function upfile($dir_id,$size = 512000, $mime = array()){
        $dir = $this->get_uploadimg_dir(intval($dir_id));
        if (isset($_FILES) and !empty($dir)) {
            $upload = new Upload();
            $upload->__set('rootPath',C("UP_PATH"));
            $upload->__set('savePath', $dir . '/');
            $upload->__set('mimes', $mime);
            $upload->__set('maxSize', $size);
            $upload->__set('subName', array('date','Y-m'));
            if ($filename = $upload->upload($_FILES)) {
                foreach ($filename as &$v) {
                    $v['savepath'] =  C("UP_PATH") . $v['savepath'];
                }
                return $this->save_upload_img($dir_id,$filename);
            } else {
                return $upload->getError();
            }
        } else {
            return false;
        }
    }

    /**
     * 保存上传的文件 记录到数据库
     * @param $dir_id
     * @param $img
     * @return array|bool
     */
    protected function save_upload_img($dir_id, $img){
        if (!is_array($img)) {
            return false;
        } else {
            $data = array();
            $result= array();
            foreach ($img as $k => $v) {
                $result[$k] = $v['savepath'] . $v['savename'];
                $data[] = array(
                    'cat_id'    => $dir_id,
                    'user_id'   =>  $this->user_id,
                    'upname'   =>  $v['name'] ? $v['name'] : '',
                    'uptype'   =>  $v['type'] ? $v['type'] : '',
                    'upsize'   =>  $v['size'] ? $v['size'] : '',
                    'upkey'   =>   $v['key'] ? $v['key'] : '',
                    'md5_key'   =>  $v['md5'],
                    'sha1_key'   =>  $v['sha1'],
                    'savename'   =>  $v['savename'],
                    'savepath'   =>  $v['savepath'],
                    'user_ip'   =>  get_client_ip(),
                    'add_time'   =>  time(),
                );
            }
            if (!empty($data)) {
                if (M('ImgGallery')->addall($data)) {
                    return $result;
                } else {
                    Log::record('文件上传失败！' . print_r($img,1) . print_r($data,1));
                    return false;
                }
            } else {
                return false;
            }
        }
    }


    /**
     * 获取新更新二维码信息
     * @param $user_info
     * @param $store_id
     * @return array
     */
    protected function get_qr_info($user_info,$store_id) {
        $user_model = new Model\UsersModel();
        $qr = $user_model->user_qr_get($user_info['user_id'], $store_id);
        //$time = time();//var_dump($qr);exit( $qr['add_time'] + $qr['expire_seconds'] . '=' . $time);
        if (empty($qr) or ($this->time - $qr['add_time']) > $qr['expire_seconds']) {
            //if (!empty($qr)) @unlink(__ROOT__ . $qr['img_address']);
            $res = $this->get_share_qrcode($user_info['user_id'], $qr, $store_id);
            if ($res['error'] > 0) {
                return $res;
            } else {
                $info = $res['data'];
            }
            $info['user_avatar'] = img_url($user_info['user_avatar']);
            $info['user_name'] = $user_info['user_name'];
            $info['nickname'] = $user_info['nickname'];
        } else {
            $info = $qr;
        }
        if($info){//获取店铺信息
            $info['store_config'] = M('store_qrconfig')->where(['store_id'=>$store_id])->find();
            debug($info);
            $info['store_config']['qr_config'] = unserialize($info['store_config']['qr_config']);
            $info['store_config']['bg_img'] = img_url($info['store_config']['bg_img']);
        }
        $info['img_address'] = img_url($info['img_address']);
        return result_ajax(0,'获取成功！', $info);
    }

    /**
     * 获取二维码
     * @param $user_id
     * @param array $qr_info
     * @param $store_id
     * @return array
     */
    protected function get_share_qrcode($user_id, $qr_info = array(), $store_id){
        $time = time();
        $user_model = new Model\UsersModel();
        if ($qr_info && $qr_info['id'] > 0) {       //存在记录
            $qr_md5 = $qr_info['md5'];
            $file = $qr_info['img_address'];
            $qr_id = $qr_info['id'];
        } else {                                    //新添加
            $qr_md5 = md5($this->get_rand_str(22,2) . $time);
            $file = '';
        }
        //$qr_data = $this->cur_domain . U('User/register') . '?rec=' . AesEnCrypt($user_id) . '&store_token=' . $store_id;
        $qr_data = get_server('PASSPORT_SERVER', '/User/index', ['rec'=>AesEnCrypt($user_id), C('store_token_name')=>$store_id]);
        $result = $this->img_qrcode($qr_data, 'L', 0, $store_id);//读取QR信息
        if ($result && $result['error'] == 200) {
            $filepath = $result['data']['filepath'];
            if(isset($qr_id)){
                $user_model->user_qr_edit($qr_id, $qr_md5, $filepath, 604800);//保存到数据库
            }else{
                $qr_id = $user_model->user_qr_add($user_id, $qr_md5, $filepath, 604800);//保存到数据库
            }
            $info['id'] = $qr_id;
            $info['md5'] = $qr_md5;
            $info['user_id'] = $user_id;
            $info['img_address'] = $filepath;
            $info['add_time'] = $time;
            $result = result_ajax(0, '', $info);
        } else {
            $result = result_ajax(302,'没有读取到二维码信息');
        }
        return $result;
    }

    protected function img_qrcode($qr_data, $level = 'L', $icon = 0, $store_id=0) {
        //$url = C('UPLOAD_SERVER') . U('img/Uploader/qrcode') . '?module=Wap&' . C('VAR_AJAX_SUBMIT') . '=1';
        $url =  get_server('IMG_SERVER', '/Uploader/qrcode',
            [
                'module'                =>  'Wap',
                C('VAR_AJAX_SUBMIT')    =>  1,
                C("VAR_SESSION_ID") =>  AesEnCrypt(session_id()),
                C("store_token_name") =>  $store_id
            ],  1);
        $data = array(
            'data'      => urlencode($qr_data),
            'level'     =>  $level,
            'icon'      =>  $icon
        );
        return json_decode(curlGet($url,'post',$data),1);
    }

    /**
     * 在图片服务器创建二维码
     * @param $qr_data
     * @param $user_id
     * @return $user_qrcode
     */
    protected function create_qrcode($qr_data, $user_id, $user_qrcode=''){
        $store_info=array(
            'user_url'     => $qr_data,
            'user_id'      =>  $user_id,
            'user_qrcode'   =>  $user_qrcode
        );
        //$url = C('IMG_SERVER') . U('img/Uploader/userqrcode',['module'=>'Wap', '_ajax'=>1]);
        $url =get_server('IMG_SERVER', '/Uploader/userqrcode', ['module'=>'Wap', '_ajax'=>1], 1);
        $ch = curl_init();
        $header = array("Accept-Charset"=>"UTF-8");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_COOKIE,"wp_WAP=".cookie('WAP'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $store_info);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); //强制协议为1.0
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect: ')); //头部要送出'Expect: '
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
        $output = curl_exec($ch);
        curl_close($ch);
        $data=json_decode($output, true);
        if(isset($data['data']['filepath'])){
            return $data['data']['filepath'];
        }
        return false;
    }

    protected function get_share_qr($user_id, $qr_info = array()){
        $time = time();
        $user_model = new Model\UsersModel();
        if ($qr_info && $qr_info['id'] > 0) {       //存在记录
            //$user_model->user_qr_save($QR_info['id'], $QR_info['img_address']);
            $qr_md5 = $qr_info['md5'];
            $file = $qr_info['img_address'];
            $qr_id = $qr_info['id'];
        } else {                                    //新添加
            $qr_md5 = md5($this->get_rand_str(22,2) . $time);
            $file = $this->get_save_path(2) . $time . '_' . $qr_md5 . '.png';//生成文件名
            $qr_id = $user_model->user_qr_add($user_id, $qr_md5, $file);//保存到数据库
        }
        $info = $this->get_wxuser_qr($qr_id);//读取QR信息
        if ($info) {
            @mkdir(dirname($file));
            if ($this->is_weixin) {
                $this->save_net_file($file,'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $info['ticket']);
            } else {
                $this->create_qr($this->cur_domain . U('User/register') . '?rec=' . AesEnCrypt($user_id),$file);
            }
            $user_model->user_qr_save($qr_id, $info);
            $info['md5'] = $qr_md5;
            $info['user_id'] = $user_id;
            $info['id'] = $qr_id;
            $info['img_address'] = $file;
            $info['add_time'] = $time;
            $result = result_ajax(0,'',$info);
        } else {
            $result = result_ajax(1,'没有读取到二维码信息');
        }
        //$info = $info//array_merge($info, $user_id);
        return $result;
    }

    /**
     * 将二进制图片字符串保存为图片
     * @param $str
     * @return bool|string
     */
    protected function save_str_img($str){
        $str = explode('base64,', $str, 2);
        if (count($str) == 2) {
            $type = str_replace(array('data:',';'),'',$str[0]);
            $save_name =  $this->get_save_name('jpg');
            $save_path = $this->get_save_path(3);
            $file = $save_path .  $save_name;
            //$file_size = file_put_contents(C('IMG_SERVER').'/'. $file, base64_decode($str[1]));
            $file_size = file_put_contents(C('UPLOAD_SERVER').'/'. $file, base64_decode($str[1]));
            $data = array(
                'cat_id'    => 3,
                'user_id'   =>  $this->user_id,
                'upname'   =>  '',
                'uptype'   =>  $type,
                'upsize'   =>  $file_size,
                'upkey'   =>   'file',
                'md5_key'   =>  md5($file_size),
                'sha1_key'   =>  sha1($file_size),
                'savename'   =>  $save_name,
                'savepath'   =>  $save_path,
                'user_ip'   =>  get_client_ip(),
                'add_time'   =>  time(),
            );
            if (M('ImgGallery')->data($data)->add()) {
                return $file;
            } else {
                //Log::record('文件上传失败！' . print_r($img,1) . print_r($data,1));
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 取上传图片的目录
     * @param $id
     * @param string $field
     * @return mixed
     */
    protected function get_uploadimg_dir($id, $field = 'img_dir'){
        return M('ImgCat')->where('cat_id=' . $id)->getfield($field);
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
        $this->back_url = $back_url;
    }

    /**
     * 获取cookie登录数据
     * @param string $auth_key
     * @return bool|array
     */
    protected function get_auth($auth_key = 'Auth'){
        $cookie = $_COOKIE[$auth_key];
        if ($cookie) {
            $cookie = AesDeCrypt($cookie);
            if (is_array($cookie)) {
                return $cookie;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * 验证用户登录跳转
     * @param string $url
     * @param int $sess_time
     * @return bool
     */
    protected function void_user( $url = '', $sess_time = 3600){
        $time = time();
        if ($this->is_login()){
            if ($time - $this->login_info['add_time'] < $sess_time) {
                session("login_info_{$this->store_token}.add_time",$time);
                return true;
            } else {
                session('login_info_'.$this->store_token,null);
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
                    C('store_token_name')   => $this->store_token
                ], 1);
            if ($this->ajax) {
                /*$result['message'] = '请先登录！';
                $result['error']   = 2;
                $result['contents']['url'] = $url;*/
                $result = result_ajax(301,'请先登录！', ['url'=>$url]);
                $this->ajaxReturn($result);
            } else {
                header('Location: ' . $url);
                exit;
            }
        }
    }

    /**
     * 验证用户是否登录
     * @param $login_sess array 信息
     * @return bool
     */
    protected function is_login($login_sess = array()){
        $login_sess = !empty($login_sess) ? $login_sess : $this->login_info;
        if ($login_sess and $login_sess['user_id'] > 0) {
            $user_info = M('users')
                ->alias('u')
                //->join('LEFT JOIN __USERS__ u1 ON u.parent_id=u1.user_id')
                 ->join('LEFT JOIN __USER_RELEVANCE__ u1 ON u.user_id=u1.user_id')
                ->join('LEFT JOIN __USER_RANK__ ur ON u1.rank_id=ur.rank_id')
                ->field('u.*,if(u.user_name = "",u.nickname,u.user_name) as parent_name,ur.rank_name, ur.rank_id')
                ->where('u.user_id=' . intval($login_sess['user_id']))
                ->find();
            if ($user_info) {
                $user_model = new Model\UsersModel();
                $stock_model = new Model\StockModel();
                $login_sess = $user_info;
                if($stock_model->is_store($user_info['user_id'])) $login_sess['is_store'] = true;
                /*$login_sess['parent_name'] = $user_info['parent_name'];
                $login_sess['rank_name'] = $user_info['rank_name'];
                $login_sess['user_money'] = $user_info['user_money'];
                $login_sess['user_rank'] = $user_info['user_rank'];
                $login_sess['rank_id'] = $user_info['rank_id'];*/
                if ($user_info['rank_name'] == '') $login_sess['rank_name'] = '员工';
                $login_sess['pay_points'] = intval($user_info['pay_points']);
                $login_sess['user_avatar'] = $user_model->img_url($user_info['user_avatar']);
                $this->user_info = $this->login_info = array_merge($login_sess, $this->login_info);
                $this->user_id = $user_info['user_id'];
                return true;
            } else {
                session('login_info_'.$this->store_token,null);
                return false;
            }
        } else {
            session('login_info'.$this->store_token,null);
            return false;
        }
    }

    /**
     * 查询物流信息
     * @param $com
     * @param $nu
     * @return boolean
     */
    protected function get_shipping_info($com, $nu){
        //http://api.kuaidi100.com/api?id=40fa1d2921ea0510&com=zhongtong&nu=374967701376&show=json&muti=1&order=desc
        //$key = $this->_CFG['kuidi100_key'] = '40fa1d2921ea0510';
        //$url = 'http://api.kuaidi100.com/api?id=%s&com=%s&nu=%s';
        if (empty($com) or empty($nu)) return false;
        $url = 'http://www.kuaidi100.com/query?type=%s&postid=%s&id=1&valicode=&temp='.$this->get_rand_str(4,1).'&sessionid=&tmp='.$this->get_rand_str(4,1);
        $url = sprintf($url,$com,$nu);
        return $this->obj2array(json_decode($this->curlGet($url)));
        /*if ($key != '') {
            $url = sprintf($url,$com,$nu);
            return $this->obj2array(json_decode($this->curlGet($url)));
        } else {
            return false;
        }*/
    }

    /**
     * 设置隐藏字符
     * $type 参数  1：用户名   2：邮箱    3：IP地址   4：手机号码
     * @param string    $str
     * @param int       $type
     * @return string
     */
    protected function set_hidden_str($str, $type = 1){
        $ss = '';
        $x = '';
        $y = '';
        switch($type){
            default:
            case 1:
                $num = strlen($str) / 2;
                for($i = 1;$i <= $num;$i++){
                    $x .= '*';
                }
                $ss = substr($str, 0, $num) . $x;
                break;
            case 2:
                $email = explode('@',$str);
                $num = strlen($email[0]) / 2;
                for($i = 1;$i <= $num;$i++){
                    $x .= '*';
                }
                $ext = explode('.', $email[1]);
                for ($i = 1;$i <= strlen($ext[0]);$i++){
                    $y .= '*';
                }
                $ss = substr($str, 0, $num) . $x . '@' . $y . '.' . $ext[1];
                break;
            case 3:
                if ($str){
                    $ip = explode('.', $str);
                    for($i = 1;$i <= strlen($ip[3]);$i++){
                        $x .= '*';
                    }
                    $ss = $ip[0] . '.' . $ip[1] . '.' . $ip[2] . '.' . $x;
                }
                break;
            case 4:
                if (strlen($str) != ''){
                    $ss = substr($str,0,3) . '****' . substr($str,7,4);
                }
                break;
            case 5:
                $len = mb_strlen($str);
                if ($len == 12) {
                    $xin = '***';
                } elseif ($len == 9) {
                    $xin = '**';
                } else {
                    $xin = '*';
                }
                $ss = $xin . str_replace(mb_substr($str, 0 , -1, 'utf-8'),'',$str);
        }
        return $ss;
    }

    /**
     * 格式化email
     * @param $mail
     * @return string
     */
    protected function format_mail($mail) {
        $email_arr = explode("@", $mail);
        $mail = $email_arr[0] . "@***";
        return $mail;
    }

    protected function get_session($str = ''){
        if ($str == '') $str = $this->ssid_name;
        $sess_id = trim(session($str));
        if ($sess_id == '') {
            $sess_id = trim(cookie($str));
            if ($sess_id == '') {
                $sess_id = md5($this->get_rand_str(16));
                cookie($str, $sess_id);
            }
            session($str, $sess_id);
        } else {
            if (!cookie($str)) {
                cookie($str, $sess_id);
            }
        }
        return $sess_id;
    }


    /**
     * 添加用户
     * @param array $classData
     * @param int $uid
     * @return array
     */
    protected function adddUserInfo($classData,$uid = 0){
        $this->logger(print_r($classData,1) . "\n" . $uid . "===========");
        $db = M('users');
        $data['store_id'] = 0;
        $file = $this->get_save_path(3) . $this->get_save_name('jpg');
        $this->save_net_file($file, $classData['headimgurl']);
        //$file = $classData['headimgurl'];
        //$data['inviter'] = null;
        //$data['subscribe'] = $classData['subscribe'];
        //$data['groupid'] = $classData['groupid'];
        //$data['g_id'] = $json->groupid? $json->groupid: 0;
        $data['openid']         = trim($classData['openid']);
        $data['nickname']       = $classData['nickname'];
        $data['user_name']      = trim($classData['openid']);
        $data['sex']            = $classData['sex'];
        $data['user_areainfo']  = $classData['city'].' '.$classData['province'] == '' ? '' : $classData['city'].' '.$classData['province'];
        $data['user_avatar']    = $file;
        $data['reg_time']       = (isset($classData['subscribe_time']) and !empty($classData['subscribe_time'])) ? $classData['subscribe_time'] : time();
        $data['alias']          = '';
        $data['msn']            = '';
        $data['qq']             = '';
        $data['office_phone']   = '';
        $data['home_phone']     = '';
        $data['mobile_phone']   = '';
        $data['credit_line']    = 0;
        $data['user_rank']      = 1;
        $item = $db->cache('user_id_'.$data['openid'])->field('openid,store_id')->where(array('store_id' => $data['store_id'], 'openid' => $data['openid']))->find();
        if (empty($item)) {
            if ($uid) {
                $data['parent_id'] = $uid;
            }
            $this->logger('添加用户插入数据：' . print_r($data,1) . "\n" . $uid . "===========");
            $db->add($data);
            $id = $db->getLastInsID();
            $img_data[] = array(
                'user_id'   =>  $id,
                'upname'    =>  '自动上传头像',
                'uptype'    =>  'weixin',
                'upsize'    =>  filesize($file),
                'upkey'     =>  'upload',
                'md5'       =>  md5($classData['headimgurl']),
                'sha1'      =>  sha1($classData['headimgurl']),
                'savename'  =>  str_replace(dirname($file) . '/','', $file),
                'savepath'  =>  dirname($file) . '/',
            );
            $this->save_upload_img(3,$img_data);
            return $id;
        } else {
            $where = array('store_id' => $data['store_id'], 'openid' => $data['openid']);
            $db->where($where)->save($data);
            return $db->where($where)->getfield('user_id');
        }
    }

    /**
     * 设置缓存
     */
    protected function no_cache(){
        header("Cache-control:no-cache,no-store,must-revalidate");
        header("Pragma:no-cache");
        header("Expires:0");
    }
    /**
     * 判断是否微信浏览器登录
     * @return bool
     */
    protected function is_weixin(){
//        return false;
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
            return true;
        }
        return false;
    }


    /**
     * 对数进行分级整理
     * @param $arr                  $需要要整理的数组
     * @param $id_name              $ID字段名
     * @param $parent_id_name       $parent_id字段名
     * @param string $add_list_name
     * @return array|bool
     */
    protected function get_array_tree($arr, $id_name = 'id', $parent_id_name = 'parent_id',$add_list_name = 'children_list',$sort='asc'){
        if (!is_array($arr)) return false;
        if($sort === 'desc'){
            sort($arr);
        }else{
            rsort($arr);
        }
        foreach ($arr as $k => $v){
            $children = array();
            if ($v){
                foreach ($arr as $kk => $vv){
                    if ($vv[$parent_id_name] == $v[$id_name]){
                        $children[] = $vv;
                        unset($arr[$kk]);
                    }
                }
                if ($children and isset($arr[$k])){
                    $arr[$k][$add_list_name] = $children;
                    unset($children);
                }
            }
        }
        return $arr;
    }

    protected function error($message='',$jumpUrl='',$ajax=false){
        //出现错误时随机推荐商品
        $goods_list = $this->rand_goods(2,1800);
        $this->assign('goods_list',$goods_list);
        $this->assign('user_head',['title'=>'错误信息']);
        parent::error($message, $jumpUrl, $ajax);
    }

    protected function success($message='',$jumpUrl='',$ajax=false){
        //$this->assign('','');
        parent::success($message, $jumpUrl, $ajax);
    }

    /***
     * 随机返回数组中的值
     * @param $arr
     * @param int $num
     * @return array
     */
    protected function array_random($arr, $num = 1) {
        shuffle($arr);

        $r = array();
        for ($i = 0; $i < $num; $i++) {
            $r[] = $arr[$i];
        }
        return  $r;
    }

    /**
     * 随机获取商品
     * @param int $num
     * @param int $save_time
     * @return mixed
     */
    protected function rand_goods($num=4,$save_time=1800){
        $dealer = $this->get_dealer_status();
        if($list = S('rand_goods_'.$dealer)) return $list;
        $goods_model = new Model\GoodsModel();
        $map = array();
        $map['is_delete'] = array('eq',0);
        $map['is_on_sale'] = array('eq',1);
        $map['is_check'] = array('eq',0);
        $map['is_dealer'] = array('eq',$dealer);
        $gids = $goods_model->field('goods_id')->where($map)->select();
        $gids = array_map('array_shift',$gids);
        $rand = $this->array_random($gids,$num);
        $map = array();
        $map['g.goods_id'] = array('in',$rand);
        $list = $goods_model->alias('g')
            ->field('g.*,si.store_label,b.brand_name,b.brand_logo,c.cat_name')
            ->where($map)
            ->join('LEFT JOIN __STORE_IMAGES__ si ON g.store_id=si.store_id')
            ->join('LEFT JOIN __STORE_CREDIT__ sc ON g.store_id=sc.store_id')
            ->join('LEFT JOIN __BRAND__ b ON g.brand_id=b.brand_id')
            ->join('LEFT JOIN __CATEGORY__ c ON g.cat_id=c.cat_id')
            ->select();
        foreach ($list as &$v) {
            $v['goods_thumb']   = $goods_model->img_url($v['goods_thumb']);
            $v['url']           = U('Goods/index') . '?id=' . $v['goods_id'];
            $v['brand_logo']    = $goods_model->img_url($v['brand_logo']);
        }
        S('rand_goods_'.$dealer,$list,$save_time);
        return $list;

    }

    protected function get_debug() {
        $debug = I('request.debug');
        $this->debug = $debug != 'keheng' ? '' : 'keheng';
        $this->assign('debug', $this->debug);
    }
    /**
     * 调试函数
     * @param $obj
     * @param int $d
     */
    protected function debug($obj,$d = 0) {
        if ((isset($_GET['debug']) and $_GET['debug'] == 'keheng') or $d == 1) {
            echo '<pre>';
            if (is_array($obj)) {
                print_r($obj);
            } else {
                echo $obj;
            }
            echo '</pre>';
        }
    }

    /**
     * 多维数组查找
     * @param array $arr
     * @param string $column
     * @param string $findstring
     * @return bool
     */
    protected function array_search_multi($arr,$column,$findstring){
        foreach ($arr as $value){
            if($value[$column] == $findstring)
                return $value;
        }
        return false;
    }

    /**
     * 多维数组中查找对应的值的数组并返回
     * @param array             $arr            查找的数组
     * @param string|array      $key_name       查找的KEY
     * @param string|integer    $value          查找的值
     * @param string            $list_name      下级列表名称
     * @return bool
     */
    protected function get_array_search($arr, $key_name, $value, $list_name = 'list'){
        if (!is_array($arr)){
            return false;
        } else {
            foreach ($arr as $k => $v){
                $condition = '';
                if (is_array($key_name)) {
                    $i = 0;
                    foreach ($key_name as $kk => $vv) {
                        if ($i > 0) {
                            if ($value == 'and' or $value == '&&') {
                                $condition = ($condition and ($v[$kk] == $vv));
                            } elseif ($value == 'or' or $value == '||') {
                                $condition = ($condition or ($v[$kk] == $vv));
                            }
                        } else {
                            $condition = ($v[$kk] == $vv);
                        }
                        $i++;
                    }
                } else {
                    $condition = ($v[$key_name] == $value);
                }
                if ($condition){
                    return $arr[$k];
                }else{
                    if (is_array($v[$list_name]) and $v[$list_name]){
                        $result = $this->get_array_search($v[$list_name],$key_name,$value,$list_name);
                        if ($result){
                            return $result;
                        }
                    }
                }
            }
        }
    }

    /**
     * 搜索数组内所有某个键值
     * @param array     $arr                搜索的数组
     * @param string    $key                需要搜索的键名
     * @param string    $add_list_name      子分类的键名
     * @return array|bool
     */
    protected function get_array_value($arr, $key = 'id', $add_list_name = 'children_list') {
        if (!is_array($arr)){
            return false;
        } else {
            $result = array();
            if ((isset($arr[$key]) and isset($arr[$add_list_name])) and is_array($arr[$add_list_name])) {
                $result[] = $arr[$key];
                foreach($arr[$add_list_name] as $k => $v) {
                    $result[] = $v[$key];
                    if (isset($v[$add_list_name]) and !empty($v[$add_list_name])) {
                        $result = array_merge($result, $this->get_array_value($v[$add_list_name],$key,$add_list_name));
                    }
                }
            } else {
                foreach($arr as $k => $v) {
                    if (!isset($v[$key])) continue;
                    $result[] = $v[$key];
                    if (isset($v[$add_list_name]) and !empty($v[$add_list_name])) {
                        $result = array_merge($result,$this->get_array_value($v[$add_list_name],$key,$add_list_name));
                    }
                }
            }
            return $result;
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
     * 按指定键盘排序
     * @param $array
     * @param $key
     * @param string $order
     * @return array
     */
    protected function get_array_sort($array, $key, $order = "asc"){//asc是升序 desc是降序
        $arr_nums = $arr = array();
        foreach($array as $k => $v){
            $arr_nums[$k] = $v[$key];
        }
        if($order == 'asc'){
            asort($arr_nums);
        } else {
            arsort($arr_nums);
        }
        foreach($arr_nums as $k => $v){
            $arr[$k] = $array[$k];
        }
        return $arr;
    }

    /**
     * 新建一个订单号
     * @param string $star
     * @return string
     */
    protected function create_order_sn($star = ''){
        if ($star == '') $star = date('Ym',time());
        return $star . $this->get_rand_str(8,1);
    }


    /**
     * 新建一个支付序列号
     * @param string $star
     * @return string
     */
    protected function create_cash_sn($star = '') {
        if ($star == '') $star = date('Ym',time());
        return $star . $this->get_rand_str(8,1);
    }

    protected static function EnCrypt($value, $key = ''){
        if ($key == '') $key = self::$crypt_key;
        $td = mcrypt_module_open('tripledes', '', 'ecb', '');
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_DEV_RANDOM);
        $key = substr($key, 0, mcrypt_enc_get_key_size($td));
        mcrypt_generic_init($td, $key, $iv);
        $ret = base64_encode(mcrypt_generic($td, $value));
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $ret;
    }
    protected static function DeCrypt($value, $key = ''){
        if ($key == '') $key = self::$crypt_key;
        $value = str_replace(' ','+', $value);
        $td = mcrypt_module_open('tripledes', '', 'ecb', '');
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_DEV_RANDOM);
        $key = substr($key, 0, mcrypt_enc_get_key_size($td));
        $key = substr($key, 0, mcrypt_enc_get_key_size($td));
        mcrypt_generic_init($td, $key, $iv);
        $ret = trim(mdecrypt_generic($td, base64_decode($value)));
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $ret;
        //$str = explode('<br />',$ret);
        return trim($str[0]);
    }

    /**
     * 加密时的字节填充，保持和java 一致
     * @param $text
     * @param $blocksize
     * @return string
     */
    private function pkcs5_pad ($text, $blocksize) {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    /**
     * //加密
     * @param $input
     * @param $key
     * @return string
     */
    protected function EnCrypt1($input,$key = '')  {
        if ($key == '') $key = $this->crypt_key;
        $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
        $input = $this->pkcs5_pad($input, $size);
        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
        $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $data = mcrypt_generic($td, $input);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $data = base64_encode($data);
        $data = str_replace('+','~',$data);
        return $data;
    }

    /**
     * //解密
     * @param $str
     * @param $key
     * @return string
     */
    protected function DeCrypt1($str,$key = '')  {
        if ($key == '') $key = $this->crypt_key;
        $str = str_replace('~','+',$str);
        $decrypted = mcrypt_decrypt(
            MCRYPT_RIJNDAEL_128,
            $key,
            base64_decode($str),
            MCRYPT_MODE_ECB
        );
        $dec_s = strlen($decrypted);
        $padding = ord($decrypted[$dec_s - 1]);
        $decrypted = substr($decrypted, 0, -$padding);
        return $decrypted;
    }


    protected function logger($log_content) {
        /*if (isset($_SERVER['HTTP_APPNAME'])) {   //SAE
            sae_set_display_errors(false);
            sae_debug($log_content);
            sae_set_display_errors(true);
        } else */if (APP_DEBUG) { //LOCAL
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
     * 验证字符串是否为数字,字母,中文和下划线构成
     * @param $str string
     * @return bool
     */
    protected function is_check_string($str){
        if(preg_match('/^[\x{4e00}-\x{9fa5}\w_]+$/u',$str)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 是否为一个合法的email
     * @param $email string
     * @return boolean
     */
    protected function is_email($email){
        if (preg_match("/^([a-z0-9+_-]+)(.[a-z0-9+_-]+)*@([a-z0-9-]+.)+[a-z]{2,6}$/ix",$email)) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * 是否为一个合法的url
     * @param string $url
     * @return boolean
     */
    protected function is_url($url){
        if (filter_var ($url, FILTER_VALIDATE_URL )) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * 是否为一个合法的ip地址
     * @param string $ip
     * @return boolean
     */
    protected function is_ip($ip){
        if (ip2long($ip)) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * 是否为整数
     * @param int $number
     * @return boolean
     */
    protected function is_number($number){
        if(preg_match('/^[-\+]?\d+$/',$number)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 是否为正整数
     * @param int $number
     * @return boolean
     */
    protected function is_positive_number($number){
        if(ctype_digit ($number)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 是否为小数
     * @param float $number
     * @return boolean
     */
    protected function is_decimal($number){
        if(preg_match('/^[-\+]?\d+(\.\d+)?$/',$number)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 是否为正小数
     * @param float $number
     * @return boolean
     */
    protected function is_positive_decimal($number){
        if(preg_match('/^\d+(\.\d+)?$/',$number)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 是否为英文
     * @param string $str
     * @return boolean
     */
    protected function is_english($str){
        if(ctype_alpha($str))
            return true;
        else
            return false;
    }
    /**
     * 是否为中文
     * @param string $str
     * @return boolean
     */
    protected function is_chinese($str){
        if(preg_match('/^[\x{4e00}-\x{9fa5}]+$/u',$str))
            return true;
        else
            return false;
    }
    /**
     * 判断是否为图片
     * @param string $file  图片文件路径
     * @return boolean
     */
    protected function is_image($file){
        if(file_exists($file)&&getimagesize($file===false)){
            return false;
        }else{
            return true;
        }
    }
    /**
     * 是否为合法的身份证(支持15位和18位)
     * @param string $card
     * @return boolean
     */
    protected function is_card($card){
        if(preg_match('/^[1-9]\d{7}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}$/',$card)||preg_match('/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{4}$/',$card))
            return true;
        else
            return false;
    }
    /**
     * 验证日期格式是否正确
     * @param string $date
     * @param string $format
     * @return boolean
     */
    protected function is_date($date,$format='Y-m-d'){
        $t=date_parse_from_format($format,$date);
        if(empty($t['errors'])){
            return true;
        }else{
            return false;
        }
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
     * 设置商品类型
     * @param $v
     */
    public function set_dealer_status($v){
        if(in_array($v,array(GOODS_DEFAULT,GOODS_STOCK,GOODS_INTEGRAL))){
            session('is_dealer',intval($v));
            if($v==GOODS_INTEGRAL){
                session('flow_type',CART_EXCHANGE_GOODS);
            }else{
                session('flow_type',CART_GENERAL_GOODS);
            }

        }else{
            session('is_dealer',GOODS_DEFAULT);
        }
        //url后缀
        $this->assign('is_dealer',$v);
        $suf = $this->dealer_suf();
        $this->assign('dealer_suf',$suf[$v]);



    }
    /*
     * 获取商品类型
     */
    public function get_dealer_status(){
        $is_dealer = session('is_dealer');
        $this->assign('is_dealer',$is_dealer);
        //url后缀
        $suf = $this->dealer_suf();
        $this->assign('dealer_suf',$suf[$is_dealer]);
        return $is_dealer;
    }
    /**
     * 获取后缀
     */
    public function dealer_suf(){
        return array(
            0=>'normal',
            1=>'stock',
            2=>'gift'
        );
    }

    /**
     * 生成验证码
     */
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
     * 视图url参数过滤
     * @return mixed
     */
    public function I_filter(){
        $request = $_REQUEST;
        if(!$request) return ;
        foreach($request as $k=>&$v){
           if(is_string($v)){
               $v = I($k,'',array('stripslashes','htmlspecialchars','trim'));
           }
        }
        $this->assign('II',$request);
        return $request;
    }
//
//    //获取用户地区
//    protected function get_region($user_id=0){
//        if(!$user_id) $user_id = $this->user_id;
//        //province
//        //city
//        //district
//    }

    //判断是否在微信里面访问
    private function is_store_wx(){
        if(stripos($_SERVER['REQUEST_URI'], 'article') || stripos($_SERVER['REQUEST_URI'], 'login')) return true;
        if($this->is_weixin){
            $store_info = session('store_info');
            //设置微信支付是否开启
            $this->wx_pay = isset($this->store_wx['is_open']) ? true : false;
            $this->wx_store_id = !isset($store_info['store_id']) ?  null : $store_info['store_id'];

            if(stripos($_SERVER['REQUEST_URI'], 'store') && stripos($_SERVER['REQUEST_URI'], 'init') && !$store_info){
                return true;
            }
            //判断首次进入店铺
            if(!stripos($_SERVER['REQUEST_URI'], 'done') && !stripos($_SERVER['PHP_SELF'], 'store') && !$store_info){
                $this->error('请从店铺首页开始访问！');
            }else{
                //允许进入的控制器
                $host_data = array('store', 'goods', 'cart', 'order', 'user', 'checkout', 'rebate', 'wallet', 'apply', 'profile', 'article', 'share', 'done', 'team', 'rebate', 'wexin', 'api', 'profile', 'member', 'select', 'payment', 'article');
                foreach($host_data as $v){
                    if(stripos($_SERVER['REQUEST_URI'],$v)){
                        return true;
                    }
                }
                $url = U('Store/info').'?id='.session('store_info.store_id');
                header('location:'.$url);
            }

        }
        return true;
    }

}
 
