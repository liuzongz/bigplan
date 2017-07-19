<?php
namespace Common\Element\AdvType;



class Text extends AdvType {

    public $desc = '输入文字作为广告内容';

    public function validate($value){
        if($value){
            if(preg_match('/^[\w'.REG_ZH.']{1,20}$/u', $value)){
                return $value;
            }else{
                $this->error = '文字广告内容为1-20个中英文非特殊字符';
                return '';
            }
        }else{
            $this->error = $this->desc;
            return '';
        }
    }
}


