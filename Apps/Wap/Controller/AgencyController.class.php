<?php

/**
 * 即牛 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: AgencyController.class.php 17156 2016-01-26 13:19:47Z keheng $
*/
namespace Wap\Controller;
use Wap\Model;

class AgencyController extends WapController {
    protected $agency_model;
    protected $apply_info;
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();
        $this->agency_model = new Model\AgencyModel();
        $this->apply_info = $this->agency_model->get_apply($this->user_id);
        if (empty($this->apply_info)) {
            $this->error('您不是特约经销商，不能进入经销商系统!');
        } else if ($this->apply_info['state'] != AS_THROUGH) {
            $this->error('您的申请还未通过审核，不能进入经销商系统!');
        }
    }

    public function index(){

    }


}

 