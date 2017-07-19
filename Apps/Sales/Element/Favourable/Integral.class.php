<?php
namespace Sales\Element\Favourable;

use Sales\Element\Activity;
use Sales\Element\Element;

class Integral extends Favourable {

    public function getConfig(){
        return array(
            'cate'      => $this->templateConfig['cate'],
            'integral'  => array(
                'value'     => '999999',
                'title'     => '所需微币',
                'desc'      => '商品支付时还需要支付一定数量的微币',
                'validate'  => array(
                    array('integral','/^[1-9]\d{0,5}$/','所需微币为1-999999之间的整数'),
                ),
            ),
        );
    }

    public function price(Activity $activity){
        $this->checkActivity($activity);
        $wb = (int)$activity->config['integral']['value'];
        return Element::createObject(array('class'=>'price', 'WB'=>$wb ? $wb : $activity->goods->price));
    }

}


