<?php
namespace Wap\Controller;
use Wap\Model;
class CheckoutController extends WapController {
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();
    }



    public function index(){
        $this->no_cache();
        //来自U('Cart/index')表单令牌验证
//        $this->check_token('form_token1');

        /*$id = I('get.id',0,'intval');
        if ($id <= 0) {
            $ids        = I('post.id');
            $flow_type  = I('post.flow_type');
        } else {
            $ids = array($id);
            //$flow_type  = I('post.flow_type');
        }*/

        if (IS_POST) {
            $ids        = I('post.id');
            $this->check_token();
        } else {
            $ids = [I('get.id',0,'intval')];
        }

        $user_id    = $this->user_id;
        $me         = new Model\CartModel();        //定义模型
        $order_me   = new Model\OrderModel();

        $cart = $me->is_cart_goods($user_id,$ids);

        if ($cart['error'] > 0) {
            $this->error($cart['message'], U('Cart/index'));
        }
        $cart_list = $cart['data']['list'];
        $total = $cart['data']['total'];


        //实付款金额
        $total['total_amount'] = 0;
        foreach($cart_list as $list){
            $total['total_amount']       += ($list['deposit_price']?:$list['goods_price'])*$list['goods_number'];
        }
        $total['total_amount']  = price_format($total['total_amount']);
        //实付款金额结束
//        $this->assign('flow_type', $flow_type);
        $this->assign('total', $total);
        $this->assign('cart_list',  $cart_list);

        $this->assign('consignee',  $consignee = $me->get_consignee($user_id, 1));       //显示收货地址*/
        $this->assign('consignee_list', $me->get_consignee($user_id));
        $this->assign('invoice',    $order_me->get_inv());  //读取发票信息
//        $this->assign('bonus',      $order_me->get_bonus($user_id, $cart_list['total'], $flow_type));    //读取红包信息
//        $this->assign('integral',   $order_me->get_integral($this->user_info,$flow_type));  //读取积分信息
        $this->assign('surplus',    $order_me->get_surplus($this->user_info));    //读取余额信息
        $this->assign('best_time',  $order_me->get_besttime());         //最佳送货时间
        $url = array(
            'edit_address'  => U('Consignee/index?uri=Checkout/index'),
            'edit_flow'     => U('Flow/index'),
            'orderfrm_sub'  => U('Done/index'),
            'get_region'    => U('Checkout/region') . '?_ajax=1',
            'add_consignee' => U('Checkout/add_consignee') . '?_ajax=1',
            'get_total'     => U('Checkout/get_total') . '?_ajax=1'
        );
        $this->assign('url', $url);
        $this->set_form_token();//设置当前表单的表单令牌
        $this->assign('user_head', array('title'=>'填写核对购物信息','backUrl'=>get_server('WAP_SERVER', '/store/info', ['id'=>$this->store_token, 'store_token'=>$this->store_token]),'backText'=>'首页'));
        $this->assign('tkd', array('title'=>'填写核对购物信息','keywords'=>'','discription'=>'首页'));
        $this->display('checkout:flow');
    }




    public function get_total(){
        $post['address_id']         =   I('addr_id',0,'intval');//:2
        $post['rec_id']             =   explode('|',I('rec'));//[]:52
        $post['receipt']            =   I('is_inv',0,'intval');//:0
        $post['receipt_type']       =   I('inv_type');//:1
        $post['receipt_content']    =   I('inv_cont','','trim');//:办公用品
        $post['receipt_title']      =   I('inv_title','','trim');
        $post['bonus']              =   I('is_bonus',0,'intval');
        $post['bonus_id']           =   I('bonus_id',0,'intval');
        $post['bonus_sn']           =   I('bonus_sn');//:0
        $post['integral']           =   I('is_integral',0,'intval');//:0
        $post['integral_number']    =   I('integral_number',0,'intval');
        $post['shipping']           =   I('shipping');//:0
        $post['comment']            =   I('comment');//:0

        $order = new Model\OrderModel();
        $total = $order->total($this->user_info, $post);
        if ($total['error'] > 0) {
            $result = $total;
        } else {
            $t = $total['data']['total'];
            $data = [
                'goods_price_formated'  =>  $t['goods_price_formated'],
                'shipping_fee_formated' =>  $t['shipping_fee_formated'],
                'amount_formated'       =>  $t['amount_formated'],
                'integral'              =>  $t['integral'] . $this->_CFG['integral_name']
            ];
            $result = result_ajax(0,'获取成功！',$data);
        }
        $this->ajaxReturn($result);
    }

    private  function total1($address_id = 0){
        //$this->CheckLogin();
        $user_id = $this->user_id;

        $url = array();
        /* 取得购物类型 */
        $flow_type = session('flow_type');
        $flow_order = session('flow_order');
        $flow_type = !is_null($flow_type) ? intval($flow_type) : CART_GENERAL_GOODS;
        if ($flow_type == CART_GROUP_BUY_GOODS)    {        // 团购标志
            $this->assign('is_group_buy', 1);
        } elseif ($flow_type == CART_EXCHANGE_GOODS) {      // 积分兑换商品
            $this->assign('is_gift', 1);
        } else {                                            //正常购物流程  清空其他购物流程情况
            session('flow_order.extension_code','');
        }

        $me         = new Model\CartModel();//D('Flow');        //定义模型
        $order_me   = new Model\OrderModel();//D('Order');

        //检查数据库是否存在已钩选产品
        $total = $me->cart_list1($this->user_id, true);
        $cart_list = $total['list'];
        if (!$cart_list or count($cart_list) <= 0){
            $this->error('未发现购物车内钩选产品！',U('Cart/index'));
            //$this->redirect('Flow/index');
        }
        $ids = $total['total']['ids'];//取购物车内已选产品ID
        $this->assign('cart_list', $total['list']);             //显示产品

        //检查收货地址信息
        if(is_numeric($address_id) and $address_id > 0){
            $addr_id = $address_id;
        } else {
            $addr_id = 1;
        }
        //$consignee = $me->get_consignee($user_id, $addr_id);/print_r($consignee_list);

        $this->assign('consignee', $consignee = $me->get_consignee($user_id, $addr_id));         //显示收货地址*/
        $this->assign('consignee_list', $me->get_consignee($user_id));
        /*$consignee = $me->get_consignee($user_id, $addr_id);
        if (!$consignee){
            $this->redirect('Consignee/index?uri=Checkout/index');
        }*/

        $order = $order_me->flow_order_info($flow_type,$flow_order,$user_id);//初始化订单信息
        //$order['shipping_id'] = 15;      //默认物流
        //$this->assign('order', $order);
//print_r($order);
        /* 计算折扣 */
        if ($flow_type != CART_EXCHANGE_GOODS && $flow_type != CART_GROUP_BUY_GOODS) {      //不是团购和积分商城
            $discount1 = $order_me->Calculate_discount($total);         //取得活动信息
        } else {        //团购
            $discount1 = array();
        }
        $this->assign('discount', $discount1);

        //计算订单的费用                订单初始化  购物车产品统计  收货信息  优惠活动
        $total1 = $order_me->order_fee($order, $total, $consignee, $discount1, $user_id);
        $this->assign('expenses_total', $total1);
        $this->assign('shopping_money', sprintf('优购物金额小计 %s', $total1['formated_goods_price']));
        $this->assign('market_price_desc', sprintf('比市场价 %s 节省了 %s (%s)', $total1['formated_market_price'], $total1['formated_saving'], $total1['save_rate']));

        /* 取得配送列表 */
        $region            = array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district']);
        $shipping_list     = $order_me->available_shipping_list($region);
        $cart_weight_price = $order_me->cart_weight_price($flow_type);
        $insure_disabled   = true;
        $cod_disabled      = true;
        $goods_model = new Model\GoodsModel();
        $sess = $goods_model->getSessionIDorUserID();
        // 查看购物车中是否全为免运费商品，若是则把运费赋为零
        $shipping_count = M('cart')->where("`extension_code` != 'package_buy' AND `is_shipping` = 0 and $sess ")->getfield('count(*)');//print_r($shipping_count);
        foreach ($shipping_list AS $key => $val)  {
            $shipping_cfg = $me->unserialize_config($val['configure']);
            $shipping_fee = ($shipping_count == 0 AND $cart_weight_price['free_shipping'] == 1) ? 0 : $order_me->shipping_fee($val['shipping_code'], unserialize($val['configure']), $cart_weight_price['weight'], $cart_weight_price['amount'], $cart_weight_price['number']);

            $shipping_list[$key]['format_shipping_fee'] = $goods_model->price_format($shipping_fee, false);
            $shipping_list[$key]['shipping_fee']        = $shipping_fee;
            $shipping_list[$key]['free_money']          = $goods_model->price_format($shipping_cfg['free_money'], false);
            $shipping_list[$key]['insure_formated']     = strpos($val['insure'], '%') === false ? $goods_model->price_format($val['insure'], false) : $val['insure'];

            /* 当前的配送方式是否支持保价 */
            if ($val['shipping_id'] == $order['shipping_id'])
            {
                $insure_disabled = ($val['insure'] == 0);
                $cod_disabled    = ($val['support_cod'] == 0);
            }
        }

        $this->assign('shipping_list',   $shipping_list);
        $this->assign('insure_disabled', $insure_disabled);
        $this->assign('cod_disabled',    $cod_disabled);

        /* 取得支付列表 */
        $cod_fee    = 0;
        if ($order['shipping_id'] == 0) {
            $cod        = true;
            $cod_fee    = 0;
        }
        else
        {
            $shipping = $order_me->shipping_info($order['shipping_id']);
            $cod = $shipping['support_cod'];

            if ($cod)  {
                /* 如果是团购，且保证金大于0，不能使用货到付款 */
                if ($flow_type == CART_GROUP_BUY_GOODS)
                {
                    $group_buy_id = $_SESSION['extension_id'];
                    if ($group_buy_id <= 0)
                    {
                        $this->error('error group_buy_id');
                    }
                    $group_buy = $order_me->group_buy_info($group_buy_id);
                    if (empty($group_buy))
                    {
                        $this->error('group buy not exists: ' . $group_buy_id);
                    }

                    if ($group_buy['deposit'] > 0)
                    {
                        $cod = false;
                        $cod_fee = 0;

                        /* 赋值保证金 */
                        $this->assign('gb_deposit', $group_buy['deposit']);
                    }
                }

                if ($cod)
                {
                    $shipping_area_info = $order_me->shipping_area_info($order['shipping_id'], $region);
                    $cod_fee            = $shipping_area_info['pay_fee'];
                }
            }
            else
            {
                $cod_fee = 0;
            }
        }

        // 给货到付款的手续费加<span id>，以便改变配送的时候动态显示
        $pay_id = session('flow_order.pay_id');
        $payment_list = $order_me->available_payment_list(1, $cod_fee);
        if(isset($payment_list))  {
            foreach ($payment_list as $key => $payment)
            {
                if ($payment['is_cod'] == '1')
                {
                    $payment_list[$key]['format_pay_fee'] = '<span id="ECS_CODFEE">' . $payment['format_pay_fee'] . '</span>';
                }
                /* 如果有易宝神州行支付 如果订单金额大于300 则不显示 */
                if ($payment['pay_code'] == 'yeepayszx' && $total1['amount'] > 300)
                {
                    unset($payment_list[$key]);
                }
                /* 如果有余额支付 */
                if ($payment['pay_code'] == 'balance')
                {
                    /* 如果未登录，不显示 */

                    if ($user_id == 0)
                    {
                        unset($payment_list[$key]);
                    }
                    else
                    {
                        if ($pay_id == $payment['pay_id'])
                        {
                            $this->assign('disable_surplus', 1);
                        }
                    }
                }
            }
        }
        $this->assign('payment_list', $payment_list);

        /* 取得包装与贺卡 */
        if ($total1['real_goods_count'] > 0)
        {
            /* 只有有实体商品,才要判断包装和贺卡 */
            if (!isset($this->_CFG['use_package']) || $this->_CFG['use_package'] == '1')
            {
                /* 如果使用包装，取得包装列表及用户选择的包装 */
                $this->assign('pack_list', $order_me->pack_list());
            }

            /* 如果使用贺卡，取得贺卡列表及用户选择的贺卡 */
            if (!isset($this->_CFG['use_card']) || $this->_CFG['use_card'] == '1')
            {
                $this->assign('card_list', $order_me->card_list());
            }
        }

        $user_info = $goods_model->get_user_info($user_id);

        /* 如果使用余额，取得用户余额 */
        if ((!isset($this->_CFG['use_surplus']) || $this->_CFG['use_surplus'] == '1') && $user_id > 0 && $user_info['user_money'] > 0)   {
            $surplus = array(
                'allow_use_surplus'     =>  1,                          // 能使用余额
                'your_surplus'          =>  $user_info['user_money']        // 当前余额
            );
            $this->assign('surplus',$surplus);
        }

        /* 如果使用积分，取得用户可用积分及本订单最多可以使用的积分 */
        if ((!isset($this->_CFG['use_integral']) || $this->_CFG['use_integral'] == '1') && $user_id > 0 && $user_info['pay_points'] > 0
            && ($flow_type != CART_GROUP_BUY_GOODS && $flow_type != CART_EXCHANGE_GOODS))  {
            $integral = array(
                    'allow_use_integral'    =>  1,                                  // 能否使用积分
                    'order_max_integral'    =>  $order_me->flow_available_points($sess),    // 可用积分
                    'your_integral'         =>  $user_info['pay_points']            // 用户积分
            );
            $this->assign('integral', $integral);
        }


        /* 如果使用红包，取得用户可以使用的红包及用户选择的红包 */
        if ((!isset($this->_CFG['use_bonus']) || $this->_CFG['use_bonus'] == '1') && ($flow_type != CART_GROUP_BUY_GOODS && $flow_type != CART_EXCHANGE_GOODS)) {
            // 取得用户可用红包
            $user_bonus = $order_me->user_bonus($user_id, $total1['goods_price']);
            if (!empty($user_bonus)) {
                foreach ($user_bonus AS $key => $val) {
                    $user_bonus[$key]['bonus_money_formated'] = $me->price_format($val['type_money'], false);
                }
            }
            $bonus = array(
                'allow_use_bonus'   =>  1,          // 能使用红包
                'bonus_list'        =>  $user_bonus //显示红包
            );
            $this->assign('bonus', $bonus);
        }

        /* 如果使用缺货处理，取得缺货处理列表 */
        if (!isset($this->_CFG['use_how_oos']) || $this->_CFG['use_how_oos'] == '1') {
            if (is_array($GLOBALS['_LANG']['oos']) && !empty($GLOBALS['_LANG']['oos'])) {
                $this->assign('how_oos_list', $GLOBALS['_LANG']['oos']);
            }
        }

        /* 如果能开发票，取得发票内容列表 */
        if ((!isset($this->_CFG['can_invoice']) || $this->_CFG['can_invoice'] == '1')
            && isset($this->_CFG['invoice_content'])
            && trim($this->_CFG['invoice_content']) != '' && $flow_type != CART_EXCHANGE_GOODS)
        {
            $inv_content_list = explode("\n", str_replace("\r", '', $this->_CFG['invoice_content']));//读取发票内容

            $inv_type_list = array();
            foreach ($this->_CFG['invoice_type']['type'] as $key => $type)  {                       //读取发票类型
                if (!empty($type))  {
                    $inv_type_list[$type] = $type . ' [' . floatval($this->_CFG['invoice_type']['rate'][$key]) . '%]';
                }
            }
            $invoice = array(
                'inv_type_list'     =>  $inv_type_list,         //显示发票类型
                'inv_content_list'  =>  $inv_content_list       //显示发票内容
            );
            $this->assign('invoice', $invoice);
        }
         /* 保存 session */
        //$_SESSION['flow_order'] = $order;
        session('flow_order', $order);

        $url['edit_address']    = U('Consignee/index?uri=Checkout/index');
        $url['edit_flow']       = U('Flow/index');
        $url['orderfrm_sub']    = U('Done/index');
        $url['get_region']      = U('Checkout/region') . '?ajax=1';
        $url['add_consignee']      = U('Checkout/add_consignee') . '?ajax=1';
        $this->assign('url', $url);
        $this->assign('user_head', array('title'=>'填写核对购物信息','backUrl'=>U('Index/index'),'backText'=>'首页'));
        $this->assign('best_time', $order_me->get_besttime());
        $this->display('checkout:flow');
    }



    public function region(){
        $id = I('id',0,'intval');
        $order_me = new Model\OrderModel();
        $result = $this->result;
        //$ss = $order_me->get_region($id);

        $result['contents'] = $order_me->get_region($id);
        $result['message'] = '获取成功！';

        $this->ajaxReturn($result);

    }

    public function add_consignee(){
        $user_id = $this->user_id;
        //$user_id    = session('user_id');
        $id         = intval(I('post.id'));
        $nick       = I('post.nick');
        $prov       = intval(I('post.prov'));
        $city       = intval(I('post.city'));
        $dist       = intval(I('post.dist'));
        $addr       = I('post.addr');
        $email      = I('post.email');
        $tel        = I('post.tel');
        $zipcode    = intval(I('post.zipcode'));
        $building   = I('post.building');
        $best       = intval(I('post.best'));
        $mobile     = I('post.mobile');
        $me = new Model\UsersModel();

        $result = $this->result;
        if ($user_id <= 0){
            $result['error'] = 11;
            $result['message'] = '请先登录后再添加收货地址！';
            $result['content']['url'] = U('User/login?uri=Flow/consignee');
        } else if ($nick == '') {
            $result['error'] = 1;
            $result['message'] = '收货人姓名不能为空，请输入收货人姓名！';
        } else if ($prov > 0 and $me->get_region_text($prov) == ''){
            $result['error'] = 2;
            $result['message'] = '请选择收货省份！';
        } else if ($city > 0 and $me->get_region_text($city) == ''){
            $result['error'] = 3;
            $result['message'] = '请选择收货城市！';
            /* } else if ($me->get_region_text($dist) == ''){
                 $result['error'] = 4;
                 $result['message'] = '请选择收货区或县！';*/
        } else if ($addr == ''){
            $result['error'] = 5;
            $result['message'] = '请输入收货详细地址！';
        } else if ($email != '' and !$me->is_email($email)){
            $result['error'] = 6;
            $result['message'] = '请输入正确的邮箱地址！';
        } else if ($mobile != '' and !$me->is_mobile_num($mobile)){
            $result['error'] = 7;
            $result['message'] = '请输入正确的手机号码！';
        } else if ($tel != '' and !$me->is_tel($tel)){
            $result['error'] = 8;
            $result['message'] = '电话号码输入不正确，请输入区号 + 减号（-） + 电话号码！';
        } else if ($tel == '' and $mobile == ''){
            $result['error'] = 9;
            $result['message'] = '联系电话和手机号码最少要输入一个！';
        } else {

            $data = array(
                'consignee'     =>  $nick,
                'country'       =>  0,
                'province'      =>  $prov,
                'city'          =>  $city,
                'district'      =>  $dist,
                'address'       =>  $addr,
                'email'         =>  $email,
                'tel'           =>  $tel,
                'mobile'        =>  $mobile,
                'zipcode'       =>  $zipcode,
                'sign_building' =>  $building,
                'best_time'     =>  $best,
                'user_id'       =>  $user_id
            );
            $m = D('user_address');
            if ($id > 0){
                $m->where('address_id=' . $id)->save($data);
                $result['error'] = 0;
                $result['message'] = '修改成功';
                $result['contents']['id'] = $id;
                $result['contents']['handle'] = 'edit';
            } else {
                $m->add($data);
                $result['contents']['id'] = $m->getLastInsID();
                $result['error'] = 0;
                $result['message'] = '添加成功';
                $result['contents']['handle'] = 'add';
            }
        }
        $this->ajaxReturn($result);
    }

}
