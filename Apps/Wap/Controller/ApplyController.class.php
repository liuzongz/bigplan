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
 * $Id: ApplyController.class.php 17156 2016-12-06 10:28:47Z keheng $
*/
namespace Wap\Controller;
use Wap\Model;
class ApplyController extends WapController {
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();
    }

    public function index(){
        $stock_model = new Model\StockModel();
        if ($apply = $stock_model->get_store_info('s.user_id=' . $this->user_id)) {
            if ($apply['store_state'] == STORE_AUDIT) {
                $apply['state_icon'] = 'audit_timeOut.png';
                $apply['state_name'] = '审核中';
                $apply['state_text'] = '<p>您申请的店铺正在审核中，请耐心等待！</p><p class="for_where_to">您可以<a class="cancel" href="' . U('apply_cancel') . '">取消申请</a> &nbsp;&nbsp; <a class="edit" href="' . U('apply_edit') . '">修改申请</a></p>';
            } else if ($apply['store_state'] == STORE_CLOSED) {
                $apply['state_icon'] = 'audit_false.png';
                $apply['state_name'] = '审核失败';
                $apply['state_text'] = '<p>' . $apply['store_audit_info'] . '</p><p class="for_where_to">立刻前往<a class="edit" href="' . U('apply_edit') . '">重新申请&nbsp; &gt; &gt;</a>';
            }else if ($apply['store_state'] == STORE_OPEN && $apply['store_end_time'] < time()){
                $apply['state_icon'] = 'audit_false.png';
                $apply['state_name'] = '已到期！';
                $apply['state_text'] = '<p>对不起，你的签约期限已到期，请续签</p>
<p class="for_where_to">立刻前往<a class="renew" href="' . U('apply_renew') . '">续签&nbsp; &gt; &gt;</a></p>';
            }else if ($apply['store_state'] == STORE_CANCEL){
                $apply['state_icon'] = 'audit_false.png';
                $apply['state_name'] = '已取消！';
                $apply['state_text'] = '<p>申请已取消！</p>
<p class="for_where_to">您可以<a class="edit" href="' . U('apply_edit') . '">修改申请</a>重新提交</p>';
            } else if ($apply['store_state'] == STORE_OPEN) {
                $apply['state_icon'] = 'audit_success.jpg';
                $apply['state_name'] = '正常开启';
                if ($apply['store_end_time'] - $this->time <= (60 * 60 * 24 * 30) ) {
                    $apply['state_text'] = '<p>你的店铺签约将于' . date('Y-m-d',$apply['store_end_time']) . '已到期，请快续签，以免影响您店铺的正常使用</p><p class="for_where_to"><a>立刻前往续签&nbsp; &gt; &gt;</a></p>';
                } else {
                    $apply['state_text'] = '尊敬的'.$apply['contact'].',您的申请已通过审核。';
                    if ($this->user_info['user_name'] == '' or
                        $this->user_info['user_name'] == $this->user_info['openid'] and
                        $this->user_info['openid'] != '')
                    {
                        $apply['state_text'] .= '<br/>请<a href="' . U('profile/pass') . '">设置</a>登录用户和密码';
                    } else {
                        $apply['state_text'] .= '<br/>请通过您设置的登录用户名和密码在电脑浏览器中进行登录';
                    }
                    $apply['state_text'] .= '<br/><br/>登录地址：' . $this->cur_domain . '/seller';
                }
            }
            $this->assign('apply', $apply);
            $this->assign('user_head', array('title'=>'特约经销商入驻','backUrl'=>U('User/index'),'backText'=>'会员首页'));
            $this->display('Agency:audit');
        } else {
            $this->redirect('apply_edit');
        }
    }

    //取消
    public function apply_cancel(){
        $stock_model = new Model\StockModel();
        $store_info = $stock_model->get_store_info('user_id=' . $this->user_id);
        if ($store_info) {
            if ($store_info['store_state'] != STORE_AUDIT) {
                $this->error('不能取消审核店铺！' . STORE_AUDIT . '=' . $store_info['store_state']);
            } else {
                if ($stock_model->set_store_state($store_info['store_id'], STORE_CANCEL)) {
                    $this->success('取消成功！',U('apply/index'));
                } else {
                    $this->error('取消失败！',U('apply/index'));
                }
            }
        } else {
            $this->error('店铺不存在！');
        }
    }
    //编辑
    public function apply_edit(){
        $stock_model = new Model\StockModel();
        $store_info = $stock_model->get_store_info('user_id=' . $this->user_id);
        if (IS_POST) {
            $dir_id = 1;
            $store_name     = I('post.shopname','','trim');
            $grade_id       = I('post.grade_id');
            $nickname       = I('post.name','','trim');
            $phone          = I('post.phone','','trim');
            $range          = I('post.range','','trim');
            $terms          = I('post.terms');
            $province_id    = I('post.province',0,'intval');
            $city_id        = I('post.city',0,'intval');
            $area_id        = I('post.district',0,'intval');
            $store_company_name         = I('post.store_company_name');
            $business_num   = I('post.business_num');
            $business_img   = I('post.business_img','','trim');

            if (empty($store_name)) {
                $this->error('请输入店铺名称!');
            } else if(!$stock_model->checkShopNameValidity($store_name,'2,30')){
                $this->error("店铺名称长度应为2-30位之间");
            } else if ($stock_model->is_exists($store_name,'store_name') and $store_info['store_name'] != $store_name) {
                $this->error('店铺名已经存在');
            } else if (empty($grade_id)) {
                $this->error('请选择店铺类型！');
            } else if (empty($nickname)) {
                $this->error('请输入联系人名称！');
            } else if (!$stock_model->is_mobile_num($phone)) {
                $this->error('请输入正确的手机号码！');
            } else if ($store_company_name == '') {
                $this->error('公司名称必须与营业执照保持一致，否则将不通过审核！');
            } else if(!$stock_model->checkShopNameValidity($store_company_name,"4,20")){
                $this->error("公司名称长度应为4-20位之间");
            } else if ($business_num == '') {
                $this->error('营业执照号码必须与营业执照保持一致，否则将不通过审核！');
            } else if (!$store_info and ($province_id == 0 or $city_id == 0 or $area_id == 0)) {
                $this->error('请选择您所在区域！');
            } else if (empty($range)) {
                $this->error('请输入营业执照上的经营范围！');
            } else if ($terms != 'on') {
                $this->error('您必须同意' . $this->_CFG['shop_name'] . '服务协议才可以进入下一步！');
            } else if ($business_img == '') {
                $this->error('请上传营业执照照片');
            } else {
//                $upload = $this->upfile($dir_id, 512000, array( 'image/jpeg' ));

//                if ($store_info) {
                    if(!$province_id){
                        $province_id = $store_info['province'];
                        $city_id = $store_info['city'];
                        $area_id = $store_info['district'];
                    }
                    $data = array(
                        'user_id'           => $this->user_id,
                        'store_name'        => $store_name,
                        'business_num'      => $business_num,
                        'store_company_name'=> $store_company_name,
                        'business'          => $business_img,
                        'contact'           => $nickname,
                        'mobile'            => $phone,
                        'store_start_time'  => $this->time,
                        'store_end_time'    => strtotime("next year"),
                        'range'             => $range,
                        'add_time'          => $this->time,
                        'add_ip'            => get_client_ip(),
                        'province'          => $province_id,
                        'city'              => $city_id,
                        'district'          => $area_id,
                        'grade_id'          => $grade_id
                    );


//                    if(is_array($upload)) $data['business'] = $upload['business'];
                    $user_info = M('Users')->where(['user_id'=>$this->user_id])->find();
                    if(!empty($user_info['store_id'])) $data['store_id'] = $user_info['store_id'];
                    if ($stock_model->add_store($data)) {
                        $this->success('申请成功！',U('apply/index'));
                    } else {
                        $this->error('申请入驻失败！');
                    }
//                } else {
//                    $this->error($upload);
//                }
            }
        } else {
            if ($this->user_info['user_name'] == '' or
                $this->user_info['user_name'] == $this->user_info['openid'] and
                $this->user_info['openid'] != '')
            {
                $this->error('请先设置登录用户名和密码。', U('Profile/pass'));
            }
            if ($store_info) {
                if (!in_array($store_info['store_state'],[STORE_AUDIT,STORE_CANCEL])) {
                    $this->error('暂时不能修改信息');
                }
                $this->assign('store_info', $store_info);
            }
            if ($url = C('IMG_SERVER')) {
                $upload_server = get_server('IMG_SERVER', '/Uploader/index',
                    [
                        'module'=>'Wap','cat_id'=>3, 'is_size'=>1, '_ajax'=>1
                    ], 1);
                /*$upload_server = '//' . $url . U('img/Uploader/index',['module'=>'Wap','cat_id'=>3, 'is_size'=>1, '_ajax'=>1]);*/
            } else {
                $upload_server = U('user_face');
            }
            $this->assign('upload_url', $upload_server);
//            print_r($store_info);exit;
            $region = $stock_model->get_region_info(1);
            $this->assign('region_list', $region);
            $this->assign('grade_list',$stock_model->get_grade_list());
            $this->assign('user_head', array('title'=>'特约经销商入驻申请','backText'=>'会员'));
            $this->display('Agency:apply');
        }
    }
    //续费
    public function apply_renew(){

    }

} 