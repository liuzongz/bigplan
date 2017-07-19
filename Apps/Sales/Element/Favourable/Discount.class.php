<?php
namespace Sales\Element\Favourable;

use Sales\Element\Activity;
use Sales\Element\Element;

class Discount extends Favourable {

    public function getConfig(){

        return array(
            'discount'  => $this->templateConfig['discount'],
            'cate'      => $this->templateConfig['cate'],
            'limit'     => array(
                'value'     => '0',
                'title'     => '是否限时',
                'desc'      => '设置大于0时表示限时折扣,会显示倒计时',
                'validate'  => array(
                    array('limit','/^\d$/','是否限时标记为0-9之间的整数'),
                ),
            ),
            'rebate'     => array(
                'value'     => '0',
                'title'     => '开启超级返利',
                'desc'      => '0为不开启，返利比例在1-100之间',
                'validate'  => array(
                    array('rebate','/^[0-9]\d{0,1}$|^100$/','返还比例过大，请不要超过100'),
                ),
            ),
        );
    }
    public function price(Activity $activity){
        $this->checkActivity($activity);
        return Element::createObject(array('class'=>'price', 'RMB'=>$activity->goods->price * $activity->config['discount']['value'] / 100));
    }
}


