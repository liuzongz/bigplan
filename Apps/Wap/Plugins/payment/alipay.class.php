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
    $modules[$i]['desc']    = 'alipay_desc';        //描述对应的语言项
    $modules[$i]['is_cod']  = '0';//是否支持货到付款
    $modules[$i]['is_online']  = '1';       //是否支持在线支付
    $modules[$i]['author']  = 'Jiniu TEAM';      //作者
    $modules[$i]['website'] = 'http://www.alipay.com';          //网址
    $modules[$i]['version'] = '1.0.2';          //版本号
    $modules[$i]['config']  = array(            //配置信息
        array('name' => 'alipay_account',           'type' => 'text',   'value' => ''),
        array('name' => 'alipay_key',               'type' => 'text',   'value' => ''),
        array('name' => 'alipay_partner',           'type' => 'text',   'value' => ''),
//        array('name' => 'alipay_real_method',       'type' => 'select', 'value' => '0'),
//        array('name' => 'alipay_virtual_method',    'type' => 'select', 'value' => '0'),
//        array('name' => 'is_instant',               'type' => 'select', 'value' => '0')
        array('name' => 'alipay_pay_method',        'type' => 'select', 'value' => '')
    );
    return;
}

require 'aop/AopClient.php';
class alipay extends Controller\WapController {
    protected $api_info = array();
    protected $aliapi = array();
    function _initialize() {
        parent::_initialize();
        /*$pay_info = C('PAYINFO');print_r($pay_info);exit('end');
        $pay_info = $pay_info['wxpay'];*/
        /*$this->api_info = array(
            'url'           =>  $pay_info['url'],//'https://openapi.alipay.com/gateway.do',
            'appid'         =>  $pay_info['appid'],//'2016081001728673',
            'seller_id'     =>  $pay_info['seller_id'],
            'private_key'   =>  $pay_info['private_key'],
            'public_key'    =>  $pay_info['public_key'],
            'Charset'       =>  $pay_info['Charset']
        );*/
        $this->api_info = array(
            'url'           =>  /*'https://openapi.alipaydev.com/gateway.do',//*/'https://openapi.alipay.com/gateway.do',
            'appid'         =>  /*'2016072800111400',//*/'2016081001728673',
            'seller_id'     =>  /*'2088102168879604',//*/'2088711477460911',
            'private_key'   =>  CONF_PATH  . 'rsa_private_key.pem',
            'public_key'    =>  'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDIgHnOn7LLILlKETd6BFRJ0GqgS2Y3mn1wMQmyh9zEyWlz5p1zrahRahbXAfCfSqshSNfqOmAQzSHRVjCqjsAw1jyqrXaPdKBmr90DIpIxmIyKXv4GGAkPyJ/6FTFY99uhpiq0qadD/uSzQsefWo0aTvP/65zi3eof7TcZ32oWpwIDAQAB',
            'Charset'       =>  'UTF-8'
        );
        $this->aliapi = array(
            'alipay.trade.wap.pay',     //手机网页支付接口
            'alipay.trade.close',       //交易关闭接口
            'alipay.trade.query',       //交易状态查询接口
            'alipay.trade.refund',      //交易退款接口
            'alipay.trade.fastpay.refund.query',        //退款查询
            'alipay.data.dataservice.bill.downloadurl.query',       //账单查询接口
        );

    }
    /**
     * 生成支付代码
     * @param array $order 订单信息
     * @param array $log_data 支付方式信息
     * * @param array $user_info
     * @return string
     */
    function get_code($order, $log_data, $user_info) {
        require 'aop/request/AlipayTradeWapPayRequest.php';
        $aop = new alipay\AopClient();
        $this->SetAop($aop);
        $request = new alipay\AlipayTradeWapPayRequest();
        $request->setNotifyUrl($this->cur_domain . U('Done/gateway?sid=' . $order['pay_id']));
        $request->setReturnUrl($this->cur_domain . U('Done/Response?sid=' . $order['pay_id']));
        $request->setBizContent($data = "{" .
            "    \"body\":\"{$this->_CFG['shop_name']}订单：{$order['order_sn']}\"," .
            "    \"subject\":\"{$this->_CFG['shop_name']}订单：{$order['order_sn']}\"," .
            "    \"out_trade_no\":\"{$order['order_sn']}\"," .
            "    \"timeout_express\":\"90m\"," .
            "    \"total_amount\":{$order['pay_fee']}," .
            "    \"product_code\":\"QUICK_WAP_PAY\"" .
            "  }");
//print_r($this->_CFG);exit;
        $pay_info = $aop->execute ( $request);
        $this->assign('pay_info', $pay_info);
        $pay_str = $this->fetch('checkout:go_alipay');
        $this->assign('pay_str',$pay_str);
        $time = time();$ip = get_client_ip();
        $add_data = array(
            'user_id'   =>  $this->login_info['user_id'],
            'order_id'  =>  $order['order_id'],
            'pay_type'    =>  $order['pay_id'],
            'send_data' =>  $data,
            'get_data'  =>  '',
            'add_time'  =>  $time,
            'ip_address'  =>  $ip,
            'handle_name' =>  '支付宝下单',
        );
        $paydata = M('PaydataLog');
        $paydata->add($add_data);
        /*$data_id = $paydata->getLastInsID();
        M('PayLog')->where('log_id=' . $log_data['log_id'])->save(
                array(
                    'pay_result_id'     =>$res['prepay_id'],
                    'pay_result_time'   =>$time,
                    'data_id'           =>$data_id
                )
            );*/


        //echo $result;exit;
        //$this->assign('pay_url', $result);
        return true;
    }

    /**
     * 异步回调
     */
    function respond($obj = array())  {
        /*$data = array(
            'notify_id' => '32e1f670f82c0b3a453701f56ede457ibe',
            'gmt_paymen' => '2016-08-29 18:16:02',
            'notify_type' => 'trade_status_sync',
            'sign' => 'ioei0oGseA4Q9hklcPIlztFBiQ1jaPdDiPxuSq/s+6Ba3ejGBClA1VrqO9Sc+jwdN5VoiQtG8i++whJQyiqP/2d6nOooHLxXSoUKej/eX4moCfr2BliAJNcwasVxn7nrMDEQw8frlQeQJk6jBh/bWwR2JqxVnN846zmOvzGKscs=',
            'trade_no' => '2016082921001004300200046987',
            'buyer_id' => '2088102168902306',
            'body' => '微品订单：20160869265638',
            'app_id' => '2016082000299562',
            'gmt_create' => '2016-08-29 18:15:59',
            'out_trade_no' => '20160869265638',
            'seller_id' => '2088102172312083',
            'notify_time' => '2016-08-29 18:16:03',
            'subject' => '微品订单：20160869265638',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '1.00',
            'sign_type' => 'RSA',
        );*/
        $data = $_POST;
        $aop = new alipay\AopClient();
        $this->SetAop($aop);        //TODO:此处缺签名验证
        /*if ($aop->rsaCheckV1($data, CONF_PATH  . 'rsa_public_key.pem')) {
            exit('Y');
        } else {
            exit('N');
        }*/
        /*$sign = $data['sign'];$data['sign'] = NULL;$data['sign_type'] = NULL;//unset($data['sign']);
        $getSign = $aop->rsaSign($data, 'RSA');
        //$aop->checkResponseSign($data,$sign,,);
        exit('返回签名：' . $sign . "\n加密签名：" . $getSign);*/
        $result = $this->result_ajax(0,'');
        if ($data['trade_status'] == 'TRADE_SUCCESS') {
            if ($data['app_id'] != $this->api_info['appid'] or $data['seller_id'] != $this->api_info['seller_id']) {
                $result = $this->result_ajax(1,'返回商户ID和appid与商城不符！',$data);
            } else {
                $order_model = new Model\OrderModel();
                $pay_info = array(
                    'pay_type'          =>  'alipay',
                    'order_sn'          =>  stripslashes($data['out_trade_no']),
                    'user_sn'           =>  stripslashes($data['buyer_id']),
                    'time_end'          =>  strtotime($data['gmt_payment']),
                    'total_fee'         =>  $data['total_amount'],
                    'transaction_id'    =>  $data['trade_no'],
                    'data'              =>  $data
                );
                $result = $order_model->payed_result($pay_info, $obj);
            }
        } else if ($_GET['trade_status'] == 'WAIT_SELLER_SEND_GOODS') {
            //order_paid($order_sn, 2);/* 改变订单状态 */
        } elseif ($_GET['trade_status'] == 'TRADE_FINISHED') {
            //order_paid($order_sn);      /* 改变订单状态 */
        } else {
        }
        echo 'success';
        return $result;
    }

    /**
     * 支付同步返回
     */
    /**
    [total_amount] => 215.00
    [timestamp] => 2016-08-30 08:53:18
    [sign] => uU8yfK1dlMcoZYqMJkNyvlks15Rq5ac/GHUeDxlggYFfCJSKOC1kOJjYQcaTqHZdoACBKUg1lv7eHN1aDW9XU2IZA2WTySAqj9n2kfOtuEhc+QcjHpXojRJIgFhvodYI3OozpwUpInrXlcg64QCMrrvEXcKPppsQ3ntHK70nbyE=
    [trade_no] => 2016083021001004300200046989
    [sign_type] => RSA
    [charset] => UTF-8
    [seller_id] => 2088102172312083
    [method] => alipay.trade.wap.pay.return
    [app_id] => 2016082000299562
    [out_trade_no] => 20160868509518
    [version] => 1.0
    http://www.weipincn.com/wap/done/response/sid/5.html?total_amount=215.00&timestamp=2016-08-30+08%3A53%3A18&sign=uU8yfK1dlMcoZYqMJkNyvlks15Rq5ac%2FGHUeDxlggYFfCJSKOC1kOJjYQcaTqHZdoACBKUg1lv7eHN1aDW9XU2IZA2WTySAqj9n2kfOtuEhc%2BQcjHpXojRJIgFhvodYI3OozpwUpInrXlcg64QCMrrvEXcKPppsQ3ntHK70nbyE%3D&trade_no=2016083021001004300200046989&sign_type=RSA&charset=UTF-8&seller_id=2088102172312083&method=alipay.trade.wap.pay.return&app_id=2016082000299562&out_trade_no=20160868509518&version=1.0
     */
    public function result($user_info){
        $data = array(
            'total_amount'  =>  $_GET['total_amount'],
            'timestamp'     =>  strtotime($_GET['timestamp']),
            'sign'          =>  trim($_GET['sign']),
            'trade_no'      =>  trim($_GET['trade_no']),
            'sign_type'     =>  trim($_GET['sign_type']),
            'charset'       =>  $_GET['charset'],
            'seller_id'     =>  $_GET['seller_id'],
            'method'        =>  $_GET['method'],
            'app_id'        =>  $_GET['app_id'],
            'out_trade_no'  =>  stripslashes($_GET['out_trade_no']),
            'version'       =>  $_GET['version']
        );
        $aop = new alipay\AopClient();
        $this->SetAop($aop);        //TODO:此处缺签名验证
        /*if ($aop->rsaCheckV1($data, CONF_PATH  . 'rsa_public_key.pem')) {
            exit('Y');
        } else {
            exit('N');
        }*/
        /*$sign = $data['sign'];$data['sign'] = NULL;$data['sign_type'] = NULL;//unset($data['sign']);
        $getSign = $aop->rsaSign($data, 'RSA');
        //$aop->checkResponseSign($data,$sign,,);
        exit('返回签名：' . $sign . "\n加密签名：" . $getSign);*/
        if ($data['app_id'] != $this->api_info['appid'] or $data['seller_id'] != $this->api_info['seller_id']) {
            $result = $this->result_ajax(5,'返回商户ID和appid与商城不符！');
        } else {
            if ($data['method'] == 'alipay.trade.wap.pay.return') {
                $order_model = new Model\OrderModel();
                $login_info = $this->login_info;
                $order_info = $order_model->get_order_info($login_info['user_id'], $data['out_trade_no']);
                if ($order_info) {
                    if ($order_info['order_amount'] != $data['total_amount']) {
                        $result = $this->result_ajax(2,'支付金额与订单金额不符！');
                    } elseif ($order_info['pay_status'] != ORDER_PAYED) {
                        $result = $this->result_ajax(7,'支付失败！');
                    } else {

                        $result = $this->result_ajax(0);
                    }
                } else {
                    $result = $this->result_ajax(1,'订单不存在！');
                }
            } else {
                $result = $this->result_ajax(3,'未知错误！');
                \Think\Log::record('支付返回信息未找到处理方法！' . "\nGET:" . print_r($_GET,1) . "\nPOST:" . print_r($_POST,1));
            }
        }
        return $result;
    }

    private function SetAop($obj){
        $obj->gatewayUrl            = $this->api_info['url'];
        $obj->appId                 = $this->api_info['appid'];
        $obj->rsaPrivateKeyFilePath = $this->api_info['private_key'];
        $obj->alipayPublicKey       = $this->api_info['public_key'];
        $obj->apiVersion            = '1.0';
        $obj->postCharset           = $this->api_info['Charset'];
        $obj->format                = 'json';
    }
}




?>