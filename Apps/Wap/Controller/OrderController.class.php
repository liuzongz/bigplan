<?php

namespace Wap\Controller;
use Wap\Model;
class OrderController extends WapController {
    protected function _initialize() {
        parent::_initialize();
        $this->void_user();
    }

    //列表
    public function index(){

        $t = I('get.t');//pay   confirm    comment REFUNDED
        $order_model = new Model\OrderModel();
        $order_list = $order_model->get_order_list($this->user_id, $t, 5, $this->store_token);
        $order_titles = array(
            'unpaid' => '：待付款',
            'pay'=>'：待发货',
            'confirm'=>'：已发货',
            'comment'=>'：待评价',
            'refund'=>'：已退货',
        );
        //CART_EXCHANGE_GOODS
        $this->assign('order_list', $order_list);

        $this->assign('empty_order', $this->fetch('Order:empty'));
        $this->assign('empty_goods', '<div class="order_goods" style="height:5rem;text-align:center;line-height:5rem;">该订单下无产品</div>');
        $this->assign('user_head', array('title'=>'订单列表'.$order_titles[$t],'backUrl'=>'','backText'=>'首页'));
        $this->display('Order:orderlist');
    }
    //详细
    public function detail(){
        $id = I('get.id','','stripslashes');
        $order_model = new Model\OrderModel();
        $order_info = $order_model->get_order_info($this->user_id,$id);
        if ($order_info) {

            $order_info['goods_list'] = $order_model->get_order_goods($order_info['order_id']);
            $order_info['shipping_list'] = $this->get_shipping_info($order_info['shipping_code'],$order_info['shipping_num']);
            //            $this->debug($order_info,1);
            $this->assign('order_info', $order_info);
            $this->assign('user_head', array('title'=>'订单详细信息','backUrl'=>U('Order/index'),'backText'=>'订单列表'));
            $this->display('Order:orderdetail');
        } else {
            $this->error('订单不存在！');
        }
    }
    //关闭订单
    public function cancel(){
        $id = I('get.id','','stripslashes');
        $order_model = new Model\OrderModel();
        $order_model->user_info = $this->user_info;
        $result = $order_model->order_cancel($id,'用户取消订单');
        if ($result['error'] == 0) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

//        if (!is_numeric($id)) {
//            $this->error('取消订单失败！');
//        } else {
//
//        }
    }

    //确认收货
    public function confirm(){
        $id = I('id');
        $order_model = new Model\OrderModel();
        $order_model->user_info = $this->user_info;
        $result = $order_model->order_confirm($id,'用户确认收货');
        if ($result['error'] == 0) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }
//        if (!is_numeric($id)) {
//            $this->error('确认收货失败！');
//        } else {
//
//        }
    }
    //申请退款
    public function refund(){

    }
    //申请退货//
    public function returng(){

        //获取该商品详细
        $rec_id = I('get.rec_id',null,'intval');
        list($refund) = $this->refund_need($rec_id);
        if($refund['step']!==0&&$refund['step']!==3) $this->error("该商品不符合退货要求");
        $this->assign('refund',$refund);
        $this->assign('user_head', array('title'=>'退货申请单','backUrl'=>U('Order/index'),'backText'=>'订单列表'));
        $this->display();
    }

    public function returng_act(){
        $order_sn =  I('post.id','','stripslashes');
        $rec_id =  I('post.rec_id',0,'intval');
        list($refund,$goods_info) = $this->refund_need($rec_id);
        //更改订单商品状态
        if($refund['step']===0||$refund['step']===3){
            $prepare = array(
                'rec_id'=>0,
                'cause_type'=>0,
                'cause_describe'=>'',
                'check_time'=>0,
                'refund_shipping_num'=>0,
                'is_return'=>1,
                'buyer_refund_img'=>'',
                'apply_time'=>time(),
            );
            $data = array();
            $data['rec_id']         = I('post.rec_id',0,'intval');
            $data['cause_type']     = I('post.cause_type','','htmlspecialchars');
            $data['cause_describe'] = I('post.cause_describe','','htmlspecialchars');
            $data['refund_price']   = I('post.refund_price',0.00,'floatval');
            //验证
             if(!$data['cause_type'])
                 $this->error("请选择退货原因");
             if(!$data['cause_describe']||mb_strlen($data['cause_describe'],'UTF-8')>70)
                 $this->error("退货描述请控制在70个字以内");
             if(!$this->is_decimal($data['refund_price'])&&!$this->is_number($data['refund_price']))
                 $this->error("请输入正确的返款金额1");
             if(floatval($data['refund_price'])<0)
                 $this->error("请输入正确的返款金额2");
            //dump($goods_info['max_refund']);
             if($data['refund_price']>$goods_info['max_refund'])
                 $this->error("退款金额不能超过最大退款金额");

            $img = join(',',$this->upfile(1391));
            $data['buyer_refund_img'] =$img?:"";
            $data = array_merge($prepare,$data);


            $model = M('order_refund');
            if($model->where('rec_id=%d',$data['rec_id'])->count()){
                $re = $model->where('rec_id=%d',$data['rec_id'])->save($data);
            }else{
                $re = M('order_refund')->add($data);
            }

            if($re !== false){
                $this->success("已提交退货申请，请等待审核！",U('index'));
            }else{
                $this->error("操作失败！",U('index'));
            }

        }else{
            $this->error("该商品不符合退货要求！",U('index'));
        }

    }

    //退货-填写快递单号
    public function edit_shipnum(){
        $rec_id = I('rec_id',0,'intval');
        list($refund) = $this->refund_need($rec_id);
        if($refund['step']!==2) $this->error('非法操作');
        if(IS_POST){
            $refund_shipping_num = I('refund_shipping_num','','htmlspecialchars');
            $re = M('order_refund')->where('rec_id=%d',$rec_id)->save(array('refund_shipping_num'=>$refund_shipping_num));
            if($re!==false){
                $this->success("快递单号已添加，请等待返款",U('index'));
            }else{
                $this->error("操作失败");
            }
        }else{
            //获取该商品详细

            $this->assign('user_head', array('title'=>'退货申请单-退货单填写','backUrl'=>U('Order/index'),'backText'=>'订单列表'));
            $this->display();
        }

    }
    /**
     * 退货-退款信息 退款各阶段提示信息
     * 申请退货前 0
     * 1申请退货后 1
     * 2卖家同意 11
     * 3卖家拒绝 1
     * 4填写快递单号后 1
     * 5卖家收货后 1
     * 6退货失败 1
     */
    public function refund_info(){
        $rec_id = I('get.rec_id',0,'intval');
        $order_sn = I('get.id');
        list($refund) = $this->refund_need($rec_id);
        $this->assign('refund',$refund);
        $this->display();
    }

    /*
     * 获取一些在退货视图里必要的信息
     * 1.商品信息
     * 2.商家信息
     * 3.退货状态信息
     */
    public function refund_need($rec_id){
        $model = new Model\OrderModel;
        $goods_info = $model->get_refund_goods($rec_id);
            $this->assign('goods_info',$goods_info);
        $status = $model->get_return_status($rec_id);
        $refund_text = $model->get_return_text($status);
        return array($refund_text,$goods_info);
    }




    //删除订单
    public function delete(){
        $order_id =  I('get.id','','stripslashes');
       // if (!is_numeric($order_id)) $this->error('删除订单失败！[0]');
        $order_model = new Model\OrderModel();
        $order_model->user_info = $this->user_info;
        $order_info = $order_model->get_order_info($this->user_info['user_id'],$order_id);
        if (!$order_info) {
            $this->error("删除订单失败！[1]");
        } else {
            $allow_delete = $order_info['order_status']     == OS_CANCELED //已取消
                        and $order_info['shipping_status']  == SS_UNSHIPPED //未发货
                        and $order_info['pay_status']       == PS_UNPAYED; //未付款
            if($allow_delete){
                $order_model->startTrans();
                $re_info = $order_model->where('user_id=%d and order_id=%d',$this->user_id,$order_info['order_id'])->delete();
                $re_goods = M('order_goods')->where('order_id=%d',$order_info['order_id'])->delete();
                if($re_info!==false&&$re_goods!==false){
                    //添加日志
                    $data = array(
                        'order_id'          => $order_id,
                        'order_status'      => OS_INVALID,
                        'shipping_status'   => $order_info['shipping_status'],
                        'pay_status'        => $order_info['pay_status'],
                    );
                    $re = $order_model->add_order_log($this->user_info, $order_info['order_id'],$data,"用户删除订单");
                    $order_model->commit();
                    $this->success("订单删除成功！");
                }else{
                    $order_model->rollback();
                    $this->error("删除订单失败！[2]");
                }
            }
        }
    }
}
