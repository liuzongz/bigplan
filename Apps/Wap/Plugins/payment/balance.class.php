<?php

/**
 * 美家家商城 - 余额支付插件
 * ============================================================================
 * 版权所有 2011-2013 佛山市木美居家具销售有限公司(美家家商城)，并保留所有权利。
 * 网站地址: http://www.mjiajia.cn；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liuhui $
 * $Id: balance.php 17063 2010-03-25 06:35:46Z liuhui $
 */
namespace Wap\Plugins\payment;
use Wap\Controller;
use Wap\Model;
/*defined('MJJ_SHOP') and die('Hacking attempt');
$payment_lang =  '../languages/' .$GLOBALS['_CFG']['lang']. '/payment/balance.php';
if (file_exists($payment_lang)) {
    global $_LANG;
    include_once($payment_lang);
}*/

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'balance_desc';

    /* 是否货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'JINIU TEAM';

    /* 网址 */
    $modules[$i]['website'] = 'http://www.jiniu.cc';

    /* 版本号 */
    $modules[$i]['version'] = '1.0.0';

    /* 配置信息 */
    $modules[$i]['config']  = array();

    return;
}

/**
 * 类
 */
class balance extends Controller\WapController {
    private $api_info = [];
    function _initialize() {
        parent::_initialize();
        $this->api_info = [
            'notify_url'    => $this->cur_domain . U('Done/gateway?sid=1'),
            'result_url'    => $this->cur_domain . U('Done/Response?sid=1'),
            'pay_url'       => $this->cur_domain . U('Done/balance_pay'),
            'pay_error'     => $this->cur_domain . U('Done/PayError'),
        ];
    }


    public function get_code($order, $log_data, $user_info) {
        $notify_data = [
            'total_amount'  =>  $order['pay_fee'],
            'timestamp'     =>  $this->time,
            //'trade_no'      =>  $cash_sn,
            'sign_type'     =>  'MD5',
            'charset'       =>  'UTF-8',
            'seller_id'     =>  $user_info['user_id'],
            'method'      =>  'hkhp.trade.wap.pay',
            'app_id'      =>  '2016082000299562',
            'out_trade_no'  =>  $order['order_sn'],
        ];
        $notify_data['sign']   = $this->get_Sign($notify_data);
        $notify_data['form_token'] = $this->set_form_token();
        $notify_data['ajax'] = 1;
        $pay_info = [
            'pay_url'       =>  $this->api_info['pay_url'],
            'result_url'    =>  $this->api_info['result_url'],
            'pay_error'     =>  $this->api_info['pay_error'],
            'data'          =>  $this->Array2Str($notify_data),
        ];
        $this->assign('pay_info',$pay_info);
        $pay_str = $this->fetch('checkout:go_balance');
        $this->assign('pay_str',$pay_str);//exit($pay_str);
    }
    /**
     * 生成支付代码  //作废 20160925
     * @param array $order 订单信息
     * @param array $log_data 支付方式信息
     * @param array $user_info
     * @return string
     */
    protected function get_code1($order, $log_data, $user_info) {
        if ($user_info['user_money'] < $order['order_amount']) {
            $result = $this->result_ajax(1,'用户余额不足！');
        } else {
            $user_model = new Model\UsersModel();
            if ($user_model->where('user_id=' . $user_info['user_id'])->setDec('user_money', $order['order_amount'])) {
                //写入支付日志
                $cash_sn = $user_model->add_cash_log([
                    'store_id'      =>  0,
                    'user_id'        =>  $user_info['user_id'],
                    'stage'         =>  'order',
                    'price'         =>  '-' . $order['order_amount'],
                    'type'          =>  0,
                    'desc'          =>  '支付订单：' . $order['order_sn'] . '费用'
                ]);
                //类似支付宝微信异步调用返回信息
                $notify_data = [
                    'total_amount'  =>  $order['order_amount'],
                    'timestamp'     =>  $this->time,
                    'trade_no'      =>  $cash_sn,
                    'sign_type'     =>  'MD5',
                    'charset'       =>  'UTF-8',
                    'seller_id'     =>  $user_info['user_id'],
                    //'method'      =>  'alipay.trade.wap.pay.return',
                    //'app_id'      =>  '2016082000299562',
                    'out_trade_no'  =>  $order['order_sn'],
                ];
                $notify_data['sign']   = $this->get_Sign($notify_data);//echo $this->api_info['notify_url'] . "\n";
                //\Think\Log::record('开始 curl:' . microtime());
                $res = $this->curlGet($this->api_info['notify_url'], 'post', $this->Array2Str($notify_data));
                //\Think\Log::record('结束 curl:' . microtime());
                if (strtolower($res) == 'success' or !$res) {
                    $result_data = [
                        'total_amount'  =>  $order['order_amount'],
                        'timestamp'     =>  $this->time,
                        'trade_no'      =>  $cash_sn,
                        'sign_type'     =>  'MD5',
                        'charset'       =>  'UTF-8',
                        'out_trade_no'  =>  $order['order_sn'],
                    ];
                    $result_data['sign']   = $this->get_Sign($result_data);
                    $this->assign('pay_info', ['result_url'=>$this->api_info['result_url'] . '?' . $this->Array2Str($result_data)]);
                    $pay_str = $this->fetch('checkout:go_balance');
                    $this->assign('pay_str',$pay_str);//exit($pay_str);
                    $result = $this->result_ajax(0,'支付成功！');
                } else {
                    $result = $this->result_ajax(5,'异步通知时失败！',$res);
                }
            } else {
                $result = $this->result_ajax(2,'支付失败！');
            }
        }
        return $result;
    }

    /**
     * 回调处理函数
     */
    function respond($obj = array()) {
        $data = $_POST;//print_r($data);print_r($data);exit('end1111111111');
        ob_end_clean();
        if ($this->test_Sign($data, '', 0)) {
            $order_model = new Model\OrderModel();
            $pay_info = array(
                'pay_type'          =>  'balance',
                'order_sn'          =>  stripslashes($data['out_trade_no']),
                'user_sn'           =>  stripslashes($data['buyer_id']),
                'time_end'          =>  $data['gmt_payment'],
                'total_fee'         =>  $data['total_amount'],
                'transaction_id'    =>  $data['trade_no'],
                'data'              =>  $data
            );
            $result = $order_model->payed_result($pay_info, $obj);
        } else {
            $result = $this->result_ajax(1,'效验码错误！',$data);
        }
        echo json_encode($result);
        \Think\Log::record( json_encode($result) . microtime());
        return $result;
    }

    public function result($user_info){
        $data = array(
            'total_amount'  =>  ($_GET['total_amount']),
            'timestamp'     =>  strtotime(date('Y-m-d H:i:s',$_GET['timestamp'])),
            'trade_no'      =>  stripslashes($_GET['trade_no']),
            'sign_type'     =>  stripslashes($_GET['sign_type']) == 'MD5' ? 'MD5' : 'RSA',
            'charset'       =>  $_GET['charset'],
            'out_trade_no'  =>  stripslashes($_GET['out_trade_no']),
            'sign'          =>  $_GET['sign'],
        );
        if ($this->test_Sign($data, '', 0)) {
            $order_model = new Model\OrderModel();
            $order_info = $order_model->get_order_info($user_info['user_id'], $data['out_trade_no']);
            if ($order_info) {
                if ($order_info['pey_fee'] != $data['total_amount']) {
                    $result = $this->result_ajax(2,'支付金额与订单金额不符！');
                } elseif ($order_info['pay_status'] != PS_PAYED) {
                    $result = $this->result_ajax(7,'支付失败111！');
                } else {
                    $result = $this->result_ajax(0);
                }
            } else {
                $result = $this->result_ajax(1,'订单不存在！');
            }
        } else {
            $result = $this->result_ajax(1,'请不要修改URL提交！');
        }
        return $result;
    }


    public function pay($user_info) {
        $data = [
            'total_amount'  =>  I('post.total_amount',0),
            'timestamp'     =>  I('post.timestamp',0),
            'sign_type'     =>  I('post.sign_type','MD5'),
            'charset'       =>  I('post.charset','UTF-8'),
            'seller_id'     =>  I('post.seller_id'),
            'method'        =>  I('post.method'),
            'app_id'        =>  I('post.app_id'),
            'out_trade_no'  =>  I('post.out_trade_no',0,'stripslashes'),
            'sign'          =>  I('post.sign'),
        ];
        if ($this->test_Sign($data, '', 0)) {
            $order_model = new Model\OrderModel();
            $user_model = new Model\UsersModel();
            $order = $order_model->get_order_info($user_info['user_id'],$data['out_trade_no']);
            if ($order) {
                if ($user_info['user_money'] < $order['pey_fee']) {
                    $result = $this->result_ajax(20,'用户余额不足！');
                } elseif ($order['order_integral'] > 0 && $user_info['pay_points'] < $order['order_integral']) {
                    $result = $this->result_ajax(30,'用户' . $this->_CFG['integral_name'] . '不足！');
                } else {
                    if ($cash_sn = $user_model->set_user_money(['user_id'=>$user_info['user_id'],'store_id'=>$order['store_id']],-$order['order_amount'],'order','支付订单：' . $order['order_sn'] . '费用，支付系统记录！')) {
                        //类似支付宝微信异步调用返回信息
                        $notify_data = [
                            'total_amount'  =>  $order['pay_fee'],
                            'timestamp'     =>  $this->time,
                            'trade_no'      =>  $cash_sn,
                            'sign_type'     =>  'MD5',
                            'charset'       =>  'UTF-8',
                            'seller_id'     =>  $user_info['user_id'],
                            //'method'      =>  'alipay.trade.wap.pay.return',
                            //'app_id'      =>  '2016082000299562',
                            'out_trade_no'  =>  $order['order_sn'],
                        ];
                        $notify_data['sign']   = $this->get_Sign($notify_data);
                        $res = $this->curlGet($this->api_info['notify_url'], 'post', $this->Array2Str($notify_data));
                        if ($res['error'] == 0) {
                            $result_data = [
                                'total_amount'  =>  $order['pey_fee'],
                                'timestamp'     =>  $this->time,
                                'trade_no'      =>  $cash_sn,
                                'sign_type'     =>  'MD5',
                                'charset'       =>  'UTF-8',
                                'out_trade_no'  =>  $order['order_sn'],
                            ];
                            $result_data['sign']   = $this->get_Sign($result_data);
                            $result = $this->result_ajax(0,'支付成功！', ['url'=>$this->api_info['result_url'] . '?' . $this->Array2Str($result_data)]);
                        } else {
                            $result = $this->result_ajax(5,'异步通知时失败！',$res);
                        }
                    } else {
                        $result = $this->result_ajax(2,'支付失败！');
                    }
                }
            } else {
                $result = $this->result_ajax(2,'订单信息不存在！');
            }
        } else {
            $result = $this->result_ajax(10,'数据效验失败！',$data);
        }
        return $result;
    }
}
?>