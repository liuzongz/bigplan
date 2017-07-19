<?php
namespace Common\Element\AdvType;



class Img extends AdvType {

    public $desc = '选择一张图片作为广告内容';

    public function validate($value){
        //图片传递的是图片ID,小于0时如果没有选择图片,就是广告id,如果存在就无需修改
        $value = (int)$value;
        $value = $value >= 0 ?
            M('img_gallery')->field('concat(savepath,savename)url')->where(array('id'=>(int)$value))->find() :
            M('ad')->field('ad_code url')->where(array('ad_id'=>-$value))->find();
        if(isset($value['url']) && preg_match(REG_URI, '/'.trim($value['url'], '/'))){
            return $value['url'];
        }else{
            $this->error = '图片不存在';
            return '';
        }
    }

}


