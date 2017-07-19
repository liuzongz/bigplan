<?php

/**
 * 即牛 - 我的钱包
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: Walletcontroller.class.php 17156 2016-04-19 16:27:47Z keheng $
*/

namespace Passport\Controller;
use Passport\Model;
class WalletController extends WechatController {
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();

    }

    /**
     * 用户个人中心
     */
    public function index(){
        $user = new Model\UsersModel();
        /*$res = $user->get_cash_log($this->user_id, 5);
        $this->assign('cash_log', $res);*/
        $res  = $user->get_hongbao($this->user_id);
        $prev_day = strtotime(date('Y-m-d', time()- 60 * 60 * 24));
        $today = strtotime(date('Y-m-d', time()));
        foreach ($res['list'] as &$v) {
            if ($v['add_time'] > $prev_day) {       //今天和昨天
                if ($v['add_time'] > $today) {
                    $v['month'] = '今天';
                } else {
                    $v['month'] = '昨天';
                }
                $v['day'] = date('H:i', $v['add_time']);
            } else {        //昨天之前
                $v['month'] = $this->format_month(date('m', $v['add_time']));
                $v['day'] = date('m-d', $v['add_time']);
            }
        }
        $this->assign('hongbao', $res);
        $this->assign('pageStr',$res['show']);
        $this->assign('hongbao_empty','<tr class="bor"><td colspan="3" style="height:100px;line-height:100px;">暂时还没有红包，赶紧分享赚取！</td></tr> ');
        $this->assign('user_head', array('title'=>'我的账户','backUrl'=>U('User/index'),'backText'=>'会员首页'));
        $this->assign('tkd', array('title'=>'我的账户'));
        $this->assign('user_info', $this->user_info);
        $this->display();

    }

    /**
     * 我的账户(暂时在wap模块 后续可能会迁移过来)
     */
    public function dynamic(){
        $user = new Model\UsersModel();
        $res = $user->get_user_account($this->user_id, 4);
        $user_info = $user->get_userinfo(['user_id'=>$this->user_id]);
        $time = time();
        $prev_day = strtotime(date('Y-m-d', $time - 60 * 60 * 24));
        $today = strtotime(date('Y-m-d', $time));
        foreach ($res['list'] as &$v) {
            if ($v['add_time'] > $prev_day) {       //今天和昨天
                if ($v['add_time'] > $today) {
                    $v['month'] = '今天';
                } else {
                    $v['month'] = '昨天';
                }
                $v['day'] = date('H:i', $v['add_time']);
            } else {        //昨天之前
                $v['month'] = $this->format_month(date('m', $v['add_time']));
                $v['day'] = date('m-d', $v['add_time']);
            }
            $v['type_icon'] = $user->get_account_icon($v['payent']);
            if ($v['process_type'] == 1) {      //提现
                $v['handle'] = array(
                    array('url'=>U('uncash') . '?id=' . $v['id'],'text'=>'取消','confirm'=>'确定要取消吗？'),
                );//'<a href="' . U('uncash') . '?id=' . $v['id'] . '" onclick="return confirm(\'确定要取消吗？\')">取消</a>';
            } else {            //充值
                $v['handle'] = array(
                    array('url'=>U('uncash') . '?id=' . $v['id'],'text'=>'取消','confirm'=>'确定要取消吗？'),
                    array('url'=>U('uncash') . '?id=' . $v['id'],'text'=>'取消','confirm'=>'确定要取消吗？'),
                );
            }
            $v['paid_state'] = $this->get_cash_state($v['is_paid']);
        }
        $this->assign('cash_log', $res);/*print_r($res);*/
        $this->assign('cash_empty','<tr class="bor"><td colspan="3" style="height:100px;line-height:100px;">暂时还没有余额动态！</td></tr> ');
        $this->assign('user_head', array('title'=>'我的钱包','backUrl'=>U('User/index'),'backText'=>'会员首页'));
        $this->assign('user_info', $user_info);
        $this->display();
    }

    /**
     * 我的kp劵 已弃用
     */
    public function kp(){
        $user = new Model\UsersModel();
        $res = $user->get_user_kp($this->user_id, 10);
        $time = time();
        $prev_day = strtotime(date('Y-m-d', $time - 60 * 60 * 24));
        $today = strtotime(date('Y-m-d', $time));
        foreach ($res['list'] as &$v) {
            if ($v['add_time'] > $prev_day) {       //今天和昨天
                if ($v['add_time'] > $today) {
                    $v['month'] = '今天';
                } else {
                    $v['month'] = '昨天';
                }
                $v['day'] = date('H:i', $v['add_time']);
            } else {        //昨天之前
                $v['month'] = $this->format_month(date('m', $v['add_time']));
                $v['day'] = date('m-d', $v['add_time']);
            }
            $v['type_icon'] = $user->get_account_icon($v['payent']);
            $v['paid_state'] = $this->get_cash_state($v['is_paid']);
        }
        $this->assign('kp', $res);
        $this->assign('cash_log', $res);
        $this->assign('cash_empty','<tr class="bor"><td colspan="3" style="height:100px;line-height:100px;">暂时还没有kp点动态！</td></tr> ');
        $this->assign('user_head', array('title'=>'我的钱包','backUrl'=>U('User/index'),'backText'=>'会员首页'));
        $this->assign('user_info', $this->user_info);
        $this->display();
    }

    public function uncash(){

    }

    /**
     * 订单状态
     * @param $id
     * @return array
     */
    private function get_cash_state($id){
        switch ($id) {
            case PS_UNPAYED :    $result = ['txt'=>'待支付','col'=>'#ff9191'];break;
            case PS_PAYING :    $result = ['txt'=>'处理中','col'=>'#05ff1d'];break;
            case PS_PAYED :    $result = ['txt'=>'已完成','col'=>'#05b5ff'];break;
            //case PS_PAYED :    $result = ['txt'=>'支付成功','col'=>'#05b5ff'];break;
            case PS_FAILED :    $result = ['txt'=>'支付失败','col'=>'#ff0575'];break;
            default:$result = ['txt'=>'未知状态','col'=>'#4d4d4d'];
        }
        return $result;
    }

    /**
     * 用户提现(暂时在wap模块 后续可能会迁移过来)
     */
    public function cash(){
        $user = new Model\UsersModel();
        $cash_account = $user->get_cash_account($this->user_id);
        $min_cash = 100;
        if (!$cash_account) {
            $this->error('您还没有添加提现帐号，请先添加！', U('cashaccount') . '?ref=' . AesEnCrypt(U()));
        } else {
            if (IS_POST) {
                //$this->error('提现功能出现故障，暂时停止提现，请于4月9日后再试！');die();
                $cash_id = I('post.cash_id', 0, 'intval');
                $cash_num = I('post.cash_num', 0, 'intval');
                $user_note = I('post.user_note');
                if (!$this->check_form_token()) {
                    $result = $this->result_ajax(301, '重复提交失败，请在页面进行提交！');
                } elseif ($cash_num < $min_cash) {
                    $result = $this->result_ajax(301, '提现错误，提现金额不得小于' . $min_cash . '！');
                } elseif ($cash_id <= 0 or $cash_id != $cash_account['id']) {
                    $result = $this->result_ajax(301, '提现错误，提现帐号不存在！');
                } elseif (!is_numeric($cash_num) or $cash_num <= 0) {
                    $result = $this->result_ajax(301, '提现金额输入错误！');
                } elseif ($cash_num > $this->user_info['user_money']) {
                    $result = $this->result_ajax(301, '提现金额不能大于帐户余额！');
                } else {
                    //设置用户提现金额到冻结金额中
                    record($this->user_id . '');
                    $user->set_user_money(['user_id'=>$this->user_id],-$cash_num,'cash','会员申请提现减少预存款金额');
                    $user->set_user_frozen(['user_id'=>$this->user_id],$cash_num,'cash','会员申请提现增加冻结预存款金额');
                    $time = $this->time;
                    //写入提现信息
                    $cash_data = array(
                        'user_id'       =>  $this->user_id,
                        'amount'       =>  '-' . $cash_num,
                        'add_time'      =>  $time,
                        'user_note'     =>  $user_note,
                        'process_type'  =>  1,
                        'payment'       =>  '支付宝',
                        'pay_code'      =>  $cash_account['pay_type'],
                        'pay_name'      =>  $cash_account['pay_username'],
                        'pay_account'   =>  $cash_account['pay_account'],
                        'desc'          => '用户提现',
                        'stage'         =>  ST_CASH,
                        'cash_sn'       => create_sn(PREFIX_ORDER),
                        'admin_note'    => ''
                    );//print_r($cash_data);exit;
                    M('UserAccount')->add($cash_data);
                    //$user_model = new Model\UsersModel();
                    //$user_model->add_user_account($this->user_id,'-' . $cash_num,[]);
                    $result = $this->result_ajax(200,'提现申请成功，请等待审核！', U('Wallet/dynamic'));
                    //$this->success('提现申请成功，请等待审核！', U('Wallet/dynamic'));
                }
                $this->ajaxReturn($result);
            } else {
                if ($this->is_phone($cash_account['pay_account'])) {
                    $hidden_id = 4;
                } else if ($this->is_email($cash_account['pay_account'])) {
                    $hidden_id = 2;
                } else {
                    $hidden_id = 1;
                }
                $cash_account['pay_account'] = $this->set_hidden_str($cash_account['pay_account'],$hidden_id);
                $cash_account['pay_username'] = $this->set_hidden_str($cash_account['pay_username'],5);
                $this->assign('cash_account', $cash_account);
                $this->assign('user_info', $this->user_info);
                $this->assign('user_head', array('title'=>'钱包提现','backUrl'=>U('User/index'),'backText'=>'会员首页'));
                $this->set_form_token();
                $this->display();
            }

        }
    }

    /**
     * 填写提现账户记录
     */
    public function cashaccount(){
        $user = new Model\UsersModel();
        $cash_account = $user->get_cash_account($this->user_id);
        if (IS_POST) {
            $mobile = I('post.mobile');
            $truename = I('post.truename');
            $cash_type = I('post.cash_type');
            $account = I('post.account');
            $ref = AesDeCrypt(I('post.ref'));
            if (!$this->is_phone($mobile)) {
                $this->error('请输入正确的手机号码');
            } else if ($truename == '') {
                $this->error('请输入真实姓名！');
            } else if ($cash_type == '') {
                $this->error('请选择帐户类型！');
            } else if (!$this->is_phone($account) and !$this->is_email($account)) {
                $this->error('帐号必须是手机号码或邮箱' . $account);
            } else {
                $data = array(
                    'user_id'       =>  $this->user_id,
                    'pay_username'  =>  $truename,
                    'pay_account'   =>  $account,
                    'pay_area'      =>  '',
                    'pay_cnname'    =>  '支付宝（中国）网络技术有限公司',
                    'pay_type'      =>  $cash_type,
                    'pay_name'      =>  '支付宝'
                );
                $ref = $ref == '' ? U('cash') : $ref;
                if ($cash_account) {
                    if (M('CashAccount')->where('id=' . $cash_account['id'])->save($data)) {
                        $this->success('添加成功！',$ref);
                    } else {
                        $this->error('添加失败！',$ref);
                    }
                } else {
                    if (M('CashAccount')->add($data)) {
                        $this->success('添加成功！',$ref);
                    } else {
                        $this->error('添加失败！',$ref);
                    }
                }
            }
        } else {
            $ref = AesDeCrypt(I('get.ref'));
            $this->assign('ref', AesEnCrypt($ref));
            $this->assign('account', $cash_account);
            $this->assign('user_head', array('title'=>'提现帐户设置','backUrl'=>U('User/index'),'backText'=>'会员首页'));
            $this->assign('user_info', $this->user_info);
            $this->display();
        }

    }
} 