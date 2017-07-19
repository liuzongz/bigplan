<?php

namespace Wap\Controller;
use Wap\Model;

class AdController extends WapController {
    protected function _initialize(){
        parent::_initialize();
        /*ob_start();
        define('AD_CLASS','.ad');*/
        //exit('43432');
    }

    public function index() {
//        $ctrl = A('Wap/Index');
//        $ctrl->index0809();
//        $html = $this->get_ad_block($ctrl->index0809());
//        $this->assign('html',$html);
       $this->display();
    }
}
