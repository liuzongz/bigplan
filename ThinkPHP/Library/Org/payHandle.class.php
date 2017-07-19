<?php

final class payHandle {
    public $from;
    public $db;
    public $payType;
    public $token;
    public function __construct($token, $from, $paytype = 'tenpay') {
        $this->from = $from;
        $this->from = $from ? $from : 'Groupon';
        $this->from = $this->from != 'groupon' ? $this->from : 'Groupon';
		switch (strtolower($this->from)) {
            default:
            case 'store':
                $this->db = M('ProductCart');
                break;

            case 'repast':
                $this->db = M('DishOrder');
                break;
        }
        $this->token = $token;
        $this->payType = $paytype;
    }
    public function getFrom() {
        return $this->from;
    }
    public function beforePay($id) {
        $thisOrder = $this->db->where(array(
            'token' => $this->token,
            'orderid' => $id
        ))->find();
        switch (strtolower($this->from)) {
            default:
                $price = $thisOrder['price'];
                break;

            case 'business':
                $price = $thisOrder['payprice'];
                break;
        }
        return array(
            'orderid' => $thisOrder['orderid'],
            'price' => $price,
            'wecha_id' => $thisOrder['wecha_id'],
            'token' => $thisOrder['token']
        );
    }
    public function afterPay($id, $transaction_id = '',$cashpay='') {
        $thisOrder = $this->beforePay($id);
        $wecha_id = $thisOrder['wecha_id'];
        $users = M('WechaUser');
        $order_model = $this->db;
        $order_model->where(array('orderid' => $id))->setField('paid', 1);
        if(!empty($cashpay)){
			$users->where(array('wecha_id' => $wecha_id))->setDec('cash', $thisOrder['price']);
		}
        return $thisOrder;
    }
}

