<?php
namespace Wap\Controller;
use Wap\Model;
use Wap\Plugins\payment;
class DoneController extends WapController {
    protected function _initialize(){
        parent::_initialize();
    }

    public function index(){
        $this->no_cache();
//        if (!$this->check_form_token()) {
//            $this->error('请不要重复提交订单！');
//        }
        $this->void_user();
        $flow_type          = I('post.flow_type',0,'intval');//session('flow_type');
        $flow_order         = intval(session('flow_order'));
        $post['address_id']         =   I('post.address_id',0,'intval');//:2
        $post['rec_id']             =   I('post.rec_id');//[]:52
        $post['receipt']            =   I('post.receipt',0,'intval');//:0
        $post['receipt_type']       =   I('post.receipt_type');//:1
        $post['receipt_content']    =   I('post.receipt_content','','trim');//:办公用品
        $post['receipt_title']      =   I('post.receipt_title','','trim');
        $post['bonus']              =   I('post.bonus',0,'intval');
        $post['bonus_id']           =   I('post.bonus_id',0,'intval');
        $post['bonus_sn']           =   I('post.bonus_sn');//:0
        $post['integral']           =   I('post.integral',0,'intval');//:0
        $post['integral_number']    =   I('post.integral_number',0,'intval');
        $post['shipping']           =   I('post.shipping');//:0
        $post['comment']            =   I('post.comment');//:0
        $order_model                =   new Model\OrderModel();

        $total = $order_model->total($this->user_info, $post);


        foreach ($total['data']['cart_list']['list'] as &$v){
            if(!empty($v['extension_code'])){
            $aid = M('GoodsActivity')->where(array('id'=>$v['extension_code']))->getField('act_id');
            $result= curlpost('get_goods_act', array_merge(['goods_info'=>json_encode($v), 'aid'=>AesEnCrypt($aid)], C('API_APPINFO')));
            if($result['status'] != 200 || !$result['data']['is_pay'])
                $this->error('该订单中可能包含不能付款的商品！');
            }

        }

        /*
        exit($post['integral_number'] . '>' .  $this->user_info['pay_points']);*/
        /*if (!$this->check_form_token()) {
            $this->error('请不要重复提交订单！');
        } elseif ($post['integral_number'] > $this->user_info['pay_points']) {
            $this->error('您的' . $this->_CFG['integral_name'] . '额度不足，不能支付本次订单！',U('Cart/index') . '?gift');
        } else */
        if ($total['error'] > 0) {
            if ($total['url']) {
                $this->error($total['msg'],$total['url']);
            } else {
                $this->error($total['msg']);
            }
        } else {
            $order_fee = $total['data']['total'];
            $cart_list = $total['data']['cart_list'];
            $consignee = $total['data']['consignee'];

            $order_sn = $order_model->create_order();
            $add_order = array(
                'order_sn'          =>  $order_sn,
                'agency_id'         =>  0,
                'inv_type'          =>  '',
                'tax'               =>  0,
                'discount'          =>  0,
                'user_id'           =>  $this->user_info['user_id'],
                'extension_code'    =>  $flow_type  ? $flow_type : '',
                'extension_id'      =>  $flow_order['extension_id'] ? intval($flow_order['extension_id']) : 0,
            );

            if ($order_model->add($add_order)) {          //生成订单
                $insertID = $order_model->getLastInsID();
                $data = array(      //订单信息
                    'order_status'      => OS_CONFIRMED,
                    'shipping_status'   => SS_UNSHIPPED,
                    'pay_status'        => PS_UNPAYED,
                );
                $update_order = array(
                    'order_sn'          =>  $order_sn,
                    'store_id'          =>  $order_fee['store_id'],
                    //收货信息
                    'address_id'        =>  $consignee['address_id'],
                    'consignee'         =>  $consignee['consignee'],
                    'country'           =>  $consignee['country'],
                    'province'          =>  $consignee['province'],
                    'city'              =>  $consignee['city'],
                    'district'          =>  $consignee['district'],
                    'address'           =>  $consignee['address'],
                    'zipcode'           =>  $consignee['zipcode'],
                    'tel'               =>  $consignee['tel'],
                    'mobile'            =>  $consignee['mobile'],
                    'email'             =>  $consignee['email'],
                    'best_time'         =>  $consignee['best_time'],
                    'sign_building'     =>  $consignee['sign_building'],
                    'postscript'        =>  $post['comment'],
                    'from_ad'           =>  0,
                    'referer'           =>  $_SERVER['referer'] == '' ? '' : $_SERVER['referer'] ,

                    'goods_amount'      =>  $order_fee['goods_price'],
                    'order_amount'      =>  $order_fee['amount'],
                    'order_integral'    =>  $order_fee['integral'],
                    'shipping_fee'      =>  $order_fee['shipping_fee'],
                    'insure_fee'        =>  0,     //保价
                    'pay_fee'           =>  $order_fee['amount'],      //支付金额
                    'surplus'           =>  0,        //余额

                    'add_time'          =>  $this->time,
                    'confirm_time'      =>  $this->time,
                    /*'extension_code'    =>  intval($flow_type),//购物车类型session('extension_code') ? session('extension_code') : '',
                    'extension_id'      =>  $flow_order['extension_id'] ? intval($flow_order['extension_id']) : 0,*/
                    //优惠券信息
                    /*'bonus'             =>  $this->user_info['user_id'],
                    'bonus_id'          =>  $time,*/
                    //'invoice_no'   =>  $time,
                    //发票信息
                    /*'inv_payee'   =>  $this->user_info['user_id'],
                    'inv_content'   =>  $this->user_info['user_id'],
                    'inv_type'   =>  $time,*/
                    //使用积分信息
                    'integral'      =>  $post['integral_number'],
                    'integral_money'   =>  0,
                    //运输信息
                    /*'shipping_id'   =>  $this->user_info['user_id'],
                    'shipping_name'   =>  $this->user_info['user_id'],*/
                    //支付信息
                    /*'pay_id'   =>  $this->user_info['user_id'],
                    'pay_name'   =>  $this->user_info['user_id'],*/
                    //缺货信息
                    /*'how_oos'   =>  $this->user_info['user_id'],
                    'how_surplus'   =>  $this->user_info['user_id'],*/
                    //来源信息
                );
                /*print_r($order_fee);
                print_r($order);exit;*/
                if ($order_model->where('order_id=' . $insertID)->save(array_merge($data,$update_order))) {     //插入订单数据
                    $order_model->add_order_log($this->user_info, $insertID, $data, '生成订单');
                    $data = array();
                    foreach ($cart_list['list'] as $val) {
                        $data[] = array(
                            'order_id'      =>  $insertID,
                            'goods_id'      =>  $val['goods_id'],
                            'goods_name'    =>  $val['goods_name'],
                            'goods_sn'      =>  $val['goods_sn'],
                            'product_id'    =>  $val['product_id'],
                            'goods_number'  =>  $val['goods_number'],
                            'market_price'  =>  $val['market_price'],
                            'goods_price'   =>  $val['goods_price'],
                            'goods_attr'    =>  $val['goods_attr'] ? $val['goods_attr'] : '',
                            'goods_attr_id' =>  $val['goods_attr_id'],
                            'send_number'   =>  0,
                            'is_real'       =>  $val['is_real'],
                            'extension_code'=>  $val['extension_code'] ? $val['extension_code'] : '',
                            'is_gift'       =>  $val['is_gift'],
                            'integral'      =>  $val['integral']
                        );
                    }
                    if (!empty($data)) {
                        //来自U('Checkout/index')的表单令牌验证
//                        $this->check_token();
                        M('OrderGoods')->addAll($data);     //插入订单产品
                        $where = $order_model->db_create_in($cart_list['total']['ids'],'rec_id');
                        M('cart')->where($where)->delete();         //清除购物车
                        M('CartAttr')->where($where)->delete();     //清除购物车属性
                        $this->redirect('Done/Pay?id=' . $order_sn);
                    } else {
                        $this->error('订单提交失败！NO.03','',1000);
                    }
                } else {
                    $this->error('订单提交失败！NO.02');
                }
            } else {
                $this->error('订单提交失败！NO.01');
            }
        }
    }


/**
* 订单提交选择支付方式
* @param int $id
*/
    function pay($id = 0){
        /*if (!$this->check_form_token()) {
            $this->error('请不要重复提交！');
        } else {*/
        $this->void_user();
        $id = I('get.id',"",'stripslashes');
        if ($id == ''){
            $this->redirect('Cart/index');
        }
        $order_model = new Model\OrderModel();
        $status = array(        //已确认未付款订单
            'order_status'      =>  OS_CONFIRMED,
            'shipping_status'   =>  SS_UNSHIPPED,
            'pay_status'        =>  PS_UNPAYED
        );
        $order_info = $order_model->get_order_info($this->user_id, $id, $status);

        if (!$order_info){
            $this->redirect('Cart/index');
        }

        //付款超时
        $out_time = $this->_CFG['auto_cancel']*24*3600;
        if(time()-strtotime($order_info['add_time'])-$out_time>0)
            $this->error("系统等待支付超过时限，请重新购买");

        $order_info['goods_number'] = $order_model->get_order_goods_count($order_info['order_id']);//M('order_goods')->where('order_id=' . $order_info['order_id'])->getfield('sum(goods_number)');
        $this->assign('order_info', $order_info);
        $this->assign('is_weixin', $this->is_weixin);
        $pay = $order_model->get_payment(0,$this->is_weixin);
        foreach ($pay as &$v) {
            $v['enabled'] = 1;
            if ($v['pay_code'] == 'balance') {
                $v['pay_name'] .= '(余额：￥' . sprintf('%.2f',$this->user_info['user_money']) . ')';
                $v['pay_logo'] = $this->_CFG['shop_logo'];
                if ($this->_CFG['use_surplus']) {
                    $v['enabled'] = 0;
                    $v['click_msg'] = '系统已关闭余额支付功能，请改用其它支付功能支付！';
                } elseif ($this->user_info['user_money'] < $order_info['order_amount']) {
                    $v['enabled'] = 0;
                    $v['click_msg'] = '余额不足,不能使用余额支付！';
                }
            }
        }
        $this->assign('pay_list', $pay);//print_r($pay);
        $url = array(
            'orderfrm_sub'=>U('Done/go')
        );
        $this->assign('url', $url);
        $this->assign('is_weixin', $this->is_weixin);
        $this->assign('user_info', $this->user_info);
        $this->assign('user_head', array('title'=>'订单付款页','backUrl'=>get_server('WAP_SERVER', '/store/info', ['id'=>$this->store_token, 'store_token'=>$this->store_token]),'backText'=>'首页', 'backText'=>'首页'));
        $this->assign('tkd', ['title'=>'订单付款']);
        $this->set_form_token();
        $this->display('checkout:pay');
        /*}*/
    }

    /**
     * 跳转付款页面
     * @param int $pay
     * @param int $order_sn
     */
    public function go($pay = 0, $order_sn = 0){
        //http://www.jiniu.cc/Wap/Weixin/jsApiCall/pay_sn/780505673252915737/token/100184/wecha_id/o_7iQt0SQ0mbcfM2qmWYAQxe6YTI
        $pay_id = intval($pay);
        $order_sn = stripslashes(trim($order_sn));
        if (!$this->check_form_token() or !IS_POST) {
            $this->error('提交失败！',U('pay?id=' . $order_sn));
        } else {
            $this->void_user(U('pay') . '?id=' . $order_sn);
            $order_model = new Model\OrderModel();
            $pay_info = $order_model->get_payment($pay_id, $this->is_weixin);
            if (!$pay_info and $pay_id <= 0) {
                $this->error('支付失败NO.01！');
            } else {
                $where = array(
                    'order_status'      => OS_CONFIRMED ,
                    'shipping_status'   => SS_UNSHIPPED ,
                    'pay_status'        => PS_UNPAYED
                );
                //$wb = parent::change_integral(1);        //取用户微币
                $order_info = $order_model->get_order_info($this->user_id, $order_sn, $where);
                if (!$order_info) {
                    $this->error('订单不存在或此订单不是当前用户订单！');
                } else {

                    //检查店铺余额
                    $stock_model = new Model\StockModel();
                    $store_info = $stock_model->get_store_info(array('s.store_id'=> $order_info['store_id']));
                    if(intval($this->_CFG['order_radio']) > 0 and intval($store_info['store_money']) < intval($store_info['min_sale_money'])) {
                        $this->error('商品库存不足，无法继续支付，请联系商家！');
                    }

                    /*if (intval($wb) < intval($order_info['order_integral'])) {
                        $this->error('用户' . $this->_CFG['integral_name'] . '不足, 无法继续支付！NO:1', U('pay?id=' . $order_sn));
                    }*/
                    //保存支付信息
                    $data = array(
                        'pay_id'    =>  $pay_info['pay_id'],
                        'pay_name'  =>  $pay_info['pay_name']
                    );
                    $order_info['pay_id'] = $pay_info['pay_id'];
                    $order_model->where("order_id='{$order_info['order_id']}'")->save($data);
                    //查找支付日志
                    $where = array(
                        'order_id'      =>  $order_info['order_id'],
                        'order_amount'  =>  $order_info['pay_fee'],
                        'pay_id'        =>  $pay_info['pay_id'],
                        'user_id'       =>  $this->user_id,
                        'pay_result_time'=> 0,
                        //'pay_result_id' =>  0
                    );
                    $pay_model = M('PayLog');
                    $pay_log = $pay_model->where($where)->find();
                    if (!$pay_log) {
                        $where['add_time'] = $this->time;
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

                    $class = '\Wap\Plugins\payment\\' . $pay_info['pay_code'];
                    $payment = new $class();
                    $payment->get_code($order_info, $log_data, $this->user_info);
                    /*if ($result['error'] > 0) {
                        $this->logger("当前订单：" . print_r($order_info['order_sn'],1) . "\n\n\n" . print_r($result,1));
                        //$this->error('支付失败，请与管理员联系！');
                        $this->error($result['msg']);
                    } else {*/
                    $this->assign('user_head', array('title'=>$pay_info['pay_name'] . '安全支付','backUrl'=>U('Order/index'),'backText'=>'会员中心'));
                    $this->display('checkout:go');// . $pay_info['pay_code']);
                    /*}*/
                }
            }
        }
    }

    /**
     * 应用网关
     */
    public function gateway(){
        debug('==========================GETEWAY 支付异步回调记录开始===' .  microtime('true') . '===============' . "\n" . '回调URL：' . $this->cur_url, 1);
        debug('回调数据：' . $GLOBALS["HTTP_RAW_POST_DATA"], 1);
        debug("=============支付返回信息==========\nGET：" . print_r($_GET,1) . "\nPOST：" . print_r($_POST,1) . "\n", 1);
        $pay_id = I('get.sid', 0, 'intval');
        if ($pay_id > 0) {
            $order_model = new Model\OrderModel();
            $pay_info = $order_model->get_payment($pay_id);
            if ($pay_info) {
                $class = '\Wap\Plugins\payment\\' . $pay_info['pay_code'];
                $payment = new $class();
                $result = $payment->respond($this);
                debug("\n\nGETEWAY 返回插件调用\n" . print_r($result,1), 1);
                /*if ($result['error'] == 0) {
                    //修改订单状态、减商城余额、减积分、送积分、添加拥金
                    $res = $result['contents'];
                    $this->brokerage2($res['order_id'], $this->user_id);
                }*/
            }
        } else {
            $result = $this->result_ajax(1,'支付回调！', $_REQUEST);
            debug("\n\nGETEWAY 回调错误：\n" . $result, 1);
        }
        debug('==========================GETEWAY 支付异步回调记录结束===' .  microtime('true') . '===============' . "\n\n\n", 1);
        exit();
    }
    /**
     * 支付回调页面
     */
    public function Response(){
        header("Cache-control:no-cache,no-store,must-revalidate");
        header("Pragma:no-cache");
        header("Expires:0");
        $this->void_user();
        $pay_id = I('get.sid', 0, 'intval');
        \Think\Log::record('支付返回记录==================' . "\n" . '回调URL：' . $this->cur_url);
        \Think\Log::record('回调数据：' . $GLOBALS["HTTP_RAW_POST_DATA"]);
        \Think\Log::record("=============支付宝支付返回信息==========\nGET：" . print_r($_GET,1) . "\nPOST：" . print_r($_POST,1));
        if ($pay_id > 0) {
            $order_model = new Model\OrderModel();
            $pay_info = $order_model->get_payment($pay_id);
            if ($pay_info) {
                $class = '\Wap\Plugins\payment\\' . $pay_info['pay_code'];
                $payment = new $class();
                $result = $payment->result($this->user_info);
                $this->logger("支付返回插件调用\n" . print_r($result,1));
                if ($result['error'] > 0) {
                    $this->error($result['msg']);
                } else {
                    $this->redirect('PaySuccess');
                }
            } else {
                $this->redirect('PayError');
            }
        } else {
            $this->redirect('PayError');
        }
    }

    public function balance_pay(){
        $this->void_user();
        if (!$this->check_form_token() or !IS_POST) {
            $result = $this->result_ajax(10,'提交失败！');
        } else {
            /** @var \Wap\Plugins\payment\ $paymat */
            $this->user_info['pay_points'] = parent::change_integral(1);        //取用户微币
            $class = '\Wap\Plugins\payment\balance';
            $payment = new $class();
            $result = $payment->pay($this->user_info);
        }
        $this->ajaxReturn($result);
    }

    public function PayError(){
        $result = array(
            'error'     => 1,
            'message'   =>  '支付失败',
            'desc'      =>  '',
            'url'       =>  U('Order/index')
        );
        $this->assign('result', $result);
        $this->display('payresponse');
    }

    public function PaySuccess(){
        $result = array(
            'error'     => 0,
            'message'   =>  '支付成功',
            'desc'      =>  '',
            'url'       =>  U('Order/index')
        );
        $this->assign('result', $result);
        $this->display('payresponse');
    }

    /**
     * 支付微币orderModel.class.php中调用
     * @param int $user_id
     * @param int $order_sn
     * @param $wb
     * @return bool|int|void
     */
    public function change_integral($user_id, $order_sn, $wb){
        if (intval($user_id) <= 0 or !$order_sn) {
            return result_ajax(340,'调用错误！',['user_id'=>$user_id,'order_sn'=>$order_sn,'wb'=>$wb]);
        }
        $order_model = new Model\OrderModel();
        $order_info = $order_model->get_order_info(intval($user_id), xss_filter($order_sn));
        if ($order_info) {
            $this->user_id = $user_id;
            parent::change_integral($order_sn, $wb);
        } else {
            return result_ajax(341,'调用错误！');
        }
    }

    protected function brokerage2($orderid, $user_id = 0){
        if (!is_numeric($orderid))return $this->result_ajax(10,'订单号错误');
        //检查已付款订单是否存在
        $proxy_ratio = .1;
        $three_and_ds_ratio = .6;   //三级分销与董事分拥比例
        $order_model = new Model\OrderModel();
        $user_model = new Model\UsersModel();
        $store_model = new Model\StockModel();
        /*$orders = $order_model
            ->where('(order_sn="' . $orderid . '" or order_id="' . $orderid . '") and order_status=' . OS_CONFIRMED)
            ->find();//echo $order_model->getLastSql();*/
        $orders = $order_model->get_order_info($user_id,$orderid,['order_status'=>OS_CONFIRMED,'shipping_status'=>SS_UNSHIPPED,'pay_status'=>PS_PAYED]);
        if ($orders) {
            $orders['pay_fee'] = 100;
            if ($orders['pay_fee'] <= 0){
                $result = $this->result_ajax(1,'支付金额小于1，不能分拥！');
            } else {
                $inviter = $user_model->get_user_inviter1($orders['user_id']);
                $order_goods = $order_model->get_order_goods($orders['order_id']);//$this->debug($order_goods,1);exit('end');
                $store_info = $store_model->get_store_info('s.store_id=' . intval($orders['store_id']));
                $user_rank = $user_model->get_user_rank();

                if ($order_goods) {
                    $time = time();
                    $orders_ratio = 0;
                    $brok_ratio = $data = [];//分配比例
                    $gaving_kp = 0;     //赚送KP
                    $brok_pee = 0;      //参与分层的价格
                    foreach ($order_goods as $k => $v) {
                        if ($v['split_ratio'] > 1) {        //三级分销
                            $orders_ratio += $v['split_ratio'] / 100;
                            $brok_pee += $v['goods_price'];
                            $i = 1;
                            foreach ($inviter as $kk => $vv) {
                                $brok_ratio[$i - 1] += (integer)$v['level' . $i] / 100;
                                if ($k == count($order_goods) - 1) {
                                    $brok_ratio[$i - 1] = $brok_ratio[$i - 1] / count($order_goods);
                                }
                                $i++;
                            }
                        } else {                //KP返利
                            $gaving_kp += $v['giving_kp'];
                        }
                    }
                    $orders_ratio /= count($order_goods);       //订单分层比例
                    $layered = $brok_pee * $orders_ratio;//订单分层金额
                    $proxy_fee = $layered * $proxy_ratio;//代理商费用
                    $manager_fee = $layered * $three_and_ds_ratio;     //三级分销
                    $distribution_fee = $layered - $proxy_fee - $manager_fee;//三级董事
                    /*echo "上级用户：" . print_r($inviter,1) . "\n";
                    echo "产品列表：" . print_r($order_goods,1) . "\n";
                    echo "订单费用：" . $brok_pee . "\n";
                    echo "分拥比例：" . $orders_ratio . "\n";
                    echo "分拥金额：" . $layered . "\n";
                    echo "代理费用：" . $proxy_fee . "\n";
                    echo "三级费用：" . $manager_fee . "\n";
                    echo "董事费用：" . $distribution_fee . "\n";
                    echo "分销比例：" . print_r($brok_ratio,1) . "\n";
                    echo "店铺信息：" . print_r($store_info,1) . "\n";*/
                    $userLevel = M('UserLevel');
                    $relevel = $userLevel->field('*')
                        ->where(array('orderid' => $orders['order_id']))
                        ->select();
                    if ($relevel) {
                        $result = $this->result_ajax(2,'该订单已返拥！');
                    } else {
                        foreach ($inviter as $k => $v) {
                            $ds = [     //基础数据
                                'orderid'       => $orders['order_id'],
                                'store_id'      => $orders['store_id'],
                                'uid'           => $v['user_id'],
                                'level'         => $k + 1,
                                'goods_count'   => count($order_goods),
                                'order_fee'     => $brok_pee,
                                'addtime'       => $time,
                            ];
                            if ($k == 0 and $proxy_fee > 0) {               //代理分拥
                                $ds['money']   = $this->get_float($proxy_fee);
                                $ds['stype']   =  YJ_TYPE_2;
                                $ds['readme']  = '区域代理分拥：'  . $proxy_fee;
                                $data[] = $ds;
                            }
                            if ($v['user_rank'] >= intval($this->_CFG['share_rank'])) {      //会员及以上级别才可以返拥
                                $ds['money']   = $this->get_float($manager_fee * $brok_ratio[$k]);
                                $ds['stype']   =  YJ_TYPE_0;
                                $ds['readme']  = '三级分销分拥：'  . $manager_fee . ' * ' . $brok_ratio[$k];
                                $data[] = $ds;
                            }
                            if ($v['user_rank'] >= 3) {      //董事级别才可以返拥
                                $ds['money']   =  $this->get_float($distribution_fee * $v['rank_brok'] / 100);
                                $ds['stype']   =  YJ_TYPE_1;
                                $ds['readme']  = '董事分拥：' . $distribution_fee . ' * ' . $v['rank_brok'] / 100;
                                $data[] = $ds;
                            }
                        }
                        $userLevel->addall($data);
                        $user_model->gaving_kp($orders['user_id'],$orders['order_id'], $gaving_kp);
                        $result = $this->result_ajax(0,'提取完成',$data);
                    }
                } else {
                    $result = $this->result_ajax(2,'该订单下无产品！');
                }
            }
        } else {
            $result = $this->result_ajax(3,'订单不存在！');
        }
        return $result;
    }
    /**
     * 返拥2
     * @param array $orderid
     * @return array
     */
    protected function brokerage1($orderid){
        if (!is_numeric($orderid))return false;
        //检查已付款订单是否存在
        $order_model = new Model\OrderModel();
        $user_model = new Model\UsersModel();
        /*$orders = $order_model->get_order_info($this->user_id,$orderid,['order_status'=>1,'shipping_status'=>0,'pay_status'=>1]);*/
        $orders = $order_model
            ->where('(order_sn="' . $orderid . '" or order_id="' . $orderid . '") and order_status=' . OS_CONFIRMED)
            ->find();//echo $order_model->getLastSql();
        if ($orders) {
            /*$order_goods = M('OrderGoods')
                ->alias('og')
                ->field('og.*,l.level1,l.level2,l.level3,l.level4,l.level5')
                ->where('order_id=' . $orders['order_id'])
                ->join('LEFT JOIN __GOODS_LEVEL__ l ON og.goods_id=l.goods_id')
                ->select();*/
            $order_goods = $order_model->get_order_goods($orders['order_id']);//$this->debug($order_goods,1);exit('end');
            if ($order_goods) {
                #产线当前代理商活动的一级，二级，三级佣金分别是多少
                $user = $user_model->where('user_id=' . $orders['user_id'])->find();
                //$this->debug($orders,1);
                $proxy = M('StoreProxy')
                    ->field('first_commission as commission1,second_commission as commission2,
                    third_commission as commission3')
                    ->where('store_id=' . $orders['agency_id'])
                    ->order("end_time desc")
                    ->find();
                $relevel = M('UserLevel')->field('orderid,openid,uid,shopid')
                    ->where(array('orderid' => $orders['order_id']))
                    ->select();
                $inviter = $user_model->get_user_inviter1($orders['user_id']);
                $user_rank = $user_model->get_user_rank();//TODO:需要添加董事返拥功能;
                /*echo '代理活动$proxy：' . print_r($proxy, 1) . '<br />';
                echo '是否拥金$relevel：' . print_r($relevel, 1) . '<br />';
                echo '该用户上级$inviter：' . print_r($inviter, 1) . '<br />';
                echo '订单产品$order_goods：' . print_r($order_goods, 1) . '<br />';
                echo '当前用户$user：' . print_r($user, 1) . '<br />';*/
                $data = array();
                $time = $this->time;
                foreach ($order_goods as $k => $v) {
                    if($v['goods_price'] > 0){
                        foreach ($inviter as $kk => $val) {        #判断该上级是否已返拥
                            $is_level = false;
                            foreach ($relevel as $key => $value) {//满足3条件后确定是否已返拥
                                if ($value['orderid'] == $orders['order_id']     #拥金表订单ID对应订单ID
                                    and $value['uid'] == $val['user_id']      #该用户上级对应拥金表UID
                                    and $value['shopid'] == $v['goods_id']) { #拥金表产品ID对应订单产品ID
                                    $is_level = true;
                                    break;
                                }
                            }
                            if (!$is_level) {
                                $proxy_level = !empty($val['proxy_state'])? $proxy['commission' . ($kk + 1)]: 0;
                                $userone = $v['goods_price']
                                    * ($v['level' . ($kk + 1)] + $proxy_level) / 100
                                    * $v['goods_num'];
                                $data[] = array(
                                    'orderid'       => $orders['order_id'],
                                    'stype'         => 0,
                                    'shopid'        => $v['goods_id'],
                                    'shopname'      => $v['goods_name'],
                                    'openid'        => $val['openid'],
                                    'token'         => $val['store_id'],
                                    'uid'           => $val['member_id'],
                                    'member_name'   => $val['member_name'],
                                    'level'         => $kk + 1,
                                    'sum'           => $v['goods_num'], #数量待完善
                                    'money'         => $userone,
                                    'summoney'      => $v['goods_price'] * $v['goods_num'],
                                    'addtime'       => $time
                                );
                            }
                        }
                    }
                }
                if ($data) {
                    //M('UserLevel')->addall($data);
                    //$this->debug($data,1);
                    return array('error'=>1,'message' => '返拥完成！','contents'=>$data);
                } else {
                    return array('error'=>1,'message' => '该订单已返拥！');
                }
            } else {
                return array('error'=>2,'message' => '该订单无产品信息！');
            }
        } else {
            return array('error'=>3,'message' => '未找到订单！');
        }
    }


    public function test_brok(){
        $sid = I('get.sid',0);
        $uid = I('get.uid',0);
        $result = $this->brokerage2($sid,$uid);
        print_r($result);
    }

    public function jiniuPay(){
        if (!$this->check_form_token() or !IS_POST) {
            return result_ajax('302', '提交失败！');
        } else {
            $order_amount = (double)I('order_amount');
            $user_act_id = (int)I('user_act_id');
            if(!$order_amount) return result_ajax('303', '缺少支付金额参数！');
            if($this->is_login()) return result_ajax('301', '请先登录');
            $order_model = new Model\OrderModel();
            $order_info = array(
                    'order_on'  => $order_model->create_order(),
                    'user_id'   => $this->login_info['user_id'],
                    'add_time'  => time(),
                    'order_amount' => $order_amount,
                    'order_status'      => OS_CONFIRMED ,
                    'shipping_status'   => SS_UNSHIPPED ,
                    'pay_status'        => PS_UNPAYED,
                    'pay_id'            => 4,
                    'pay_name'          => 'wxpay',
                    'pay_note'          => 'jiniu',
                    'goods_id'          =>  $user_act_id
                );

            if(!($order_id = $order_model->add($order_info))) return result_ajax('401', '支付失败！');


            //查找支付日志
            $where = array(
                'order_id'      =>  $order_info['order_id'],
                'order_amount'  =>  $order_info['order_amount'],
                'pay_id'        =>  $order_info['pay_id'],
                'user_id'       =>  $this->user_id,
                'pay_result_time'=> 0,
                //'pay_result_id' =>  0
            );
            $pay_model = M('PayLog');
            $pay_log = $pay_model->where($where)->find();
            if (!$pay_log) {
                $where['add_time'] = $this->time;
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

            //$class = '\Wap\Plugins\payment\\' . $pay_info['pay_code'];
            $payment = new \Wap\Plugins\payment\wxpay();
            $payment->get_code($order_info, $log_data, $this->user_info);
            $this->assign('user_head', array('title'=>$order_info['pay_name'] . '安全支付','backUrl'=>U('Order/index'),'backText'=>'会员中心'));
            $this->display('checkout:go');// . $pay_info['pay_code']);
        }
    }
}