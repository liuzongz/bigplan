<?php
namespace Sales\Controller;

use Sales\Model;

class PaymentController extends WechatController {

    protected function _initialize()    {
        parent::_initialize();
    }

    public function index(){
        $this->assign('form_token', $this->set_form_token());
        $this->display('Payment:go');
    }

    /**
     * 跳转付款页面
     */
    public function pay(){
        if (/*!$this->check_form_token('pay_form_token') or*/ !IS_POST) {
            $this->ajaxReturn(result_ajax('302', '提交失败！'));
        } else { //TODO 123
            void_user($this->store_id, '');
            debug($this->store_id, 1);
            debug($this->login_info, 1);
            //if(!$this->is_login()) $this->ajaxReturn(result_ajax('301', '请先登录'));
            $order_amount = (double)I('post.order_amount');
            $attend_id = I('post.attend_id', 0, 'int');
            $act_id = I('post.act_id', 0, 'AesDeCrypt');

            //检查参与信息表是否存在
            $UsersActivity = new Model\UsersActivityModel();
            $attend_info = $UsersActivity->get_attend_info($attend_id);
            if(!$attend_info || $attend_info['action_id'] != $act_id) $this->ajaxReturn(result_ajax('307', '活动信息不存在！'));

            if(!$order_amount) $this->ajaxReturn(result_ajax('303', '缺少支付金额参数！'));
            if(!$attend_id && $act_id) $this->ajaxReturn(result_ajax('302', '缺少参数！'));
            $order_model = new Model\OrderModel();
            $result = $order_model->add_order(intval($this->login_info['user_id']), $order_amount, $attend_id, $act_id);

            if(!$result or !($order_info = $order_model->get_order(['order_id'=>$result]))) $this->ajaxReturn(result_ajax('401', '支付失败！'));

            $pay_info = $order_model->get_payment(4, true);
            if(!$pay_info) $this->ajaxReturn(result_ajax('503', '支付失败没有找到支付方式！'));
            $act_info = M('FavourableUserActivity')
                ->where(['id'=>(int)$order_info['act_id']])
                ->find();

            //查找支付日志
            $where = array(
                'order_id'      =>  intval($order_info['order_id']),
                'order_amount'  =>  $order_info['order_amount'],
                'user_id'       =>  intval($this->login_info['user_id']),
                'pay_id'        =>  intval($pay_info['pay_id']),
                'pay_result_time'=> 0,
            );
            $pay_model = M('PayLog');
            $pay_log = $pay_model->where($where)->find();
            if (!$pay_log) {
                $where['add_time'] = time();
                if ($pay_model->add($where)) {
                    $log_data = array(
                        'log_id'        =>  $pay_model->getLastInsID(),
                        'pay_result_id' =>  ''
                    );
                } else {
                    $log_data = array();
                    $this->error('支付失败NO.03');
                }
            } else {
                $log_data = array(
                    'log_id'        =>  $pay_log['log_id'],
                    'pay_result_id' =>  $pay_log['pay_result_id'],
                    'pay_result_time' =>  $pay_log['pay_result_time']
                );
            }
            $class = '\Sales\Plugins\payment\\' . $pay_info['pay_code'];
            $payment = new $class;
            $order_info['pay_id'] = $pay_info['pay_id'];
            $this->ajaxReturn($payment->get_code($order_info, $log_data, $this->login_info, $act_info));
        }
    }

    /**
     * 应用网关
     */
    public function gateway(){
        \Think\Log::record('==========================GETEWAY 支付异步回调记录开始===' .  microtime('true') . '===============' . "\n" . '回调URL：' . $this->cur_url,'DEBUG');
        \Think\Log::record('回调数据：' . $GLOBALS["HTTP_RAW_POST_DATA"],'DEBUG');
        \Think\Log::record("=============支付返回信息==========\nGET：" . print_r($_GET,1) . "\nPOST：" . print_r($_POST,1) . "\n",'DEBUG');
        $pay_id = I('get.sid', 0, 'intval');
        if ($pay_id > 0) {
            $order_model = new Model\OrderModel();
            $pay_info = $order_model->get_payment($pay_id);
            if ($pay_info) {
                $class = '\Sales\Plugins\payment\\' . $pay_info['pay_code'];
                $payment = new $class($pay_info);
                $result = $payment->respond();
                \Think\Log::record("\n\nGETEWAY 返回插件调用\n" . print_r($result,1));
            }
        } else {
            $result = $this->result_ajax(1,'支付回调！', $_REQUEST);
            \Think\Log::record("\n\nGETEWAY 回调错误：\n" . $result,'DEBUG');
        }
        \Think\Log::record('==========================GETEWAY 支付异步回调记录结束===' .  microtime('true') . '===============' . "\n\n\n",'DEBUG');
        exit();
    }
}