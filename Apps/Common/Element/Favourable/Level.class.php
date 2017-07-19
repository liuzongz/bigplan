<?php
namespace Common\Element\Favourable;

use Common\Element\Activity;
use Common\Element\Element;

class Level extends Favourable {

    public function getConfig(){
        return array(
            'cost'     => array(
                'value'     => '0',
                'title'     => '费用比例',
                'desc'      => '按此比例从商品价格中分出一部分用于分销',
                'validate'  => array(
                    array('cost','/^[1-9]\d{0,1}$|^100$/','费用比例值为0-100之间的整数'),
                ),
            ),
            'level1'     => array(
                'value'     => '0',
                'title'     => '一级比例',
                'desc'      => '一级分销比例',
                'validate'  => array(
                    array('level1','/^[1-9]\d{0,1}$|^100$/','分销比例值为0-100之间的整数'),
                ),
            ),
            'level2'     => array(
                'value'     => '0',
                'title'     => '二级比例',
                'desc'      => '二级分销比例',
                'validate'  => array(
                    array('level2','/^[1-9]\d{0,1}$|^100$/','分销比例值为0-100之间的整数'),
                ),
            ),
            'level3'     => array(
                'value'     => '0',
                'title'     => '三级比例',
                'desc'      => '三级分销比例',
                'validate'  => array(
                    array('level3','/^[1-9]\d{0,1}$|^100$/','分销比例值为0-100之间的整数'),
                ),
            ),
        );
    }

    public function price(Activity $activity){
        $this->checkActivity($activity);
        return Element::createObject(array('class'=>'price', 'RMB'=>$activity->goods->price));
    }

    public function paid(Activity $activity, $order, $goods, $level){
        $cost   = $activity->config['cost']['value'] / 100;
        $list   = array();
        $index  = 0;
        foreach($level as $user){
            $index++;
            $list[] = array(
                'stype'         => YJ_TYPE_0,
                'uid'           => $user['user_id'],
                'level'         => $index,
                'money'         => $goods['goods_price'] * $goods['goods_number'] * $cost * ($activity->config["level{$index}"]['value'] / 100),
            );
        }
        return array(
            'class' => $this->name,
            'list'  => $list
        );
    }
}


