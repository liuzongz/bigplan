<?php
namespace Wap\Model;
use Wap\Model;
class OrderModel extends BaseModel {
    public $auto_time = array();
    protected $tableName = 'order_info';


    /**
     * 获取订单产品数量
     * @param $order_id
     * @return mixed
     */
    public function get_order_goods_count($order_id) {
        return M('order_goods')->where('order_id=' . $order_id)->getfield('sum(goods_number)');
    }
    /**
     * 支付回调日志记录
     * @param $data
     */
    public function add_gateway_log($data){
        $add_data = [
            'get_type'  =>  $data['pay_type'],
            'get_url'   =>  ($_SERVER['HTTPS'] != "on" ? 'http://' : 'https://') .  $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'data'      =>  print_r($data, 1),
            'add_time'  =>  time(),
            'ip'        =>  get_client_ip(),
        ];
        M('GatewayLog')->data($add_data)->add();
        $this->base_money($data['total_fee'], '订单：' . $data['order_sn'] . '支付费用，支付来源：' . $data['pay_type']);
    }

    /**
     * 虚拟产品自动发货
     * @param $order
     */
    public function delivery_order($order){

        //获取快递信息
        $express    = array('id'=>0, 'name'=>'');

        //修改订单状态
        $order_data = array(
            'order_status'      => OS_SPLITED,
            'shipping_status'   => SS_SHIPPED,
            'shipping_time'     => NOW_TIME
            );
        $order_status = M('order_info')->where(array('order_id'=>$order['order_id']))->save($order_data);
        if(!$order_status) return result_ajax(301, '发货订单状态修改失败！');

        $model = M('DeliveryOrder');
        //插入前删除不应该出现的垃圾数据
        $model->where(array('order_id'=>$order['order_id']))->delete();

        //查询店铺默认发货地址
        $seller_address = M('SellerAddress')->where(array('store_id'=>$order['store_id'],'default'=>1))->find();
        if(!$seller_address) return result_ajax(302, '发货订单状态修改失败！');

        //插入发货记录
        $data       = array(
            'delivery_sn'   => create_sn('DSN'),
            'status'        => 12,
            'seller_remark' => "系统自动发货",
            'express_no'    => '',
            'add_time'      => NOW_TIME,
            'update_time'   => NOW_TIME,
            'express_id'    => 0,
            'express_name'  => '',
            'order_sn'      => $order['order_sn'],
            'order_id'      => $order['order_id'],
            'user_id'       => $order['user_id'],
            'agency_id'     => $order['agency_id'],
            'best_time'     => $order['best_time'],
            'postscript'    => $order['postscript'],
            'how_oos'       => $order['how_oos'],
            'insure_fee'    => $order['insure_fee'],
            'shipping_fee'  => $order['shipping_fee'],
            'consignee'     => $seller_address['contact'],
            'address'       => $seller_address['address'],
            'province'      => $seller_address['province'],
            'city'          => $seller_address['city'],
            'district'      => $seller_address['district'],
            'sign_building' => $seller_address['company'],
            'tel'           => $seller_address['tel'],
            'country'       => 1,
            'email'         => '',
            'zipcode'       => '',
            'mobile'        => '',
            'suppliers_id'  => '',
        );
        $deliver_id = $model->add($data);
        if(!$deliver_id){
            //恢复订单状态
            $this->table('__ORDER_INFO__')
                ->field('order_status,shipping_status,pay_status,order_id')
                ->where(array('order_id'=>$order['order_id']))
                ->save(array(
                    'order_status'      => $order['order_status'],
                    'shipping_status'   => $order['shipping_status'],
                    'pay_status'        => $order['pay_status']
                ));
            return result_ajax(303, '发货失败！');
        }

        return result_ajax(200, '发货成功');
    }

    /**
     * 支付回调功能
     * @param $result
     * @param $obj
     * @return array
     */
    public function payed_result($result, $obj){
        \Think\Log::record('回调记录payed_result:' . print_r($result,1));
        $this->add_gateway_log($result);
        $order_info = $this->get_order_info1(
            $result['order_sn'],
            [
                'order_status'      => OS_CONFIRMED ,
                'shipping_status'  => SS_UNSHIPPED ,
                'pay_status'       => PS_UNPAYED
            ]
        );
        if ($order_info and $order_info['order_id'] > 0) {
            $user_info = M('users')->where('user_id=' . $order_info['user_id'])->find();
            if ($user_info) {
                $log_where = [
                    'order_id'      =>  $order_info['order_id'],
                    'order_amount'  =>  $order_info['pay_fee'],
                    'pay_id'        =>  $order_info['pay_id'],
                    'user_id'       =>  $order_info['user_id'],
                ];
                $log = M('PayLog');
                if ($log->where($log_where)->count() > 0) {
                    if ($result['pay_type'] == 'wxpay') {           //微信返回信息填充
                        $where = array(       //查询条件
                            'order_id'      =>  $order_info['order_id'],
                            'user_id'       =>  $order_info['user_id'],
                            'pay_type'      =>  $order_info['pay_id'],
                            'handle_name'   =>  '统一下单'
                        );
                        M('PaydataLog')->where($where)->data(array('result_data'=>print_r($result,1)))->save();     //保存接收的数据
                    }

                    //设置已付款

                    $data = [
                        'pay_status'=> PS_PAYED,
                        'pay_time'  => $result['time_end'] ? $result['time_end'] : time(),
                        //'pay_fee'   => $result['total_fee']
                    ];
                    $order_info['pay_status']   = $data['pay_status'];
                    $order_info['pay_time']     = $data['pay_time'];
                    //$order_info['pay_fee']      = $data['pay_fee'];
                    $this->where('order_id=' . $order_info['order_id'])->save($data);
                    //填写付款回单
                    $log_save_data = array(
                        'pay_return_id' => $result['transaction_id'],       //返回的支付序列号
                        'pay_result_id' => 0,
                        'pay_return_time' => $result['time_end'],       //支付时间
                        'is_paid'       => 1
                    );
                    $log->where($log_where)->save($log_save_data);

                    //扣除订单需要用的积分
                    if ($order_info['order_integral'] > 0) {
                        //$done_control = new \Wap\Controller\DoneController();
                        /* @var \Wap\Controller\DoneController  $obj */
                        $s = $obj->change_integral($order_info['user_id'],$order_info['order_sn'], $order_info['order_integral']);
                        //$this->change_integral($order_info['user_id'], -$order_info['order_integral'], $order_info['order_sn']);
                        if ($s['error'] > 0) {

                        }
                    }

                    //扣除店铺营销比例费用
                    if(intval($this->_CFG['order_radio']) > 0) {
                        $store_account = M('users')->where('store_id='.$order_info['store_id'])->getField('user_id');
                        $order_radio_money = $order_info['pay_fee'] * $this->_CFG['order_radio'] / 100;
                        $user_model = new UsersModel();
                        $user_model->set_store_money(['store_id'=>$order_info['store_id']],-$order_radio_money,'income','销售比例费用');

                        $user_account_id = $user_model->add_user_account(
                            ['store_id'=>$order_info['store_id']],
                            '-'.$order_radio_money,
                            ['desc'=>'店铺营销比例费用','type'=>PT_MONEY,'user_note'=>'','stage'=>ST_PAY]
                        );
                        $user_model->set_user_account($user_account_id,PS_PAYED,[
                            'pay_code'      =>'system',
                            'payment_name'  =>'系统处理',
                            'pay_account'   =>$store_account,
                            'pay_name'      =>'admin'
                        ]);
                    }

                    \Think\Log::record('支付订单回调功能:' . print_r($order_info,1));
                    \Think\Log::record('支付订单回调功能:' . print_r($s,1));

                    //扣款及返拥设置
                    $brokerage = $this->brokerage3($order_info, $result);
                    \Think\Log::record('支付订单回调分拥功能:' . print_r($brokerage,1));


                    //虚拟自动商品自动发货
                    $order_goods = $this->get_order_goods($order_info['order_id']);
                    if($order_goods[0] && intval($order_goods[0]['is_real']) > 0 && intval($order_goods[0]['is_auto']) > 0){
                        $delivery_order = $this->delivery_order($order_info);
                        \Think\Log::record('虚拟商品自动发货:' . $delivery_order);
                    }

                    //返回数据
                    $res_data = array(
                        'order_id'      =>  $order_info['order_id'],
                        'order_sn'      =>  $order_info['order_sn'],
                        'pay_id'        =>  $order_info['pay_id'],
                        'user_id'       =>  $order_info['user_id'],
                        'openid'        =>  $result['user_sn'],
                        'pay_result_id' =>  $result['transaction_id'],
                    );
                    $res = result_ajax(0,'付款成功！', $res_data);
                } else {
                    $res = result_ajax(101,'款金额与订单金额不符！');
                }
            } else {
                $res = result_ajax(102,'未找到该用户！');
            }
        } else {
            $res = result_ajax(103,'未找到订单！');
        }
        return $res;
    }

    protected function brokerage3($order_info, $res_data){
        $order_model = new OrderModel();
        $user_model = new UsersModel();
        //$store_model = new StockModel();
        if ($order_info and $order_info['order_id'] > 0) {
            if ($order_info['pay_fee'] >= 1){
                $userLevel = M('UserLevel');
                $relevel = $userLevel->field('*')
                    ->where(array('orderid' => $order_info['order_id']))
                    ->select();
                if (!$relevel) {
                    $order_goods = $order_model->get_order_goods($order_info['order_id']);
                    $inviter = $user_model->get_user_inviter2($order_info['user_id'], $order_info['store_id']);
                    krsort($inviter);
                    //$store_user = $store_model->store_user(intval($order_info['store_id']));
                    if ($order_goods) {
                        $time = time();
                        $data = [];         //分配比例
                        $goods_fee = 0;      //参与分层的价格
                        $kp_goods_fee = 0;      //KP券总价格

                        $goods_num = count($order_goods);
                        $level_money1 = $level_money2 = $level_money3 = $kp_money = $level_goods_price = 0;
                        $pay_fee = $order_info['pay_fee'] - $order_info['shipping_fee']; //减去运费后的支付金额
                        foreach ($order_goods as  $v){
                            $goods_fee += $v['goods_price'] * $v['goods_number'];
                            $paid = ['order_info'=>$order_info, 'order_goods'=>$v, 'inviter'=>$inviter];
                            //接口获取营销工具数据
                            $act_result= curlpost('get_act_info', array_merge(['act_id'=>AesEnCrypt($v['extension_code']), 'paid'=>json_encode($paid)], C('API_APPINFO')));
                            debug($act_result, 1);
                            if($act_result['status'] != 200){
                                \Think\Log::record('支付回调分拥功能:活动错误！' . $act_result['status']);
                                continue;
                            } else {
                                $result = $act_result['data']['paid'];
                                $config = $act_result['data']['config'];//获取活动属性
                                if($result['class'] === 'level'){//计算三级分总金额
                                    $level_goods_price += $v['goods_price'] * $v['goods_number'] / ($order_info['order_amount'] - $order_info['shipping_fee']) * $pay_fee;
                                    $cost   = $config['cost']['value'] / 100;
                                    foreach ($result['list'] as &$val){
                                        switch ($val['level']) {
                                            case 1:
                                                $level_money1 += $val['money'];
                                                $val['money'] = $level_money1;
                                                $level_readme1  = '三级分销分拥：'  . $level_goods_price . ' * ' . $cost .' * '. $config["level1"]['value']/100;
                                                $val['readme'] = $level_readme1;
                                                break;
                                            case 2;
                                                $level_money2 += $val['money'];
                                                $val['money'] = $level_money2;
                                                $level_readme2  = '三级分销分拥：'  . $level_goods_price . ' * ' . $cost .' * '. $config["level2"]['value']/100;
                                                $val['readme'] = $level_readme2;
                                                break;
                                            case 3:
                                                $level_money3 += $val['money'];
                                                $val['money'] = $level_money3;
                                                $level_readme3  = '三级分销分拥：'  . $level_goods_price . ' * ' . $cost .' * '. $config["level3"]['value']/100;
                                                $val['readme'] = $level_readme3;
                                                break;
                                        }

                                    }
                                    $level = $result['list'];
                                }else{
                                    \Think\Log::record('支付回调分拥功能:活动class错误' . print_r($result));
                                }

                            }
                        }

                        $ds = [     //基础数据
                            'orderid'       => $order_info['order_id'],
                            'store_id'      => $order_info['store_id'],
                            'goods_count'   => $goods_num,
                            'order_fee'     => $goods_fee,
                            'addtime'       => $time,
                            'state'         => YJ_UNAUDIT
                        ];

                        $account_data = [    //account 写入基础数据
                            'store_id'      =>  intval($order_info['store_id']),
                            'add_time'      =>  time(),
                            'stage'         =>  ST_INCOME,
                            'desc'          =>  "订单：{$order_info['order_sn']}返利收入",
                            'process_type'  =>  PT_MONEY,
                            'user_note'     =>  '',
                            'admin_note'    =>  '',
                            'pay_code'      =>  'system',
                            'payment'       =>  '系统处理',
                            'pay_account'   =>  $order_info['user_id'],
                            'pay_name'      =>  'admin',
                            'is_paid'       =>  1,
                            'paid_time'     =>  time(),
                            'cash_sn'       =>  create_sn(PREFIX_CASH)
                        ];

                        $layered_fee = 0;
                        $store_fee = $order_info['pay_fee'];
                        if(isset($level)) {//合并三级分拥数据
                            foreach ($level as $vv) {
                                $store_fee -= $vv['money'];
                                $layered_fee += $vv['money'];
                                $data[] = array_merge($ds, $vv);
                            }
                        }

                        if(!isset($level)){
                            \Think\Log::record('支付回调分拥功能:没有分拥值！');
                        }


                        //商家应进帐
                        $ds['stype']   =  YJ_TYPE_4;
                        $ds['uid']      = 0;
                        $ds['level']    = 0;
                        $ds['money']   = $this->get_float($store_fee);
                        $ds['readme']  = sprintf('商家应进帐：%s-%s-%s=%s',$res_data['total_fee'],$layered_fee,$kp_goods_fee,$this->get_float($store_fee));
                        $data[] = $ds;
                        foreach ($data as $level_info){
                            $level_id = $userLevel->add($level_info);
                            $account_data['user_id'] = (int)$level_info['uid'];
                            $account_data['amount'] = $level_info['money'];
                            $account_data['extends_id'] = $userLevel->getLastInsID();
                            $account_data['extends_type'] = EXTEND_LEVEL;
                            $account_data['cash_sn'] = create_sn(PREFIX_CASH);
                            if(isset($level) && $level_id){
                                debug('订单分拥记录：'.print_r($account_data, 1), 1);
                                //account 表写入数据
                                if(!M('UserAccount')->data($account_data)->add())debug('返佣资金走向写入失败', 1);
                            }
                            if(!$level_id) debug('返佣记录写入失败');
                        }

                        //$userLevel->addall($data);
                        //$user_model->gaving_kp($order_info['user_id'],$order_info['order_id'], $gaving_kp);
                        $result = $this->result_ajax(0,'提取完成',$data);
                    } else {
                        $result = $this->result_ajax(2,'该订单下无产品！');
                    }
                } else {
                    $result = $this->result_ajax(2,'该订单已返拥！');
                }
            } else {
                $result = $this->result_ajax(1,'支付金额小于1，不能分拥！');
            }
        } else {
            $result = $this->result_ajax(3,'订单不存在！');
        }
        return $result;
    }

    protected function brokerage33($order_info, $res_data){
        $order_model = new OrderModel();
        $user_model = new UsersModel();
        $store_model = new StockModel();
        if ($order_info and $order_info['order_id'] > 0) {
            //$order_info['pay_fee'] = 100;
            if ($order_info['pay_fee'] >= 1){
                $proxy_ratio = $this->_CFG['proxy_ratio'] / 100;
                $three_and_ds_ratio = $this->_CFG['director_ratio'] / 100;   //三级分销与董事分拥比例
                $userLevel = M('UserLevel');
                $relevel = $userLevel->field('*')
                    ->where(array('orderid' => $order_info['order_id']))
                    ->select();
                if (!$relevel) {
                    $order_goods = $order_model->get_order_goods($order_info['order_id']);
                    $inviter = $user_model->get_user_inviter2($order_info['user_id']);
                    krsort($inviter);
                    $store_user = $store_model->store_user(intval($order_info['store_id']));
                    //$store_info = $store_model->get_store_info('s.store_id=' . intval($order_info['store_id']));
                    //$user_rank = $user_model->get_user_rank();
                    //$user_info = $user_model->get_userinfo('user_id=' . $orders['user_id']);
                    if ($order_goods) {
                        $time = time();
                        $orders_ratio = 0;
                        $brok_ratio = $data = [];//分配比例
                        $gaving_kp = 0;     //赚送KP
                        $goods_fee = 0;      //参与分层的价格
                        $kp_goods_fee = 0;      //KP券总价格
                        $kp_goods_num = 0;      //KP产品数量

                        $activity =  Element\Element::createObject('activity');



                        $goods_num = count($order_goods);
                        $level_money1 = $level_money2 = $level_money3 = $kp_money = 0;
                        $level_goods_price = 0;
                        foreach ($order_goods as  $v){
                            $goods_fee += $v['goods_price'] * $v['goods_number'];

                            $activity->setData($v['extension_code'], true);
                            if(!($activity->isExpire())) continue;
                            $result = $activity->paid($order_info, $v, $inviter);
                            $config = $activity->config;//获取活动属性

                            if ($activity->error) {
                                \Think\Log::record('支付回调分拥功能:活动错误！' . $activity->error);
                                return $this->result_ajax(1,$activity->error);
                            } else {

                                if($result['class'] == 'kp'){//计算kp费用
                                    $kp_goods_fee += ($config['cost']['value'] / 100) * $v['goods_price']*$v['goods_number'];
                                    $kp = $result['list'][0];
                                    $kp_money += $kp['money']*$v['goods_number'];
                                    $kp['money'] = $kp_money;
                                    $kp['readme'] = '用户收入的KP点数：'.$kp_money;
                                    if(intval($kp['store_kp'])){
                                        $store_kp = $kp_goods_fee;
                                    }
                                    continue;
                                }elseif($result['class'] == 'level'){//计算三级分总金额
                                    $level_goods_price += $v['goods_price']*$v['goods_number'];
                                    $cost   = $config['cost']['value'] / 100;
                                    foreach ($result['list'] as &$val){
                                        switch ($val['level']) {
                                            case 1:
                                                $level_money1 += $val['money'];
                                                $val['money'] = $level_money1;
                                                $level_readme1  = '三级分销分拥：'  . $level_goods_price . ' * ' . $cost .' * '. $activity->config["level1"]['value']/100;
                                                $val['readme'] = $level_readme1;
                                                break;
                                            case 2;
                                                $level_money2 += $val['money'];
                                                $val['money'] = $level_money2;
                                                $level_readme2  = '三级分销分拥：'  . $level_goods_price . ' * ' . $cost .' * '. $activity->config["level2"]['value']/100;
                                                $val['readme'] = $level_readme2;
                                                break;
                                            case 3:
                                                $level_money3 += $val['money'];
                                                $val['money'] = $level_money3;
                                                $level_readme3  = '三级分销分拥：'  . $level_goods_price . ' * ' . $cost .' * '. $activity->config["level3"]['value']/100;
                                                $val['readme'] = $level_readme3;
                                                break;
                                        }

                                    }
                                    $level = $result['list'];
                                }else{
                                    \Think\Log::record('支付回调分拥功能:活动class错误' . print_r($result));
                                }

                            }
                        }

                        $ds = [     //基础数据
                            'orderid'       => $order_info['order_id'],
                            'store_id'      => $order_info['store_id'],
                            'goods_count'   => $goods_num,
                            'order_fee'     => $goods_fee,
                            'addtime'       => $time,
                            'state'         => YJ_UNAUDIT
                        ];

                        $layered_fee = 0;
                        $store_fee = $order_info['pay_fee'];
                        if(isset($level)) {//合并三级分拥数据
                            foreach ($level as $vv) {
                                $store_fee -= $vv['money'];
                                $layered_fee += $vv['money'];
                                $data[] = array_merge($ds, $vv);
                            }
                        }

                        if(isset($kp)) {//合并kp数据
                            $store_fee -= $kp_goods_fee;
                            $data[] = array_merge($ds, $kp);
                        }

                        if(!isset($level) && !isset($kp)){
                            \Think\Log::record('支付回调分拥功能:没有分拥值！');
                        }

                        if(isset($store_kp)){//商家获得平台返佣
                            $store_kp = floor($store_kp);
                            $ds['stype']   =  YJ_TYPE_5;
                            $ds['uid']      = $store_user['user_id'];
                            $ds['level']    = 0;
                            $ds['money']   = $store_kp;
                            $ds['readme']  = '商家kp返点：'.$store_kp;
                            $data[] = $ds;
                        }


                        //商家应进帐
                        $ds['stype']   =  YJ_TYPE_4;
                        $ds['uid']      = 0;
                        $ds['level']    = 0;
                        $ds['money']   = $this->get_float($store_fee);
                        $ds['readme']  = sprintf('商家应进帐：%s-%s-%s=%s',$res_data['total_fee'],$layered_fee,$kp_goods_fee,$this->get_float($store_fee));
                        $data[] = $ds;
                        $userLevel->addall($data);
                        //$user_model->gaving_kp($order_info['user_id'],$order_info['order_id'], $gaving_kp);
                        $result = $this->result_ajax(0,'提取完成',$data);
                    } else {
                        $result = $this->result_ajax(2,'该订单下无产品！');
                    }
                } else {
                    $result = $this->result_ajax(2,'该订单已返拥！');
                }
            } else {
                $result = $this->result_ajax(1,'支付金额小于1，不能分拥！');
            }
        } else {
            $result = $this->result_ajax(3,'订单不存在！');
        }
        return $result;
    }

     /**   http://m.hkhp.net/done/getaway/sid/1.html?
     * 返拥设置
     * @param $order_info
     * @param $res_datav
     * @return array
     */
    protected function brokerage2($order_info, $res_data){
        //if (!is_numeric($orderid))return $this->result_ajax(10,'订单号错误');
        //检查已付款订单是否存在
        $proxy_ratio = $this->_CFG['proxy_ratio'] / 100;
        $three_and_ds_ratio = $this->_CFG['director_ratio'] / 100;   //三级分销与董事分拥比例
        $order_model = new OrderModel();
        $user_model = new UsersModel();
        $store_model = new StockModel();
        if ($order_info) {
            //$order_info['pay_fee'] = 100;
            if ($order_info['pay_fee'] >= 1){
                $userLevel = M('UserLevel');
                $relevel = $userLevel->field('*')
                    ->where(array('orderid' => $order_info['order_id']))
                    ->select();
                if (!$relevel) {
                    $inviter = $user_model->get_user_inviter1($order_info['user_id']);
                    $order_goods = $order_model->get_order_goods($order_info['order_id']);//$this->debug($order_goods,1);exit('end');
                    $store_info = $store_model->get_store_info('s.store_id=' . intval($order_info['store_id']));
                    //$user_rank = $user_model->get_user_rank();
                    //$user_info = $user_model->get_userinfo('user_id=' . $orders['user_id']);
                    if ($order_goods) {
                        $time = time();
                        $orders_ratio = 0;
                        $brok_ratio = $data = [];//分配比例
                        $gaving_kp = 0;     //赚送KP
                        $goods_fee = 0;      //参与分层的价格
                        $kp_goods_fee = 0;      //KP券总价格
                        $kp_goods_num = 0;      //KP产品数量
                        foreach ($order_goods as $k => $v) {            //拆分三级分销产品与KP券产品
                            if ($v['split_ratio'] > 0) {        //三级分销
                                $orders_ratio += $v['split_ratio'] / 100;   //每个产品的分层比例相加
                                $goods_fee += $v['goods_price'] * $v['goods_number'];
                                $i = 1;
                                foreach ($inviter as $kk => $vv) {
                                    $brok_ratio[$i - 1] += ((integer)$v['level' . $i] / 100);
                                    $i++;
                                }
                            } else {                //KP返利
                                $kp_goods_num ++;
                                $kp_goods_fee += $v['goods_price'] * $v['goods_number'];
                                $gaving_kp += $v['giving_kp'];
                            }
                        }
                        $goods_num = count($order_goods);
                        foreach ($brok_ratio as $k => $v) {     //得到平均分层比例
                            $brok_ratio[$k] =  $brok_ratio[$k] / ($goods_num - $kp_goods_num);
                        }

                        $orders_ratio      /= ($goods_num - $kp_goods_num);                               //产品分层比例除产品数量，计算得到最终三级分销分层比例
                        $layered_fee        = $goods_fee * $orders_ratio;                       //分销产品总额 * 订单分层比例 = 订单分层金额
                        $proxy_fee          = $layered_fee * $proxy_ratio;                          //订单分层金额按代理比例计算代理商费用
                        $manager_fee        = ($layered_fee - $proxy_fee) * $three_and_ds_ratio;       //订单分层金额按4:6比例计算三级分销费用
                        $distribution_fee   = $layered_fee - $proxy_fee - $manager_fee;             //剩下的三级董事
                        $kp_fee             = $gaving_kp * ($store_info['kp_ratio'] / 100);                 //KP券数量 * 店铺KP券比例 = KP券费用
                        $store_fee          = $order_info['pay_fee'] - $layered_fee - $kp_fee;    //店铺应收

                        \Think\Log::record(
                            "Brokerage:" .
                            "\n订单费用：" . $goods_fee .
                            "\n分拥比例：" . $orders_ratio .
                            "\n分拥金额：" . $layered_fee  .
                            "\n代理费用：" . $proxy_fee .
                            "\n三级费用：" . $manager_fee  .
                            "\n董事费用：" . $distribution_fee .
                            "\n上级用户：" . print_r($inviter,1) .
                            "\n产品列表：" . print_r($order_goods,1) .
                            "\n分销比例：" . print_r($brok_ratio,1) .
                            "\n店铺信息：" . print_r($store_info,1) .
                            "\n"
                        );

                        $ds = [     //基础数据
                            'orderid'       => $order_info['order_id'],
                            'store_id'      => $order_info['store_id'],
                            'goods_count'   => $goods_num,
                            'order_fee'     => $goods_fee,
                            'addtime'       => $time,
                            'state'         => YJ_UNAUDIT
                        ];
                        $state = I('get.state',0,'intval');
                        if ($state == 1) $ds['state'] = YJ_HANDLE_AUDIT;
                        foreach ($inviter as $k => $v) {
                            $ds['level']    = $k + 1;
                            $ds['uid']      = $v['user_id']  ;
                            if ($k == 0 and $proxy_fee > 0) {               //代理分拥
                                $ds['money']   = $this->get_float($proxy_fee);
                                $ds['stype']   =  YJ_TYPE_2;
                                $ds['readme']  = '区域代理分拥：'  . $proxy_fee;
                                $data[] = $ds;
                                //TODO:未读取代理人ID，后期修改
                            }
                            if (($distribution_fee * $brok_ratio[$k] > 0) and $v['user_rank'] >= intval($this->_CFG['share_rank'])) {      //会员及以上级别才可以返拥
                                $ds['money']   = $this->get_float($distribution_fee * $brok_ratio[$k]);
                                $ds['stype']   =  YJ_TYPE_0;
                                $ds['readme']  = '三级分销分拥：'  . $distribution_fee . ' * ' . $brok_ratio[$k];
                                $data[] = $ds;
                            }
                            if ( (($manager_fee * $v['rank_brok'] / 100) > 0) and $v['user_rank'] >= 3) {      //董事级别才可以返拥
                                $ds['money']   =  $this->get_float($manager_fee * $v['rank_brok'] / 100);
                                $ds['stype']   =  YJ_TYPE_1;
                                $ds['readme']  = '董事分拥：' . $manager_fee . ' * ' . $v['rank_brok'] / 100;
                                $data[] = $ds;
                            }
                        }

                        if ($gaving_kp > 0) {   //当前用户应添加的KP点数
                            $ds['money']   = intval($gaving_kp);
                            $ds['stype']   =  YJ_TYPE_3;
                            $ds['readme']  = '用户就收入的IP点数：';
                            $ds['level']   = 0;
                            $ds['uid']     = $order_info['user_id'];
                            $data[] = $ds;
                        }
                        //商家应进帐
                        $ds['money']   = $this->get_float($store_fee);
                        $ds['stype']   =  YJ_TYPE_4;
                        $ds['readme']  = sprintf('商家应进帐：%s-%s-%s=%s',$res_data['total_fee'],$layered_fee,$kp_fee,$this->get_float($store_fee));
                        $ds['level']    = 0;
                        $ds['uid']      = 0;
                        $data[] = $ds;

                        $userLevel->addall($data);
                        //$user_model->gaving_kp($order_info['user_id'],$order_info['order_id'], $gaving_kp);
                        $result = $this->result_ajax(0,'提取完成',$data);
                    } else {
                        $result = $this->result_ajax(2,'该订单下无产品！');
                    }
                } else {
                    $result = $this->result_ajax(2,'该订单已返拥！');
                }
            } else {
                $result = $this->result_ajax(1,'支付金额小于1，不能分拥！');
             }
        } else {
            $result = $this->result_ajax(3,'订单不存在！');
        }
        return $result;
    }



    /**
     * 用户积分变更(本地）
     * @param $user_id
     * @param $integral
     * @param $order_id
     * @return bool|mixed
     */
    public function change_integral1($user_id, $integral, $order_id){
        $model = M('users')->where('user_id=' . $user_id);
        if ($integral >= 0) {
            $res = $model->setInc('pay_points', $integral);
        } else {
            $res = $model->setDec('pay_points', abs($integral));
        }
        if ($res) {
            return $this->integral_log($user_id,'-' . $integral, '订单','订单ID：' . $order_id);
        } else {
            return false;
        }
    }

    /**
     * 添加积分更变
     * @param $user_id
     * @param $integral
     * @param string $from
     * @param string $readme
     * @return mixed
     */
    public function integral_log($user_id, $integral, $from = '', $readme = ''){
        $data = [
            'integral_sn'       =>  0,
            'user_id'           =>  $user_id,
            'integral_number'   =>  $integral,
            'from'              =>  $from,
            'add_time'          =>  time(),
            'readme'            =>  $readme,
        ];
        return M('IntegralLog')->add($data);
    }

    /**
     * 自动返现，测试用
     * @param int $day
     * @param int $order_id
     * @return array
     */
    public function auto_brokerage_audit2($day = 10, $order_id = 0){
        $time = time();
        if ($order_id > 0 and is_numeric($order_id)) {
            $where = ['order_id'=>$order_id];
        } else {
            $end_time = $time - (intval($day) * 86400);  //60 * 60 * 24  一天的秒数
            $where = ['received_time'=>  ['ELT',$end_time]];
        }
        //if (!$order_id) return false;
        /*print_r($order_id);exit;
        $order_ids = [];*/
        $user_level = M('UserLevel');
        $brok = $user_level->alias("ul")
            ->field('ul.*,u.is_vip,oi.order_sn')
            ->where('state=' . YJ_AUDIT  . ' and orderid in ' . $this->where(array_merge([
                    'order_status'      =>  OS_SPLITED,
                    'shipping_status'   =>  SS_RECEIVED,
                    'pay_status'        =>  PS_PAYED,
                ],$where))->field('order_id')->buildSql())
            ->join('LEFT JOIN __USERS__ u ON ul.uid=u.user_id')
            ->join('LEFT JOIN __ORDER_INFO__ oi ON oi.order_id=ul.orderid')
            ->select();
        //echo $user_level->getLastSql();
        if ($brok) {
            $ids = $user_ids = $user_data = $store_data = $kp_data = $proxy_data = array();
            foreach ($brok as $k => $v) {
                $ids[] = $v['id'];
                if ( $v['uid'] > 0 and !isset($user_ids[$v['uid']]) ) $user_ids[] = $v['uid'];
                switch( $v['stype'] ) {
                    case YJ_TYPE_0: //三级分销分拥    3
                        $user_data[$v['uid']]['money'] += $v['money'];
                        $user_data[$v['uid']]['store_id'] = $v['store_id'];
                        $user_data[$v['uid']]['user_id'] = $v['uid'];
                        $user_data[$v['uid']]['is_vip'] = $v['store_id'];
                        $user_data[$v['uid']]['desc'] = '订单号为：' . $v['order_sn'] . '分销分拥收入';
                        break;
                    case YJ_TYPE_1: //董事分拥       3
                        $user_data[$v['uid']]['money'] += $v['money'];
                        $user_data[$v['uid']]['store_id'] = $v['store_id'];
                        $user_data[$v['uid']]['user_id'] = $v['uid'];
                        $user_data[$v['uid']]['desc'] = '订单号为：' . $v['order_sn'] . '董事分拥收入';
                        break;
                    case YJ_TYPE_2: //代理分销       1
                        $user_data[$v['uid']]['money'] += $v['money'];
                        $user_data[$v['uid']]['store_id'] = $v['store_id'];
                        $user_data[$v['uid']]['user_id'] = $v['uid'];
                        $user_data[$v['uid']]['desc'] = '订单号为：' . $v['order_sn'] . '代理分拥收入';
                        break;
                    case YJ_TYPE_3: //返利分拥      1
                        $kp_data[$v['uid']]['money'] += $v['money'];
                        $kp_data[$v['uid']]['store_id'] = $v['store_id'];
                        $kp_data[$v['uid']]['user_id'] = $v['uid'];
                        $kp_data[$v['uid']]['desc'] = '订单号为：' . $v['order_sn'] . '返利分拥收入';
                        break;
                    case YJ_TYPE_4: //订单费用      1
                        $store_data[$v['store_id']]['money'] += $v['money'];
                        $store_data[$v['store_id']]['order_sn'] = $v['order_sn'];
                        $store_data[$v['store_id']]['desc'] = '订单号为：' . $v['order_sn'] . '收入';
                        break;
                    default:
                }
            }
            $user_model = new UsersModel();
            $is_vip = [];
            if ($user_data) {          //余额
                foreach ($user_data as $k => $v) {
                    if ($v['is_vip'] > 0) {
                        $is_vip[$k]['stype'] = PT_MONEY;
                        $is_vip[$k]['type'] = 'user';
                        //if ($user_model->set_user_money(['user_id'=>$k],$v['money'],'income','余额三级分销与董事收入')){
                        $user_account_id = $user_model->add_user_account(
                            ['user_id'=>$v['user_id']],
                            $v['money'],
                            ['desc'=>$v['desc'],'type'=>PT_MONEY,'user_note'=>'','stage'=>ST_INCOME]
                        );
                        $user_model->set_user_account($user_account_id,PS_PAYED,[
                            'pay_code'      =>'system',
                            'payment_name'  =>'系统处理',
                            'pay_account'   =>$k,
                            'pay_name'      =>'admin'
                        ]);
                        $is_vip[$k]['is_paid'] = YJ_AUDIT;
                        /*} else {
                            $is_vip[$k]['is_paid'] = YJ_UNAUDIT;
                        }*/
                    } else {
                        $is_vip[$k]['stype'] = PT_RED;
                        $is_vip[$k]['type'] = 'user';
                        //if ($user_model->set_user_bonus(['user_id'=>$k],$v['money'],'income','红包三级分销与董事收入')) {
                        $user_account_id = $user_model->add_user_account(
                            ['user_id'=>$v['user_id']],
                            $v['money'],
                            ['desc'=>$v['desc'],'type'=>PT_RED,'user_note'=>'','stage'=>ST_INCOME]
                        );
                        $user_model->set_user_account($user_account_id,PS_PAYED,[
                            'pay_code'=>'system',
                            'payment_name'=>'系统处理',
                            'pay_account'=>$k,
                            'pay_name'=>'admin'
                        ]);
                        $is_vip[$k]['is_paid'] = YJ_AUDIT;
                        /*} else {
                            $is_vip[$k]['is_paid'] = YJ_UNAUDIT;
                        }*/
                    }
                }
            }
            if ($kp_data) {         //KP点
                foreach ($kp_data as $k => $v) {
                    $is_vip[$k]['stype'] = PT_KP;
                    $is_vip[$k]['type'] = 'user';
                    //if ($user_model->set_user_kpnum(['user_id'=>$k],$v['money'],'销售收入')) {
                    $user_account_id = $user_model->add_user_account(
                        ['user_id'=>$v['user_id']],
                        $v['money'],
                        ['desc'=>$v['desc'],'type'=>PT_KP,'user_note'=>'','stage'=>ST_INCOME]
                    );
                    $user_model->set_user_account($user_account_id,PS_PAYED,['pay_code'=>'system','payment_name'=>'系统处理','pay_account'=>$k,'pay_name'=>'admin']);
                    $is_vip[$k]['is_paid'] = YJ_AUDIT;
                    /*} else {
                        $is_vip[$k]['is_paid'] = YJ_UNAUDIT;
                    }*/
                }
            }

            if ($store_data) {          //店铺余额
                foreach ($store_data as $k => $v) {
                    $is_vip[$k]['stype'] = PT_MONEY;
                    $is_vip[$k]['type'] = 'store';
                    //if ($user_model->set_store_money(['store_id'=>$k],$v['money'],'income','销售收入') ) {
                    $user_account_id = $user_model->add_user_account(
                        ['store_id'=>$k],
                        $v['money'],
                        ['desc'=>$v['desc'],'type'=>PT_MONEY,'user_note'=>'','stage'=>ST_INCOME]
                    );
                    $user_model->set_user_account($user_account_id,PS_PAYED,['pay_code'=>'system','payment_name'=>'系统处理','pay_account'=>$k,'pay_name'=>'admin']);
                    $is_vip[$k]['is_paid'] = YJ_AUDIT;
                    /*} else {
                        $is_vip[$k]['is_paid'] = YJ_UNAUDIT;
                    }*/
                }
            }
            $result = result_ajax(0,'处理成功！',$is_vip);
        } else {
            $result = result_ajax(302,'未找到订单拥金！');
        }
        return $result;
    }

    /**
     * 自动返现，正式用
     * @param int $day
     * @param int $order_id
     * @return array
     */
    public function auto_brokerage_audit($day = 10, $order_id = 0){
        $time = time();
        if ($order_id > 0 and is_numeric($order_id)) {
            $where = ['order_id'=>$order_id];
        } else {
            $end_time = $time - (intval($day) * 86400);  //60 * 60 * 24  一天的秒数
            $where = ['received_time'=>  ['ELT',$end_time]];
        }
        $user_level = M('UserLevel');
        $brok = $user_level->alias("ul")
            ->field('ul.*,u.is_vip,oi.order_sn')
            ->where('state=' . YJ_UNAUDIT  . ' and orderid in ' . $this->where(array_merge([
                    'order_status'      =>  OS_SPLITED,
                    'shipping_status'   =>  SS_RECEIVED,
                    'pay_status'        =>  PS_PAYED,
                ],$where))->field('order_id')->buildSql())
            ->join('LEFT JOIN __USERS__ u ON ul.uid=u.user_id')
            ->join('LEFT JOIN __ORDER_INFO__ oi ON oi.order_id=ul.orderid')
            ->select();
        if ($brok) {
            $ids = $user_ids = $user_data = $store_data = $kp_data = $proxy_data = array();
            foreach ($brok as $k => $v) {
                $ids[] = $v['id'];
                if ( $v['uid'] > 0 and !isset($user_ids[$v['uid']]) ) $user_ids[] = $v['uid'];
                switch( $v['stype'] ) {
                    case YJ_TYPE_0: //三级分销分拥    3
                        $user_data[$v['uid']]['money'] += $v['money'];
                        $user_data[$v['uid']]['store_id'] = $v['store_id'];
                        $user_data[$v['uid']]['user_id'] = $v['uid'];
                        $user_data[$v['uid']]['is_vip'] = $v['store_id'];
                        $user_data[$v['uid']]['desc'] = '订单号为：' . $v['order_sn'] . '分销分拥收入';
                        break;
                    case YJ_TYPE_1: //董事分拥       3
                        $user_data[$v['uid']]['money'] += $v['money'];
                        $user_data[$v['uid']]['store_id'] = $v['store_id'];
                        $user_data[$v['uid']]['user_id'] = $v['uid'];
                        $user_data[$v['uid']]['desc'] = '订单号为：' . $v['order_sn'] . '董事分拥收入';
                        break;
                    case YJ_TYPE_2: //代理分销       1
                        $user_data[$v['uid']]['money'] += $v['money'];
                        $user_data[$v['uid']]['store_id'] = $v['store_id'];
                        $user_data[$v['uid']]['user_id'] = $v['uid'];
                        $user_data[$v['uid']]['desc'] = '订单号为：' . $v['order_sn'] . '代理分拥收入';
                        break;
                    case YJ_TYPE_3: //返利分拥      1
                        $kp_data[$v['uid']]['money'] += $v['money'];
                        $kp_data[$v['uid']]['store_id'] = $v['store_id'];
                        $kp_data[$v['uid']]['user_id'] = $v['uid'];
                        $kp_data[$v['uid']]['desc'] = '订单号为：' . $v['order_sn'] . '返利分拥收入';
                        break;
                    case YJ_TYPE_4: //订单费用      1
                        $store_data[$v['store_id']]['money'] += $v['money'];
                        $store_data[$v['store_id']]['store_id'] = $v['store_id'];
                        $store_data[$v['store_id']]['order_sn'] = $v['order_sn'];
                        $store_data[$v['store_id']]['desc'] = '订单号为：' . $v['order_sn'] . '收入';
                        break;
                    default:
                }
            }
            $user_model = new UsersModel();
            $is_vip = [];
            if ($user_data) {
                foreach ($user_data as $k => $v) {
                    if ($v['is_vip'] > 0) {
                        $is_vip[$k]['stype'] = PT_MONEY;
                        $is_vip[$k]['type'] = 'user';
                        if ($user_model->set_user_money(['user_id'=>$k],$v['money'],'income','余额三级分销与董事收入')){
                            $user_account_id = $user_model->add_user_account(
                                ['user_id'=>$v['user_id']],
                                $v['money'],
                                ['desc'=>$v['desc'],'type'=>PT_MONEY,'user_note'=>'','stage'=>ST_INCOME]
                            );
                            $user_model->set_user_account($user_account_id,PS_PAYED,[
                                'pay_code'      =>'system',
                                'payment_name'  =>'系统处理',
                                'pay_account'   =>$k,
                                'pay_name'      =>'admin'
                            ]);
                            $is_vip[$k]['is_paid'] = YJ_AUDIT;
                        } else {
                            $is_vip[$k]['is_paid'] = YJ_UNAUDIT;
                        }
                    } else {
                        $is_vip[$k]['stype'] = PT_RED;
                        $is_vip[$k]['type'] = 'user';
                        if ($user_model->set_user_bonus(['user_id'=>$k],$v['money'],'income','红包三级分销与董事收入')) {
                            $user_account_id = $user_model->add_user_account(
                                ['user_id'=>$v['user_id']],
                                $v['money'],
                                ['desc'=>$v['desc'],'type'=>PT_RED,'user_note'=>'','stage'=>ST_INCOME]
                            );
                            $user_model->set_user_account($user_account_id,PS_PAYED,[
                                'pay_code'=>'system',
                                'payment_name'=>'系统处理',
                                'pay_account'=>$k,
                                'pay_name'=>'admin'
                            ]);
                            $is_vip[$k]['is_paid'] = YJ_AUDIT;
                        } else {
                            $is_vip[$k]['is_paid'] = YJ_UNAUDIT;
                        }
                    }
                }
            }
            if ($kp_data) {
                foreach ($kp_data as $k => $v) {
                    $is_vip[$k]['stype'] = PT_KP;
                    $is_vip[$k]['type'] = 'user';
                    if ($user_model->set_user_kpnum(['user_id'=>$k],$v['money'],'销售收入')) {
                        $user_account_id = $user_model->add_user_account(
                            ['user_id'=>$v['user_id']],
                            $v['money'],
                            ['desc'=>$v['desc'],'type'=>PT_KP,'user_note'=>'','stage'=>ST_INCOME]
                        );
                        $user_model->set_user_account($user_account_id,PS_PAYED,['pay_code'=>'system','payment_name'=>'系统处理','pay_account'=>$k,'pay_name'=>'admin']);
                        $is_vip[$k]['is_paid'] = YJ_AUDIT;
                    } else {
                        $is_vip[$k]['is_paid'] = YJ_UNAUDIT;
                    }
                }
            }

            if ($store_data) {
                foreach ($store_data as $k => $v) {
                    $is_vip[$k]['stype'] = PT_MONEY;
                    $is_vip[$k]['type'] = 'store';
                    if ($user_model->set_store_money(['store_id'=>$v['store_id']],$v['money'],'income','销售收入') ) {
                        $user_account_id = $user_model->add_user_account(
                            ['store_id'=>$v['store_id']],
                            $v['money'],
                            ['desc'=>$v['desc'],'type'=>PT_MONEY,'user_note'=>'','stage'=>ST_INCOME]
                        );
                        $user_model->set_user_account($user_account_id,PS_PAYED,['pay_code'=>'system','payment_name'=>'系统处理','pay_account'=>$k,'pay_name'=>'admin']);
                        $is_vip[$k]['is_paid'] = YJ_AUDIT;
                    } else {
                        $is_vip[$k]['is_paid'] = YJ_UNAUDIT;
                    }
                }
            }
            $result = result_ajax(0,'处理成功！',$is_vip);
        } else {
            $result = result_ajax(302,'未找到订单拥金！');
        }
        return $result;
    }


    /**
     * 自动拥金审核（作废）
     * @param int $day
     * @param int $order_id
     * @return array
     */
    public function auto_brokerage_audit1($day = 10, $order_id = 0){
        $time = time();
        if ($order_id > 0 and is_numeric($order_id)) {
            $where = ['order_id'=>$order_id];
        } else {
            $end_time = $time - (intval($day) * 86400);  //60 * 60 * 24  一天的秒数
            $where = ['received_time'=>  ['ELT',$end_time]];
        }
        $user_level = M('UserLevel');
        $brok = $user_level->alias("ul")
            ->field('ul.*')
            ->where(' orderid in ' . $this->where(array_merge([
                'order_status'      =>  OS_SPLITED,
                'shipping_status'   =>  SS_RECEIVED,
                'pay_status'        =>  PS_PAYED,
            ],$where))->field('order_id')->buildSql())
            ->join('LEFT JOIN __USERS__ u ON ul.uid=u.user_id')
            ->select();
        \Think\Log::record(print_r(M('UserLevel')->getLastSql(),1));
        if ($brok) {
            $ids = $user_ids = $user_data = $store_data = $kp_data = $proxy_data = array();
            foreach ($brok as $k => $v) {
                $ids[] = $v['id'];
                if ( $v['uid'] > 0 and !isset($user_ids[$v['uid']]) ) $user_ids[] = $v['uid'];
                switch( $v['stype'] ) {
                    case YJ_TYPE_0: //三级分销分拥    3
                    case YJ_TYPE_1: //董事分拥       3
                    case YJ_TYPE_2: //代理分销       1
                        $user_data[$v['uid']] += $v['money'];
                        break;
                    case YJ_TYPE_3: //返利分拥      1
                        $kp_data[$v['uid']] += $v['money'];
                        break;
                    case YJ_TYPE_4: //订单费用      1
                        $store_data[$v['store_id']] += $v['money'];
                        break;
                    default:
                }
            }
            $user_model = new UsersModel();
            $users = $user_model->where($this->db_create_in($user_ids, 'user_id'))->select();
            \Think\Log::record($user_model->getLastSql());
            $is_vip = [];
            foreach ($users as $k => $v) {
                $is_vip[$v['user_id']] = $v;
            }
            \Think\Log::record(print_r($is_vip,1));
            $user_level->where($this->db_create_in($ids,'id'))->save(['state'=>YJ_AUDIT,'updatetime'=>$time]);
            if ($user_data) {
                foreach ($user_data as $k => $v) {
                    if ($is_vip[$k]['is_vip'] < 0) {

                    } else if (1) { //if ($is_vip[$k]['is_vip'] > 0) {  //暂时取消佣金入红包功能
                        $is_vip[$k]['stype'] = PT_MONEY;
                        $is_vip[$k]['type'] = 'user';
                        if ($user_model->set_user_money(['user_id'=>$k],$v,'income','余额三级分销与董事收入')){
                            $user_account_id = $user_model->add_user_account(
                                ['user_id'=>$k],
                                $v,
                                ['desc'=>'销售收入','type'=>PT_MONEY,'user_note'=>'']
                            );
                            $user_model->set_user_account($user_account_id,PS_PAYED,['pay_code'=>'system','payment_name'=>'系统处理','pay_account'=>$k,'pay_name'=>'admin']);
                            $is_vip[$k]['is_paid'] = 1;
                        } else {
                            $is_vip[$k]['is_paid'] = 0;
                        }
                    } else {
                        $is_vip[$k]['stype'] = PT_RED;
                        $is_vip[$k]['type'] = 'user';
                        if ($user_model->set_user_bonus(['user_id'=>$k],$v,'income','红包三级分销与董事收入')) {
                            $user_account_id = $user_model->add_user_account(
                                ['user_id'=>$k],
                                $v,
                                ['desc'=>'销售收入','type'=>PT_RED,'user_note'=>'']
                            );
                            $user_model->set_user_account($user_account_id,PS_PAYED,['pay_code'=>'system','payment_name'=>'系统处理','pay_account'=>$k,'pay_name'=>'admin']);
                            $is_vip[$k]['is_paid'] = 1;
                        } else {
                            $is_vip[$k]['is_paid'] = 0;
                        }
                    }
                }
            }
            if ($kp_data) {
                foreach ($kp_data as $k => $v) {
                    $is_vip[$k]['stype'] = PT_KP;
                    $is_vip[$k]['type'] = 'user';
                    if ($is_vip[$k]['is_vip'] < 0) {

                    } else if ($user_model->set_user_kpnum(['user_id'=>$k],$v,'销售收入')) {
                        $user_account_id = $user_model->add_user_account(
                            ['user_id'=>$k],
                            $v,
                            ['desc'=>'销售收入','type'=>PT_KP,'user_note'=>'']
                        );
                        $user_model->set_user_account($user_account_id,PS_PAYED,['pay_code'=>'system','payment_name'=>'系统处理','pay_account'=>$k,'pay_name'=>'admin']);
                        $is_vip[$k]['is_paid'] = 1;
                    } else {
                        $is_vip[$k]['is_paid'] = 0;
                    }
                }
            }

            if ($store_data) {
                foreach ($store_data as $k => $v) {
                    $is_vip[$k]['stype'] = PT_MONEY;
                    $is_vip[$k]['type'] = 'store';
                    if ($user_model->set_store_money(['store_id'=>$k],$v,'income','销售收入') ) {
                        $user_account_id = $user_model->add_user_account(
                            ['store_id'=>$k],
                            $v,
                            ['desc'=>'销售收入','type'=>PT_MONEY,'user_note'=>'']
                        );
                        $user_model->set_user_account($user_account_id,PS_PAYED,['pay_code'=>'system','payment_name'=>'系统处理','pay_account'=>$k,'pay_name'=>'admin']);
                        $is_vip[$k]['is_paid'] = 1;
                    } else {
                        $is_vip[$k]['is_paid'] = 0;
                    }
                }
            }
            $result = result_ajax(0,'处理成功！',$is_vip);
        } else {
            $result = result_ajax(302,'未找到订单拥金！');
        }
        return $result;
    }

    /**
     * 自动取消订单
     * @param int $type     0 未付款订单  1 已发货订单
     * @param int $day      处理N天前的订单
     * @return bool|string
     */
    public function auto_order($type = 0, $day = 1){
        $time = time();
        $end_time = ($time - (intval($day) * 86400));  //60 * 60 * 24  一天的秒数
        if ($type == 1) {
            $where = " order_status='".OS_SPLITED."' and " .
                " shipping_status='".SS_SHIPPED."' and ".
                " pay_status='".PS_PAYED."' ".
                " and (receive_time <= ({$time}) and receive_time <> 0)";
            $status = array(
                'order_status'      =>  OS_SPLITED,
                'shipping_status'   =>  SS_RECEIVED,
                'pay_status'        =>  PS_PAYED,
                'action_user'       =>  'system',
                'action_user_id'    =>  0,
                'action_place'      =>  0,
                'action_note'       =>  '确认收货超时，自动确认收货',
                'log_time'          =>  $time
            );
            $save_data = ['shipping_status'=>SS_RECEIVED,'received_time'=> $time];
        } else {
            $where = " order_status='".OS_CONFIRMED."' and " .
                     " shipping_status='".SS_UNSHIPPED."' and ".
                     " pay_status='".PS_UNPAYED."' ".
                     " and (confirm_time <= ({$end_time}))";
            $status = array(
                'order_status'      =>  OS_CANCELED,
                'shipping_status'   =>  SS_UNSHIPPED,
                'pay_status'        =>  PS_UNPAYED,
                'action_user'       =>  'system',
                'action_user_id'    =>  0,
                'action_place'      =>  0,
                'action_note'       =>  '付款超时，自动取消订单',
                'log_time'          =>  $time
            );
            $save_data = ['order_status'=>OS_CANCELED];
        }
        $order_list = $this->where($where)->select();
        $ids = $edit_data = array();
        foreach ($order_list as $v) {
            $ids[] = $v['order_id'];
            $edit_data[] = array_merge(['order_id'=>$v['order_id']],$status);
        }
        /*if ($type != 0) {
        echo '类型：' . $type . "\n";
            print_r($order_list);
            echo print_r($edit_data,1);
            echo $this->getLastSql();
            //exit('end');

        echo "\n";
        }*/
        //echo $this->getLastSql() . "\n";
        if ($edit_data && $this->where($this->db_create_in($ids,'order_id'))->save($save_data)) {
            return M('OrderAction')->addall($edit_data);
        } else {
            return false;
        }
        //$this->add_order_log(array('user_name'=>'system','user_id'=>0),$ids,$status,'付款超时自动关闭');
    }
    /**
     * 取消订单
     * @param int $id
     * @param string $note
     * @param int $const_cancel
     * @return array
     */
    public function order_cancel($id, $note,$const_cancel = OS_CANCELED){
        $result = array('error' => 0,'message'=> '');
        $order_info = $this->get_order_info($this->user_info['user_id'], $id);
        if (!$order_info) {
            $result = array('error' => 1,'message'=> '取消订单失败');
        } else {
            if (($order_info['order_status'] == OS_UNCONFIRMED
                or $order_info['order_status'] == OS_CONFIRMED)
                and $order_info['shipping_status'] == SS_UNSHIPPED
                and $order_info['pay_status'] == PS_UNPAYED) {      //未确认/已确认 未发货 未付款
                $data = array(          //取消订单参数
                    'order_status'  =>  $const_cancel,
                );
                $this->where("user_id={$this->user_info['user_id']} and order_id={$order_info['order_id']}")
                    ->save($data);
                $data = array(
                    'order_status'      => $const_cancel,
                    'shipping_status'   => $order_info['shipping_status'],
                    'pay_status'        => $order_info['pay_status'],
                );
                $this->add_order_log($this->user_info, $order_info['order_id'],$data, $note);
                $result['error'] = 0;
                $result['message'] = '订单已取消！';
            } else {
                $result['error'] = 2;
                $result['message'] = '取消订单失败！';
            }
        }
        return $result;
    }
    /**
     * 订单确认收货
     * @param string $id
     * @param string $note
     * @param int $const_confirm
     * @return mixed
     */
    public function order_confirm($id, $note, $const_confirm = SS_RECEIVED) {
        $order_info = $this->get_order_info($this->user_info['user_id'],$id);
        if (empty($order_info)) {
            $result['error'] = 1;
            $result['message'] = '确认收货失败！';
        } else {
            if ($order_info['order_status'] == OS_SPLITED
                and $order_info['shipping_status'] == SS_SHIPPED
                and $order_info['pay_status'] == PS_PAYED) {        //已分单 已发货 已付款
                $data = array(          //确认收货参数
                    'shipping_status'  =>  $const_confirm,
                    'received_time'    =>  time()
                );
                $this->where("user_id={$this->user_info['user_id']} and order_id={$order_info['order_id']}")->save($data);
                $data = array(
                    'order_status'      => $order_info['order_status'],
                    'shipping_status'   => $const_confirm,
                    'pay_status'        => $order_info['pay_status'],
                );
                $this->add_order_log($this->user_info,$order_info['order_id'],$data,$note);
                $result['error'] = 0;
                $result['message'] = '成功确认收货！';
            } else {
                $result['error'] = 2;
                $result['message'] = '确认收货失败！';
            }
        }
        return $result;
    }
    /**
     * 添加订单操作日志
     * @param array $user_info
     * @param int|array $order_id
     * @param array $status
     * @param string $note
     * @return mixed
     */
    public function add_order_log($user_info, $order_id, $status, $note){
        $data = array();
        $time = time();
        if (is_array($order_id)) {
            foreach ($order_id as $v) {
                $data[] = array(
                    'order_id'          =>  $v,
                    'order_status'      =>  $status['order_status'],
                    'shipping_status'   =>  $status['shipping_status'],
                    'pay_status'        =>  $status['pay_status'],
                    'action_user'       =>  $user_info['user_name'],
                    'action_user_id'    =>  $user_info['user_id'],
                    'action_note'       =>  $note,
                    'log_time'          =>  $time,
                    'action_place'      =>  '',
                );
            }
            \Think\Log::record('订单添加日志：'.$data);
            $result = M("OrderAction")->addall($data);
        } else if (is_numeric($status['order_id'])) {
            $data = array(
                'order_id'          =>  $order_id,
                'order_status'      =>  $status['order_status'],
                'shipping_status'   =>  $status['shipping_status'],
                'pay_status'        =>  $status['pay_status'],
                'action_user'       =>  $user_info['user_name'],
                'action_user_id'    =>  $user_info['user_id'],
                'action_note'       =>  $note,
                'log_time'          =>  $time,
                'action_place'      =>  '',
            );
            \Think\Log::record('订单添加日志：'.$data);
            $result = M("OrderAction")->add($data);
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * 获取单个订单信息（支付返回使用）
     * @param $id
     * @param array $status
     * @return mixed
     */
    public function get_order_info1($id, $status = array()){
        $where = "(oi.order_id='{$id}' or oi.order_sn='{$id}')";
        if (!empty($status)) {
            $where .= " and oi.order_status={$status['order_status']} and oi.shipping_status={$status['shipping_status']} and oi.pay_status={$status['pay_status']}";
        }
        return $this->alias('oi')->where($where)->find();
    }
    /**
     * 获取单个订单信息
     * @param int $user_id      用户ID
     * @param int $id           订单ID
     * @param array $status     订单状态
     * @return mixed
     */
    public function get_order_info($user_id, $id, $status = array()){
        //if (intval($user_id) <= 0 or intval($id) <= 0) return false;
        if (!empty($status)) {
            $where = " and oi.order_status={$status['order_status']} and oi.shipping_status={$status['shipping_status']} and oi.pay_status={$status['pay_status']}";
        } else {
            $where = '';
        }//exit($where);
        $s = $this
            ->alias('oi')
            ->where("oi.user_id={$user_id} and (oi.order_id='{$id}' or oi.order_sn='{$id}') {$where}")
            ->field('oi.order_id,order_sn,oi.store_id,oi.consignee,oi.country,oi.province,oi.city,oi.district,oi.address,
            oi.zipcode,oi.tel,oi.mobile,oi.best_time,oi.sign_building,oi.shipping_id,oi.shipping_name,oi.pay_fee,
            pay_id,pay_name,order_amount,oi.order_integral,r1.region_name as province_name,
            r2.region_name as city_name, r3.region_name as country_name,
            r4.region_name as district_name,bt.text as besttime_title,oi.order_status,
            oi.shipping_status,oi.pay_status,oi.address_id,oi.shipping_fee,oi.add_time,
            oi.user_id,u.user_name,oi.pay_time,oi.shipping_time,s.shipping_code')
            ->join('LEFT JOIN __REGION__ r1 on r1.region_id=oi.province')
            ->join('LEFT JOIN __REGION__ r2 on r2.region_id=oi.city')
            ->join('LEFT JOIN __REGION__ r3 on r3.region_id=oi.country')
            ->join('LEFT JOIN __REGION__ r4 on r4.region_id=oi.district')
            ->join('LEFT JOIN __BESTTIME__ bt on bt.id=oi.best_time')
            ->join('LEFT JOIN __USERS__ u on u.user_id=oi.user_id')
            ->join('LEFT JOIN __SHIPPING__ s on s.shipping_id=oi.shipping_id')
            ->find();
        //echo $this->getLastSql();
        if (!empty($s)) {
            $s['order_goods'] = M('order_goods')->where('order_id=%d',$s['order_id'])->select();
            $s['add_time'] = date('Y-m-d H:i:s', $s['add_time']);
            $s['status'] = $this->get_order_status($s);
            if ($s['pay_time'] > 0) $s['pay_time'] = date('Y-m-d H:i:s', $s['pay_time']);
            if ($s['shipping_time'] > 0) $s['shipping_time'] = date('Y-m-d H:i:s', $s['shipping_time']);
            $s['order_amount_formated'] = intval($s['order_integral']) > 0 ? intval($s['order_integral']) . $this->_CFG['integral_name']:'';
            $s['order_amount_formated'] .= intval($s['order_amount']) > 0 ? $this->price_format($s['order_amount']):'';
            $s['pay_fee_formated'] = intval($s['pay_fee']) > 0 ? $this->price_format($s['pay_fee']):'';
            return $s;
        } else {
            return false;
        }

    }
    /**
     * 获取订单列表
     * @param int $user_id
     * @param int $status
     * @param int $pagesize
     * @param int $store_id
     * @return array
     */
    public function get_order_list($user_id, $status = 0, $pagesize = 5, $store_id = 0){
        $where = 'user_id=' . $user_id;
        if ($status == 'pay') {
            $where .= " and order_status=" . OS_CONFIRMED . " and shipping_status=" . SS_UNSHIPPED . " and pay_status=" . PS_PAYED;
        } else if ($status == 'confirm') {
            $where .= " and order_status=" . OS_SPLITED . " and shipping_status=" . SS_SHIPPED . " and pay_status=" . PS_PAYED;
        } else if ($status == 'comment') {
            $where .= " and order_status=" . OS_SPLITED . " and shipping_status=" . SS_RECEIVED . " and pay_status=" . PS_PAYED;
        } else if($status == 'unpaid'){
            $where .= " and order_status=" . OS_CONFIRMED . " and shipping_status=" . SS_UNSHIPPED . " and pay_status=" . PS_UNPAYED;
        } else if ($status == 'refund') {
            $where .= " and order_status=" . OS_RETURNED;
        } else {

        }
        if($store_id) $where .= ' and store_id =' . $store_id;

        $Page = new \Think\Page($this->where($where)->count(), $pagesize);
        $Page->rollPage = 5;
        $Page->setConfig('prev','上一页');
        $Page->setConfig('next','下一页');
        $show = $Page->show();

        $order_list = $this->alias('oi')
            //->cache('order_' . $user_id . '_' . $status)
            ->where($where)
            ->order('order_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $ids = array();
        foreach ($order_list as $v) {
            $ids[] = $v['order_id'];
        }
        $goods = $this->get_order_goods($ids);//print_r($goods);

        if (!empty($goods)) {
            foreach ($order_list as $k => &$v) {
                $v['url'] = U('Order/detail?sn=' . $v['order_sn']);
                $v['status'] = $this->get_order_status($v);
                $v['style'] = $this->get_order_style($v);
                $v['shipping_fee_formated'] = price_format($v['shipping_fee'], true);
                $v['order_amount_formated'] = intval($v['order_integral']) > 0 ? intval($v['order_integral']) . $this->_CFG['integral_name']:'';
                $v['order_amount_formated'] .= intval($v['order_amount']) > 0 ? price_format($v['order_amount'],true):'';
                $v['add_time'] = date('Y-m-d H:i', $v['add_time']);
                $number = 0;
                foreach ($goods as $kk => &$vv) {
                    if ($v['order_id'] == $vv['order_id']) {
                        //退货相关
                        $vv['refund'] = $this->get_return_status($vv['rec_id']);
                        $vv['refund_text'] = $this->get_return_text($vv['refund']);

//                        $vv['url'] = U('Goods/index?id=' . $vv['goods_id']);
                        $vv['goods_price_formated'] = intval($vv['integral']) > 0 ? $vv['integral'] . $this->_CFG['integral_name'] : '';
                        $vv['goods_price_formated'] .= intval($vv['goods_price']) ? price_format($vv['goods_price'],true) : '';
                        //$vv['goods_thumb'] = $this->img_url($vv['goods_thumb']);
                        $v['goods_list'][] = $vv;
                        unset($goods[$kk]);
                        $number += $vv['goods_number'];
                    }
                }

                $v['goods_number'] = $number;
            }
        }
        return array('list'=>$order_list,'show'=>$show,'total'=>$Page->totalPages);
    }

    /**
     * 获取订单内所有产品
     * @param $ids
     * @return array
     */
    public function get_order_goods($ids){
        $og_model = M('OrderGoods');
        if (is_numeric($ids)) {
            $where = array('order_id'=>$ids);
        } else if (is_array($ids)) {
            $where = $this->db_create_in($ids,'order_id');
        } else {
            return [];
        }
        $res = $og_model->alias('og')
            ->where($where)
            ->field('og.*,g.goods_thumb,l.level1,l.level2,l.level3,l.level4,l.level5,g.split_ratio,g.giving_kp,c.is_real,c.cat_id,st.is_auto,st.store_id')
            ->join('LEFT JOIN __GOODS__ g on og.goods_id=g.goods_id')
            ->join('LEFT JOIN __GOODS_LEVEL__ l ON og.goods_id=l.goods_id')
            ->join('LEFT JOIN __CATEGORY__ c on g.cat_id=c.cat_id')
            ->join('LEFT JOIN __STORE_SETTING__ st on g.store_id=st.store_id')
            ->select();



        foreach ($res as &$v) {
            $v['goods_thumb'] = $this->img_url($v['goods_thumb']);
            $v['url'] = U('Goods/index') . '?id=' . $v['goods_id'] . '&store_token=' . $v['store_id'];
            if($v['extension_code']){


                $act_id = M('GoodsActivity')->where(array('id'=>$v['extension_code']))->getField('act_id');

                $result= curlpost('get_act_info', array_merge(['act_id'=>AesEnCrypt($act_id)], C('API_APPINFO')));
                if($result['status'] == 200) {
                    $v['act_name'] = $result['data']['act_name'];
                    $v['url'] .= '&aid=' . AesEnCrypt($v['extension_code']);
                }
            }
        }
        return $res;
    }
    /*
     * 根据rec_id 获取该退货商品的信心
     */
    public function get_refund_goods($rec_id){
        $goods_info = M('order_goods og')
            ->field('*,og.goods_number, c.is_real')
            ->join('left join __ORDER_REFUND__ of on og.rec_id = of.rec_id')
            ->join('left join __GOODS__ g on og.goods_id = g.goods_id')
            ->join('left join __CATEGORY__ c on g.cat_id=c.cat_id')
            ->where('og.rec_id=%d',$rec_id)
            ->find();
        if($goods_info){
            $goods_info['goods_img'] = $this->img_url($goods_info['goods_img']);
        }
        return $goods_info;

    }

    /**
     *  获取订单商品退货状态（结合订单状态）
     * @param $rec_id
     * @return array
     */
    public function get_return_status($rec_id){
        if(!$rec_id) return null;
        $goods_info = M('order_goods og')
            ->join('LEFT JOIN __ORDER_REFUND__ of ON of.rec_id = og.rec_id')
            ->where('og.rec_id=%d',$rec_id)
            ->find();

        if(!$goods_info) return null;
        $order_info = $this->where('order_info')->where('order_id=%d',$goods_info['order_id'])->find();

        //可以申请退货的
        $refund_no =    /*($order_info['order_status'] == OS_SPLITED or  $order_info['order_status'] == OS_SPLITING_PART)
                    and  */(!$goods_info['is_real'] and $order_info['pay_status']   == PS_PAYED
                    and ($goods_info['is_return']    == REFUND_NO  or  $goods_info['is_return']    ==  REFUND_REFUSE));

        //退货申请审核中的
        $refund_apply = /*($order_info['order_status'] == OS_SPLITED or  $order_info['order_status'] == OS_SPLITING_PART)
                    and */ ($order_info['pay_status']   == PS_PAYED
                    and  $goods_info['is_return']    ==  REFUND_APPLY);

        //同意退货
        $refund_agree = ($order_info['pay_status']   == PS_PAYED
            and  $goods_info['is_return']    ==  REFUND_AGREE);

        //拒绝退货
        $refund_refuse = ($order_info['pay_status']   == PS_PAYED
            and  $goods_info['is_return']    ==  REFUND_REFUSE);

        //退货成功
        $refund_success = ($order_info['pay_status']   == PS_PAYED
            and  $goods_info['is_return']    ==  REFUND_REFUSE);
        $refund_faild = (
            $order_info['pay_status']   == PS_PAYED
            and  $goods_info['is_return']    ==  REFUND_FAILD
        );
        //退款 未发货时不必填写退货物流单号
        $only_pay = (
                $order_info['order_status']     ==  OS_CONFIRMED
            and $order_info['shipping_status']  ==  SS_UNSHIPPED
            and $order_info['pay_status']       ==  PS_PAYED
        );
        //订单号填写后的状态
        $after_edit_shipnum = (
            $refund_refuse&&$goods_info['refund_shipping_num']
        );
        return array(
            'refund_no'         =>  $refund_no,
            'refund_apply'      =>  $refund_apply,
            'refund_agree'      =>  $refund_agree,
            'refund_refuse'     =>  $refund_refuse,
            'refund_faild'      =>  $refund_faild,
            'refund_success'    =>  $refund_success,
            'only_pay'          =>  $only_pay,
            'after_edit_shipnum'=>  $after_edit_shipnum
        );


    }

    /**
     * 根据退货状态获取订单列表的退货相关的显示
     * @param $status
     * @return array
     */
    public function get_return_text($status){
        if(!$status) return null;
        $arr = array();
        if($status['refund_no']){
            if($status['refund_refuse']){
                $arr['text'] = "审核失败，重新申请";
            }else if($status['only_pay']){
                $arr['text'] = "申请退款";
            } else{
                $arr['text'] = "申请退货";
            }
            $arr['url'] = U('Order/returng');
        }

        else if($status['refund_apply']){
            $arr['text'] = "退款中";
            $arr['url'] = '';
        }

        else if($status['refund_agree']){
            if($status['only_pay']){
                $arr['text'] = "请等待返款";
                $arr['url'] = '';
            }else{
                $arr['text'] = "请填写快递单号";
                $arr['url'] = U('Order/edit_shipnum');
            }
        }

        else if($status['refund_success']){
            $arr['text'] = "返款成功";
            $arr['url'] = '';
        }

        else if($status['refund_faild']){
            $arr['text'] = "对不起，商家拒绝退货";
            $arr['url'] = '';
        }

        return $arr;
    }



    /**
     * 根据订单状态获取订单颜色样式
     * @param $or
     * @return mixed
     */
    public function get_order_style($or) {
        $state = '%s_%s_%s';
        $states = sprintf($state, $or['order_status'], $or['shipping_status'], $or['pay_status']);
        switch( $states ) {
            case sprintf($state,OS_UNCONFIRMED,SS_UNSHIPPED,PS_UNPAYED)://0.0.0
                $style = 'a1';
                break;
            case sprintf($state,OS_CONFIRMED,SS_UNSHIPPED,PS_UNPAYED)://1.0.0
                $style = 'a2';
                break;
            case sprintf($state,OS_CONFIRMED,SS_UNSHIPPED,PS_PAYED)://1.0.2
                $style = 'a3';
                break;
            case sprintf($state,OS_SPLITED,SS_SHIPPED,PS_PAYED):        //5.1.2
                $style = 'a4';
                break;
            case sprintf($state,OS_CONFIRMED,SS_RECEIVED,PS_PAYED)://1.2.2
                $style = 'a5';
                break;
            case sprintf($state,OS_INVALID,SS_RECEIVED,PS_PAYED)://3.2.2
                $style = 'a6';
                break;
            case sprintf($state,OS_CONFIRMED,SS_PREPARING,PS_PAYED)://1.3.2
                $style = 'a7';
                break;
            case sprintf($state,OS_SPLITED,SS_SHIPPED_ING,PS_PAYED)://5.5.2
                $style = 'a8';
                break;
            case sprintf($state,OS_RETURNED,'',''):
                $style = 'a9';
                break;
            case sprintf($state,OS_CANCELED,SS_UNSHIPPED,PS_UNPAYED):
                $style = 'a10';
                break;
            case sprintf($state,OS_SPLITED,SS_RECEIVED,PS_PAYED):
                $style = 'a11';
                break;
            case sprintf($state,OS_CLOSED,SS_RECEIVED,PS_PAYED):
                $style = 'a12';
                break;
            default:
                $style = 'a0';
                break;
        }
        return ' ' . $style;
    }

    /**
     * 格式化订单状态
     * @param $or
     * @param bool $is_admin
     * @return array
     */
    public function get_order_status($or,$is_admin = false) {
        $state = '%s_%s_%s';
        $user = $admin = array();
        $states = sprintf($state, $or['order_status'], $or['shipping_status'], $or['pay_status']);
        switch( $states ) {
            case sprintf($state,OS_UNCONFIRMED,SS_UNSHIPPED,PS_UNPAYED)://0.0.0
                $user = array('status_name'   =>  '未确认');
                $admin = array('status_name'   =>  '待确认');
                break;
            case sprintf($state,OS_CONFIRMED,SS_UNSHIPPED,PS_UNPAYED)://1.0.0
                $user = array(
                    'status_name'   =>  '待付款',
                    'handle'    =>  array(
                        array('text'=>'取消订单','url'=>U('Order/cancel?id=' . $or['order_sn']),'click'=>'return confirm(\'定需要取消订单吗？\')'),
                        array('text'=>'付款','url'=>U('Done/pay?id=' . $or['order_sn']))
                    )
                );
                $admin = array('status_name'   =>  '待付款');
                break;
            case sprintf($state,OS_CONFIRMED,SS_UNSHIPPED,PS_PAYED)://1.0.2
                $user = array('status_name'   =>  '待发货',
                    'handle'    =>  array(
                        array('text'=>'查看详细','url'=>U('Order/detail?id=' . $or['order_sn'])),
                    ),
                );
                $admin = array('status_name'   =>  '已付款');
                break;
            case sprintf($state,OS_SPLITED,SS_SHIPPED,PS_PAYED):        //5.1.2
            //case sprintf($state,OS_CONFIRMED,SS_SHIPPED,PS_PAYED):          //1.1.2
                $user = array('status_name'   =>  '已发货',
                    'handle'    =>  array(
                        array('text'=>'查看详细','url'=>U('Order/detail?id=' . $or['order_sn'])),
                        array('text'=>'查看物流','url'=>U('Order/cancel?id=' . $or['order_sn'])),
                        array('text'=>'确认收货','url'=>U('Order/confirm?id=' . $or['order_sn'])),
                    ),);
                $admin = array('status_name'   =>  '待收货');
                break;
            case sprintf($state,OS_CONFIRMED,SS_RECEIVED,PS_PAYED)://1.2.2
                $user = array('status_name'   =>  '已收货');
                $admin = array('status_name'   =>  '已收货');
                break;
            case sprintf($state,OS_INVALID,SS_RECEIVED,PS_PAYED)://3.2.2
                $user = array('status_name'   =>  '无效订单');
                $admin = array('status_name'   =>  '无效订单');
                break;
            case sprintf($state,OS_CONFIRMED,SS_PREPARING,PS_PAYED)://1.3.2
                $user = array('status_name'   =>  '备货中');
                $admin = array('status_name'   =>  '备货中');
                break;
            case sprintf($state,OS_SPLITED,SS_SHIPPED_ING,PS_PAYED)://5.5.2
                $user = array('status_name'   =>  '发货中');
                $admin = array('status_name'   =>  '发货中');
                break;
            case sprintf($state,OS_RETURNED,'',''):
                $user = array('status_name'   =>  '已退货');
                $admin = array('status_name'   =>  '已退货');
                break;
            case sprintf($state,OS_CANCELED,SS_UNSHIPPED,PS_UNPAYED):
                $user = array('status_name'   =>  '交易取消',
                    'handle'    =>  array(
                        /*array('text'=>'查看详细',
                            'url'=>U('Order/detail?id=' . $or['order_sn']),
                            ),*/
                        array('text'=>'删除订单',
                            'url'=>U('Order/delete?id=' . $or['order_sn']),
                            'click'=>'return confirm(\'定需要删除该订单吗？\')'),
                    ));
                $admin = array('status_name'   =>  '交易取消');
                break;
            case sprintf($state,OS_SPLITED,SS_RECEIVED,PS_PAYED):
                $user = array('status_name'   =>  '交易成功');
                $admin = array('status_name'   =>  '交易成功');
                break;
            case sprintf($state,OS_CLOSED,SS_RECEIVED,PS_PAYED):
                $user = array('status_name'   =>  '交易关闭');
                $admin = array('status_name'   =>  '交易关闭');
                break;
            default:
                $user = array('status_name'   =>  '无效订单' . $states);
                $admin = array('status_name'   =>  '无效订单' . $states);
                break;
        }
        if ($is_admin) {
            return $admin;
        } else {
            return $user;
        }
    }
    /**
     * 获取支付方式
     * @param bool $is_weixin
     * @param int $id
     * @return mixed
     */
    public function get_payment($id = 0,$is_weixin = false) {
        $where = ' enabled=1 ';
        $m = M('payment');
        if ($id > 0) {
            $where .= ' and pay_id=' . $id;
            $result = $m->where($where)->find();
            if ($result)
            $result['pay_config'] = $this->unserialize_config($result['pay_config']);
        } else {
            //if ($is_weixin) {
                $where .= ' and is_weixin=' . intval($is_weixin);
            //}
            $result = $m->where($where)->select();
            foreach ($result as &$v) {
                $v['pay_logo'] = $this->img_url($v['pay_logo']);
            }
        }
        return $result;
    }
    /**
     * 计算运费（选择城市待完善）
     * @param $shipping   int   1:自提  0:物流
     * @param $goods_list   array   需要计算费用的数组，购物车读取
     * @param $consignee array              区域信息
     * @param int $base_shipping    int    如果费用计算失败
     * @return int
     */
    public function get_shipping1($shipping, $goods_list, $consignee = array(), $base_shipping = 30) {
        $shipping_fee = 0;
        if ($shipping) {    //自提费用计划
            foreach ($goods_list as $v) {
                $shipping_fee += $v['shipping_auto'] * $v['goods_number'];
            }//print_r($goods_list);
        } else {   //物流
            foreach ($goods_list as $v) {
                if ($v['shipping_type'] == 0) {     //固定费用
                    $shipping_fee += $v['shipping_cont'] * $v['goods_number'];
                } else {                            //使用运费模板
                    $sss = M('ShippingArea')
                        ->cache('ShippingArea_' . $v['shipping_cont'])
                        ->where('shipping_area_id=' . $v['shipping_cont'])
                        ->getfield('configure');
                    if ($sss) {
                        $sss = $this->unserialize_config($sss);
                        if ($sss['fee_compute_mode'] == 'by_weight') {      //按重量
                            $weight = $v['goods_weight'] * $v['goods_number'];
                            if ($weight <= $sss['free_money']) {
                                //免运费
                            } else if ($weight > 1) {       //如果大于1千克
                                $weight = ceil($weight - 1) * $sss['step_fee'];
                                $shipping_fee += $sss['base_fee'] + $weight;
                            } else {
                                $shipping_fee += $sss['base_fee'];
                            }
                        } else if ($sss['fee_compute_mode'] == 'by_number') {       //按数量
                            $shipping_fee += $sss['base_fee'] * $v['goods_number'];
                        } else {
                            $shipping_fee += $base_shipping;
                        }
                    } else {
                        $shipping_fee += $base_shipping;//如果费用计算失败默认30
                    }
                }
            }
        }
        return $shipping_fee;
    }

    public function get_shipping($user_id,$flow_type,$region,$shipping_count,$order){
        $shipping_list = $this->available_shipping_list($region);
        $cart_weight_price  = $this->cart_weight_price($user_id,$flow_type);
        $insure_disabled   = true;
        $cod_disabled      = true;
        // 查看购物车中是否全为免运费商品，若是则把运费赋为零
        foreach ($shipping_list AS $key => $val)  {
            $shipping_cfg = $this->unserialize_config($val['configure']);
            if ($shipping_count == 0 AND $cart_weight_price['free_shipping'] == 1) {
                $shipping_fee = 0;
            } else {
                $shipping_fee = $this->shipping_fee($val['shipping_code'],
                    unserialize($val['configure']),
                    $cart_weight_price['weight'],
                    $cart_weight_price['amount'],
                    $cart_weight_price['number']);
            }
            $shipping_list[$key]['format_shipping_fee'] = $this->price_format($shipping_fee, false);
            $shipping_list[$key]['shipping_fee']        = $shipping_fee;
            $shipping_list[$key]['free_money']          = $this->price_format($shipping_cfg['free_money'], false);
            $shipping_list[$key]['insure_formated']     = strpos($val['insure'], '%') === false ? $this->price_format($val['insure'], false) : $val['insure'];

            /* 当前的配送方式是否支持保价 */
            if ($val['shipping_id'] == $order['shipping_id'])  {
                $insure_disabled = ($val['insure'] == 0);
                $cod_disabled    = ($val['support_cod'] == 0);
            }
        }
        return $shipping_list;
    }
    /**
     * 取优惠活动计算折扣
     * @param $flow_type
     * @param $total
     * @return array
     */
    public function get_discount($flow_type,$total){
        if ($flow_type != CART_EXCHANGE_GOODS
            && $flow_type != CART_GROUP_BUY_GOODS) {  //不是团购和积分商城
            $discount1 = $this->Calculate_discount($total); //取得活动信息
        } else {        //团购
            $discount1 = array();
        }
        return $discount1;
    }
    /**
     * 如果使用积分，取得用户可用积分及本订单最多可以使用的积分
     * @param $user_info
     * @param $flow_type
     * @return array
     */
    public function get_integral($user_info,$flow_type){
        $integral = array('allow_use_integral'=>0,'order_max_integral'=>0);
        /*if ((!isset($this->_CFG['use_integral']) || $this->_CFG['use_integral'] == '1')
            && $user_info['user_id'] > 0 && $user_info['pay_points'] > 0
            && ($flow_type != CART_GROUP_BUY_GOODS && $flow_type != CART_EXCHANGE_GOODS))  {*/
        if ((!isset($this->_CFG['use_integral']) || $this->_CFG['use_integral'] == '1')
            && $user_info['user_id'] > 0 && $user_info['pay_points'] > 0
            && ($flow_type == CART_EXCHANGE_GOODS)) {
            $integral = array(
                'allow_use_integral'    =>  1,                                  // 能否使用积分
                'order_max_integral'    =>  $this->flow_available_points('user_id=' . $user_info['user_id']),
                //'your_integral'         =>  $user_info['pay_points']            // 用户积分
            );
        }
        $integral['your_integral'] = $user_info['pay_points'];
        return $integral;
    }

    /**
     * 如果使用余额，取得用户余额
     * @param $user_info
     * @return array
     */
    public function get_surplus($user_info){
        $surplus = array('allow_use_surplus'=>0);
        if ((!isset($this->_CFG['use_surplus']) || $this->_CFG['use_surplus'] == '1')
            && $user_info['user_id'] > 0 && $user_info['user_money'] > 0)   {
            $surplus = array(
                'allow_use_surplus'     =>  1,                          // 能使用余额
                'your_surplus'          =>  $user_info['user_money']        // 当前余额
            );
        }
        return $surplus;
    }
    /**
     * 如果使用红包，取得用户可以使用的红包及用户选择的红包
     * @param $user_id   int   用户ID
     * @param $total    array   订单产品总价
     * @param $flow_type   string        购物车类型
     * @return array
     */
    public function get_bonus($user_id, $total, $flow_type){
        $bonus = array();
        if ((!isset($this->_CFG['use_bonus']) || $this->_CFG['use_bonus'] == '1')
            && ($flow_type != CART_GROUP_BUY_GOODS && $flow_type != CART_EXCHANGE_GOODS)) {
            $user_bonus = $this->user_bonus($user_id, $total['goods_price']);// 取得用户可用红包
            if (!empty($user_bonus)) {
                foreach ($user_bonus AS $key => $val) {
                    $user_bonus[$key]['bonus_money_formated'] = $this->price_format($val['type_money'], false);
                }
            }
            $bonus = array(
                'allow_use_bonus'   =>  1,          // 能使用红包
                'bonus_list'        =>  $user_bonus //显示红包
            );
        }
        return $bonus;
    }
    /**
     * 取发票信息
     * @return mixed
     */
    public function get_inv(){
        $result['invoice_content'] = $this->_CFG['invoice_content'];
        $invoice_type = $this->_CFG['invoice_type'];
        foreach ($invoice_type['type'] as $k => $v) {
            if ($v != '') {
                $inv['type'] = $v;
                $inv['rate'] = $invoice_type['rate'][$k];
                $result['invoice_type'][] = $inv;
            }
        }
        return $result;
    }

    public function get_besttime(){
        return M('besttime')->where('class=1')->select();
    }

    public function get_region($id = 0) {
        return M('region')->where('parent_id=' . $id)->select();
    }
    /**
     * 计算指定的金额需要多少积分
     *
     * @access  public
     * @param   integer $value  金额
     * @return  float
     */
    function integral_of_value($value) {
        $scale = floatval($GLOBALS['_CFG']['integral_scale']);
        return $scale > 0 ? round($value / $scale * 100) : 0;
    }
    /**
     * 计算积分的价值（能抵多少钱）
     * @param   int     $integral   积分
     * @return  float   积分价值
     */
    function value_of_integral($integral) {
        $scale = floatval($GLOBALS['_CFG']['integral_scale']);
        return $scale > 0 ? round(($integral / 100) * $scale, 2) : 0;
    }

    /**
     * 获取相关活动
     * @param $user_rank
     * @param bool $get
     * @return mixed
     */
    function get_favourable_activity($user_rank, $get = false){
        $now = time();
        if ($user_rank <= 0) $user_rank = 1;
        $m = M('favourable_activity');
        $m->alias('fa')
            ->field('fa.act_id,fa.act_name,fa.store_id,fa.discount,fa.act_type,fa.act_config')
            ->where("fa.act_starttime <= '$now' and fa.act_endtime >= '$now' and far.id='$user_rank' and fa.act_type " . $this->db_create_in(array(FAT_DISCOUNT, FAT_PRICE)))
            ->join('LEFT JOIN __FAVOURABLE_ACTIVITY_RANK__ far on far.act_id=fa.act_id');
            //->order('min_amount desc');
        if ($get == false){
            $favourable_list = $m->find();
        } else {
            $favourable_list = $m->select();
        }
        return $favourable_list;
    }

    /**
     * 计算购物车中的商品能享受红包支付的总额
     * @param $user_rank
     * @param string $sess
     * @return int 享受红包支付的总额
     */
    function compute_discount_amount($user_rank, $sess){
        /* 查询优惠活动 */
        $favourable_list = $this->get_favourable_activity($user_rank,true);
        if (!$favourable_list) {
            return 0;
        }

        /* 查询购物车商品 */
        /*$sql = "SELECT c.goods_id, c.goods_price * c.goods_number AS subtotal, g.cat_id, g.brand_id " .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " . $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE c.goods_id = g.goods_id " .
            "AND c.session_id = '" . SESS_ID . "' " .
            "AND c.parent_id = 0 " .
            "AND c.is_gift = 0 " .
            "AND rec_type = '" . CART_GENERAL_GOODS . "'";
        $goods_list = $GLOBALS['db']->getAll($sql);*/

        $goods_list = M('cart')
                    ->alias('c')
                    ->where("c.parent_id = 0 and c.is_gift = 0 and is_selected=1 and rec_type = '" . CART_GENERAL_GOODS . "' AND $sess")
                    ->join('LEFT JOIN __GOODS__ g ON c.goods_id = g.goods_id')
                    ->field('c.goods_id, c.goods_price * c.goods_number AS subtotal, g.cat_id, g.brand_id')
                    ->select();

        if (!$goods_list) {
            return 0;
        }

        /* 初始化折扣 */
        $discount = 0;
        $favourable_name = array();

        /* 循环计算每个优惠活动的折扣 */
        $cate_list = R('Public/get_categories_tree1');
        foreach ($favourable_list as $favourable)  {
            $total_amount = 0;
            //echo ($favourable['act_range']) . "\n";
            if ($favourable['act_range'] == FAR_ALL)  {     //全部商品
                foreach ($goods_list as $goods) {
                    $total_amount += $goods['subtotal'];
                }
            } elseif ($favourable['act_range'] == FAR_CATEGORY) {       // 按分类选择
                /* 找出分类id的子分类id */
                $id_list = array();
                $raw_id_list = explode(',', $favourable['act_range_ext']);
                foreach ($raw_id_list as $k => $id)  {
                    $cat = get_array_search($cate_list, 'cat_id', $id, 'children_list');
                    $cat_arr = get_array_tree($cat);
                    foreach ($cat_arr[0] as $kk => $v){
                        $id_list[] = $v['cat_id'];
                    }
                    //$id_list = array_merge($id_list, array_keys(cat_list($id, 0, false)));
                }
                $ids = join(',', array_unique($id_list));
                foreach ($goods_list as $goods) {
                    if (strpos(',' . $ids . ',', ',' . $goods['cat_id'] . ',') !== false)  {
                        $total_amount += $goods['subtotal'];
                    }
                }
            } elseif ($favourable['act_range'] == FAR_BRAND){       // 按品牌选择
                foreach ($goods_list as $goods)  {
                    if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $goods['brand_id'] . ',') !== false){
                        $total_amount += $goods['subtotal'];
                    }
                }
            } elseif ($favourable['act_range'] == FAR_GOODS) {      // 按商品选择
                foreach ($goods_list as $goods)  {
                    if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $goods['goods_id'] . ',') !== false)  {
                        $total_amount += $goods['subtotal'];
                    }
                }
            } else {
                continue;
            }
            if ($total_amount > 0 && $total_amount >= $favourable['min_amount'] && ($total_amount <= $favourable['max_amount'] || $favourable['max_amount'] == 0)) {
                if ($favourable['act_type'] == FAT_DISCOUNT)  {     // 价格打折优惠
                    $discount += $total_amount * (1 - $favourable['act_type_ext'] / 100);
                } elseif ($favourable['act_type'] == FAT_PRICE) {       // 现金减免
                    $discount += $favourable['act_type_ext'];
                }
            }
        }

        return $discount;
    }

    /**
     * 计算折扣：根据购物车和优惠活动
     * @param array $total
     * @return  float   折扣
     */
    function compute_discount($total) {
        /* 查询优惠活动 */
        $now = time();
        $user_rank = ',' . session('user_rank') . ',';
        /*$sql = "SELECT *" .
            "FROM " . $GLOBALS['ecs']->table('favourable_activity') .
            " WHERE start_time <= '$now'" .
            " AND end_time >= '$now'" .
            " AND CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
            " AND act_type " . $this->db_create_in(array(FAT_DISCOUNT, FAT_PRICE));
        $favourable_list = $GLOBALS['db']->getAll($sql);*/
        /*$favourable_list = M('favourable_activity')
            ->where("start-time <= '$now' and end_time >= '$now' and CONCAT(',', user_rank, ',') like '%$user_rank%' and act_type " . $this->db_create_in(array(FAT_DISCOUNT, FAT_PRICE)))
            ->select();*/
        $rank = session('user_rank');
        $favourable_list = $this->get_favourable_activity($rank);
        if (!$favourable_list) {
            return 0;
        }

        /* 查询购物车商品 */
        $w = $this->getSessionIDorUserID('c');
        $sql = "SELECT c.goods_id, c.goods_price * c.goods_number AS subtotal, g.cat_id, g.brand_id " .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " . $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE c.goods_id = g.goods_id " .
            "AND $w " .
            "AND c.parent_id = 0 " .
            "AND c.is_gift = 0 " .
            "AND rec_type = '" . CART_GENERAL_GOODS . "'";
        $goods_list = $GLOBALS['db']->getAll($sql);
        if (!$goods_list) {
            return 0;
        }

        /* 初始化折扣 */
        $discount = 0;
        $favourable_name = array();

        /* 循环计算每个优惠活动的折扣 */
        foreach ($favourable_list as $favourable) {
            $total_amount = 0;
            if ($favourable['act_range'] == FAR_ALL) {
                foreach ($goods_list as $goods) {
                    $total_amount += $goods['subtotal'];
                }
            } elseif ($favourable['act_range'] == FAR_CATEGORY) {
                /* 找出分类id的子分类id */
                $id_list = array();
                $raw_id_list = explode(',', $favourable['act_range_ext']);
                foreach ($raw_id_list as $id) {
                    $id_list = array_merge($id_list, array_keys($this->cat_list($id, 0, false)));
                }
                $ids = join(',', array_unique($id_list));

                foreach ($goods_list as $goods) {
                    if (strpos(',' . $ids . ',', ',' . $goods['cat_id'] . ',') !== false) {
                        $total_amount += $goods['subtotal'];
                    }
                }
            } elseif ($favourable['act_range'] == FAR_BRAND) {
                foreach ($goods_list as $goods) {
                    if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $goods['brand_id'] . ',') !== false) {
                        $total_amount += $goods['subtotal'];
                    }
                }
            } elseif ($favourable['act_range'] == FAR_GOODS) {
                foreach ($goods_list as $goods) {
                    if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $goods['goods_id'] . ',') !== false) {
                        $total_amount += $goods['subtotal'];
                    }
                }
            } else {
                continue;
            }

            /* 如果金额满足条件，累计折扣 */
            if ($total_amount > 0 && $total_amount >= $favourable['min_amount'] && ($total_amount <= $favourable['max_amount'] || $favourable['max_amount'] == 0)) {
                if ($favourable['act_type'] == FAT_DISCOUNT) {
                    $discount += $total_amount * (1 - $favourable['act_type_ext'] / 100);

                    $favourable_name[] = $favourable['act_name'];
                } elseif ($favourable['act_type'] == FAT_PRICE) {
                    $discount += $favourable['act_type_ext'];

                    $favourable_name[] = $favourable['act_name'];
                }
            }
        }

        return array('discount' => $discount, 'name' => $favourable_name);
    }

    /**
     * 计算折扣：根据购物车和优惠活动
     * @param array $order
     * @return array
     * @Author: keheng $
     * @date: 2013-05-20
     */
    function Calculate_discount($order = array()){
        /* 查询优惠活动 */
        $now = time();
        $ids = array();
        $user_rank = intval(session('user_rank'));
        $countAmt = isset($order['countAmt'])?$order['countAmt']:null;
        /*取符合条件优惠活动*/
        $res2 = $this->get_favourable_activity($user_rank);
        $arr = array('name'=>'','youhui_price'=>0,'discount'=>0,'price'=>0);
        if (!$res2) {
            $res2['act_name'] = '无优惠活动';
        } else {
            if ( $res2['act_type'] == FAT_PRICE){               //现金减免
                $arr['price'] = $countAmt - $res2['act_type_ext'];
            }elseif( $res2['act_type'] == FAT_DISCOUNT){        // 价格打折优惠
                $arr['price'] = $countAmt * $res2['act_type_ext'] / 100;
            }else{
                $arr['price'] = $countAmt;
            }
            $arr['youhui_price'] = $countAmt - $arr['price'];
            $arr['discount'] = $arr['youhui_price'];
            $arr['name'] = array($res2['act_name']);
        }
        return $arr;
    }

    /**
     * 取得包装列表
     * @return  array   包装列表
     */
    function pack_list() {
        $res = M('pack')->select();
        $list = array();
        if ($res) {
            foreach($res as $row) {
                $row['format_pack_fee'] = $this->price_format($row['pack_fee'], false);
                $row['format_free_money'] = $this->price_format($row['free_money'], false);
                $list[] = $row;
            }
        }

        return $list;
    }

    /**
     * 取得包装信息
     * @param   int     $pack_id    包装id
     * @return  array   包装信息
     */
    function pack_info($pack_id) {
        return M('pack')->where("pack_id = '$pack_id'")->find();
    }

    /**
     * 根据订单中的商品总额来获得包装的费用
     *
     * @access  public
     * @param   integer $pack_id
     * @param   float   $goods_amount
     * @return  float
     */
    function pack_fee($pack_id, $goods_amount) {
        $pack = $this->pack_info($pack_id);
        $val = (floatval($pack['free_money']) <= $goods_amount && $pack['free_money'] > 0) ? 0 : floatval($pack['pack_fee']);
        return $val;
    }

    /**
     * 取得贺卡列表
     * @return  array   贺卡列表
     */
    function card_list() {
        $res = M('card')->select();
        $list = array();
        if ($res) {
            foreach($res as $row) {
                $row['format_card_fee'] = $this->price_format($row['card_fee'], false);
                $row['format_free_money'] = $this->price_format($row['free_money'], false);
                $list[] = $row;
            }
        }

        return $list;
    }


    /**
     * 取得购物车总金额
     * @params  boolean $include_gift   是否包括赠品
     * @param   int     $type           类型：默认普通商品
     * @return  float   购物车总金额
     */
    function cart_amount($sess, $include_gift = true, $type = CART_GENERAL_GOODS) {
        /*$sql = "SELECT SUM(goods_price * goods_number) " .
            " FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE $sess " .
            "AND rec_type = '$type' ";*/
        $sql = '';
        if (!$include_gift) {
            $sql = ' AND is_gift = 0 AND goods_id > 0';
        }
        $sum = M('cart')->alias('c')->where("rec_type = '$type' and $sess $sql")->getfield('SUM(goods_price * goods_number)');
        return floatval($sum);
    }
    /**
     * 取得贺卡信息
     * @param   int     $card_id    贺卡id
     * @return  array   贺卡信息
     */
    function card_info($card_id) {
        return M('card')->where("card_id = '$card_id'")->select();
    }

    /**
     * 根据订单中商品总额获得需要支付的贺卡费用
     *
     * @access  public
     * @param   integer $card_id
     * @param   float   $goods_amount
     * @return  float
     */
    function card_fee($card_id, $goods_amount) {
        $card = $this->card_info($card_id);
        return ($card['free_money'] <= $goods_amount && $card['free_money'] > 0) ? 0 : $card['card_fee'];
    }


    /**
     * 取得用户当前可用优惠券
     * @param   int     $user_id        用户id
     * @param   float   $goods_amount   订单商品金额
     * @return  array   优惠券数组
     */
    function user_bonus($user_id, $goods_amount = 0) {
        $day = getdate();
        $today = $this->local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);

        /*$sql = "SELECT t.type_id, t.type_name, t.type_money, b.bonus_id " .
            "FROM " . $GLOBALS['ecs']->table('bonus_type') . " AS t," .
            $GLOBALS['ecs']->table('user_bonus') . " AS b " .
            "WHERE t.type_id = b.bonus_type_id " .
            "AND t.use_start_date <= '$today' " .
            "AND t.use_end_date >= '$today' " .
            "AND t.min_goods_amount <= '$goods_amount' " .
            "AND b.user_id = '$user_id' " .
            "AND b.order_id = 0";
        //exit($sql);*/
        return  M('bonus_type')
            ->alias('bt')
            ->where("bt.use_start_date <= '$today' AND bt.use_end_date >= '$today'
            AND bt.min_goods_amount <= '$goods_amount' AND ub.user_id = '$user_id' AND ub.order_id = 0")
            ->join('LEFT JOIN __USER_BONUS__ ub ON bt.type_id=ub.bonus_type_id')
            ->select();
    }

    /**
     * 取得优惠券信息
     * @param   int     $bonus_id   优惠券id
     * @param   string  $bonus_sn   优惠券序列号
     * @param   array   优惠券信息
     */
    function bonus_info($bonus_id, $bonus_sn = '') {
        /*$sql = "SELECT t.*, b.* " .
            "FROM " . $GLOBALS['ecs']->table('bonus_type') . " AS t," .
            $GLOBALS['ecs']->table('user_bonus') . " AS b " .
            "WHERE t.type_id = b.bonus_type_id ";*/
        if ($bonus_id > 0) {
            $sql = " b.bonus_id = '$bonus_id'";
        } else {
            $sql = " b.bonus_sn = '$bonus_sn'";
        }
        return M('bonus_type')
            ->alias('t')
            ->where($sql)
            ->join('LEFT JOIN __USER_BONUS__ b on b.bonus_type_id=t.type_id')
            ->select();
    }

    /**
     * 检查优惠券是否已使用
     * @param   int $bonus_id   优惠券id
     * @return  bool
     */
    function bonus_used($bonus_id) {
        /*$sql = "SELECT order_id FROM " . $GLOBALS['ecs']->table('user_bonus') .
            " WHERE bonus_id = '$bonus_id'";
        return $GLOBALS['db']->getOne($sql) > 0;*/
        return M('user_bonus')->where("bonus_id = '$bonus_id'")->getfield('order_id') > 0;
    }

    /**
     * 设置优惠券为已使用
     * @param   int     $bonus_id   优惠券id
     * @param   int     $order_id   订单id
     * @return  bool
     */
    function use_bonus($bonus_id, $order_id) {
        /*$sql = "UPDATE " . $GLOBALS['ecs']->table('user_bonus') .
            " SET order_id = '$order_id', used_time = '" . gmtime() . "' " .
            "WHERE bonus_id = '$bonus_id' LIMIT 1";
        M('user_bonus')->where("bonus_id = '$bonus_id'")->save(array('order_id'=>$order_id,'used_time'=>time()));
        return $GLOBALS['db']->query($sql);*/
        return M('user_bonus')->where("bonus_id = '$bonus_id'")->save(array('order_id'=>$order_id,'used_time'=>time()));
    }

    /**
     * 设置优惠券为未使用
     * @param   int     $bonus_id   优惠券id
     * @param   int     $order_id   订单id
     * @return  bool
     */
    function unuse_bonus($bonus_id) {
        /*$sql = "UPDATE " . $GLOBALS['ecs']->table('user_bonus') .
            " SET order_id = 0, used_time = 0 " .
            "WHERE bonus_id = '$bonus_id' LIMIT 1";
        return $GLOBALS['db']->query($sql);*/
        return M('user_bonus')->where("bonus_id = '$bonus_id'")->save(array('order_id'=>0,'used_time'=>0));
    }


    /**
     * 取得当前用户应该得到的红包总额
     * @return float mixed
     */
    function get_total_bonus()  {
        $day    = getdate();
        $today  = $this->local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);

        /* 按商品发的红包 */
        /*$sql = "SELECT SUM(c.goods_number * t.type_money)" .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, "
            . $GLOBALS['ecs']->table('bonus_type') . " AS t, "
            . $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE c.session_id = '" . SESS_ID . "' " .
            "AND c.is_gift = 0 " .
            "AND c.goods_id = g.goods_id " .
            "AND g.bonus_type_id = t.type_id " .
            "AND t.send_type = '" . SEND_BY_GOODS . "' " .
            "AND t.send_start_date <= '$today' " .
            "AND t.send_end_date >= '$today' " .
            "AND c.rec_type = '" . CART_GENERAL_GOODS . "'";
        $goods_total = floatval($GLOBALS['db']->getOne($sql));*/
        $where = $this->getSessionIDorUserID('c');
        $goods_total = M('cart')->alias('c')
                        ->where("c.is_gift = 0 and t.send_type = '" . SEND_BY_GOODS . "' and t.send_start_date <= '$today' and c.rec_type = '" . CART_GENERAL_GOODS . "' and $where")
                        ->join('LEFT JOIN __GOODS__ g ON c.goods_id = g.goods_id')
                        ->join('LEFT JOIN __BONUS_TYPE__ t ON g.bonus_type_id = t.type_id')
                        ->getfield('SUM(c.goods_number * t.type_money)');
        /* 取得购物车中非赠品总金额 */
        /*$sql = "SELECT SUM(goods_price * goods_number) " .
            "FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE session_id = '" . SESS_ID . "' " .
            " AND is_gift = 0 " .
            " AND rec_type = '" . CART_GENERAL_GOODS . "'";
        $amount = floatval($GLOBALS['db']->getOne($sql));*/
        /*$amount = M('cart')->alias('c')
                    ->where("is_gift = 0 and rec_type = '" . CART_GENERAL_GOODS . "' and $where")
                    ->getfield('SUM(goods_price * goods_number)');*/
        $amount = $this->cart_amount($where,true,CART_GENERAL_GOODS);
        /* 按订单发的红包 */
        /*$sql = "SELECT FLOOR('$amount' / min_amount) * type_money " .
            "FROM " . $GLOBALS['ecs']->table('bonus_type') .
            " WHERE send_type = '" . SEND_BY_ORDER . "' " .
            " AND send_start_date <= '$today' " .
            "AND send_end_date >= '$today' " .
            "AND min_amount > 0 ";
        $order_total = floatval($GLOBALS['db']->getOne($sql));*/
        $order_total = M('bonus_type')
                        ->where("send_type = '" . SEND_BY_ORDER . "' and send_start_date <= '$today' AND send_end_date >= '$today' AND min_amount > 0 ")
                        ->getfield(" FLOOR('$amount' / min_amount) * type_money");
        $order_total = floatval($order_total);
        return $goods_total + $order_total;
    }

    /**
     * 处理红包（下订单时设为使用，取消（无效，退货）订单时设为未使用
     * @param int $bonus_id     红包编号
     * @param int $order_id     订单号
     * @param bool $is_used 是否使用了
     */
    function change_user_bonus($bonus_id, $order_id, $is_used = true) {
        $time = time();
        if ($is_used) {
            $data = array(
                'used_time' => $time,
                'order_id'  => $order_id
            );
        } else {
            $data = array(
                'used_time' => 0,
                'order_id'  => 0
            );
        }
        M('user_bonus')->where("bonus_id = '$bonus_id'")->save($data);
    }



    /**
     * 取得已安装的配送方式
     * @return  array   已安装的配送方式
     */
    function shipping_list() {
        /*$sql = 'SELECT shipping_id, shipping_name ' .
            'FROM ' . $GLOBALS['ecs']->table('shipping') .
            ' WHERE enabled = 1';*/

        return  M('shipping')->where('enabled = 1')->field('shipping_id, shipping_name ')->select();
    }

    /**
     * 取得配送方式信息
     * @param   int     $shipping_id    配送方式id
     * @return  array   配送方式信息
     */
    function shipping_info($shipping_id) {
        return  M('shipping')->where("enabled = 1 and shipping_id = '$shipping_id'")->find();
    }

    /**
     * 取得可用的配送方式列表
     * @param   array   $region_id_list     收货人地区id数组（包括国家、省、市、区）
     * @return  array   配送方式数组
     */
    function available_shipping_list($region_id_list) {
        return M('shipping')->alias('s')
            ->where($this->db_create_in($region_id_list, 'r.region_id') . ' AND s.enabled = 1')
            ->field('s.shipping_id, s.shipping_code, s.shipping_name, s.shipping_desc, s.insure, s.support_cod, a.configure')
            ->join('LEFT JOIN __SHIPPING_AREA__ a ON a.shipping_id = s.shipping_id')
            ->join('LEFT JOIN __AREA_REGION__ r ON r.shipping_area_id = a.shipping_area_id')
            ->order('s.shipping_order')
            ->select();
    }

    /**
     * 取得某配送方式对应于某收货地址的区域信息
     * @param   int     $shipping_id        配送方式id
     * @param   array   $region_id_list     收货人地区id数组
     * @return  array   配送区域信息（config 对应着反序列化的 configure）
     */
    function shipping_area_info($shipping_id, $region_id_list)  {
        $row = M('shipping')
            ->alias('s')
            ->field('s.shipping_code, s.shipping_name, s.shipping_desc, s.insure, s.support_cod, a.configure')
            ->join('LEFT JOIN __SHIPPING_AREA__ a ON a.shipping_id = s.shipping_id')
            ->join('LEFT JOIN __AREA_REGION__ r ON r.shipping_area_id=a.shipping_area_id')
            ->where("s.shipping_id = '$shipping_id' AND r.region_id " . $this->db_create_in($region_id_list) ."  AND s.enabled = 1")
            ->find();
        //print_r($row);
        if (!empty($row)) {
            $shipping_config = $this->unserialize_config($row['configure']);
            if (isset($shipping_config['pay_fee'])) {
                if (strpos($shipping_config['pay_fee'], '%') !== false) {
                    $row['pay_fee'] = floatval($shipping_config['pay_fee']) . '%';
                } else {
                    $row['pay_fee'] = floatval($shipping_config['pay_fee']);
                }
            } else {
                $row['pay_fee'] = 0.00;
            }
            //print_r($shipping_config);
        }

        return $row;
    }

    /**
     * 计算运费
     * @param string    $shipping_code          配送方式代码
     * @param string    $shipping_config        配送方式配置信息
     * @param float     $goods_weight           商品重量
     * @param float     $goods_amount           商品金额
     * @param int       $goods_number           商品数量
     * @return int  运费
     */
    function shipping_fee($shipping_code, $shipping_config, $goods_weight, $goods_amount, $goods_number=0) {
        if (!is_array($shipping_config)) {
            $shipping_config = unserialize($shipping_config);
        }
        $class = 'Wap\Plugins\shipping\\' . $shipping_code;
        $url = APP_PATH . 'Wap\Plugins\shipping\\' . $shipping_code . '.class.php';
        if (file_exists($url)){
            //require ($url);
            $obj = new $class($shipping_config);//exit($filename);
            return $obj->calculate($goods_weight, $goods_amount, $goods_number);
        } else {
            return 0;
        }

    }

    /**
     * 获取指定配送的保价费用
     *
     * @access  public
     * @param   string      $shipping_code  配送方式的code
     * @param   float       $goods_amount   保价金额
     * @param   mix         $insure         保价比例
     * @return  float
     */
    function shipping_insure_fee($shipping_code, $goods_amount, $insure)
    {
        if (strpos($insure, '%') === false)
        {
            /* 如果保价费用不是百分比则直接返回该数值 */
            return floatval($insure);
        }
        else
        {
            //$path = APP_PATH . '../Public/plugins/shipping/class/' . $shipping_code . '.php';
            $class = 'Home\Plugins\shipping\\' . $shipping_code;
            if (file_exists(APP_PATH . $class . '.class.php'))  {
                //include_once($path);
                $shipping = new $class;//new $shipping_code;
                $insure   = floatval($insure) / 100;

                if (method_exists($shipping, 'calculate_insure'))     {
                    return $shipping->calculate_insure($goods_amount, $insure);
                }
                else
                {
                    return ceil($goods_amount * $insure);
                }
            }
            else
            {
                return false;
            }
        }
    }

    /**
     * 取得已安装的支付方式列表
     * @return  array   已安装的配送方式列表
     * //TODO:已经替换
     */
    function payment_list()  {
        return M('payment')->where('enabled = 1')->field('pay_id, pay_name')->select();
    }

    /**
     * 取得支付方式信息
     * @param   int     $pay_id     支付方式id
     * @return  array   支付方式信息
     * //TODO:已经替换
     */
    function payment_info($pay_id) {
        return  M('payment')->where("pay_id = '$pay_id' AND enabled = 1")->find();
    }

    /**
     * 获得订单需要支付的支付费用
     *
     * @access  public
     * @param   integer $payment_id
     * @param   float   $order_amount
     * @param   mix     $cod_fee
     * @return  float
     * //TODO:已经替换
     */
    function pay_fee($payment_id, $order_amount, $cod_fee=null) {
        $pay_fee = 0;
        $payment = $this->payment_info($payment_id);
        $rate    = ($payment['is_cod'] && !is_null($cod_fee)) ? $cod_fee : $payment['pay_fee'];
        if (strpos($rate, '%') !== false)     {
            /* 支付费用是一个比例 */
            $val     = floatval($rate) / 100;
            $pay_fee = $val > 0 ? $order_amount * $val /(1- $val) : 0;
        }  else  {
            $pay_fee = floatval($rate);
        }
        return round($pay_fee, 2);
    }

    /**
     * 取得可用的支付方式列表
     * @param   bool    $support_cod        配送方式是否支持货到付款
     * @param   int     $cod_fee            货到付款手续费（当配送方式支持货到付款时才传此参数）
     * @param   int     $is_online          是否支持在线支付
     * @return  array   配送方式数组
     * //TODO:已经替换
     */
    function available_payment_list($support_cod, $cod_fee = 0, $is_online = false)   {
        /*$sql = 'SELECT pay_id, pay_code, pay_name, pay_fee, pay_desc, pay_config, is_cod' .
            ' FROM ' . $GLOBALS['ecs']->table('payment') .
            ' WHERE enabled = 1 ';*/
        $sql = '';
        if (!$support_cod){
            $sql = ' AND is_cod = 0 '; // 如果不支持货到付款
        }
        if ($is_online) {
            $sql .= " AND is_online = '1' ";
        }
        /*$sql .= 'ORDER BY pay_order'; // 排序
        $res = $GLOBALS['db']->query($sql);*/
        $res = M('payment')->field('pay_id, pay_code, pay_name, pay_fee, pay_desc, pay_config, is_cod')
            ->where('enabled = 1 ' . $sql)->order('pay_order')->select();
        $pay_list = array();
        /*while ($row = $GLOBALS['db']->fetchRow($res))*/
        foreach($res as $row)  {
            if ($row['is_cod'] == '1') {
                $row['pay_fee'] = $cod_fee;
            }
            $row['format_pay_fee'] = strpos($row['pay_fee'], '%') !== false ? $row['pay_fee'] :
                $this->price_format($row['pay_fee'], false);
            $modules[] = $row;
        }
        //include_once(APP_PATH . '../Public/plugins/lib_compositor.php');
        if(isset($modules))
        {
            return $modules;
        }
    }


    /**
     * 获得购物车中商品的总重量、总价格、总数量
     * @param $user_id
     * @param int $type 类型：默认普通商品
     * @return mixed
     */
    function cart_weight_price($user_id, $type = CART_GENERAL_GOODS) {
        $package_row['weight'] = 0;
        $package_row['amount'] = 0;
        $package_row['number'] = 0;
        $packages_row['free_shipping'] = 1;

        /* 计算超值礼包内商品的相关配送参数 */
        $row = M('cart')
            ->where("extension_code = 'package_buy' and user_id={$user_id}")
            ->field('goods_id, goods_number, goods_price ')
            ->select();

        if ($row) {
            $packages_row['free_shipping'] = 0;
            $free_shipping_count = 0;

            foreach ($row as $val)  {
                // 如果商品全为免运费商品，设置一个标识变量
                $shipping_count = M('package_goods')
                            ->alias('pg')
                            ->where("g.is_shipping = 0 AND pg.package_id = '"  . $val['goods_id'] . "'")
                            ->join('LEFT JOIN __GOODS__ g on g.goods_id = pg.goods_id')
                            ->getfield('count(*)');
                if ($shipping_count > 0)  {
                    // 循环计算每个超值礼包商品的重量和数量，注意一个礼包中可能包换若干个同一商品
                    $goods_row = M('package_goods')
                                ->alias('pg')
                                ->where("g.is_shipping = 0 AND pg.package_id = '"  . $val['goods_id'] . "'")
                                ->join('LEFT JOIN __GOODS__ g on g.goods_id = pg.goods_id')
                                ->field('SUM(g.goods_weight * pg.goods_number) AS weight, SUM(pg.goods_number) AS number')
                                ->find();

                    $package_row['weight'] += floatval($goods_row['weight']) * $val['goods_number'];
                    $package_row['amount'] += floatval($val['goods_price']) * $val['goods_number'];
                    $package_row['number'] += intval($goods_row['number']) * $val['goods_number'];
                } else {
                    $free_shipping_count++;
                }
            }
            $packages_row['free_shipping'] = $free_shipping_count == count($row) ? 1 : 0;
        }

        /* 获得购物车中非超值礼包商品的总重量 */
        $row = M('cart')
            ->alias('c')
            ->where("user_id={$user_id} and rec_type = '$type' AND g.is_shipping = 0 AND c.extension_code != 'package_buy'")
            ->join('LEFT JOIN __GOODS__ g ON g.goods_id = c.goods_id')
            ->field('SUM(g.goods_weight * c.goods_number) AS weight, SUM(c.goods_price * c.goods_number) AS amount, SUM(c.goods_number) AS number')
            ->find();
        $packages_row['weight'] = floatval($row['weight']) + $package_row['weight'];
        $packages_row['amount'] = floatval($row['amount']) + $package_row['amount'];
        $packages_row['number'] = intval($row['number']) + $package_row['number'];
        /* 格式化重量 */
        $packages_row['formated_weight'] = $this->formated_weight($packages_row['weight']);
        return $packages_row;
    }


    /**
     * 添加礼包到购物车
     *
     * @access  public
     * @param   integer $package_id   礼包编号
     * @param   integer $num          礼包数量
     * @return  boolean
     */
    function add_package_to_cart($package_id, $num = 1)
    {
        $GLOBALS['err']->clean();

        /* 取得礼包信息 */
        $package = $this->get_package_info($package_id);

        if (empty($package))
        {
            $GLOBALS['err']->add($GLOBALS['_LANG']['goods_not_exists'], ERR_NOT_EXISTS);

            return false;
        }

        /* 是否正在销售 */
        if ($package['is_on_sale'] == 0)
        {
            $GLOBALS['err']->add($GLOBALS['_LANG']['not_on_sale'], ERR_NOT_ON_SALE);

            return false;
        }

        /* 现有库存是否还能凑齐一个礼包 */
        if ($GLOBALS['_CFG']['use_storage'] == '1' && $this->judge_package_stock($package_id))
        {
            $GLOBALS['err']->add(sprintf($GLOBALS['_LANG']['shortage'], 1), ERR_OUT_OF_STOCK);

            return false;
        }

        /* 检查库存 */
//    if ($GLOBALS['_CFG']['use_storage'] == 1 && $num > $package['goods_number'])
//    {
//        $num = $goods['goods_number'];
//        $GLOBALS['err']->add(sprintf($GLOBALS['_LANG']['shortage'], $num), ERR_OUT_OF_STOCK);
//
//        return false;
//    }

        /* 初始化要插入购物车的基本件数据 */
        $parent = array(
            'user_id'       => $_SESSION['user_id'],
            'session_id'    => session('SESS_ID'),
            'goods_id'      => $package_id,
            'goods_sn'      => '',
            'goods_name'    => addslashes($package['package_name']),
            'market_price'  => $package['market_package'],
            'goods_price'   => $package['package_price'],
            'goods_number'  => $num,
            'goods_attr'    => '',
            'goods_attr_id' => '',
            'is_real'       => $package['is_real'],
            'extension_code'=> 'package_buy',
            'is_gift'       => 0,
            'rec_type'      => CART_GENERAL_GOODS
        );
        $sess = $this->getSessionIDorUserID();
        /* 如果数量不为0，作为基本件插入 */
        if ($num > 0)
        {
            /* 检查该商品是否已经存在在购物车中 */
            /*$sql = "SELECT goods_number FROM " .$GLOBALS['ecs']->table('cart').
                " WHERE session_id = '" .SESS_ID. "' AND goods_id = '" . $package_id . "' ".
                " AND parent_id = 0 AND extension_code = 'package_buy' " .
                " AND rec_type = '" . CART_GENERAL_GOODS . "'";
            $row = $GLOBALS['db']->getRow($sql);*/
            $row = M('cart')->where("goods_id = '" . $package_id . "' AND parent_id = 0 AND extension_code = 'package_buy' AND rec_type = '" . CART_GENERAL_GOODS . "' and " . $sess)
                    ->field('goods_number')
                    ->find();
            if($row) //如果购物车已经有此物品，则更新
            {
                $num += $row['goods_number'];
                if ($GLOBALS['_CFG']['use_storage'] == 0 || $num > 0)
                {
                    /*$sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET goods_number = '" . $num . "'" .
                        " WHERE session_id = '" .SESS_ID. "' AND goods_id = '$package_id' ".
                        " AND parent_id = 0 AND extension_code = 'package_buy' " .
                        " AND rec_type = '" . CART_GENERAL_GOODS . "'";
                    $GLOBALS['db']->query($sql);*/
                    $data = array('goods_number'=>$num);
                    M('cart')->where("goods_id = '$package_id' AND parent_id = 0 AND extension_code = 'package_buy'
                    AND rec_type = '" . CART_GENERAL_GOODS . "' and $sess")->save($data);
                }
                else
                {
                    $GLOBALS['err']->add(sprintf($GLOBALS['_LANG']['shortage'], $num), ERR_OUT_OF_STOCK);
                    return false;
                }
            }
            else //购物车没有此物品，则插入
            {
                //$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('cart'), $parent, 'INSERT');
                M('cart')->add($parent);
            }
        }

        /* 把赠品删除 */
        /*$sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE session_id = '" . SESS_ID . "' AND is_gift <> 0";
        $GLOBALS['db']->query($sql);*/
        M('cart')->where('is_gift <> 0 and ' . $sess)->delete();
        return true;
    }

    /**
    * 获取指定id package 的信息
    * @access  public
    * @param   int         $id         package_id
    * @return array       array(package_id, package_name, goods_id,start_time, end_time, min_price, integral)
    */
    function get_package_info($id) {
        global $ecs, $db, $_CFG;
        $id = is_numeric($id) ? intval($id) : 0;
        $now = time();
        /*$sql = "SELECT act_id AS id,  act_name AS package_name, goods_id , goods_name, start_time, end_time, act_desc, ext_info" .
            " FROM " . $GLOBALS['ecs']->table('goods_activity') .
            " WHERE act_id='$id' AND act_type = " . GAT_PACKAGE;

        $package = $db->GetRow($sql);*/
        $package = M('goods_activity')->where("act_id='$id' AND act_type = " . GAT_PACKAGE)
            ->field('act_id AS id,  act_name AS package_name, goods_id , goods_name, start_time, end_time, act_desc, ext_info')
            ->find();
        /* 将时间转成可阅读格式 */
        if ($package['start_time'] <= $now && $package['end_time'] >= $now) {
            $package['is_on_sale'] = "1";
        } else {
            $package['is_on_sale'] = "0";
        }
        $package['start_time'] = date('Y-m-d H:i', $package['start_time']);
        $package['end_time'] = date('Y-m-d H:i', $package['end_time']);
        $row = unserialize($package['ext_info']);
        unset($package['ext_info']);
        if ($row) {
            foreach ($row as $key => $val) {
                $package[$key] = $val;
            }
        }

        /*$sql = "SELECT pg.package_id, pg.goods_id, pg.goods_number, pg.admin_id, " .
            " g.goods_sn, g.goods_name, g.market_price, g.goods_thumb, g.is_real, " .
            " IFNULL(mp.user_price, g.shop_price * '$_SESSION[discount]') AS rank_price " .
            " FROM " . $GLOBALS['ecs']->table('package_goods') . " AS pg " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g  ON g.goods_id = pg.goods_id " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            " WHERE pg.package_id = " . $id . " " .
            " ORDER BY pg.package_id, pg.goods_id";

        $goods_res = $GLOBALS['db']->getAll($sql, true);*/

        $goods_res = M('package_goods')
            ->alias('pg')
            ->field('pg.package_id, pg.goods_id, pg.goods_number, pg.admin_id,g.goods_sn, g.goods_name, g.market_price, g.goods_thumb, g.is_real,IFNULL(mp.user_price, g.shop_price * ' . session('discount') . ') AS rank_price')
            ->join('LEFT JOIN __MEMBER_PRICE__ mp ON mp.goods_id = g.goods_id AND mp.user_rank = ' . session('user_rank'))
            ->join('LEFT JOIN __GOODS__ g ON g.goods_id = pg.goods_id')
            ->where('pg.package_id = ' . $id )
            ->order('pg.package_id, pg.goods_id')
            ->select();
        $market_price = 0;
        $real_goods_count = 0;
        $virtual_goods_count = 0;

        foreach ($goods_res as $key => $val) {
            $goods_res[$key]['goods_thumb'] = $this->get_image_path($val['goods_id'], $val['goods_thumb'], true);
            $goods_res[$key]['market_price_format'] = $this->price_format($val['market_price']);
            $goods_res[$key]['rank_price_format'] = $this->price_format($val['rank_price']);
            $market_price += $val['market_price'] * $val['goods_number'];
            /* 统计实体商品和虚拟商品的个数 */
            if ($val['is_real']) {
                $real_goods_count++;
            } else {
                $virtual_goods_count++;
            }
        }

        if ($real_goods_count > 0) {
            $package['is_real'] = 1;
        } else {
            $package['is_real'] = 0;
        }

        $package['goods_list'] = $goods_res;
        $package['market_package'] = $market_price;
        $package['market_package_format'] = $this->price_format($market_price);
        $package['package_price_format'] = $this->price_format($package['package_price']);

        return $package;
    }

    /**
     * 获得指定礼包的商品
     *
     * @access  public
     * @param   integer $package_id
     * @return  array
     */
    function get_package_goods($package_id,  $package_attr_id='') {
        /*$sql = "SELECT pg.goods_id, g.goods_name, pg.goods_number, p.goods_attr, p.product_number, p.product_id
            FROM " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON pg.goods_id = g.goods_id
                LEFT JOIN " . $GLOBALS['ecs']->table('products') . " AS p ON pg.product_id = p.product_id
            WHERE pg.package_id = '$package_id'";*/
        if ($package_id == 0) {
            $sql = " AND pg.admin_id = '$_SESSION[admin_id]'";
        }
        //$resource = $GLOBALS['db']->getall($sql, true);

        $resource = M('package_goods')->alias('pg')->field('pg.goods_id, g.goods_name, pg.goods_number, p.goods_attr, p.product_number, p.product_id')
                    ->join('LEFT JOIN __PRODUCTS__ p ON pg.product_id = p.product_id')
                    ->join('LEFT JOIN __GOODS__ g ON pg.goods_id = g.goods_id')
                    ->where("pg.package_id = '$package_id'" . $sql)
                    ->select();
        if (!$resource) {
            return array();
        }

        $row = array();

        /* 生成结果数组 取存在货品的商品id 组合商品id与货品id */
        $good_product_str = '';
        foreach($resource as $k => $_row)
            //while ($_row = $GLOBALS['db']->fetch_array($resource))
        {
            if ($_row['product_id'] > 0) {
                /* 取存商品id */
                $good_product_str .= ',' . $_row['goods_id'];

                /* 组合商品id与货品id */
                $_row['g_p'] = $_row['goods_id'] . '_' . $_row['product_id'];
            } else {
                /* 组合商品id与货品id */
                $_row['g_p'] = $_row['goods_id'];
            }

            //生成结果数组
            $row[] = $_row;
        }
        $good_product_str = trim($good_product_str, ',');

        /* 释放空间 */
        unset($resource, $_row, $sql);

        /* 取商品属性 */
        if ($good_product_str != '') {
            /*$sql = "SELECT goods_attr_id, attr_value FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE goods_id IN ($good_product_str)";
            $result_goods_attr = $GLOBALS['db']->getAll($sql, true);*/
            $result_goods_attr = M('goods_attr')->field('goods_attr_id, attr_value')->where('goods_id IN (' . $good_product_str . ')')->select();
            $_goods_attr = array();
            foreach ($result_goods_attr as $value) {
                $_goods_attr[$value['goods_attr_id']] = $value['attr_value'];
            }
        }

        /* 过滤货品 */
        $format[0] = '%s[%s]--[%d]';
        $format[1] = '%s--[%d]';
        foreach ($row as $key => $value) {
            if ($value['goods_attr'] != '') {
                $goods_attr_array = explode('|', $value['goods_attr']);

                $goods_attr = array();
                foreach ($goods_attr_array as $_attr) {
                    $goods_attr[] = $_goods_attr[$_attr];
                }

                $row[$key]['goods_name'] = sprintf($format[0], $value['goods_name'], implode('，', $goods_attr), $value['goods_number']);
            } else {
                $row[$key]['goods_name'] = sprintf($format[1], $value['goods_name'], $value['goods_number']);
            }
        }

        return $row;
    }


    /**
     * 重新获得商品图片与商品相册的地址
     *
     * @param int $goods_id 商品ID
     * @param string $image 原商品相册图片地址
     * @param boolean $thumb 是否为缩略图
     * @param string $call 调用方法(商品图片还是商品相册)
     * @param boolean $del 是否删除图片
     *
     * @return string   $url
     */
    function get_image_path($goods_id, $image='', $thumb=false, $call='goods', $del=false) {
        $url = empty($image) ? $GLOBALS['_CFG']['no_picture'] : $image;
        //$url = str_replace('images/','http://image'.floor(substr($goods_id,-1)/2).'.mjiajia.cn/images/',$url);
        return $url;
    }


    /**
     * 得到新发货单号
     * @return  string
     */
    function get_delivery_sn() {
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        return date('YmdHi') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * 检查礼包内商品的库存
     * @param int $package_id
     * @param int $package_num
     * @return bool
     */
    function judge_package_stock($package_id, $package_num = 1) {
        /*$sql = "SELECT goods_id, product_id, goods_number
            FROM " . $GLOBALS['ecs']->table('package_goods') . "
            WHERE package_id = '" . $package_id . "'";
        $row = $GLOBALS['db']->getAll($sql);*/
        $row = M('package_goods')->where("package_id = '" . $package_id . "'")->field('goods_id, product_id, goods_number')->select();
        if (empty($row))
        {
            return true;
        }

        /* 分离货品与商品 */
        $goods = array('product_ids' => '', 'goods_ids' => '');
        foreach ($row as $value)
        {
            if ($value['product_id'] > 0)
            {
                $goods['product_ids'] .= ',' . $value['product_id'];
                continue;
            }

            $goods['goods_ids'] .= ',' . $value['goods_id'];
        }

        /* 检查货品库存 */
        if ($goods['product_ids'] != '')
        {
            /*$sql = "SELECT p.product_id
                FROM " . $GLOBALS['ecs']->table('products') . " AS p, " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                WHERE pg.product_id = p.product_id
                AND pg.package_id = '$package_id'
                AND pg.goods_number * $package_num > p.product_number
                AND p.product_id IN (" . trim($goods['product_ids'], ',') . ")";
            $row = $GLOBALS['db']->getAll($sql);*/
            $row = M('products')->alias('p')
                    ->join('LEFT JOIN __PACKAGE_GOODS__ pg ON pg.product_id = p.product_id')
                    ->where("pg.package_id = '$package_id' g.goods_number * $package_num > p.product_number p.product_id IN (" . trim($goods['product_ids'], ',') . ")")
                    ->field('p.product_id')
                    ->select();
            if (!empty($row))
            {
                return true;
            }
        }

        /* 检查商品库存 */
        if ($goods['goods_ids'] != '')
        {
            /*$sql = "SELECT g.goods_id
                FROM " . $GLOBALS['ecs']->table('goods') . "AS g, " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                WHERE pg.goods_id = g.goods_id
                AND pg.goods_number * $package_num > g.goods_number
                AND pg.package_id = '" . $package_id . "'
                AND pg.goods_id IN (" . trim($goods['goods_ids'], ',') . ")";
            $row = $GLOBALS['db']->getAll($sql);*/
            $row = M('goods')->alias('g')
                    ->join('LEFT JOIN __PACKAGE_GOODS__ pg ON pg.goods_id = g.goods_id')
                    ->where("pg.package_id = '$package_id ' pg.goods_number * $package_num > g.goods_number pg.goods_id IN (" . trim($goods['goods_ids'], ',') . ")")
                    ->field('g.goods_id')
                    ->select();
            if (!empty($row))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * 用户表里面的支付和配送方式
     * @param $user_id
     * @return array
     */
    function get_user_shippay($user_id)    {
        $user_model = new UsersModel();
        $row = $user_model->get_userinfo($user_id);
        if (empty($row)) {
            $arr = M('order_info')->where("user_id = '$user_id'")->order('order_id DESC')->field('shipping_id, pay_id')->select();
            if (empty($row)) {      /* 如果获得是一个空数组，则返回默认值 */
                $row = array('shipping_id' => 0, 'pay_id' => 0);
            } else {                /* 获得上次使用的配送和支付 */
                $row = array('shipping_id' => $arr['shipping_id'], 'pay_id' => $arr['pay_id']);
            }
        } else {
            $row = array();
            $row['shipping_id'] = isset($row['shipping_id'])?$row['shipping_id']:null;
            $row['pay_id'] = isset($row['pay_id'])?$row['pay_id']:null;
            //$row = array('shipping_id' => $row['shipping_id'], 'pay_id' => $row['pay_id']);
        }
        return $row;
    }

    /**
     * 获得上一次用户采用的支付和配送方式
     * @param $user_id
     * @return array
     */
    function last_shipping_and_payment($user_id) {
        /*$sql = "SELECT shipping_id, pay_id " .
            " FROM " . $GLOBALS['ecs']->table('order_info') .
            " WHERE user_id = '$_SESSION[user_id]' " .
            " ORDER BY order_id DESC LIMIT 1";
        $row = $GLOBALS['db']->getRow($sql);*/
        $row = M('order_info')->where("user_id = '$user_id'")->order('order_id DESC')->field('shipping_id, pay_id')->select();
        if (empty($row)) {
            /* 如果获得是一个空数组，则返回默认值 */
            $row = array('shipping_id' => 0, 'pay_id' => 0);
        } else {

        }

        return $row;
    }

    /**
     * 取得购物车该赠送的积分数
     * @param $goods
     * @return int  积分数
     */
    function get_give_integral($goods) {
        $rec = '';
        if($goods['list']){
            $rec_id = $goods['total']['ids'];
            if ($rec_id){
                $rec = ' and ' . $this->db_create_in($rec_id,'c.rec_id');
                //$rec = ' and c.rec_id in (' . implode(',',$rec_id) . ')';
            }
        }
        $where = $this->getSessionIDorUserID('c');
        $where .=  ' ' . $rec;
        $s = M('cart')
            ->alias('c')
            ->where("c.goods_id > 0 and c.parent_id = 0 and c.rec_type = 0 and c.is_gift = 0 and $where")
            ->join('LEFT JOIN __GOODS__ g ON c.goods_id = g.goods_id')
            ->getfield('SUM(c.goods_number * IF(g.give_integral > -1, g.give_integral, c.goods_price)) as inte');
        //此处存在thinkphp读取数据库问题，getfield返回应该为一个字段的值，此为bug显示为数组，用循环读取所需要数值
        foreach($s as $v){
            $s = $v['inte'];
            break;
        }
        return intval($s);
    }

    /**
     * 获得用户的可用积分
     * @access  private
     * @param string $sess
     * @return array
     */
    function flow_available_points($sess) {
        /*$sql = "SELECT SUM(g.integral * c.goods_number) ".
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " . $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE c.session_id = '" . SESS_ID . "' AND c.goods_id = g.goods_id AND c.is_gift = 0 AND g.integral > 0 " .
            "AND c.rec_type = '" . CART_GENERAL_GOODS . "'";

        $val = intval($GLOBALS['db']->getOne($sql));*/
        $m = M('cart');
        $val = $m->alias('c')
            ->where("c.is_gift = 0 AND g.integral > 0 AND c.rec_type = '" . CART_GENERAL_GOODS . "' and $sess")
            ->join('LEFT JOIN __GOODS__ g ON c.goods_id = g.goods_id')
            ->getfield('SUM(g.integral * c.goods_number) as s');
        //echo $m->getLastSql();exit;
        return $this->integral_of_value($val);
    }

    /**
     * 获得购物车中的商品
     * @param array $cart_list
     * @return array
     */
    function get_cart_goods($cart_list)
    {
        /* 初始化 */
        $goods_list = array();
        $total = array(
            'goods_price'  => 0, // 本店售价合计（有格式）
            'market_price' => 0, // 市场售价合计（有格式）
            'saving'       => 0, // 节省金额（有格式）
            'save_rate'    => 0, // 节省百分比
            'goods_amount' => 0, // 本店售价合计（无格式）
        );

        $sess = $this->getSessionIDorUserID();
        /* 循环、统计 */
        /* $res = M('cart')->field('*, IF(parent_id, parent_id, goods_id) AS pid')
                ->where("rec_type = '" . CART_GENERAL_GOODS . "' and $sess")
                ->order('pid, parent_id');*/
        /* 用于统计购物车中实体商品和虚拟商品的个数 */
        $virtual_goods_count = 0;
        $real_goods_count    = 0;

        foreach ($cart_list as $k => $row)  {
            $total['goods_price']  += $row['goods_price'] * $row['goods_number'];
            $total['market_price'] += $row['market_price'] * $row['goods_number'];

            $row['subtotal']     = $this->price_format($row['goods_price'] * $row['goods_number'], false);
            $row['goods_price']  = $this->price_format($row['goods_price'], false);
            $row['market_price'] = $this->price_format($row['market_price'], false);

            /* 统计实体商品和虚拟商品的个数 */
            if ($row['is_real']) {
                $real_goods_count++;
            } else {
                $virtual_goods_count++;
            }

            /* 查询规格 */
            if (trim($row['goods_attr_id']) != '' and isset($row['attr_list']))  {
                foreach ($row['attr_list'] AS $attr) {
                    $row['goods_name'] .= ' [' . $attr['attr_value'] . '] ';
                }
            }

            /* 增加是否在购物车里显示商品图 */
            if (($GLOBALS['_CFG']['show_goods_in_cart'] == "2" || $GLOBALS['_CFG']['show_goods_in_cart'] == "3") && $row['extension_code'] != 'package_buy')
            {
                $goods_thumb = $row['goods_thumb'];
                $row['goods_thumb'] = $this->get_image_path($row['goods_id'], $goods_thumb, true);
            }
            if ($row['extension_code'] == 'package_buy')
            {
                $row['package_goods_list'] = $this->get_package_goods($row['goods_id']);
            }
            $goods_list[] = $row;
        }

        $total['goods_amount'] = $total['goods_price'];
        $total['saving']       = $this->price_format($total['market_price'] - $total['goods_price'], false);
        if ($total['market_price'] > 0)
        {
            $total['save_rate'] = $total['market_price'] ? round(($total['market_price'] - $total['goods_price']) *
                    100 / $total['market_price']).'%' : 0;
        }
        $total['goods_price']  = $this->price_format($total['goods_price'], false);
        $total['market_price'] = $this->price_format($total['market_price'], false);
        $total['real_goods_count']    = $real_goods_count;
        $total['virtual_goods_count'] = $virtual_goods_count;

        return array('goods_list' => $goods_list, 'total' => $total);
    }

    /**
     * 检查订单中商品库存
     * @param array $arr
     * @param string $sess 是否登录用户返回session或user_id
     * @return array
     */
    function flow_cart_stock($arr, $sess) {
        $result = array('error'=>0,'message'=>'');
        foreach ($arr AS $key => $val) {
            $val = intval(D('Base')->make_semiangle($val));
            if ($val <= 0 || !is_numeric($key)) {
                continue;
            }

            $goods = M('cart')->where("rec_id='$key' AND $sess")->field('`goods_id`, `goods_attr_id`, `extension_code`')->find();

            $row = M('goods')->alias('g')
                ->field('g.goods_name, g.goods_number, c.product_id')
                ->where(" c.rec_id = '$key'")
                ->join('LEFT JOIN __CART__ c ON g.goods_id = c.goods_id')
                ->find();
            //系统启用了库存，检查输入的商品数量是否有效
            if (intval($GLOBALS['_CFG']['use_storage']) > 0 && $goods['extension_code'] != 'package_buy') {
                if ($row['goods_number'] < $val) {
                    $result['error'] = 1;
                    $result['message'] = sprintf($GLOBALS['_LANG']['stock_insufficiency'], $row['goods_name'], $row['goods_number'], $row['goods_number']);
                    return $result;
                }

                /* 是货品 */
                $row['product_id'] = trim($row['product_id']);
                if (!empty($row['product_id'])) {

                    $product_number = M('products')->where("goods_id = '" . $goods['goods_id'] . "' AND product_id = '" . $row['product_id'] . "'")
                        ->getfield('product_number');
                    if ($product_number < $val) {
                        $result['error'] = 1;
                        $result['message'] = sprintf($GLOBALS['_LANG']['stock_insufficiency'], $row['goods_name'], $row['goods_number'], $row['goods_number']);
                        return $result;
                    }
                }
            }
            elseif (intval($GLOBALS['_CFG']['use_storage']) > 0 && $goods['extension_code'] == 'package_buy')
            {
                if ($this->judge_package_stock($goods['goods_id'], $val))
                {
                    $result['error'] = 1;
                    $result['message'] = $GLOBALS['_LANG']['package_stock_insufficiency'];
                    return $result;
                }
            }
        }

    }

    /**
     * 初始化交易信息
     * @param $flow_type
     * @param $flow_order
     * @param int $user_id
     * @return array
     */
    public function flow_order_info($flow_type, $flow_order, $user_id = 0) {
        $order = !empty($flow_order) ? $flow_order : array();
        /* 初始化配送和支付方式 */
        if (!isset($order['shipping_id']) || !isset($order['pay_id'])) {
            /* 如果还没有设置配送和支付 */
            if ($user_id > 0) {
                /*用户已经登录了，则获得用户表里面的支付和配送方式*/
                $arr = $this->get_user_shippay($user_id);
                if (!isset($order['shipping_id'])) {
                    $order['shipping_id'] = $arr['shipping_id'];
                }
                if (!isset($order['pay_id'])) {
                    $order['pay_id'] = $arr['pay_id'];
                }
            } else {
                if (!isset($order['shipping_id'])) {
                    $order['shipping_id'] = 0;
                }
                if (!isset($order['pay_id'])) {
                    $order['pay_id'] = 0;
                }
            }
        }

        if (!isset($order['pack_id'])) {
            $order['pack_id'] = 0;  // 初始化包装
        }
        if (!isset($order['card_id'])) {
            $order['card_id'] = 0;  // 初始化贺卡
        }
        if (!isset($order['bonus'])) {
            $order['bonus'] = 0;    // 初始化优惠券
        }
        if (!isset($order['integral'])) {
            $order['integral'] = 0; // 初始化积分
        }
        if (!isset($order['surplus'])) {
            $order['surplus'] = 0;  // 初始化余额
        }
        /* 扩展信息 */
        if (isset($flow_type) && intval($flow_type) != CART_GENERAL_GOODS) {
            $order['extension_code'] = $flow_order['extension_code'];
            $order['extension_id'] = $flow_order['extension_id'];
        }
        return $order;
    }


    /**
     * 生成不重复订单号并插入ID
     * @return string
     */
    function create_order(){
        do {
            $order_sn = create_sn(PREFIX_ORDER);
        } while ($this->where('order_sn="' . $order_sn . '"')->count() > 0);
        return $order_sn;
    }

    /**
     * 查询配送区域属于哪个办事处管辖
     * @param   array   $regions    配送区域（1、2、3、4级按顺序）
     * @return  int     办事处id，可能为0
     */
    function get_agency_by_regions($regions)  {
        if (!is_array($regions) || empty($regions)) {
            return 0;
        }
        $arr = array();
        $res = M('region')->where('region_id > 0 AND agency_id > 0 and ' . $this->db_create_in($regions,'region_id'))
            ->field('region_id, agency_id')->select();
        foreach ($res as $row) {
            $arr[$row['region_id']] = $row['agency_id'];
        }
        if (empty($arr))  {
            return 0;
        }
        $agency_id = 0;
        for ($i = count($regions) - 1; $i >= 0; $i--)   {
            if (isset($arr[$regions[$i]]))
            {
                return $arr[$regions[$i]];
            }
        }
    }

    /**
     * 统计购物车产品
     * @param $user_info
     * @param array $post
     * @return mixed
     */
    public function total($user_info,$post = array()){
        $user_id            = $user_info['user_id'];
        $flow_type          = session('flow_type');
        $flow_order         = session('flow_order');
        $cart_model         = new CartModel();
        $consignee          = $cart_model->get_consignee($user_id, $post['address_id']);
        if (!$consignee or $post['address_id'] <= 0) {
            $result = result_ajax(1,'该收货地址不存在，请填写收货地址！');
        } else {
            $cart = $cart_model->is_cart_goods($user_id, $post['rec_id']);
            if ($cart['error'] > 0) {
                $result = result_ajax($cart['error'], $cart['message'], [], ['url'=>$cart['url']]);
            } else {
                $cart_list = $cart['data'];
                    /*$cart_list = $data['list'];*/
                if ($post['receipt'] and $this->_CFG['can_invoice']) {      //发票
                    if (!in_array($post['receipt_type'],$this->_CFG['invoice_content'])){
                        $result = result_ajax(4, '发票部分异常！');
                    } else if (!in_array($post['receipt_content'],$this->_CFG['invoice_type']['rate'])){
                        $result = result_ajax(5, '发票部分异常！');
                    } else if ($post['receipt_title'] == '') {
                        $result = result_ajax(6, '发票部分异常！');
                    }
                    $result = result_ajax(7, '发票部分异常！');
                    if ($result['error'] > 0) return $result;//$this->ajaxReturn($result);
                }
                if ($post['bonus'] and $this->_CFG['use_bonus']) {          //红包处理
                    if ($post['bonus_id'] > 0) {
                        $bonus = $this->get_bonus($user_id,$cart_list['total'],$flow_type);
                        if ($bonus) {

                        } else {
                            //$bonus_sn
                        }
                        //红包部分待完善
                    } else {

                    }
                    $result = result_ajax(17, '暂未开放红包功能！');
                    if ($result['error'] > 0) return $result;//$this->ajaxReturn($result);
                }
                if ($post['integral'] and $this->_CFG['use_integral']) {
                    if ($user_info['pay_points'] < $post['integral_number']) {
                        return result_ajax(8, $this->_CFG['integral_name'] . '不能超过您已获得的' . $this->_CFG['integral_name'] . '总数！');
                    }
                }

                //计算订单的费用
                $total1 = $this->order_fee(
                    $this->flow_order_info($flow_type, $flow_order, $user_id), //初始化订单信息,
                    $cart_list,         //购物车产品统计
                    $consignee,         //收货信息
                    $this->get_discount($flow_type, $cart_list['total']),//获取折扣信息 优惠活动
                    $user_id
                );

                //print_r($total1);exit;
                /* 取得配送列表 */
                $region            = array(
                    $consignee['country'],
                    $consignee['province'],
                    $consignee['city'],
                    $consignee['district']
                );

                $total1['shipping_fee'] = $this->get_shipping1($post['shipping'],$cart_list['list'],$region);
                $total1['shipping_fee_formated'] = price_format($total1['shipping_fee']);
                $total1['amount'] = $total1['amount'] + $total1['shipping_fee'];
                //$total1['amount'] = $total1['amount'] + $total1['shipping_fee'];
                $total1['amount_formated'] = price_format($total1['amount']);
                $total1['store_id'] = $cart_list['total']['store_id'];
                $total = [
                    'total' =>  $total1,
                    'cart_list' =>  $cart_list,
                    'consignee' =>  $consignee,
                ];
                $result = result_ajax(0,'价格获取成功！', $total);
                /*$res['cart_list'] = $cart_list;
                $res['consignee'] = $consignee;*/

            }
        }
        return $result;
    }

    /**
     * 获得订单中的费用信息
     * @access  public
     * @param array $order_init
     * @param int $goods
     * @param string $consignee
     * @param string $discount
     * @param int $user_id
     * @param bool $is_gb_deposit    是否团购保证金（如果是，应付款金额只计算商品总额和支付费用，可以获得的积分取 $gift_integral）
     * @return array
     */
    function order_fee($order_init, $goods, $consignee, $discount, $user_id, $is_gb_deposit = false) {
        /* 初始化订单的扩展code */
        $get_total = $goods['total'];
        $get_goods = $goods['list'];

        //初始化订单的扩展code
        if (!isset($order_init['extension_code'])) {
            $order_init['extension_code'] = '';
        }
        if ($order_init['extension_code'] == 'group_buy') {
            $group_buy = $this->group_buy_info($order_init['extension_id']);
        }

        $total  = array(
            'real_goods_count' => 0,
            'gift_amount'      => 0,
            'goods_price'      => 0,
            'market_price'     => 0,
            'discount'         => 0,
            'pack_fee'         => 0,
            'card_fee'         => 0,
            'shipping_fee'     => 0,
            'shipping_insure'  => 0,
            'integral_money'   => 0,
            'bonus'            => 0,
            'surplus'          => 0,
            'cod_fee'          => 0,
            'pay_fee'          => 0,
            'tax'              => 0,
        );
        $weight = 0;
        $rate = 0;
        $rec_id = $get_total['ids'];

        $total['deposit_price']         = $get_total['deposit_price'];          #定金总额
        //$total['goods_price']           = $get_total['goods_price'];            #产品总价
        #产品总价开始
        //现在有定金商品，考虑到定金商品和非定金商品混合在一个订单内的情况
        $total['goods_price'] = 0;
        foreach($get_goods as $g){
            $total['goods_price']       += ($g['deposit_price']?:$g['goods_price'])*$g['goods_number'];
        }
        #产品总价结束
        $total['market_price']          = $get_total['market_price'];           #市场价总价
        $total['saving']                = $get_total['market_price'] - $get_total['goods_price'];#市场与商城差价
        $total['save_rate']             = $get_total['market_price'] ? round($get_total['saving'] * 100 / $get_total['market_price']) . '%' : 0;    #市场价差价比例
        $total['deposit_price_formated']= $this->price_format($get_total['deposit_price'], false);
        $total['goods_price_formated']  = $this->price_format($get_total['goods_price'], false);
        $total['market_price_formated'] = $this->price_format($get_total['market_price'], false);
        $total['saving_formated']       = $this->price_format($get_total['saving'], false);
        $total['real_goods_count']      = $get_total['real_goods_count'];
        /* 折扣 */
        if ($order_init['extension_code'] != 'group_buy')  {         //团购商品
            $total['discount'] = $discount['discount'];
            if ($total['discount'] > $total['goods_price'])  {
                $total['discount'] = $total['goods_price'];
            }
        }
        $total['discount_formated'] = $this->price_format($total['discount'], false);

        /* 税额 */
        if (!empty($order_init['need_inv']) && $order_init['inv_type'] != '') {
            $rate = 0;  /* 查税率 */
            foreach ($this->_CFG['invoice_type']['type'] as $key => $type) {
                if ($type == $order_init['inv_type']) {
                    $rate = floatval($this->_CFG['invoice_type']['rate'][$key]) / 100;
                    break;
                }
            }
            if ($rate > 0)   {
                $total['tax'] = $rate * $total['goods_price'];
            }
        }
        $total['tax_formated'] = $this->price_format($total['tax'], false);

        /* 包装费用 */
        if (!empty($order_init['pack_id'])) {
            $total['pack_fee']      = $this->pack_fee($order_init['pack_id'], $total['goods_price']);
            $total['pack_fee_formated'] = $this->price_format($total['pack_fee'], false);
        }

        /* 贺卡费用 */
        if (!empty($order_init['card_id'])) {
            $total['card_fee']      = $this->card_fee($order_init['card_id'], $total['goods_price']);
            $total['card_fee_formated'] = $this->price_format($total['card_fee'], false);
        }

        /* 优惠券 */
        if (!empty($order_init['bonus_id'])) {
            $bonus          = $this->bonus_info($order_init['bonus_id']);
            $total['bonus'] = $bonus['type_money'];
            $total['bonus_formated'] = $this->price_format($total['bonus'], false);
        }

        /* 线下优惠券 */
        if (!empty($order_init['bonus_kill'])){
            $bonus          = $this->bonus_info(0,$order_init['bonus_kill']);
            $total['bonus_kill'] = $order_init['bonus_kill'];
            $total['bonus_kill_formated'] = $this->price_format($total['bonus_kill'], false);
        }
        $where = ' user_id=' . $user_id;
        /* 配送费用 */
        $shipping_cod_fee = NULL;
        //$order_init['shipping_id'] = 15;
        if ($order_init['shipping_id'] > 0 && $total['real_goods_count'] > 0 && isset($is_shipping)) {
            $region['country']  = $consignee['country'];
            $region['province'] = $consignee['province'];
            $region['city']     = $consignee['city'];
            $region['district'] = $consignee['district'];
            $shipping_info = $this->shipping_area_info($order_init['shipping_id'], $region);
            //print_r($total);print_r($order_init);print_r($shipping_info);exit('45324');
            if (!empty($shipping_info)) {
                if ($order_init['extension_code'] == 'group_buy') {
                    $weight_price = $this->cart_weight_price($user_id,CART_GROUP_BUY_GOODS);
                } else {
                    $weight_price = $this->cart_weight_price($user_id);
                }
                $in_rec = $this->db_create_in($rec_id,'rec_id');//'rec_id in (' . implode(',',$rec_id) .')';

                // 查看购物车中是否全为免运费商品，若是则把运费赋为零
                $shipping_count = M('cart')
                    ->where("`extension_code` != 'package_buy' AND `is_shipping` = 0 and $in_rec and{$where}")
                    ->getfield('count(*)');

                if ($shipping_count == 0 AND $weight_price['free_shipping'] == 1) {
                    $total['shipping_fee'] = 0;
                } else {
                    $total['shipping_fee'] = $this->shipping_fee($shipping_info['shipping_code'],$shipping_info['configure'], $weight_price['weight'], $total['goods_price'], $weight_price['number']);
                }
                if (!empty($order_init['need_insure']) && $shipping_info['insure'] > 0) {
                    $total['shipping_insure'] = $this->shipping_insure_fee($shipping_info['shipping_code'],
                        $total['goods_price'], $shipping_info['insure']);
                } else {
                    $total['shipping_insure'] = 0;
                }

                if ($shipping_info['support_cod']) {
                    $shipping_cod_fee = $shipping_info['pay_fee'];
                }
            }
        }

        $total['shipping_fee_formated']    = $this->price_format($total['shipping_fee'], false);
        $total['shipping_insure_formated'] = $this->price_format($total['shipping_insure'], false);

        // 购物车中的商品能享受优惠券支付的总额
        //$sess = $this->getSessionIDorUserID();
        $bonus_amount = $this->compute_discount_amount(session('user_rank'),$where);
        // 优惠券和积分最多能支付的金额为商品总额
        $max_amount = $total['goods_price'] == 0 ? $total['goods_price'] : $total['goods_price'] - $bonus_amount;

        /* 计算订单总额 */
        if ($order_init['extension_code'] == 'group_buy' && $group_buy['deposit'] > 0)  {
            $total['amount'] = $total['goods_price'];
        } else {
            $total['amount'] = $total['goods_price'] - $total['discount'] + $total['tax'] + $total['pack_fee'] + $total['card_fee'] +
                $total['shipping_fee'] + $total['shipping_insure'] + $total['cod_fee'];

            // 减去优惠券金额
            $use_bonus        = min($total['bonus'], $max_amount); // 实际减去的优惠券金额
            if(isset($total['bonus_kill'])) {
                $use_bonus_kill   = min($total['bonus_kill'], $max_amount);
                $total['amount'] -=  $price = number_format($total['bonus_kill'], 2, '.', ''); // 还需要支付的订单金额
            }

            $total['bonus']   = $use_bonus;
            $total['bonus_formated'] = $this->price_format($total['bonus'], false);

            $total['amount'] -= $use_bonus; // 还需要支付的订单金额
            $max_amount      -= $use_bonus; // 积分最多还能支付的金额

        }

        /* 余额 */
        $order_init['surplus'] = $order_init['surplus'] > 0 ? $order_init['surplus'] : 0;
        if ($total['amount'] > 0)  {
            if (isset($order_init['surplus']) && $order_init['surplus'] > $total['amount'])  {
                $order_init['surplus'] = $total['amount'];
                $total['amount']  = 0;
            } else {
                $total['amount'] -= floatval($order_init['surplus']);
            }
        } else {
            $order_init['surplus'] = 0;
            $total['amount']  = 0;
        }
        $total['surplus'] = $order_init['surplus'];
        $total['surplus_formated'] = $this->price_format($order_init['surplus'], false);

        /* 积分抵扣现金 */
//        $order_init['integral'] = $order_init['integral'] > 0 ? $order_init['integral'] : 0;
//        if ($total['amount'] > 0 && $max_amount > 0 && $order_init['integral'] > 0 && 0) {
//            $integral_money = $this->value_of_integral($order_init['integral']);
//            // 使用积分支付
//            $use_integral            = min($total['amount'], $max_amount, $integral_money); // 实际使用积分支付的金额
//            $total['amount']        -= $use_integral;
//            $total['integral_money'] = $use_integral;
//            $order_init['integral']       = $this->integral_of_value($use_integral);
//        } else {
        $total['integral_money'] = 0;
        $order_init['integral']       = 0;
//        }
        $total['integral'] = $get_total['integral'];
        //$total['integral_formated'] = $this->price_format($total['integral_money'], false);

        /* 保存订单信息 */
        session('flow_order', $order_init);
        $fo = session('flow_type');
        $se_flow_type = is_null($fo) ? $fo : '';

        /* 支付费用 */
        if (!empty($order_init['pay_id']) && ($total['real_goods_count'] > 0 || $se_flow_type != CART_EXCHANGE_GOODS)) {
            $total['pay_fee']      = $this->pay_fee($order_init['pay_id'], $total['amount'], $shipping_cod_fee);
        }
        $total['pay_fee_formated'] = $this->price_format($total['pay_fee'], false);
        $total['amount']           += $total['pay_fee']; // 订单总额累加上支付费用
        $total['amount_formated']  = $this->price_format($total['amount'], false);

        /* 取得可以得到的积分和优惠券 */
        if ($order_init['extension_code'] == 'group_buy') {
            $total['will_get_integral'] = $group_buy['gift_integral'];
        } elseif ($order_init['extension_code'] == 'exchange_goods')  {
            $total['will_get_integral'] = 0;
        } else  {
            $total['will_get_integral'] = $this->get_give_integral($goods);
        }

        $total['will_get_bonus']        = $order_init['extension_code'] == 'exchange_goods' ? 0 : $this->price_format($this->get_total_bonus(), false);
        $total['formated_goods_price']  = $this->price_format($total['goods_price'], false);
        $total['formated_market_price'] = $this->price_format($total['market_price'], false);
        $total['formated_saving']       = $this->price_format($total['saving'], false);

        if ($order_init['extension_code'] == 'exchange_goods')    {
            /*$sql = 'SELECT SUM(eg.exchange_integral) '.
                'FROM ' . $GLOBALS['ecs']->table('cart') . ' AS c,' . $GLOBALS['ecs']->table('exchange_goods') . 'AS eg '.
                'FROM ' . $GLOBALS['ecs']->table('cart') . ' AS c,' . $GLOBALS['ecs']->table('exchange_goods') . 'AS eg '.
                "WHERE c.goods_id = eg.goods_id AND c.session_id= '" . SESS_ID . "' " .
                "  AND c.rec_type = '" . CART_EXCHANGE_GOODS . "' " .
                '  AND c.is_gift = 0 AND c.goods_id > 0 ' .
                'GROUP BY eg.goods_id';
            $exchange_integral = $GLOBALS['db']->getOne($sql);*/
            $exchange_integral = M('cart')->alias('c')
                ->join('LEFT JOIN __EXCHANGE_GOODS__ eg on c.goods_id = eg.goods_id')
                ->where("c.rec_type = '" . CART_EXCHANGE_GOODS . "' AND c.is_gift = 0 AND c.goods_id > 0 and $where")
                ->group('eg.goods_id')
                ->getfield('SUM(eg.exchange_integral)');
            $total['exchange_integral'] = $exchange_integral;
        }

        return $total;
    }

    /**
     * 获取配送费用（未完善，暂未使用）
     * @param $user_id
     * @param $consignee
     * @param $order_init
     */
    function get_shipping_fee($user_id, $consignee, $order_init){
        $region['country']  = $consignee['country'];
        $region['province'] = $consignee['province'];
        $region['city']     = $consignee['city'];
        $region['district'] = $consignee['district'];
        $shipping_info = $this->shipping_area_info($order_init['shipping_id'], $region);
        //print_r($total);print_r($order_init);print_r($shipping_info);exit('45324');
        if (!empty($shipping_info)) {
            if ($order_init['extension_code'] == 'group_buy') {
                $weight_price = $this->cart_weight_price($user_id,CART_GROUP_BUY_GOODS);
            } else {
                $weight_price = $this->cart_weight_price($user_id);
            }
            $in_rec = $this->db_create_in($rec_id,'rec_id');//'rec_id in (' . implode(',',$rec_id) .')';

            // 查看购物车中是否全为免运费商品，若是则把运费赋为零
            $shipping_count = M('cart')
                ->where("`extension_code` != 'package_buy' AND `is_shipping` = 0 and $in_rec and{$where}")
                ->getfield('count(*)');

            if ($shipping_count == 0 AND $weight_price['free_shipping'] == 1) {
                $total['shipping_fee'] = 0;
            } else {
                $total['shipping_fee'] = $this->shipping_fee($shipping_info['shipping_code'],$shipping_info['configure'], $weight_price['weight'], $total['goods_price'], $weight_price['number']);
            }
            if (!empty($order_init['need_insure']) && $shipping_info['insure'] > 0) {
                $total['shipping_insure'] = $this->shipping_insure_fee($shipping_info['shipping_code'],
                    $total['goods_price'], $shipping_info['insure']);
            } else {
                $total['shipping_insure'] = 0;
            }

            if ($shipping_info['support_cod']) {
                $shipping_cod_fee = $shipping_info['pay_fee'];
            }
        }
    }

    /**
     * 取得团购活动信息
     * @param int $group_buy_id         团购活动id
     * @param int $current_num      本次购买数量（计算当前价时要加上的数量）
     * @return array
     */
    function group_buy_info($group_buy_id, $current_num = 0) {
        /* 取得团购活动信息 */
        $group_buy_id = intval($group_buy_id);
        $group_buy = M('goods_activity')
            ->field('*, act_id AS group_buy_id, act_desc AS group_buy_desc, start_time AS start_date, end_time AS end_date')
            ->where("act_id = '$group_buy_id' AND act_type = '" . GAT_GROUP_BUY . "'")
            ->find();
        /* 如果为空，返回空数组 */
        if (empty($group_buy)) {
            return array();
        }

        $ext_info = unserialize($group_buy['ext_info']);
        $group_buy = array_merge($group_buy, $ext_info);

        /* 格式化时间 */
        $group_buy['formated_start_date'] = date('Y-m-d H:i', $group_buy['start_time']);
        $group_buy['formated_end_date'] = date('Y-m-d H:i', $group_buy['end_time']);

        /* 格式化保证金 */
        $group_buy['formated_deposit'] = $this->price_format($group_buy['deposit'], false);

        /* 处理价格阶梯 */
        $price_ladder = $group_buy['price_ladder'];
        if (!is_array($price_ladder) || empty($price_ladder)) {
            $price_ladder = array(array('amount' => 0, 'price' => 0));
        } else {
            foreach ($price_ladder as $key => $amount_price) {
                $price_ladder[$key]['formated_price'] = $this->price_format($amount_price['price'], false);
            }
        }
        $group_buy['price_ladder'] = $price_ladder;

        /* 统计信息 */
        $stat = $this->group_buy_stat($group_buy_id, $group_buy['deposit']);
        $group_buy = array_merge($group_buy, $stat);

        /* 计算当前价 */
        $cur_price = $price_ladder[0]['price']; // 初始化
        $cur_amount = $stat['valid_goods'] + $current_num; // 当前数量
        foreach ($price_ladder as $amount_price) {
            if ($cur_amount >= $amount_price['amount']) {
                $cur_price = $amount_price['price'];
            } else {
                break;
            }
        }
        $group_buy['cur_price'] = $cur_price;
        $group_buy['formated_cur_price'] = $this->price_format($cur_price, false);

        /* 最终价 */
        $group_buy['trans_price'] = $group_buy['cur_price'];
        $group_buy['formated_trans_price'] = $group_buy['formated_cur_price'];
        $group_buy['trans_amount'] = $group_buy['valid_goods'];

        /* 状态 */
        $group_buy['status'] = $this->group_buy_status($group_buy);
        if (isset($GLOBALS['_LANG']['gbs'][$group_buy['status']])) {
            $group_buy['status_desc'] = $GLOBALS['_LANG']['gbs'][$group_buy['status']];
        }

        $group_buy['start_time'] = $group_buy['formated_start_date'];
        $group_buy['end_time'] = $group_buy['formated_end_date'];

        return $group_buy;
    }

    /**
     * 得到新订单号
     * @return  string
     */
    function get_order_sn()  {
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);

        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }


    /**
     * 记录帐户变动
     * @param int       $user_id        用户id
     * @param int       $user_money     可用余额变动
     * @param int       $frozen_money   冻结余额变动
     * @param int       $rank_points    等级积分变动
     * @param int       $pay_points     消费积分变动
     * @param string    $change_desc    变动说明
     * @param int       $change_type    变动类型：参见常量文件
     */
    function log_account_change($user_id, $user_money = 0, $frozen_money = 0, $rank_points = 0, $pay_points = 0, $change_desc = '', $change_type = ACT_OTHER) {
        /* 插入帐户变动记录 */
        $account_log = array(
            'user_id'       => $user_id,
            'user_money'    => $user_money,
            'frozen_money'  => $frozen_money,
            'rank_points'   => $rank_points,
            'pay_points'    => $pay_points,
            'change_time'   => time(),
            'change_desc'   => $change_desc,
            'change_type'   => $change_type
        );
        //$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('account_log'), $account_log, 'INSERT');
        M('account_log')->add($account_log);
        /* 更新用户信息 */
        /*$sql = "UPDATE " . $GLOBALS['ecs']->table('users') .
            " SET user_money = user_money + ('$user_money')," .
            " frozen_money = frozen_money + ('$frozen_money')," .
            " rank_points = rank_points + ('$rank_points')," .
            " pay_points = pay_points + ('$pay_points')" .
            " WHERE user_id = '$user_id' LIMIT 1";
        $GLOBALS['db']->query($sql);*/
        /*$data = array(
            'user_money'    =>  "user_money + ('$user_money')",
            'frozen_money'  => "frozen_money + ('$frozen_money')",
            'rank_points'   => "rank_points + ('$rank_points'),",
            'pay_points'    => "pay_points + ('$pay_points')"
        );*/
        M('users')->where('user_id=' . $user_id)->setInc('user_money',$user_money);
        M('users')->where('user_id=' . $user_id)->setInc('frozen_money',$frozen_money);
        M('users')->where('user_id=' . $user_id)->setInc('rank_points',$rank_points);
        M('users')->where('user_id=' . $user_id)->setInc('pay_points',$pay_points);
    }

    /**
     * 改变订单中商品库存
     * @param int       $order_id   订单号
     * @param bool      $is_dec     是否减少库存
     * @param int       $storage    减库存的时机，1，下订单时；0，发货时；
     */
    function change_order_goods_storage($order_id, $is_dec = true, $storage = 0) {
        /* 查询订单商品信息 */
        /*switch ($storage)
        {
            case 0 :
                $sql = "SELECT goods_id, SUM(send_number) AS num, MAX(extension_code) AS extension_code, product_id FROM " . $GLOBALS['ecs']->table('order_goods') .                    " WHERE order_id = '$order_id' AND is_real = 1 GROUP BY goods_id, product_id";
                break;

            case 1 :
                $sql = "SELECT goods_id, SUM(goods_number) AS num, MAX(extension_code) AS extension_code, product_id FROM " . $GLOBALS['ecs']->table('order_goods') .
                    " WHERE order_id = '$order_id' AND is_real = 1 GROUP BY goods_id, product_id";
                break;
        }

        $res = $GLOBALS['db']->query($sql);*/
        $res = M('order_goods')->field('goods_id, SUM(goods_number) AS num, MAX(extension_code) AS extension_code, product_id')
                ->where("order_id = '$order_id' AND is_real = 1 ")->group('goods_id, product_id')->select();
        foreach ($res as $row)    {
            if ($row['extension_code'] != "package_buy")      {
                if ($is_dec) {
                    $this->change_goods_storage($row['goods_id'], $row['product_id'], - $row['num']);
                } else {
                    $this->change_goods_storage($row['goods_id'], $row['product_id'], $row['num']);
                }
                //$GLOBALS['db']->query($sql);
            }  else  {
                /*$sql = "SELECT goods_id, goods_number" .
                    " FROM " . $GLOBALS['ecs']->table('package_goods') .
                    " WHERE package_id = '" . $row['goods_id'] . "'";
                $res_goods = $GLOBALS['db']->query($sql);*/
                $res_goods = M('package_goods')->where("package_id = '" . $row['goods_id'] ."'")->field('goods_id, goods_number')->select();
                foreach ($res_goods as $row_goods) {
                    /*$sql = "SELECT is_real" .
                        " FROM " . $GLOBALS['ecs']->table('goods') .
                        " WHERE goods_id = '" . $row_goods['goods_id'] . "'";
                    $real_goods = $GLOBALS['db']->query($sql);
                    $is_goods = $GLOBALS['db']->fetchRow($real_goods);*/
                    $is_goods = M('goods')->where('goods_id=' . $row_goods['goods_id'])->getfield('is_real');
                    if ($is_dec) {
                        $this->change_goods_storage($row_goods['goods_id'], $row['product_id'], - ($row['num'] * $row_goods['goods_number']));
                    }  elseif ($is_goods['is_real']){
                        $this->change_goods_storage($row_goods['goods_id'], $row['product_id'], ($row['num'] * $row_goods['goods_number']));
                    }
                }
            }
        }

    }

    /**
    * 商品库存增与减 货品库存增与减
    * @param   int    $good_id         商品ID
    * @param   int    $product_id      货品ID
    * @param   int    $number          增减数量，默认0；
    * @return  bool               true，成功；false，失败；
    */
    function change_goods_storage($good_id, $product_id, $number = 0){
        if ($number == 0)   {
            return true; // 值为0即不做、增减操作，返回true
        }
        if (empty($good_id) || empty($number))  {
            return false;
        }

        $number = ($number > 0) ? '+ ' . $number : $number;

        /* 处理货品库存 */
        $products_query = true;
        if (!empty($product_id))   {
            /*$sql = "UPDATE " . $GLOBALS['ecs']->table('products') ."
                SET product_number = product_number $number
                WHERE goods_id = '$good_id'
                AND product_id = '$product_id'
                LIMIT 1";
            $products_query = $GLOBALS['db']->query($sql);*/
            $products_query = M('products')->where("goods_id = '$good_id' AND product_id = '$product_id'")->setInc('product_number',$number);
        }

        /* 处理商品库存 */
        /*$sql = "UPDATE " . $GLOBALS['ecs']->table('goods') ."
            SET goods_number = goods_number $number
            WHERE goods_id = '$good_id'
            LIMIT 1";
        $query = $GLOBALS['db']->query($sql);*/
        $query = M('goods')->where('goods_id=' . $good_id)->setInc('goods_number',$number);
        if ($query && $products_query)  {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取邮件模板
     * @access  public
     * @param  string $tpl_name       模板代码
     * @return array
     */
    function get_mail_template($tpl_name) {
        /*$sql = 'SELECT template_subject, is_html, template_content FROM ' . $GLOBALS['ecs']->table('mail_templates') . " WHERE template_code = '$tpl_name'";
        return $GLOBALS['db']->GetRow($sql);*/
        return M('mail_templates')->where("template_code = '$tpl_name'")->field('template_subject, is_html, template_content')->find();

    }

    /**
     * 将支付LOG插入数据表
     *
     * @access  public
     * @param   integer     $id         订单编号
     * @param   float       $amount     订单金额
     * @param   integer     $type       支付类型
     * @param   integer     $is_paid    是否已支付
     *
     * @return  int
     */
    function insert_pay_log($id, $amount, $type = PAY_SURPLUS, $is_paid = 0)  {
        /*$sql = 'INSERT INTO ' .$GLOBALS['ecs']->table('pay_log')." (order_id, order_amount, order_type, is_paid)".
            " VALUES  ('$id', '$amount', '$type', '$is_paid')";
        $GLOBALS['db']->query($sql);*/
        $data = array(
            'order_id'      => $id,
            'order_amount'  => $amount,
            'order_type'    => $type,
            'is_paid'       => $is_paid,
        );
        $insert_id = M('pay_log')->add($data);
        return $insert_id;

    }

    /**
     *  获取用户指定范围的订单列表
     *
     * @access  public
     * @param   int         $user_id        用户ID号
     * @param   int         $num            列表最大数量
     * @param   int         $start          列表起始位置
     * @return  array       $order_list     订单列表
     */
    function get_user_orders_keheng($user_id, $num = 10, $start = 0){
        /* 取得订单列表 */
        //echo $GLOBALS['_LANG']['ss_received'];
        $arr    = array();

        /*$sql = "SELECT oi.order_id, order_sn, order_status,pay_status, shipping_status,consignee,pay_id, pay_name, pay_status, xiadan_userid, xiadan_username, add_time, " .
            "(goods_amount + shipping_fee + insure_fee + pay_fee + pack_fee + card_fee + tax - discount) AS total_fee ,pl.log_id,oi.order_amount".
            " FROM " .$GLOBALS['ecs']->table('order_info') . " as oi
		    left join "  .$GLOBALS['ecs']->table('pay_log') . " as pl on pl.order_id = oi.order_id
            WHERE user_id = '$user_id' ORDER BY add_time DESC";//AND order_status not in(2,3,4)
        $res = $GLOBALS['db']->SelectLimit($sql, $num, $start);*/
        $res = M('order_info')
            ->alias('oi')
            ->field('oi.order_id, order_sn, order_status,pay_status, shipping_status,consignee,pay_id, pay_name, pay_status, xiadan_userid, xiadan_username, add_time, (goods_amount + shipping_fee + insure_fee + pay_fee + pack_fee + card_fee + tax - discount) AS total_fee ,pl.log_id,oi.order_amount')
            ->join('LEFT JOIN __PAY_LOG__ pl ON on pl.order_id = oi.order_id')
            ->where(" user_id = '$user_id' ORDER BY add_time DESC")
            ->limit($num, $start)
            ->select();
        foreach($res as $k => $row)   {

            $row['order_station'] 	= $row['order_status'];//add by tan
            $row['shipping_status'] = ($row['shipping_status'] == SS_SHIPPED_ING) ? SS_PREPARING : $row['shipping_status'];
            //$row['order_amount']	= $row['total_fee'];
            //$row['order_status'] = $GLOBALS['_LANG']['os'][$row['order_status']] . ',' . $GLOBALS['_LANG']['ps'][$row['pay_status']] . ',' . $GLOBALS['_LANG']['ss'][$row['shipping_status']];

            //获得该订单是否已经评论过了 add by tan
            if( ($row['pay_status'] == 2) && ($row['shipping_status'] == 2)  && ($row['order_station'] == 1 || $row['order_station'] ==5 ) ) //已完成
            {
                /*$sql = "SELECT COUNT(rec_id) AS goodcount FROM ".$GLOBALS['ecs']->table('order_goods')." WHERE order_id = '".$row['order_id']."'";
                $goodscount = $GLOBALS['db']->getRow($sql);   //订单的商品数量
                $sql = "SELECT COUNT(record_id) AS commentcount FROM ".$GLOBALS['ecs']->table('record')." WHERE record_orderid = '".$row['order_id']."' AND record_flag =1 AND record_userid = '".$_SESSION['user_id']."'";
                $commentcount = $GLOBALS['db']->getRow($sql); //订单商品评论的数量
                */
                $GoodsCount = M('order_goods')->where('order_id = ' . $row['order_id'] )->getfield('COUNT(rec_id) AS goodcount');
                $CommentCount = M('record')
                    ->where('record_orderid = ' . $row['order_id'] . ' AND record_flag =1 AND record_userid = ' . session('user_id'))
                    ->getfield('COUNT(record_id) AS commentcount');

                if($GoodsCount == $CommentCount)  {
                    $row['comment_sn'] = 1; //已评论过订单所有商品
                }  else {
                    $row['comment_sn'] = 0; //未评论或只评论过订单部分商品
                }
            }
            else
            {
                $row['comment_sn'] = 2;     //没有评论的资格
            }
            //end
            /*$sql = "select a.*,b.goods_thumb,ore.Refund_id from " .$GLOBALS['ecs']->table('order_goods') . " as a
				left join " .$GLOBALS['ecs']->table('goods') . " as b on a.goods_id=b.goods_id
				left join ".$GLOBALS['ecs']->table('order_refund')." as ore on a.rec_id = ore.red_id and ore.order_id= " . $row['order_id'] ."
			 	WHERE a.order_id = ".$row['order_id'];
            $goods_list = $GLOBALS['db']->getall($sql);*/
            $goods_list = M('order_goods')->alias('a')->where('a.order_id=' . $row['order_id'])
                    ->join('LEFT JOIN __GOODS__ b ON a.goods_id=b.goods_id')
                    ->join('LEFT JOIN __ORDER_REFUND__ ore ON a.rec_id = ore.red_id and ore.order_id= ' . $row['order_id'])
                    ->field('a.*,b.goods_thumb,ore.Refund_id')
                    ->select();
            $arra = $this->order_composite_Status($row);

            $arr[] = array('order_id'       	=> $row['order_id'],
                'order_sn'       	=> $row['order_sn'],
                'consignee'      	=> $row['consignee'],
                'pay_name'       	=> $row['pay_name'],
                'pay_status'       	=> $row['pay_status'],
                'order_time'     	=> date($this->_CFG['time_format'], $row['add_time']),
                'order_status'   	=> $row['order_status'],
                'total_fee'      	=> price_format($row['total_fee'], false),
                'comment_sn'     	=> $row['comment_sn'],
                'xiadan_userid'    	=> $row['xiadan_userid'],
                'xiadan_username'  	=> $row['xiadan_username'],
                'keheng_handler'     => $arra['keheng_handler'],
                'keheng_status'		=> $arra['keheng_status'],
                'keheng_status_num'	=> $arra['keheng_status_num'],
                'goods_num'			=>	count($goods_list),
                'goods_list'		=>	$goods_list,
                'is_Refund'			=>	$row['is_Refund'],
                'Refund_id'			=>	$row['Refund_id'],
            );
        }
        return $arr;
    }

    /**
     * 取用户订单
     * @param $user_id
     * @param int $num
     * @param int $start
     * @return array
     */
    function get_user_orders($user_id, $num = 10, $start = 0) {
        /* 取得订单列表 */
        //echo $GLOBALS['_LANG']['ss_received'];
        $arr    = array();

        /*$sql = "SELECT order_id, order_sn, order_status, shipping_status,consignee, pay_name, pay_status, xiadan_userid, xiadan_username, add_time, " .
            "(goods_amount + shipping_fee + insure_fee + pay_fee + pack_fee + card_fee + tax - discount) AS total_fee ".
            " FROM " .$GLOBALS['ecs']->table('order_info') .
            " WHERE user_id = '$user_id' AND order_status not in(2,3,4) ORDER BY add_time DESC";
        $res = $GLOBALS['db']->SelectLimit($sql, $num, $start);*/
        $res = M('order_info')
            ->field('order_id, order_sn, order_status, shipping_status,consignee, pay_name, pay_status, xiadan_userid, xiadan_username, add_time, (goods_amount + shipping_fee + insure_fee + pay_fee + pack_fee + card_fee + tax - discount) AS total_fee')
            ->where("user_id = '$user_id' AND order_status not in(2,3,4)")
            ->order('add_time DESC')
            ->limit($num, $start)
            ->select();
        foreach($res as $k => $row)
            //while ($row = $GLOBALS['db']->fetchRow($res))
        {
            if ($row['order_status'] == OS_UNCONFIRMED)
            {
                $row['handler'] = "<a href=\"user.php?act=cancel_order&order_id=" .$row['order_id']. "\" onclick=\"if (!confirm('".$GLOBALS['_LANG']['confirm_cancel']."')) return false;\">".$GLOBALS['_LANG']['cancel']."</a>";
            }
            else if ($row['order_status'] == OS_SPLITED)
            {
                /* 对配送状态的处理 */
                if ($row['shipping_status'] == SS_SHIPPED)
                {
                    @$row['handler'] = "<a href=\"user.php?act=affirm_received&order_id=" .$row['order_id']. "\" onclick=\"if (!confirm('".$GLOBALS['_LANG']['confirm_received']."')) return false;\">".$GLOBALS['_LANG']['received']."</a>";
                }
                elseif ($row['shipping_status'] == SS_RECEIVED)
                {
                    @$row['handler'] = '<span style="color:red">'.$GLOBALS['_LANG']['ss_received'] .'</span>'; //已完成
                }
                else
                {
                    if ($row['pay_status'] == PS_UNPAYED)
                    {
                        @$row['handler'] = "<a href=\"user.php?act=order_detail&order_id=" .$row['order_id']. '">' .$GLOBALS['_LANG']['pay_money']. '</a>';
                    }
                    else
                    {
                        @$row['handler'] = "<a href=\"user.php?act=order_detail&order_id=" .$row['order_id']. '">' .$GLOBALS['_LANG']['view_order']. '</a>';
                    }

                }
            }
            else
            {
                $row['handler'] = '<span style="color:red">'.$GLOBALS['_LANG']['os'][$row['order_status']] .'</span>';
            }
            $row['order_station'] = $row['order_status'];//add by tan
            $row['shipping_status'] = ($row['shipping_status'] == SS_SHIPPED_ING) ? SS_PREPARING : $row['shipping_status'];
            $row['order_status'] = $GLOBALS['_LANG']['os'][$row['order_status']] . ',' . $GLOBALS['_LANG']['ps'][$row['pay_status']] . ',' . $GLOBALS['_LANG']['ss'][$row['shipping_status']];

            //获得该订单是否已经评论过了 add by tan
            if( ($row['pay_status'] == 2) && ($row['shipping_status'] == 2)  && ($row['order_station'] == 1 || $row['order_station'] ==5 ) ) //已完成
            {
                /*$sql = "SELECT COUNT(rec_id) AS goodcount FROM ".$GLOBALS['ecs']->table('order_goods')." WHERE order_id = '".$row['order_id']."'";
                $goodscount = $GLOBALS['db']->getRow($sql);   //订单的商品数量

                $sql = "SELECT COUNT(record_id) AS commentcount FROM ".$GLOBALS['ecs']->table('record')." WHERE record_orderid = '".$row['order_id']."' AND record_flag =1 AND record_userid = '".$_SESSION['user_id']."'";
                $commentcount = $GLOBALS['db']->getRow($sql); //订单商品评论的数量*/

                $GoodsCount = M('order_goods')->where('order_id = ' . $row['order_id'] )->getfield('COUNT(rec_id) AS goodcount');
                $CommentCount = M('record')
                    ->where('record_orderid = ' . $row['order_id'] . ' AND record_flag =1 AND record_userid = ' . session('user_id'))
                    ->getfield('COUNT(record_id) AS commentcount');

                if($GoodsCount == $CommentCount)
                {
                    $row['comment_sn'] = 1; //已评论过订单所有商品
                }
                else
                {
                    $row['comment_sn'] = 0; //未评论或只评论过订单部分商品
                }
            }
            else
            {
                $row['comment_sn'] = 2;     //没有评论的资格
            }
            //end
            /*$sql = "select a.*,b.goods_thumb from " .$GLOBALS['ecs']->table('order_goods') . " as a " .
                " left join " .$GLOBALS['ecs']->table('goods') . " as b on a.goods_id=b.goods_id ".
                " WHERE order_id = ".$row['order_id'];
            $goods_list = $GLOBALS['db']->getall($sql);*/
            $goods_list = M('order_goods')->alias('a')->where('a.order_id=' . $row['order_id'])
                ->join('LEFT JOIN __GOODS__ b ON a.goods_id=b.goods_id')
                ->join('LEFT JOIN __ORDER_REFUND__ ore ON a.rec_id = ore.red_id and ore.order_id= ' . $row['order_id'])
                ->field('a.*,b.goods_thumb,ore.Refund_id')
                ->select();
            $arr[] = array('order_id'       => $row['order_id'],
                'order_sn'       => $row['order_sn'],
                'consignee'      => $row['consignee'],
                'pay_name'       => $row['pay_name'],
                'order_time'     => local_date($this->_CFG['time_format'], $row['add_time']),
                'order_status'   => $row['order_status'],
                'total_fee'      => price_format($row['total_fee'], false),
                'comment_sn'     => $row['comment_sn'],
                'xiadan_userid'    => $row['xiadan_userid'],
                'xiadan_username'  => $row['xiadan_username'],
                'handler'        => $row['handler'],
                'goods_num'		=>	count($goods_list),
                'goods_list'		=>	$goods_list,
            );
        }

        return $arr;
    }


    function order_composite_status1($order, $is_user = true){
        if($order['order_status'] == OS_CANCELED or $order['order_status'] == OS_RETURNED or $order['order_status'] == OS_RETURNEDOK){
            $id = $order['order_status'];
        }else{
            $id = $order['order_status'] . $order['shipping_status'] . $order['pay_status'];
        }
        //include_once('lib_order.php');
        //require  APP_PATH . '../Public/plugins/lib_payment.php' ;
        $u = $a = array();
        if($id and is_numeric($id)){
            switch($id){
                case KEHENG_PAYMENT://,        		100); // 等待买家付款
                    $payment = $this->payment_info($order['pay_id']);
                    $pay_name = '\Home\Plugins\payment\\' . $payment['pay_code'];
                    $pay_obj = new $pay_name();
                    $pay_online = $pay_obj->get_code($order, unserialize_config($payment['pay_config']), true);
                    $order['pay_desc'] = $payment['pay_desc'];
                    $u['status']		=	$a['status']		=		'<span class="orange">待付款</span>';
                    $u['handler']		=   array(
                                                array('text'=>'付款','url'=>U('Done/pay?id=' . $order['order_sn']),'class'=>'or_pay'),
                                                //array('text'=>'找人代付','url'=>U('Done/index?sn=' . $order['order_sn'])),
                                                array('text'=>'取消订单','url'=>U('Order/cancel?id=' . $order['order_id']),'class'=>'or_cancel'),
                                            );

                    $a['handler']		=	'<span class="orange">等待买家付款</span>';
                    $a['status_desc']	=	'商品已拍下，等待买家付款';
                    break;
                case KEHENG_DELIVERY://,           102); // 买家已付款    //等待卖家发货
                    $u['status']		=	$a['status']		=		'<span class="orange">待发货</span>';
                    $u['handler']		=	array(array('text'=>'退款','url'=>U('Order/refund?id=' . $order['order_sn']),'class'=>'or_refund'));
                    $a['handler']		=	'<span class="orange">等待卖家发货</span>';
                    $a['status_desc']	=	'买家已付款,等待卖家发货';
                    break;
                case KEHENG_RECEIPT1://,           132); // 卖家正在处理订单
                    $u['status']		=	$a['status']		=		'<span class="orange">配货中</span>';
                    $u['handler']		=	'';
                    $a['handler']		=	'<span class="orange">卖家正在配货</span>';
                    $a['status_desc']	=	'卖家正在配货';
                    break;
                case KEHENG_RECEIPT2://,           532); // 卖家正在处理订单
                    $u['status']		=	$a['status']		=		'<span class="orange">处理中</span>';
                    $u['handler']		=	'';
                    $a['handler']		=	'<span class="orange">卖家正在处理订单</span>';
                    $a['status_desc']	=	'卖家正在处理订单';
                    break;
                case KEHENG_RECEIPT3://,           552); // 卖家正在处理订单
                    $u['status']		=	$a['status']		=		'<span class="orange">发货中</span>';
                    $u['handler']		=	'';
                    $a['handler']		=	'<span class="orange">卖家正在发货</span>';
                    $a['status_desc']	=	'卖家正在发货';
                    break;
                case KEHENG_RECEIPT4://,           642); // 卖家正在处理订单
                    $u['status']		=	$a['status']		=		'<span class="orange">发货部分</span>';
                    $u['handler']		=	'';
                    $a['handler']		=	'<span class="orange">部分商品已发货</span>';
                    $a['status_desc']	=	'部分商品已发货';
                    break;
                case KEHENG_EVALUATE://,           512); // 卖家已发货
                    $u['status']		=	$a['status']		=		'<span class="orange">已发货</span>';
                    $u['handler']		=	array(array('text'=>'确认收货','url'=>U('Order/received?id=' . $order['order_sn']),'class'=>'or_received'),
                                            array('text'=>'退款/退货','url'=>U('Order/return?id=' . $order['order_sn']),'class'=>'or_received'));//'<a href="user.php?act=affirm_received&order_id=' .$order['order_id'] . '" class="Confirm" onclick="if (!confirm(\'您确认已经收到货物了吗？\')) return false;">确认收货</a><a href="user.php?act=order_return&order_id=' .$order['order_id'] . '" class="return_goods">申请退货</a>';
                    $a['handler']		=	'<span class="orange">卖家已发货</span>';
                    $a['status_desc']	=	'卖家已发货，等待买家确认收货';
                    break;
                case KEHENG_RETURNSIN://,          522); // 交易成功
                    $u['status']		=	$a['status']		=		'<span style="color:#066601">交易成功</span>';
                    $u['handler']		=	'';
                    $a['handler']		=	'<span style="color:#066601">交易成功</span>';
                    $a['status_desc']	=	'交易成功';
                    break;
                case KEHENG_CANCEL_ORDER1://,       200)    未付款取消
                case KEHENG_CANCEL_ORDER://,       105); // 交易取消
                    $u['status']		=	$a['status']		=		'<span style="color:#b4b4b4">交易取消</span>';
                    $u['handler']		=	array(array('text'=>'删除','url'=>U('Order/delete?id=' . $order['order_sn']),'class'=>'or_delete'));
                    $a['status_desc']	=	'交易取消';
                    break;
                case KEHENG_COMMENT_ALL://,    		106); // 双方已评
                    $u['status']		=	$a['status']		=		'<span class="orange">双方已评</span>';
                    $a['handler']		=	'<span class="orange">双方已评</span>';
                    $a['status_desc']	=	'交易取消';
                    break;
                case KEHENG_COMMENT_BUYER://,    	107); // 买方已评
                    $u['status']		=	$a['status']		=		'<span class="orange">买方已评</span>';
                    $a['handler']		=	'<span class="orange">买方已评</span>';
                    $a['status_desc']	=	'买方已评';
                    break;
                case KEHENG_COMMENT_SELLER://,    	108); // 卖方已评
                    $u['status']		=	$a['status']		=		'<span class="orange">卖方已评</span>';
                    $a['handler']		=	'<span class="orange">卖方已评</span>';
                    $a['status_desc']	=	'卖方已评';
                    break;
                case KEHENG_REFUND_BEING1:
                case KEHENG_REFUND_BEING2://,    	109); // 退款中
                    $u['status']		=	$a['status']		=		'<span class="orange">退款中</span>';
                    $a['handler']		=	'<span class="orange">退款中</span>';
                    $a['status_desc']	=	'退款中';
                    break;
                case KEHENG_BEFUND_SUCCESS://,    	110); // 退款成功
                    $u['status']		=	$a['status']		=		'<span class="orange">退款成功</span>';
                    $a['handler']		=	'<span class="orange">退款成功</span>';
                    $a['status_desc']	=	'退款成功';
                    break;
                default:
                    $u['status']		=	$a['status']		=		'<span class="orange">'.$id.'</span>';
                    $a['status']		=	'<span class="orange">'.$id.'</span>';
                    $a['handler']		=	'<span class="orange">'.$id.'</span>';
                    break;
            }
        }else{
            $u['status']		=		$a['status']		=		'<span class="orange">'.$id.'</span>';
        }
        $u['status_num']	=	$order['order_status'] . $order['shipping_status'] . $order['pay_status'];
        $a['status_num']	=	$order['order_status'] . $order['shipping_status'] . $order['pay_status'];
        if($is_user == true){
            return $u;
        }else{
            return $a;
        }
    }


    function order_composite_Status($order, $user = 'user'){
        if($order['order_status'] == OS_CANCELED or $order['order_status'] == OS_RETURNED or $order['order_status'] == OS_RETURNEDOK){
            $id = $order['order_status'];
        }else{
            $id = $order['order_status'] . $order['shipping_status'] . $order['pay_status'];
        }
        include_once('lib_order.php');
        include_once('lib_payment.php');
        if($id and is_numeric($id)){
            //print_r($order);
            switch($id){
                case KEHENG_PAYMENT://,        		100); // 等待买家付款
                    $payment = payment_info($order['pay_id']);
                    include_once('modules/payment/' . $payment['pay_code'] . '.php');
                    $pay_obj    = new $payment['pay_code'];
                    $pay_online = $pay_obj->get_code($order, unserialize_config($payment['pay_config']));
                    $order['pay_desc'] = $payment['pay_desc'];
                    //print_r($order);
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">等待买家付款</span>';
                    $user_status['keheng_handler']		=	'<div style="text-align:center"><input type="button" onclick="location.href=\'flow.html?step=pay&sn=' . $order['order_sn'] . '\'" class="btn_now_pay" /></div><a href="#" class="Paid_behalf">找人代付</a><a class="cancel_order" href="#">取消订单</a>';//$pay_online .
                    $admin_status['keheng_handler']		=	'<span class="orange">等待买家付款</span>';
                    $admin_status['keheng_status_desc']	=	'商品已拍下，等待买家付款';
                    break;
                case KEHENG_DELIVERY://,           102); // 买家已付款    //等待卖家发货
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">等待卖家发货</span>';
                    $user_status['keheng_handler']		=	'';
                    $admin_status['keheng_handler']		=	'<span class="orange">等待卖家发货</span>';
                    $admin_status['keheng_status_desc']	=	'买家已付款,等待卖家发货';
                    break;
                case KEHENG_RECEIPT1://,           132); // 卖家正在处理订单
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">卖家正在配货</span>';
                    $user_status['keheng_handler']		=	'';
                    $admin_status['keheng_handler']		=	'<span class="orange">卖家正在配货</span>';
                    $admin_status['keheng_status_desc']	=	'卖家正在配货';
                    break;
                case KEHENG_RECEIPT2://,           532); // 卖家正在处理订单
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">卖家正在处理订单</span>';
                    $user_status['keheng_handler']		=	'';
                    $admin_status['keheng_handler']		=	'<span class="orange">卖家正在处理订单</span>';
                    $admin_status['keheng_status_desc']	=	'卖家正在处理订单';
                    break;
                case KEHENG_RECEIPT3://,           552); // 卖家正在处理订单
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">卖家正在发货</span>';
                    $user_status['keheng_handler']		=	'';
                    $admin_status['keheng_handler']		=	'<span class="orange">卖家正在发货</span>';
                    $admin_status['keheng_status_desc']	=	'卖家正在发货';
                    break;
                case KEHENG_RECEIPT4://,           642); // 卖家正在处理订单
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">部分商品已发货</span>';
                    $user_status['keheng_handler']		=	'';
                    $admin_status['keheng_handler']		=	'<span class="orange">部分商品已发货</span>';
                    $admin_status['keheng_status_desc']	=	'部分商品已发货';
                    break;
                case KEHENG_EVALUATE://,           512); // 卖家已发货
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">卖家已发货</span>';
                    $user_status['keheng_handler']		=	'<a href="user.php?act=affirm_received&order_id=' .$order['order_id'] . '" class="Confirm" onclick="if (!confirm(\'您确认已经收到货物了吗？\')) return false;">确认收货</a><a href="user.php?act=order_return&order_id=' .$order['order_id'] . '" class="return_goods">申请退货</a>';
                    $admin_status['keheng_handler']		=	'<span class="orange">卖家已发货</span>';
                    $admin_status['keheng_status_desc']	=	'卖家已发货，等待买家确认收货';
                    break;
                case KEHENG_RETURNSIN://,          522); // 交易成功
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span style="color:#066601">交易成功</span>';
                    $user_status['keheng_handler']		=	'';
                    $admin_status['keheng_handler']		=	'<span style="color:#066601">交易成功</span>';
                    $admin_status['keheng_status_desc']	=	'交易成功';
                    break;
                case KEHENG_CANCEL_ORDER://,       105); // 交易取消
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span style="color:#b4b4b4">交易取消</span>';
                    $admin_status['keheng_handler']		=	'<span style="color:#b4b4b4">交易取消</span>';
                    $admin_status['keheng_status_desc']	=	'交易取消';
                    break;
                case KEHENG_COMMENT_ALL://,    		106); // 双方已评
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">双方已评</span>';
                    $admin_status['keheng_handler']		=	'<span class="orange">双方已评</span>';
                    $admin_status['keheng_status_desc']	=	'交易取消';
                    break;
                case KEHENG_COMMENT_BUYER://,    	107); // 买方已评
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">买方已评</span>';
                    $admin_status['keheng_handler']		=	'<span class="orange">买方已评</span>';
                    $admin_status['keheng_status_desc']	=	'买方已评';
                    break;
                case KEHENG_COMMENT_SELLER://,    	108); // 卖方已评
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">卖方已评</span>';
                    $admin_status['keheng_handler']		=	'<span class="orange">卖方已评</span>';
                    $admin_status['keheng_status_desc']	=	'卖方已评';
                    break;
                case KEHENG_REFUND_BEING1:
                case KEHENG_REFUND_BEING2://,    	109); // 退款中
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">退款中</span>';
                    $admin_status['keheng_handler']		=	'<span class="orange">退款中</span>';
                    $admin_status['keheng_status_desc']	=	'退款中';
                    break;
                case KEHENG_BEFUND_SUCCESS://,    	110); // 退款成功
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">退款成功</span>';
                    $admin_status['keheng_handler']		=	'<span class="orange">退款成功</span>';
                    $admin_status['keheng_status_desc']	=	'退款成功';
                    break;
                default:
                    $user_status['keheng_status']		=	$admin_status['keheng_status']		=		'<span class="orange">'.$id.'</span>';
                    $admin_status['keheng_status']		=	'<span class="orange">'.$id.'</span>';
                    $admin_status['keheng_handler']		=	'<span class="orange">'.$id.'</span>';
                    break;
            }
        }else{
            $user_status['keheng_status']		=		$admin_status['keheng_status']		=		'<span class="orange">'.$id.'</span>';
        }
        $user_status['keheng_status_num']	=	$order['order_status'] . $order['shipping_status'] . $order['pay_status'];
        $admin_status['keheng_status_num']	=	$order['order_status'] . $order['shipping_status'] . $order['pay_status'];
        if($user=='user'){
            return $user_status;
        }else{
            return $admin_status;
        }
    }



    /*
    define('OS_UNCONFIRMED',            0); // 未确认
    define('OS_CONFIRMED',              1); // 已确认
    define('OS_CANCELED',               2); // 已取消
    define('OS_INVALID',                3); // 无效
    define('OS_RETURNED',               4); // 退货
    define('OS_SPLITED',                5); // 已分单
    define('OS_SPLITING_PART',          6); // 部分分单
    define('OS_TRANSACTION_SUCCESSFUL', 7); // 交易成功
    define('OS_COMMENTS',          	    8); // 评价
    define('OS_RETURNEDOK',             9); // 退货成功

    define('SS_UNSHIPPED',              0); // 未发货
    define('SS_SHIPPED',                1); // 已发货
    define('SS_RECEIVED',               2); // 已收货
    define('SS_PREPARING',              3); // 备货中
    define('SS_SHIPPED_PART',           4); // 已发货(部分商品)
    define('SS_SHIPPED_ING',            5); // 发货中(处理分单)

    define('PS_UNPAYED',                0); // 未付款
    define('PS_PAYING',                 1); // 付款中
    define('PS_PAYED',                  2); // 已付款

    define('KEHENG_PAYMENT',        	100); // 等待买家付款
    define('KEHENG_DELIVERY',           101); // 买家已付款    //等待卖家发货
    define('KEHENG_RECEIPT',            102); // 卖家正在处理订单
    define('KEHENG_EVALUATE',           103); // 卖家已发货
    define('KEHENG_RETURNSIN',          104); // 交易成功
    define('KEHENG_CANCEL_ORDER',       105); // 交易取消
    define('KEHENG_COMMANT_ALL',    	106); // 双方已评
    define('KEHENG_COMMANT_BUYER',    	107); // 买方已评
    define('KEHENG_COMMANT_SELLER',    	108); // 卖方已评
    define('KEHENG_REFUND_BEING',    	109); // 退款中
    define('KEHENG_BEFUND_SUCCESS',    	110); // 退款成功

    Wait for the buyer payment
    The buyer has paid / / Wait seller shipped
    Sellers are processing orders
    The seller has shipped
    Trading success
    Transaction canceled
    The two sides have been evaluated
    Refund of being
    Refund success



    */


}