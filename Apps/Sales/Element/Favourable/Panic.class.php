<?php
namespace Sales\Element\Favourable;

use Sales\Element\Activity;
use Sales\Element\Element;

class Panic extends Favourable {

    public function getConfig(){
        return array(
            'discount'  => $this->templateConfig['discount'],
            'num'       => array(
                'value'     => '0',
                'title'     => '抢购数量',
                'desc'      => '抢购商品数量,销售完毕,抢购自动结束',
                'validate'  => array(
                    array('num','/^[1-9]\d{0,5}$/','抢购数量为0-999999之间的整数'),
                ),
            ),
        );
    }

    public function cart(Activity $activity){
        parent::cart($activity);
        return $activity->config['num']['value'] >= count($activity->getDataOfOrder());
    }

    public function pay(Activity $activity){
        return $this->cart($activity);
    }

    public function price(Activity $activity){
        $this->checkActivity($activity);
        return Element::createObject(array('class'=>'price', 'RMB'=>$activity->goods->price * $activity->config['discount']['value'] / 100));
    }

}


