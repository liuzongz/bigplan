<?php

/**
 * 即牛 - 会员下级
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: LowerController.class.php 17156 2016-05-13 17:06:47Z keheng $
*/
namespace Wap\Controller;
class LowerController extends WapController {
    protected function _initialize(){
        parent::_initialize();
    }

    public function index(){
        $this->assign('user_head', array('title'=>'我的会员','backUrl'=>U('U/index'),'backText'=>'首页'));
        $this->display();
    }
} 