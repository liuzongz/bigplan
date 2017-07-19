<?php

/**
 * 即牛 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: BaseModel.class.php 17156 2016-01-05 10:00:47Z keheng $
*/
namespace Wap\Model;
use Think\Model\RelationModel;
use Wap\Plugins;
class BaseModel extends RelationModel {
    private $expira = 1200;
    protected $sess = '';
    protected $_LANG = array();
    protected $_CFG = array();
    public $user_info = array();
    private $uid = 0;
    protected $store_token = 0;
    protected $login_info = array();
    function _initialize(){
        $this->_CFG = $this->LoadConfig();
        //$this->_LANG = $GLOBALS['_LANG'] = $this->LoadLanguage('common');
        //$this->sess = $this->getSessionIDorUserID();
        $this->store_token = session('store_token');
        $this->login_info = session('login_info_'.$this->store_token);
    }

    public function setUid($id){
        $this->uid = intval($id);
    }

    /**
     * 替换数组列表中的图片
     * @param $type
     * @param $list
     * @param $index string 是否索引
     * @return array
     */
    public function replace_goods_img ($type, $list, $index = ''){
        if (!is_array($list)) return $list;
        $res = [];
        foreach($list as $k => &$v) {
            switch ($type) {
                case 'goods':
                    $v['goods_thumb'] = $this->img_url($v['goods_thumb']);
                    $v['goods_img'] = $this->img_url($v['goods_img']);
                    $v['original_img'] = $this->img_url($v['original_img']);
                    break;
            }
            if ($index) $res[$v[$index]] = $v;
        }
        if ($index) {
            return $res;
        } else {
            return $list;
        }
    }

    /**
     * 用户冻结余额变动记录
     * @param $id
     * @param $money
     * @param $stage
     * @param $desc
     * @return bool|string
     */
    public function set_user_frozen($id, $money, $stage, $desc){
        $base_money = M('users')->where('user_id="' . intval($id['user_id']) . '"');
        if ($money >= 0) {
            $base_money->setInc('frozen_money', $money);
        } else {
            $base_money->setDec('frozen_money', abs($money));
        }
        record("修改用户余额功能：" . M('users')->getlastsql());
        return $this->add_cash_log([
            'store_id'      =>  intval($id['store_id']),
            'user_id'       =>  intval($id['user_id']),
            'stage'         =>  $stage,
            'price'         =>  $money,
            'type'          =>  1,
            'desc'          =>  $desc,
        ]);
    }

    /**
     * 用户余额变动 记录
     * @param $id
     * @param $money
     * @param $stage
     * @param $desc
     * @return bool|string
     */
    public function set_user_money($id, $money, $stage, $desc){
        $base_money = M('users')->where('user_id="' . intval($id['user_id']) . '"');
        if ($money >= 0) {
              $base_money->setInc('user_money', $money);
        } else {
            $base_money->setDec('user_money', abs($money));
        }
        return $this->add_cash_log([
            'store_id'      =>  intval($id['store_id']),
            'user_id'       =>  intval($id['user_id']),
            'stage'         =>  $stage,
            'price'         =>  $money,
            'type'          =>  0,
            'desc'          =>  $desc,
        ]);
    }

    /**
     * 用户红包记录更新
     * @param $id
     * @param $money
     * @param $stage
     * @param $desc
     * @return bool|string
     */
    public function set_user_bonus($id, $money, $stage, $desc){
        $base_money = M('users')->where('user_id="' . intval($id['user_id']) . '"');
        if ($money >= 0) {
            $base_money->setInc('user_bonus', $money);
        } else {
            $base_money->setDec('user_bonus', abs($money));
        }
        return $this->add_cash_log([
            'store_id'      =>  intval($id['store_id']),
            'user_id'       =>  intval($id['user_id']),
            'stage'         =>  $stage,
            'price'         =>  $money,
            'type'          =>  0,
            'desc'          =>  $desc,
        ]);
    }

    /**
     * 用户KP点变动记录
     * @param $id
     * @param $num
     * @param $desc
     * @return bool|string
     */
    public function set_user_kpnum($id, $num, $desc){
        $model = M('users')->where('user_id="' . intval($id['user_id']) . '"');
        if ($num >= 0) {
            $model->setInc('consume_total', $num);
        } else {
            $model->setDec('consume_total', abs($num));
        }
        return $this->add_cash_log([
            'store_id'      =>  intval($id['store_id']),
            'user_id'       =>  intval($id['user_id']),
            'stage'         =>  'kp_num',
            'price'         =>  $num,
            'type'          =>  0,
            'desc'          =>  $desc,
        ]);
    }

    /**
     * 店铺余额变动
     * @param $id
     * @param $money
     * @param $stage
     * @param $desc
     * @return bool|string
     */
    public function set_store_money($id, $money, $stage, $desc){
        $base_money = M('store')->where('store_id="' . intval($id['store_id']) . '"');
        if ($money >= 0) {
            $base_money->setInc('store_money', $money);
        } else {
            $base_money->setDec('store_money', abs($money));
        }
        return $this->add_cash_log([
            'store_id'      =>  intval($id['store_id']),
            'user_id'       =>  intval($id['user_id']),
            'stage'         =>  $stage,
            'price'         =>  $money,
            'type'          =>  0,
            'desc'          =>  $desc,
        ]);
    }


    /**
     * 店铺冻结金额变动
     * @param $id
     * @param $money
     * @param $stage
     * @param $desc
     * @return bool|string
     */
    public function set_store_frozen($id, $money, $stage, $desc){
        $base_money = M('store')->where('store_id="' . intval($id['store_id']) . '"');
        if ($money >= 0) {
            $base_money->setInc('frozen_money', $money);
        } else {
            $base_money->setDec('frozen_money', abs($money));
        }
        return $this->add_cash_log([
            'store_id'      =>  intval($id['store_id']),
            'user_id'       =>  intval($id['user_id']),
            'stage'         =>  $stage,
            'price'         =>  $money,
            'type'          =>  0,
            'desc'          =>  $desc,
        ]);
    }

    /**
     * 系统钱包操作
     * @param $money
     * @param $desc
     * @return bool|string
     */
    protected function base_money($money,$desc){
        $base_money = M('shop_config')->where('code="base_money"');
        if ($money >= 0) {
            $base_money->setInc('value', $money);
        } else {
            $base_money->setDec('value', abs($money));
        }
        return $this->add_cash_log([
            'store_id'      =>  0,
            'user_id'       =>  0,
            'stage'         =>  'base_money',
            'price'         =>  $money,
            'type'          =>  0,
            'desc'          =>  $desc,
        ]);
    }

    /**
     * 新建一个订单号
     * @param string $star
     * @return string
     */
    protected function create_order_sn($star = ''){
        if ($star == '') $star = date('Ym',time());
        return $star . get_rand_str(8,1);
    }


    /**
     * 新建一个支付序列号
     * @param string $star
     * @return string
     */
    protected function create_cash_sn($star = '') {
        if ($star == '') $star = date('Ym',time());
        return $star . get_rand_str(8,1);
    }


    /**
     * 获取现金操作日志
     * @param $user_id
     * @param int $pagesize
     * @param int $type
     * @return array
     */
    public function get_cash_log($user_id, $pagesize = 5,$type = 1) {
        $m = M("CashLog");
        $where = 'user_id=' . $user_id;
        $Page = new \Think\Page($m->where($where)->count(), $pagesize);
        $show = $Page->show();
        $res = $m
            ->where($where)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('addtime desc')
            ->select();
        foreach ($res as $k => $v) {
            $res[$k]['addtime'] = date('Y-m-d H:i:s', $v['addtime']);
            $res[$k]['stage_name'] = $this->get_cash_type($v['stage']);
        }
        return array('list'=>$res,'show'=>$show,'total'=>$Page->totalPages);
    }


    /**
     * 添加现金日志
     * @param $data array(
     *      obj_type    记录类型   0用户日志  1店铺日志
     *      obj_id      obj_type=0 ? obj_id=user_id : obj_id=store_id
     *      cash_sn     日志序列号
     *      stage       日志类型 枚举 system 系统自动 recharge 充值 cash 提现 order 订单 admin 管理员手动修改 income收入 hkhp汇客惠品转入
     *      price       变动金额    减少为负数
     *      type        金额类型    0表示可用金额 1表示冻结金额
     *      desc        描述
     * )
     * @return bool|string
     */
    public function add_cash_log($data) {
        $sn = $this->create_cash_sn();
        $add_data = [
            'store_id'  =>  $data['store_id'],
            'user_id'   =>  $data['user_id'],
            'cash_sn'   =>  $sn,
            //'user_name' =>  $user_info['user_name'],
            'stage'     =>  $data['stage'],
            'type'      =>  $data['type'],
            'price'     =>  $data['price'],
            'addtime'   =>  time(),
            'desc'      =>  $data['desc']
        ];
        $m = M('CashLog');
        if ($m->data($add_data)->add()) {
            return $sn;
        } else {
            return false;
        }
    }

    /**
     * 取金额变动类型
     * @param $str
     * @return string
     */
    public function get_cash_type($str) {
        switch ($str) {
            case YE_SYSTEM:     $result = '系统自动';break;
            case YE_RECHARGE:   $result = '充值';break;
            case YE_CASH:       $result = '提现';break;
            case YE_ORDER:      $result = '订单';break;
            case YE_ADMIN:      $result = '管理员修改';break;
            case YE_INCOME:     $result = '分销收入';break;
            case YE_KP:         $result = 'KP分层';break;
            case YE_DONGSHI:    $result = '董事收入';break;
            default:            $result = '其它';
        }
        return $result;
    }

    /**
     * 保留两位小数点
     * @param $float
     * @param int $f
     * @return string
     */
    protected function get_float($float, $f = 2) {
        return sprintf("%.2f",substr(sprintf("%.3f", $float), 0, -$f));
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
     * 是否微币购物车
     * @return bool
     */
    public function is_gift(){
//        $is_gift = session('flow_type');
//        if ($is_gift == CART_EXCHANGE_GOODS) {
//            return true;
//        } else {
//            return false;
//        }
        if(session('is_dealer')==2){
            return true;
        }else {
            return false;
        }
    }
    /**
     * 获取订单状态语句
     * @param $order_status
     * @param $pay_status
     * @param $shipping_status
     * @return string
     */
    protected function get_order_status_sql($order_status,$pay_status, $shipping_status){
        return ' order_status = ' . $order_status . ' and pay_status=' . $pay_status . ' and shipping_status=' . $shipping_status;
    }
    /**
     * 验证填写的验证码是否正确   验证码超时
     * @param int $id
     * @param string $obj
     * @param string $code
     * @return bool
     */
    public function check_verification($id, $obj, $code,$useful_life=3600){
        $s = M('VerificationCode');
        $info = $s->where('id=%d',$id)->find();
        $time = time();
        if (!empty($info)) {
            if ($time - $info['add_time'] > $useful_life) {
                //echo $this->_CFG['email_verify_overdue_time'];
                $s->where('id=%d', $id)->delete();
                return false;
            } else {
                if ($code != $info['code'] or $obj != $info['subobj']) {
                    return false;
                } else {
                    $s->where('id=%d',$id)->delete();
                    return true;
                }
            }
        } else {
            return false;
        }
    }
    /**
     * 写入验证数据,邮箱或手机发送验证码记录
     * @param string $subobj
     * @param string $code
     * @param int $stype  0：邮箱，1：手机 2：其它
     * @return string
     */
    public function set_verification($subobj, $code, $stype = 0){
        $data = array(
            'stype' => $stype,
            'code'  => $code,
            'subobj'    => $subobj,
            'add_time' => time()
        );
        $s = M('VerificationCode');
        $s->add($data);
        return $s->getLastInsID();
    }

    /**
     * 发送邮件
     * @param $email
     * @param $title
     * @param $content
     * @return bool
     */
    public function send_mail($email,$title,$content) {
        $mail = new \Vendor\Email\Email();
        $data['mailto']  = 	$email; //收件人
        $data['subject'] =	$title;    //邮件标题
        $data['body'] 	 =	$content;    //邮件正文内容
        if($mail->send($data)) { //->debug(true)
            return true;
        } else {
            return false;
        }
        //* 调试邮件处理类用法:
        //$mail->debug(true)->send($data);   //开启调试功能
    }
    /**
     * 系统邮件发送函数
     * @param string $to    接收邮件者邮箱
     * @param string $name  接收邮件者名称
     * @param string $subject 邮件主题
     * @param string $body    邮件内容
     * @param string $attachment 附件列表
     * @return boolean
     */
    function think_send_mail($to, $name, $subject = '', $body = '', $attachment = null){
        $config = C('THINK_EMAIL');
        vendor('PHPMailer.class#phpmailer'); //从PHPMailer目录导class.phpmailer.php类文件
        $mail             = new PHPMailer(); //PHPMailer对象
        $mail->CharSet    = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
        $mail->IsSMTP();  // 设定使用SMTP服务
        $mail->SMTPDebug  = 0;                     // 关闭SMTP调试功能
        // 1 = errors and messages
        // 2 = messages only
        $mail->SMTPAuth   = true;                  // 启用 SMTP 验证功能
        $mail->SMTPSecure = 'ssl';                 // 使用安全协议
        $mail->Host       = $config['SMTP_HOST'];  // SMTP 服务器
        $mail->Port       = $config['SMTP_PORT'];  // SMTP服务器的端口号
        $mail->Username   = $config['SMTP_USER'];  // SMTP服务器用户名
        $mail->Password   = $config['SMTP_PASS'];  // SMTP服务器密码
        $mail->SetFrom($config['FROM_EMAIL'], $config['FROM_NAME']);
        $replyEmail       = $config['REPLY_EMAIL']?$config['REPLY_EMAIL']:$config['FROM_EMAIL'];
        $replyName        = $config['REPLY_NAME']?$config['REPLY_NAME']:$config['FROM_NAME'];
        $mail->AddReplyTo($replyEmail, $replyName);
        $mail->Subject    = $subject;
        $mail->MsgHTML($body);
        $mail->AddAddress($to, $name);
        if(is_array($attachment)){ // 添加附件
            foreach ($attachment as $file){
                is_file($file) && $mail->AddAttachment($file);
            }
        }
        return $mail->Send() ? true : $mail->ErrorInfo;
    }


    /**
     * 获取系统配置信息
     * @param string $field
     * @param int $timeout
     * @return mixed
     */
    public function get_setting($field = 'shop_config', $timeout = 3600){
        $result = array();
        $res = S($field);
        if (!$res) {
            $res = M($field)->select();
            S($field, $res, $timeout);
        }
        foreach ($res as $val) {
            C($val['code'], $val['value']);
            $result[$val['code']] = $val['value'];
        }
        return $result;
    }

    /**
     * 格式化商品价格
     * @param $price
     * @param bool $change_price 商品价格
     * @return string
     */
    function price_format($price, $change_price = true) {
        if($price === '')  {
            $price=0;
        }

        if ($change_price && defined('MJJ_ADMIN') === false) {
            switch ($this->_CFG['price_format']) {
                case 0:
                    $price = number_format($price, 2, '.', '');
                    break;
                case 1: // 保留不为 0 的尾数
                    $price = preg_replace('/(.*)(\\.)([0-9]*?)0+$/', '\1\2\3', number_format($price, 2, '.', ''));

                    if (substr($price, -1) == '.') {
                        $price = substr($price, 0, -1);
                    }
                    break;
                case 2: // 不四舍五入，保留1位
                    $price = substr(number_format($price, 2, '.', ''), 0, -1);
                    break;
                case 3: // 直接取整
                    $price = intval($price);
                    break;
                case 4: // 四舍五入，保留 1 位
                    $price = number_format($price, 1, '.', '');
                    break;
                case 5: // 先四舍五入，不保留小数
                    $price = round($price);
                    break;
            }
        } else {
            $price = number_format($price, 2, '.', '');
        }

        return sprintf($this->_CFG['currency_format'], $price);
    }

    /**
     * 去掉图片前的/
     * @param $url
     * @return string
     */
    function img_url($url) {
        return img_url($url);
    }

    /**
     * 创建像这样的查询: "IN('a','b')";
     * @param array $item_list      列表数组或字符串
     * @param string $field_name    字段名称
     * @return string
     */
    function db_create_in($item_list, $field_name = '') {
        if (empty($item_list)) {
            return $field_name . " IN ('') ";
        } else {
            if (!is_array($item_list)) {
                $item_list = explode(',', $item_list);
            }
            $item_list = array_unique($item_list);
            $item_list_tmp = '';
            foreach ($item_list AS $item) {
                if ($item !== '') {
                    $item_list_tmp .= $item_list_tmp ? ",'$item'" : "'$item'";
                }
            }
            if (empty($item_list_tmp)) {
                return $field_name . " IN ('') ";
            } else {
                return $field_name . ' IN (' . $item_list_tmp . ') ';
            }
        }
    }

    public function mysort($arr, $sort_arr, $field = 'id') {
        $this->sort_arr = $sort_arr;
        $this->sort_field = $field;
        usort($arr, array($this, "compare"));
        return $arr;
    }

    public function compare($a, $b) {
        $arr2 = $this->sort_arr;
        return (array_search($a[$this->sort_field], $arr2) < array_search($b[$this->sort_field], $arr2)) ? -1 : 1;
    }

    /**
     * 验证是否已经登录
     * @return bool|mixed
     */
    function is_login(){
        $user_id        = session('user_id');
        $user           = session('user_name');
        $last_ip        = session('last_ip');
        $last_login     = session('last_login');
        $last_time      = session('last_time');
        $login_time     = session('login_time');
        $password       = session('password');
        $time = time();
        //echo $user_id . '=' . $user . '=' . $login_time .'=' . $password;
        if ($user == '' or $login_time == '' or $user_id <= 0 or $password == ''){
            return false;
        }elseif(($time - $login_time) > $this->expira){
            session('user_id',null);
            session('login_time',null);
            session('password',null);
            session('user_name',null);
            return false;
        }else{
            $m = M('Users');
            $p = $m->where('user_id=' . $user_id)->getfield('password');
            if($p != $password){
                session('user_id',null);
                session('login_time',null);
                session('password',null);
                session('user_name',null);
                return false;
            }else{
                session('login_time',$time);
                return $user_id;
            }
        }
    }

    /**
     * 获取区域信息
     * @param int $id
     * @return mixed
     */
    function get_region_info($id = 0){
        $m = M("region");
        $arr = $m->where('parent_id=' . $id)->select();
        return $arr;
    }

    /**
     * 获取区域名称
     * @param $id
     * @param string $field
     * @return mixed|string
     */
    function get_region_text($id,$field = 'region_name'){
        $fields = array('region_name','region_en','region_id','parent_id');
        if (in_array($field,$fields)){
            $m = M("region");
            $arr = $m->where('region_id=' . $id)->getfield($field);
        } else {
            $arr = '';
        }
        return $arr;
    }

    function get_best_time_list($id = 0){
        $m = M('besttime');
        if ($id == 0){
            $arr = $m->select();
        } else {
            $arr = $m->where('id=' . $id)->getfield('title');
        }
        return $arr;
    }

    function getSessionIDorUserID($alisa = ''){
        $user_id = session('user_id');
        $sess_id = session('SESS_ID');
        if ($user_id <= 0){
            $where = "session_id='$sess_id' ";
        } else {
            $where = "user_id='$user_id' ";
        }
        if ($alisa != '') {
            $where = ' ' . $alisa . '.' . $where;
        } else {
            $where = ' ' . $where;
        }
        return $where;
    }

    /*function get_user_info($user_id){
        if ( !$user_id ){
            return false;
        } else {
            $m = M('users')->where('user_id=' . $user_id)->find();
            return $m;
        }
    }*/

    /**
     * 格式化重量：小于1千克用克表示，否则用千克表示
     * @param   float   $weight     重量
     * @return  string  格式化后的重量
     */
    function formated_weight($weight)
    {
        $weight = round(floatval($weight), 3);
        if ($weight > 0)      {
            if ($weight < 1)        {
                /* 小于1千克，用克表示 */
                return intval($weight * 1000) . $GLOBALS['_LANG']['gram'];
            }  else  {
                /* 大于1千克，用千克表示 */
                return $weight . $GLOBALS['_LANG']['kilogram'];
            }
        } else {
            return 0;
        }
    }

    function LoadConfig(){
        //$this->SS_ID();
        $arr = array();
        $F_name = 'shop_config';
        $data = F($F_name);
        if ($data === false) {
            $res = D('shop_config')->where('parent_id > 0')->field('code, value')->select();
            foreach ($res AS $row) {
                $arr[$row['code']] = $row['value'];
            }

            if(!is_numeric($arr['thumb_width'])){
                $thumb_width = preg_split('/\||,| /',$arr['thumb_width']);
                $thumb_height = preg_split('/\||,| /',$arr['thumb_height']);
            }else{
                $thumb_width = $arr['thumb_width'];
                $thumb_height = $arr['thumb_height'];
            }

            /* 对数值型设置处理 */
            $arr['watermark_alpha'] = intval($arr['watermark_alpha']);
            $arr['market_price_rate'] = floatval($arr['market_price_rate']);
            $arr['integral_scale'] = floatval($arr['integral_scale']);
            $arr['cache_time'] = intval($arr['cache_time']);
            $arr['thumb_width'] = $thumb_width;
            $arr['thumb_height'] = $thumb_height;
            $arr['image_width'] = intval($arr['image_width']);
            $arr['image_height'] = intval($arr['image_height']);
            $arr['best_number'] = !empty($arr['best_number']) && intval($arr['best_number']) > 0 ? intval($arr['best_number']) : 3;
            $arr['new_number'] = !empty($arr['new_number']) && intval($arr['new_number']) > 0 ? intval($arr['new_number']) : 3;
            $arr['hot_number'] = !empty($arr['hot_number']) && intval($arr['hot_number']) > 0 ? intval($arr['hot_number']) : 3;
            $arr['promote_number'] = !empty($arr['promote_number']) && intval($arr['promote_number']) > 0 ? intval($arr['promote_number']) : 3;
            $arr['top_number'] = intval($arr['top_number']) > 0 ? intval($arr['top_number']) : 10;
            $arr['history_number'] = intval($arr['history_number']) > 0 ? intval($arr['history_number']) : 5;
            $arr['comments_number'] = intval($arr['comments_number']) > 0 ? intval($arr['comments_number']) : 5;
            $arr['article_number'] = intval($arr['article_number']) > 0 ? intval($arr['article_number']) : 5;
            $arr['page_size'] = intval($arr['page_size']) > 0 ? intval($arr['page_size']) : 10;
            $arr['bought_goods'] = intval($arr['bought_goods']);
            $arr['goods_name_length'] = intval($arr['goods_name_length']);
            $arr['top10_time'] = intval($arr['top10_time']);
            $arr['goods_gallery_number']    = intval($arr['goods_gallery_number']) ? intval($arr['goods_gallery_number']) : 5;
            $arr['no_picture'] = !empty($arr['no_picture']) ? str_replace('../', './', $arr['no_picture']) : 'images/no_picture.gif'; // 修改默认商品图片的路径
            $arr['qq'] = !empty($arr['qq']) ? $arr['qq'] : '';
            $arr['ww'] = !empty($arr['ww']) ? $arr['ww'] : '';
            $arr['default_storage'] = isset($arr['default_storage']) ? intval($arr['default_storage']) : 1;
            $arr['min_goods_amount'] = isset($arr['min_goods_amount']) ? floatval($arr['min_goods_amount']) : 0;
            $arr['one_step_buy'] = empty($arr['one_step_buy']) ? 0 : 1;
            $arr['invoice_type'] = empty($arr['invoice_type']) ? array('type' => array(), 'rate' => array()) : unserialize($arr['invoice_type']);
            $arr['invoice_content'] = empty($arr['invoice_content']) ? array() : explode("\n",$arr['invoice_content']);
            $arr['show_order_type'] = isset($arr['show_order_type']) ? $arr['show_order_type'] : 0;    // 显示方式默认为列表方式
            $arr['help_open'] = isset($arr['help_open']) ? $arr['help_open'] : 1;    // 显示方式默认为列表方式

            if (!isset($GLOBALS['_CFG']['ecs_version'])) {
                /* 如果没有版本号则默认为2.0.5 */
                $GLOBALS['_CFG']['ecs_version'] = 'v2.0.5';
            }

            //限定语言项
            $lang_array = array('zh_cn', 'zh_tw', 'en_us');
            if (empty($arr['lang']) || !in_array($arr['lang'], $lang_array)) {
                $arr['lang'] = 'zh_cn'; // 默认语言为简体中文
            }

            if (empty($arr['integrate_code'])) {
                $arr['integrate_code'] = 'ecshop'; // 默认的会员整合插件为 ecshop
            }
            F($F_name, $arr);
        } else {
            $arr = $data;
        }
        return $arr;
    }

    function LoadLanguage($file,$lang = 'zh_cn'){
        $_LANG = array();
        require(APP_PATH . 'Wap/Plugins/languages/' . $lang . '/' . $file . '.php');
        return $_LANG;
    }

    public function SS_ID(){
        $ssid = session('SESS_ID');
        if (!$ssid){
            $ssid = cookie('SESS_ID');
            if (!$ssid){
                $ssid = md5(uniqid(mt_rand(), true));
            }
            cookie('SESS_ID', $ssid, $this->expira_cookie);
            session('SESS_ID', $ssid);
        }
    }

    /**
     *  生成一个用户自定义时区日期的GMT时间戳
     *
     * @access  public
     * @param   int     $hour
     * @param   int     $minute
     * @param   int     $second
     * @param   int     $month
     * @param   int     $day
     * @param   int     $year
     *
     * @return string
     */
    function local_mktime($hour = NULL , $minute= NULL, $second = NULL,  $month = NULL,  $day = NULL,  $year = NULL) {
        //$timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : $GLOBALS['_CFG']['timezone'];
        $timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone']
            : (isset($GLOBALS['_CFG']['timezone'])?$GLOBALS['_CFG']['timezone']:NULL);
        /**
         * $time = mktime($hour, $minute, $second, $month, $day, $year) - date('Z') + (date('Z') - $timezone * 3600)
         * 先用mktime生成时间戳，再减去date('Z')转换为GMT时间，然后修正为用户自定义时间。以下是化简后结果
         **/
        $time = mktime($hour, $minute, $second, $month, $day, $year) - $timezone * 3600;
        return $time;
    }

    /**
     * 调用使用UCenter插件时的函数
     *
     * @param   string  $func
     * @param   array   $params
     *
     * @return  mixed
     */
    function user_uc_call($func, $params = null)    {
        if (isset($GLOBALS['_CFG']['integrate_code']) && $GLOBALS['_CFG']['integrate_code'] == 'ucenter')  {
            restore_error_handler();
            if (!function_exists($func))
            {
                include_once(ROOT_PATH . 'includes/lib_uc.php');
            }

            $res = call_user_func_array($func, $params);

            set_error_handler('exception_handler');

            return $res;
        }
        else
        {
            return false;
        }

    }


    /**
     * 处理序列化的支付、配送的配置参数
     * 返回一个以name为索引的数组
     * @access  public
     * @param string $cfg
     * @return array|bool
     */
    function unserialize_config($cfg) {
        if (is_string($cfg) && ($arr = unserialize($cfg)) !== false) {
            $config = array();
            foreach ($arr AS $val)  {
                $config[$val['name']] = $val['value'];
            }
            return $config;
        } else {
            return false;
        }
    }

    /**
     * 转换字符串编码为 UTF8
     * $text string 要转换的字符串
     * return string
     */
    function conv2utf8($text){
        return mb_convert_encoding($text,'UTF-8','ASCII,GB2312,GB18030,GBK,UTF-8');
    }

    /**
     * 时间转化为字符
     * @param $second
     * @return array
     */
    function time2string($second){
        $day = floor($second/(3600*24));
        $second = $second%(3600*24);//除去整天之后剩余的时间
        $hour = floor($second/3600);
        $second = $second%3600;//除去整小时之后剩余的时间
        $minute = floor($second/60);
        $second = $second%60;//除去整分钟之后剩余的时间
        $arr = array(
            'day'	=>	$day,
            'hour'	=>	$hour,
            'minute'=>	$minute,
            'second'=>	$second
        );
        //返回字符串
        return $arr;//$day.'天'.$hour.'小时'.$minute.'分';//.$second.'秒';
    }

    /**
     * 将一个本地时间戳转成GMT时间戳
     * @access  public
     * @param   int $time
     * @return int      $gmt_time;
     */
    function time2gmt($time) {
        return strtotime(gmdate('Y-m-d H:i:s', $time));
    }

    /**
     * 取得UTC日期时间
     * @param $time
     * @return bool|string
     */
    function get_UTC_date($time){
        return date('Y-m-d H:i:s',$time + 8 * 60 * 60);
    }

    /**
     * 取得UTC time
     * @param string $time
     * @return int|string]
     */
    function get_UTC_time($time=''){
        if(!$time){
            $time = time();
        }
        return $time + 8 * 60 * 60;
    }

    /**
     * 功能：判断密码强度
     * $str		string		需要打分的密码字符串
     * return int 打分（满分为10）
     */
    function pwd_computed_strength($str){
        $score = 0;
        if(preg_match("/[0-9]+/",$str)){
            $score ++;
        }
        if(preg_match("/[0-9]{3,}/",$str)){
            $score ++;
        }
        if(preg_match("/[a-z]+/",$str)){
            $score ++;
        }
        if(preg_match("/[a-z]{3,}/",$str)){
            $score ++;
        }
        if(preg_match("/[A-Z]+/",$str)){
            $score ++;
        }
        if(preg_match("/[A-Z]{3,}/",$str)){
            $score ++;
        }
        if(preg_match("/[_|\-|+|=|*|!|@|#|$|%|^|&|(|)]+/",$str)){
            $score += 2;
        }
        if(preg_match("/[_|\-|+|=|*|!|@|#|$|%|^|&|(|)]{3,}/",$str)){
            $score ++ ;
        }
        if(strlen($str) >= 10){
            $score ++;
        }
        return $score;
    }

    /**
     * 检查URL的合法性，检测URL头是否为 http, https, ftp
     * @param string $url url
     * @return boolean
     */
    function is_url($url){
        $allow = array('http', 'https');
        if (preg_match('!^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?!', $url, $matches)){
            $scheme = $matches[2];
            if (in_array($scheme, $allow))
            {
                return true;
            }
        }
        return false;
    }



    /**
     * 判断是否手机号码
     * @param $mobile       $手机号码
     * @return bool
     */
    function is_mobile_num($mobile){
        $regex = '/13[0-9]{9}|15[0|1|2|3|5|6|7|8|9]\d{8}|18[0|5|6|7|8|9]\d{8}|17[0-9]\d{8}/';
        $arr = array('13','14','15','18','17');
        if(is_numeric($mobile) and strlen($mobile) == 11 and (in_array(substr($mobile,0,2), $arr))){
            return true;
        }else{
            return false;
        }
    }


    /**
     * 验证是否手机号码
     * @param string $mobilephone 手机号码
     * @return boolean
     */
    function checkMobileValidity($mobilephone) {
        $exp = "/^13[0-9]{1}[0-9]{8}$|15[012356789]{1}[0-9]{8}$|18[012356789]{1}[0-9]{8}$|14[57]{1}[0-9]$/";
        if (is_numeric($mobilephone) and strlen($mobilephone) == 11) {
            return true;
        } else {
            return false;
        }
        if (preg_match($exp, $mobilephone)) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * 店铺|公司名称验证
     * @param string $name 名称
     * @param string $len 长度
     */
    function checkShopNameValidity($name,$len="2,30"){
        $exp = "/^[\x{4e00}-\x{9fa5}A-Za-z_]{".$len."}+$/u";
        if(preg_match($exp,$name)){
            return true;
        }else{
            return false;
        }
    }




    /**
     * 手机号码归属地(返回: 如 广东移动)
     * @param string $mobilephone
     * @return string
     */
    function checkMobilePlace($mobilephone) {
        $url = "http://tcc.taobao.com/cc/json/mobile_tel_segment.htm?tel=" . $mobilephone . "&t=" . time();
        $content = file_get_contents($url);
        $p = substr($content, 56, 4);
        $mo = substr($content, 81, 4);
        $str = $this->conv2utf8($p) . $this->conv2utf8($mo);
        return $str;
    }


    /**
     * 测试电话号码
     * @param $tel
     * @return bool
     */
    function is_tel($tel){
        $patten = '/[\d]{3}\d-[\d]{7}\d/';
        if (preg_match($patten,$tel)){
            return true;
        } else {
            return false;
        }
    }

    /**
     * 验证输入的邮件地址是否合法
     * @param string $user_email    $需要验证的邮件地址
     * @return bool
     */
    function is_email($user_email) {
        $chars = "/^([a-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}\$/i";
        if (strpos($user_email, '@') !== false && strpos($user_email, '.') !== false) {
            if (preg_match($chars, $user_email)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    /*
     * 验证输入的信息是否是一个合法的用户名
     */
    function is_username($username) {
        $reg = "/^[a-zA-Z0-9_]{6,15}$/";
        if(preg_match($reg,$username)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 检查是否为一个合法的时间格式
     * @access  public
     * @param   string  $time
     * @return  boolean
     */
    function is_time($time) {
        $pattern = '/[\d]{4}-[\d]{1,2}-[\d]{1,2}\s[\d]{1,2}:[\d]{1,2}:[\d]{1,2}/';
        return preg_match($pattern, $time);
    }

    /**
     * 获取文章内的img
     * @param $str
     * @param int $pat
     * @return mixed
     */
    function get_img($str,$pat = 2){
        $pattern[0] = '/<img(.*)src="([^"]+)"[^>]+>/isU';
        $pattern[1] = '/<[img|IMG].*?src=[\'|\"](.*?(?:[\.gif|\.jpg]))[\'|\"].*?[\/]?>/';
        $pattern[2] = '/<[img|IMG].*?src=\\\"?(.+\.(jpg|gif|bmp|bnp|png))\"?.+[\/]>/i';
        $pattern[3] = '/<img[^src]+src=[\'|\"](.*)?[\'|\"][^>]+[\/]?>/Ui';
        $content = $str;
        preg_match_all($pattern[$pat],$content,$matches);
        return $matches;
    }


    /**
     * 取当前文件名
     * @return mixed
     */
    function get_filename() {
        $filename = str_replace('/', '', $_SERVER["SCRIPT_NAME"]);
        $arr = explode('.', $filename);
        return $arr[0];
    }

    /**
     * 过滤script html类脚本XSS跨站
     * @param $str  $要过滤的字符串
     * @param bool $conver
     * @return mixed
     */
    function filterScript($str, $conver = false)  {
        $s = $str;
        if ($conver == 'utf8') $str = mb_convert_encoding($s, "utf-8", "gbk,utf-9,gb2312");
        if (is_array($s) and !$s) {
            foreach ($s as $key => $value) {
                $str[$key] = preg_replace('/<script[^>]*?1>.*?<\/script>/si', '', $value);
                $str[$key] = str_replace(array('script', 'alert', '>', '<', '"', '\\', '/', '\'', '+', '-', 'select', '*', 'expression', 'style', '=', '(', ')'), '', $value);
            }
        }
        return $s;
    }

    /**
     * 转化HTML
     * @param $value
     * @return mixed|string
     */
    function filter_html($value)    {
        if (function_exists('htmlspecialchars')) return htmlspecialchars($value);
        return str_replace(array("&", '"', "'", "<", ">"), array("&", "\"", "'", "<", ">"), $value);
    }

    /**
     * 清除代码内的html
     * @param $str
     * @return string
     */
    function clear_html_1($str) {
        $str = strip_tags($str);
        $str_arr = preg_split("/\n/",$str);
        $result = '';
        if ($str_arr) {
            foreach ($str_arr as $v) {
                if (trim($v) != '') {
                    $result .= '<p>' . $v . '</p>';
                } else {
                    $result .= '<p>&nbsp;</p>';
                }
            }
        } else {
            $result = $str;
        }
        return $result;
    }

    /**
     * 重构range
     * @param $start
     * @param $limit
     * @param int $step
     * @return \Generator
     * @throws \Exception
     */
    function xrange($start, $limit, $step = 1) {
        if ($start < $limit) {
            if ($step <= 0) {
                throw new \Exception('Step must be +ve');
            }

            for ($i = $start; $i <= $limit; $i += $step) {
               // yield $i;
            }
        } else {
            if ($step >= 0) {
                throw new \Exception('Step must be -ve');
            }

            for ($i = $start; $i >= $limit; $i += $step) {
               // yield $i;
            }
        }
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

}