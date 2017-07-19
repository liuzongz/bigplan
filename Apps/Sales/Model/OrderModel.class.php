<?php
namespace Sales\Model;


class OrderModel extends BaseModel {
    protected $tableName = 'favourable_order';

    public function add_order($user_id, $order_amount, $attend_id, $act_id){
        $order_info = array(
            'order_sn'      => $this->create_order(),
            'attend_id'     => $attend_id,
            'act_id'        => $act_id,
            'user_id'       => $user_id,
            'pay_status'    => PS_UNPAYED,
            'order_amount'  => $order_amount,
            'pay_time'      => time(),
        );
        $result = $this->add($order_info);
        return !$result ? null : $result;
    }

    /**
     *  @param array $where
     * @return mixed|array
     */
    public function get_order($where){
        return M('FavourableOrder')->where($where)->find();
    }

    /**
     * 支付回调功能
     * @param $result
     * @return array
     */
    public function payed_result($result){
        \Think\Log::record('回调记录payed_result:' . print_r($result,1));
        $order_info = $this->get_order(['order_sn'=>$result['order_sn'], 'pay_status'=>PS_UNPAYED]);
        if(!$order_info and intval($order_info['order_id']) <= 0) return result_ajax(103,'未找到订单！');
        $user_info = M('Users')->where(['user_id' => $order_info['user_id']])->find();
        if(!$user_info) return result_ajax(102,'未找到该用户！', array_merge($user_info,$order_info));

        $log_where = [
            'order_id'      =>  $order_info['order_id'],
            //'order_amount'  =>  $order_info['order_amount'],
            //'pay_id'        =>  $order_info['pay_id'],
            'user_id'       =>  $order_info['user_id'],
        ];
        $log = M('PayLog');
        if($log->where($log_where)->count() <= 0) return result_ajax(101,'款金额与订单金额不符！');
        if ($result['pay_type'] == 'wxpay') {           //微信返回信息填充
            $where = array(       //查询条件
                'order_id'      =>  $order_info['order_id'],
                'user_id'       =>  $order_info['user_id'],
                'pay_type'      =>  $result['pay_id'],
                'handle_name'   =>  '统一下单'
            );
            M('PaydataLog')->where($where)->data(array('result_data'=>print_r($result,1)))->save();     //保存接收的数据
        }
        $act_info = M('FavourableUserActivity')
            ->alias('a')
            ->field('b.*')
            ->where(['id'=>(int)$order_info['act_id']])
            ->join(' JOIN __USERS__ b ON a.user_id=b.user_id')
            ->find();

        //修改订单状态
        M('FavourableOrder')->where(['order_id'=>$order_info['order_id']])->data(['pay_status'=>PS_PAYED])->save();
        $info = $this->allot_money($order_info, $act_info);//分配店家收入

        //加入商家订单金额走向
        $user_model = new UsersModel();
        $user_account_id = $user_model->add_user_account(
            ['user_id'=>$act_info['user_id']],
            $order_info['order_amount'],
            ['desc'=>"订单：{$order_info['order_sn']}收入",'type'=>PT_MONEY,'user_note'=>'','stage'=>ST_INCOME]
        );
        $user_account = M('users')->where('user_id='.$order_info['user_id'])->getField('user_id');
        $user_model->set_user_account($user_account_id,PS_PAYED,[
            'pay_code'      =>'system',
            'payment_name'  =>'系统处理',
            'pay_account'   =>$user_account,
            'pay_name'      =>'admin'
        ]);

        if($info){
            \Think\Log::record('回调记录分配订单收入:' . print_r($info,1));
            $res = result_ajax(200,'付款成功！');
        }else{
            $res = result_ajax(104,'店铺进账分配错误！');
        }

        return $res;
    }

    /**
     * 分配店家订单收入
     * @param array $order_info
     * @param array $act_info
     * @return array
     */
    public function allot_money($order_info, $act_info){

        if(!$act_info) return result_ajax(301, '未找到该订单');
        $user_model = new UsersModel();
        $user_model->set_user_money($act_info['user_id'], $order_info['order_amount'], 'income', '订单收入');

        if(!$user_model) return result_ajax(302, '用户金额修改失败！');

        return result_ajax(200, '用户金额加入成功！');
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
            //if ($result)
                //$result['pay_config'] = $this->unserialize_config($result['pay_config']);
        } else {
            //if ($is_weixin) {
            $where .= ' and is_weixin=' . intval($is_weixin);
            //}
            $result = $m->where($where)->select();
            foreach ($result as &$v) {
                $v['pay_logo'] = img_url($v['pay_logo']);
            }
        }
        return $result;
    }
}