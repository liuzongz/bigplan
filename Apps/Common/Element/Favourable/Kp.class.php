<?php
namespace Common\Element\Favourable;



use Common\Element\Activity;
use Common\Element\Element;

class Kp extends Favourable {

    public function getConfig(){
        return array(
            'cost'    => array(
                'value'     => '16',
                'title'     => '费用比率',
                'desc'      => '按商品价格收取的一定比率的营销费用',
                'validate'  => array(
                    array('cost','/^[1-9]\d{0,1}$|^100$/','费用比率为1-100之间的整数'),
                ),
            ),
            'kp'    => array(
                'value'     => '0',
                'title'     => '赠送Kp点',
                'desc'      => '为0时按照商品价格折算Kp点',
                'validate'  => array(
                    array('kp','/^[1-9]\d{0,5}$/','赠送Kp点为0-999999之间的整数'),
                ),
            ),
            'store_kp'    => array(
                'value'     => '0',
                'title'     => '是否赠送商店劵',
                'desc'      => '赠送商家劵',
                'validate'  => array(
                    array('store_kp','/^[0|1]/','状态只能为1（开启）0（不开启）'),
                ),
            ),
        );
    }

    public function price(Activity $activity){
        $this->checkActivity($activity);
        return Element::createObject(array('class'=>'price', 'RMB'=>$activity->goods->price));
    }

    public function paid(Activity $activity, $order, $goods, $level){

        $kp = $activity->config['kp']['value'] ? $activity->config['kp']['value'] : $goods['goods_price'];

        return array(
            'class' => $this->name,
            'list'  => array(
                array(
                    'stype'         => YJ_TYPE_3,
                    'uid'           => $order['user_id'],
                    'level'         => 0,
                    'money'         => $kp,
                    'store_kp'      => $activity->config['store_kp']['value'],
                )
            )
        );
    }
}


