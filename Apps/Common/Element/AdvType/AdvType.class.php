<?php
namespace Common\Element\AdvType;

use Common\Element\Base\Object;

abstract class AdvType extends Object {

    //当前子类中文名称
    public $title   = null;

    //当前子类的短名称标记
    public $name    = null;

    public function __toString(){
        return $this->title;
    }

    public function view($pos){
        $pos = (string)$pos;
        $this->assign('_adv_type', $this);
        return $this->fetch("{$pos}.{$this->name}");
    }

    //广告内容验证
    abstract public function validate($value);
}


