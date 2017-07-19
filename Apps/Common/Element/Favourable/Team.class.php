<?php
namespace Common\Element\Favourable;



use Common\Element\Activity;
use Common\Element\Element;

class Team extends Favourable {

    public function getConfig(){
        return array(
            'discount'  => $this->templateConfig['discount'],
            'num'       => array(
                'value'     => '5',
                'title'     => '开团人数',
                'desc'      => '付款达到团购人数才能发货',
                'validate'  => array(
                    array('num','/^[1-9]\d{0,1}$/','开团人数为1-99之间的整数'),
                ),
            ),
            'time'      => array(
                'value'     => '0',
                'title'     => '有效期限秒',
                'desc'      => '开团后,有效期内未达到开团人数,团购失败',
                'validate'  => array(
                    array('time','/^[1-9]\d{0,5}$/','有效期限为0-999999之间的整数'),
                ),
            )
        );
    }

    public function price(Activity $activity){
        $this->checkActivity($activity);
        return Element::createObject(array('class'=>'price', 'RMB'=>$activity->goods->price * $activity->config['discount']['value'] / 100));
    }
}


