<?php
namespace Common\Element;

use Common\Element\Base\Object;

/**
 * @property-read double $RMB
 * @property-read int $WB
 */
class Price extends Object{

    private $_RMB = 0;//人民币
    private $_WB  = 0;//微币

    public function getRMB(){
        return $this->_RMB * 100 / 100;
    }
    public function setRMB($RMB){
        $this->_RMB = (double)$RMB;
    }
    public function getWB(){
        return (int)$this->_WB;
    }
    public function setWB($WB){
        $this->_WB = (int)$WB;
    }

    public function __toString(){
        $str = '';
        $str .= $this->RMB ? '￥'. $this->getRMB() .'元 ' : '';
        $str .= $this->WB ? $this->getWB().'微币' : '';
        if($str){
            return $str;
        }else{
            //E('商品价格不能为空');
            $this->error = '商品价格不能为空';
            return '';
        }
    }

}


