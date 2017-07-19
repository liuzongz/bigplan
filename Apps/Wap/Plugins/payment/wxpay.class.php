<?php

/**
 * 即牛 - 支付宝插件
 * ============================================================================
 * 版权所有 2011-2013 ，并保留所有权利。
 * 网站地址: http://；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng $
 * $Id: alipay.php 17063 2015-05-04 10:55:46Z keheng $
 */
namespace Wap\Plugins\payment;
use Wap\Controller;
use Wap\Model;
/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE){
    $i = isset($modules) ? count($modules) : 0;
    $modules[$i]['code']    = basename(__FILE__, '.php');       // 代码
    $modules[$i]['desc']    = 'wxpay_desc';        //描述对应的语言项
    $modules[$i]['is_cod']  = '0';//是否支持货到付款
    $modules[$i]['is_online']  = '1';       //是否支持在线支付
    $modules[$i]['author']  = 'MJJ_SHOP TEAM';      //作者
    $modules[$i]['website'] = 'http://www.wxpay.com';          //网址
    $modules[$i]['version'] = '1.0.2';          //版本号
    $modules[$i]['config']  = array(            //配置信息
        array('name' => 'wxpay_account',           'type' => 'text',   'value' => ''),
        array('name' => 'wxpay_key',               'type' => 'text',   'value' => ''),
        array('name' => 'wxpay_partner',           'type' => 'text',   'value' => ''),
//        array('name' => 'alipay_real_method',       'type' => 'select', 'value' => '0'),
//        array('name' => 'alipay_virtual_method',    'type' => 'select', 'value' => '0'),
//        array('name' => 'is_instant',               'type' => 'select', 'value' => '0')
        array('name' => 'wxpay_pay_method',        'type' => 'select', 'value' => '')
    );
    return;
}

class wxpay extends Controller\WapController {
    //function __construct() {}
    private $parameters;        //静态链接参数
    private $prepay_id;         //预支付ID
    private $appid;             //公众号appid
    private $mchid;             //受理商ID，身份标识
    private $key;               //商户支付密钥Key。审核通过后，在微信发送的邮件中查看
    private $appsecret;         //JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看


    /*
SUCCESS—支付成功
REFUND—转入退款
NOTPAY—未支付
CLOSED—已关闭
REVOKED—已撤销（刷卡支付）
USERPAYING--用户支付中
PAYERROR--支付失败(其他原因，如银行返回失败)*/
     /**
     * 生成支付代码
     * @param array $order 订单信息
     * @param array $log_data
     * @param array $user_info
     * @return string|boolean
     */
    function get_code($order, $log_data, $user_info) {
        $res = $this->OrderQuery($order);
        if ($res['trade_state'] == 'CLOSED') { //如果订单已经关闭，同样的单号不能重复提交，只能更新序列号再提交
            $order_model = new Model\OrderModel();
            $order_sn = $order_model->create_order_sn();
            M('OrderInfo')->where('order_id=' . $order['order_id'])->data(array('order_sn' => $order_sn))->save();
            $order['order_sn'] = $order_sn;
        }
        if ($res['trade_state'] == 'CLOSED') {

        }
        $result = $this->JsApiPay($order,$log_data);
        $arr = array();
        if ($result) {
            $jsApiParameters = array();
            if ($result['return_code'] == 'SUCCESS'){
                if ($result['result_code'] == 'SUCCESS') {
                    $time = time();
                    if ($this->is_weixin) {
                        $jsApiParameters['appId']       = $this->wxuser['appid'];
                        $jsApiParameters['timeStamp']   = "{$time}";
                        $jsApiParameters['nonceStr']    = md5($this->get_rand_str(8));
                        $jsApiParameters['package']     = 'prepay_id=' . $result['prepay_id'];
                        $jsApiParameters['signType']    = 'MD5';
                        $jsApiParameters['paySign']     = $this->get_Sign($jsApiParameters, $this->wxuser['paysignkey']);
                        $this->assign('jsApiParameters', json_encode($jsApiParameters));
                    } else {
                        $jsApiParameters['appid']       = $this->wxuser['appid'];
                        $jsApiParameters['noncestr']    = md5($this->get_rand_str(8));
                        $jsApiParameters['package']     = 'WAP';
                        $jsApiParameters['prepayid']    = $result['prepay_id'];
                        $jsApiParameters['sign']        = $this->get_Sign($jsApiParameters, $this->wxuser['paysignkey']);
                        $jsApiParameters['timestamp']   = "{$time}";
                        //exit('weixin://wap/pay?' . $this->Array2Str($jsApiParameters));
                        //$this->assign('pay_url', 'weixin://wap/pay?' . $this->Array2Str($jsApiParameters));
                        $this->assign('pay_url', 'https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=' . $result['prepay_id'] . '&package=WAP');
                    }
                    $url = array(
                        'pay_no'     =>  U('Done/PayError?id=' . $order['order_sn']),
                        'pay_ok'     =>  U('Done/PaySuccess?id=' . $order['order_sn']),
                    );
                    $this->assign('url', $url);
                    $pay_str = $this->fetch('checkout:go_wxpay');
                    $this->assign('pay_str',$pay_str);
                    //$this->debug(json_encode($jsApiParameters),1);exit;
                    $this->assign('paytype', $paytype = '');
                    $arr['error'] = 0;
                    $arr['message'] = '';
                    $arr['contents'] = $jsApiParameters;
                } else {
                    $arr['error'] = 2;
                    $arr['message'] = $result['err_code_des'];
                    $arr['error_code'] = $result['err_code'];
                    if ($result['err_code'] == 'OUT_TRADE_NO_USED') {
                        $this->orderclose($order);
                        $arr['message'] .= '重复提交，订单已关闭，请再次提交！';
                    } else if ($result['err_code'] == 'ORDERCLOSED') {
                        $arr['message'] = '请取消当前订单并重新生成订单！';
                    }
                }
            } else {
                $arr['error'] = 2;
                $arr['message'] = $result['return_msg'];
            }
        } else {
            $arr['error'] = 1;
            $arr['message'] = '支付失败，未通过签名验证！';
        }
        return $arr;
    }

    /**
     * 处理返回结果
     * @param $data
     * @param string $err_msg
     * @return mixed
     */
    private function Test_Result($data, $err_msg = '失败！') {
        if ($data) {
            if ($data['return_code'] == 'SUCCESS') {
                if ($data['result_code'] == 'SUCCESS') {
                    $arr['error'] = 0;
                    $arr['message'] = '';
                } else {
                    $arr['error'] = 3;
                    $arr['message'] = $data['err_code_des'] . ':' . $data['err_code'];
                }
            } else {
                $arr['error'] = 2;
                $arr['message'] = $data['return_msg'];
            }
        } else {
            $arr['error'] = 1;
            $arr['message'] = $err_msg;
        }
        return $arr;
    }

    /**
     * 查询退款
     * @param $order
     * @return bool|mixed
     */
    public function RefundQuery($order) {
        $data = array(
            'appid'             =>  $this->wxuser['appid'],
            'mch_id'            =>  $this->wxuser['appmchid'],
            'out_trade_no'      =>  $order['order_sn'],      //商户订单号
            'nonce_str'         =>  md5($this->get_rand_str(8)),
            //'transaction_id'         =>  $order['order_amount'],    //微信订单号
            //'out_refund_no'        =>  $order['refund_amount'],    //商户退款单号
            //'refund_id'        =>  $order['op_user_id'],    //微信退款单号
        );
        $data['sign'] = $this->get_Sign($data, $this->wxuser['paysignkey']);
        $data = $this->Array2Xml($data);
        $res = $this->curlGet($this->get_url(10,3), 'post', $data);
        $add_data = array(
            'user_id'   =>  $this->login_info['user_id'],
            'order_id'  =>  $order['order_id'],
            'send_data' =>  $data,
            'get_data'  =>  $res,
            'add_time'  =>  time(),
            'ip_address'  =>  get_client_ip(),
            'handle_name'    =>  '查询退款'
        );
        M('PaydataLog')->add($add_data);
        $res = $this->Xml2Array($res);
        if ($this->test_Sign($res, $this->wxuser['paysignkey'])) {
            return $res;
        } else {
            return false;
        }
    }
    /**
     * 申请退款
     * @param $order
     * @return bool|mixed
     */
    public function OrderRefund($order){
        $data = array(
            'appid'             =>  $this->wxuser['appid'],
            'mch_id'            =>  $this->wxuser['appmchid'],
            'out_trade_no'      =>  $order['order_sn'],      //商户订单号
            'nonce_str'         =>  md5($this->get_rand_str(8)),
            'out_refund_no'     =>  $order['refund_sn'],    //退款单号
            'total_fee'         =>  $order['order_amount'],    //订单金额
            'refund_fee'        =>  $order['refund_amount'],    //退款金额
            'op_user_id'        =>  $order['op_user_id'],    //操作员ID
        );
        $data['sign'] = $this->get_Sign($data, $this->wxuser['paysignkey']);
        $data = $this->Array2Xml($data);
        $res = $this->curlGet($this->get_url(9,3), 'post', $data);
        $add_data = array(
            'user_id'   =>  $this->login_info['user_id'],
            'order_id'  =>  $order['order_id'],
            'send_data' =>  $data,
            'get_data'  =>  $res,
            'add_time'  =>  time(),
            'ip_address'  =>  get_client_ip(),
            'handle_name'    =>  '申请退款'
        );
        M('PaydataLog')->add($add_data);
        $res = $this->Xml2Array($res);
        if ($this->test_Sign($res, $this->wxuser['paysignkey'])) {
            return $res;
        } else {
            return false;
        }
    }
    /**
     * 微信关闭订单
     * @param $order
     * @return bool|mixed
     */
    protected function OrderClose($order) {
        $data = array(
            'appid'             =>  $this->wxuser['appid'],
            'mch_id'            =>  $this->wxuser['appmchid'],
            'out_trade_no'      =>  '20160826201838',//$order['order_sn'],      //商户订单号
            'nonce_str'         =>  md5($this->get_rand_str(8)),
        );
        $data['sign'] = $this->get_Sign($data, $this->wxuser['paysignkey']);
        $data = $this->Array2Xml($data);
        $res = $this->curlGet($this->get_url(8,3), 'post', $data);
        $add_data = array(
            'user_id'   =>  $this->login_info['user_id'],
            'order_id'  =>  $order['order_id'],
            'send_data' =>  $data,
            'get_data'  =>  $res,
            'add_time'  =>  time(),
            'ip_address'  =>  get_client_ip(),
            'handle_name'    =>  '关闭订单'
        );
        M('PaydataLog')->add($add_data);
        $res = $this->Xml2Array($res);
        if ($this->test_Sign($res, $this->wxuser['paysignkey'])) {
            return $res;
        } else {
            return false;
        }
    }
    /**
     * 微信查询订单
     * @param $order
     * @return bool|mixed
     */
    protected function OrderQuery($order) {
        $data = array(
            'appid'             =>  $this->wxuser['appid'],
            'mch_id'            =>  $this->wxuser['appmchid'],
            'out_trade_no'      =>  $order['order_sn'],      //商户订单号
            'nonce_str'         =>  md5($this->get_rand_str(8)),
            //'transaction_id'    =>  '',             //微信订单号
        );
        $data['sign'] = $this->get_Sign($data, $this->wxuser['paysignkey']);
        $data = $this->Array2Xml($data);
        $res = $this->curlGet($this->get_url(7,3), 'post', $data);

        $add_data = array(
            'user_id'   =>  $this->login_info['user_id'],
            'order_id'  =>  $order['order_id'],
            'pay_type'  =>  $order['pay_id'],
            'send_data' =>  $data,
            'get_data'  =>  $res,
            'add_time'  =>  time(),
            'ip_address'  =>  get_client_ip(),
            'handle_name'    =>  '查看订单'
        );
        M('PaydataLog')->add($add_data);//print_r($add_data);exit('end');
        $res = $this->Xml2Array($res);
        return $res;
        /*if ($this->test_Sign($res)) {
            exit('Y');
            return $res;
        } else {
            exit('N');
            return false;
        }*/
    }
    /**
     * 微信支付统一下单
     * @param array $order
     * @param array $log_data
     * @return mixed
     */

    protected function JsApiPay($order, $log_data){
        $time = time();
        if ($log_data['pay_result_id'] == '' or ($time - $log_data['pay_result_time'] > 3500)) {
            $ip = get_client_ip();
            $data = array(
                'appid'             =>  $this->wxuser['appid'],  //公众账号ID
                'mch_id'            =>  $this->wxuser['appmchid'],     //商户号
                'nonce_str'         =>  md5($this->get_rand_str(8)),      //随机字符串
                'body'              =>  $this->store_wx['store_name'] . '-购物',   //商品描述
                //'detail'          =>  '测试测试',//商品详情(可选)
                //'attach'          =>  '分店数据',//附加数据(可选)
                'out_trade_no'      =>  $order['order_sn'],//商户订单号
                'fee_type'          =>  'CNY',//货币类型(可选)
                'total_fee'         =>  ceil($order['pay_fee']* 100),//总金额
                'spbill_create_ip'  =>  $ip,//终端IP
                'time_start'        =>  "{$time}",//交易起始时间(可选)
                'notify_url'        =>  $this->cur_domain . U('Done/gateway?sid=' . $order['pay_id']),//通知地址
                //'device_info' =>  $this->wxuser[''],  //设备号(可选)
                //'time_expire' =>  $this->wxuser[''],//交易结束时间(可选)
                //'goods_tag' =>  $this->wxuser[''],//商品标记(可选)
                //'product_id' =>  $this->wxuser[''],//商品ID(可选)
                //'limit_pay' =>  $this->wxuser[''],//指定支付方式(可选)no_credit--指定不能使用信用卡支付
                //'sign' =>  $this->wxuser[''],       //签名
            );
            if ($this->is_weixin) {
                $data['trade_type']        =  'JSAPI';//交易类型
                $data['openid']            =  session("wx_token{$this->store_token}.openid");//用户标识(可选)
            } else {
                $data['trade_type']        =  'JSAPI';//交易类型  MWEB  APP
                $data['openid']            =  'oiUltuGRS_9GYX0vlt2cTWb5eY6M';
            }
            $data['sign'] = $this->get_Sign($data, $this->wxuser['paysignkey']);//ob_clean();
            $data = $this->Array2Xml($data);
            $this->logger('统一下单发送数据：' . print_r($data,1));
            $res = $this->curlGet($this->get_url(6,3), 'post', $data);
            //记录提交返回数据
            $add_data = array(
                'user_id'   =>  $this->login_info['user_id'],
                'order_id'  =>  $order['order_id'],
                'pay_type'    =>  $order['pay_id'],
                'send_data' =>  $data,
                'get_data'  =>  $res,
                'add_time'  =>  $time,
                'ip_address'  =>  $ip,
                'handle_name' =>  '统一下单',
            );
            $paydata = M('PaydataLog');
            $paydata->add($add_data);
            $data_id = $paydata->getLastInsID();
            //转换信息
            $res = $this->Xml2Array($res);
            /*print_r($res);exit;*/
            if ($res['return_code'] != 'SUCCESS') {

            } else {
                M('PayLog')->where('log_id=' . $log_data['log_id'])
                    ->save(
                        array(
                            'pay_result_id'     =>$res['prepay_id'],
                            'pay_result_time'   =>$time,
                            'data_id'           =>$data_id
                        )
                    );
            }

        } else {
            $res['prepay_id'] = $log_data['pay_result_id'];
            $res['return_code'] = 'SUCCESS';
            $res['result_code'] = 'SUCCESS';
        }

        if ($this->test_Sign($res, $this->wxuser['paysignkey']) || $res['prepay_id'] != '') {
            return $res;
        } else {
            return false;
        }
    }



    /**
     * 记录返回数据，返回支付反馈信息，支付ID，支付时间
     * @param $obj array
     * @return array
     */
    function respond($obj = array()){
        //$res = $GLOBALS["HTTP_RAW_POST_DATA"];
        $res = file_get_contents("php://input");
        debug('支付回调传输的数据：' . $res, 1);
        $result = $this->Xml2Array($res);
        //查询订单详情
        $order_info = M('OrderInfo')->field('oi.store_id,sw.*')
            ->alias('oi')
            ->join('LEFT JOIN __STORE_WX__ sw on oi.store_id=sw.store_id')
            ->where(['oi.order_sn'=>$result['out_trade_no']])->find();

        $this->wxuser['appid'] = $order_info['appid'];
        $this->wxuser['appmchid'] = $order_info['mchid'];
        $this->wxuser['paysignkey'] = $order_info['paysignkey'];
        if (trim($res) != '' and $this->test_Sign($result, $this->wxuser['paysignkey'])) {
            $user = new Model\UsersModel();
            $order = new Model\OrderModel();
            if ($result['trade_type'] == 'JSAPI') {

                /*$user_info = $user->get_user_wx('openid="' . $result['openid'] . '"');
                if ($user_info and $user_info['user_id'] > 0) {*/
                    $pay_info = array(
                        'pay_type'          =>  'wxpay',
                        'order_sn'          =>  $result['out_trade_no'],
                        'user_sn'           =>  $result['openid'],
                        'time_end'          =>  strtotime($result['time_end']),
                        'total_fee'         =>  $result['cash_fee'] / 100,
                        'transaction_id'    =>  $result['transaction_id'],
                        'data'              =>  $result,
                    );
                    return $order->payed_result($pay_info, $obj);
                    /*$order_info = $order->get_order_info($user_info['user_id'],
                                                $result['out_trade_no'],
                                                [
                                                    'order_status'      => OS_CONFIRMED ,
                                                    'shipping_status'  => SS_UNSHIPPED ,
                                                    'pay_status'       => PS_UNPAYED
                                                ]
                                            );
                    $log_where = [
                        'order_id'      =>  $order_info['order_id'],
                        'order_amount'  =>  $order_info['order_amount'],
                        'pay_id'        =>  $order_info['pay_id'],
                        'user_id'       =>  $user_info['user_id'],
                    ];
                    $log = M('PayLog');
                    if ($log->where($log_where)->count() <= 0) {
                        return array('error'=>5,'message'=>'付款金额与订单金额不符！');
                    } else if ($order_info and $order_info['order_id'] > 0) {
                        $where = [       //查询条件
                            'order_id'      =>  $order_info['order_id'],
                            'user_id'       =>  $user_info['user_id'],
                            'pay_id'        =>  $order_info['pay_id'],
                            'handle_name'   =>  '统一下单'
                        ];
                        M('PaydataLog')->where($where)->save(array('result_data'=>$res));     //保存接收的数据
                        $data = [
                            'pay_status'=> PS_PAYED ,
                            'pay_time'  => strtotime($result['time_end']),
                            'pay_fee'   => $result['total_fee']
                        ];
                        $order->where('order_id=' . $order_info['order_id'])->save($data); //设置已付款
                        //填写付款回单
                        $log_save_data = array(
                            'pay_return_id' => $result['transaction_id'],
                            'is_paid'       => 1
                        );
                        $log->where($log_where)->save($log_save_data);
                        $res = array(
                            'order_id'  =>  $order_info['order_id'],
                            'order_sn'  =>  $order_info['order_sn'],
                            'pay_id'    =>  $order_info['pay_id'],
                            'user_id'   =>  $user_info['user_id'],
                            'openid'    =>  $user_info['openid'],
                            'pay_result_id'  =>  $result['transaction_id'],
                        );
                        return array('error'=>0,'message'=>'付款成功！','contents'=> $res);
                    } else {
                        return array('error'=>4,'message'=>'未找到该用户的订单！');
                    }*/
                /*} else {
                    return $this->result_ajax(3,'未找到该用户！');
                }*/
            } else {
                return $this->result_ajax(2,'未找到相关付款方式！');
            }
        } else {
            return $this->result_ajax(1,'签名未通过验证！');
        }
    }

    /**
     * 响应操作
     */
    public function result($user_info){
        $data = $_POST;
        return $this->result_ajax(0);
    }
}

?>