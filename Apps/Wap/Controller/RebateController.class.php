<?php

/**
 * 即牛 - KP券功能
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: RebateController.class.php 17156 2016-04-21 16:15:47Z keheng $
 */
namespace Wap\Controller;
use Wap\Model;
class RebateController extends WeixinController {
    const TROUBLE = 0.08;
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();
        $this->assign('trouble', self::TROUBLE * 100 . '%');
    }

    public function index(){
        $todata['id'] = I('get.id',0,'intval');
        $hkhp_info = $this->hkhp_api('get_kp.html?p=' . I('get.p',0,'intval'), $todata);
        if ($hkhp_info['error'] == 202) {
            $data = array(
                'appid'     =>  $this->api['appid'],
                'uid'       =>  $this->user_id,
                'ref'       =>  urlencode($this->cur_domain . U('Rebate/index')),
            );
            $data['sign'] = $this->get_Sign($todata);
            header('location:' . $hkhp_info['data'] . '&' . $this->Array2Str($data));
        } else if ($hkhp_info['error'] == 200) {
            $this->assign('user_head', array('title'=>'我的KP券','backText'=>'会员首页'));
            $data = $hkhp_info['data'];
            $this->assign('page', $this->replace_url($data['show_page'], 'id=' . $todata['id']));
            $this->assign('is_admin', $data['user_info']['is_admin']);
            $this->assign('user_integral', $data['user_info']['member_points']);
            $this->assign('kp_type', $data['kp_type_list']);
            $this->assign('kp_info', $data['kp_type_info']);
            $this->assign('kp_list', $data['kp_list']);
            $this->assign('hkhp_cash', $data['cash']);
            $this->assign('kp_empty','<tr class="bor"><td colspan="4" style="height:100px;color:#999;">暂无KP券</td></tr>');
            $url = array(
                'get_brok'  =>  U('Rebate/get_brok') . '?id=' . $data['kp_type_info']['id'],
                'set_brok'  =>  U('Rebate/set_brok') . '?id=' . $data['kp_type_info']['id'],
                'set_cash'  =>  U('Rebate/set_cash'),
                'kp_covert'  =>  U('Rebate/kp_covert'),
            );
            $this->assign('url', $url);
            $this->assign('six_brok', $data['six_brok']);
            $this->assign('user_info', $this->user_info);
            $this->display();
        } else {
            $this->logger('API调用出错GET_KP：' . print_r($hkhp_info,1));
            if ($hkhp_info['msg']) {
                $this->error($hkhp_info['msg']);
            } else {
                $this->error('系统升级中,请稍候再试！');
            }
        }
        //$this->debug($hkhp_info,1);
    }


    /**
     * 领取KP劵
     */
    public function getkp(){
        $data['id'] = I('get.id',0,'intval');
        $hkhp_info = $this->hkhp_api('get_type.html',$data);

        $type_list = $hkhp_info['data']['kp_type_list'];
        foreach ($type_list as $k => $v) {
            $type_list[$k]['num'] = intval($this->user_info['consume_total'] / $v['value']);
        }

        $this->assign('type_list',$type_list);
        $this->assign('user_info', $this->user_info);
        $this->assign('user_head',['title'=>'KP劵领取']);
        $this->assign('tkd',['title'=>'KP劵领取']);
        $this->display();

    }

    public function kp2(){
        $data['id'] = I('post.id',0,'intval');
        $token = session('is_get');
        $token = intval($token);
        if ($token != 0) {
            $result = $this->result_ajax(203,'正在提交，请稍候再试！');
        } else {
            session('is_get', 1);
            $hkhp_info = $this->hkhp_api('get_type.html',$data);
            $type_list = $hkhp_info['data']['kp_type_list'];
            $type = [];
            foreach ($type_list as $k => $v) {
                $type[$v['id']] = $v;
            }
            if (!$type[$data['id']]) {
                $result = result_ajax(301,'该类型KP劵不存在！');
            } else {
                if ($type[$data['id']]['value'] > $this->user_info['consume_total']) {
                    $result = result_ajax(302,'领取该劵KP点不足！');
                } else {
                    $user = new Model\UsersModel();
                    $data['kp_num']     = $this->user_info['consume_total'];
                    $data['ip']         = get_client_ip();
                    $data['province']   = $this->user_info['province'];
                    $data['city']       = $this->user_info['city'];
                    $data['area']       = $this->user_info['district'];
                    $result = $this->hkhp_api('receive_kp.html',$data);
                    if ($result['error'] == 200) {
                        $user_account_id = $user->add_user_account(
                            ['user_id'=>$this->user_id],
                            -$type[$data['id']]['value'],
                            ['desc'=>'KP点兑换卷支出','type'=>PT_KP,'user_note'=>'','stage'=>ST_PAY,'is_paid'=>KP_CONVERED,'payment'=>'系统处理']
                        );
                        $user->set_user_account($user_account_id,PS_PAYED,[
                            'pay_code'      =>'system',
                            'payment_name'  =>'系统处理',
                            'pay_account'   =>$this->user_info['user_name'],
                            'pay_name'      =>'admin'
                        ]);
                        //$this->set_kpnum($this->user_info['user_id'], $type[$data['id']]['value']);
                        $user->set_user_kpnum(['user_id'=>$this->user_id],-$type[$data['id']]['value'],'cash','kp点兑换卷');
                        $result = result_ajax(200,'领取成功！','');
                    }
                }
            }
            session('is_get', null);
        }

        $this->ajaxReturn($result);
    }

    protected function set_kpnum($id,$num){
        return M('users')->where('user_id=' . $id)->setDec('consume_total', $num);
    }


    //取拥金信息
    public function get_brok(){
        $data['id'] = I('get.id',0,'intval');
        $hkhp_info = $this->hkhp_api('get_brok.html',$data);
        if ($hkhp_info['error'] == 200) {
            $hkhp_info['data']['brok'] = $hkhp_info['data']['brok']['brok1'];
        }
        exit(json_encode($hkhp_info));
    }

    //提取拥金
    public function set_brok(){
        //if ($this->user_id != '176') $this->ajaxReturn(['error'=>500,'msg'=>'系统出现故障，暂时关闭此功能，请在4月11日后再操作！']);
        $data['id'] = I('get.id',0,'intval');
        $data['trouble']    = self::TROUBLE;
        $data['up'] = I('get.up',0,'intval') > 0 ? 1 : 0;
        $hkhp_info = $this->hkhp_api('set_brok.html',$data);
        exit(json_encode($hkhp_info));
    }

    //提现
    public function set_cash(){
        $data['up']         = I('get.up',0,'intval') > 0 ? 1 : 0;
        $hkhp_info = $this->hkhp_api('set_cash.html',$data);
        if ($hkhp_info['error'] == 200) {
            $user_model = new Model\UsersModel();
            $data = $hkhp_info['data'];
            //if ($this->user_id != '176') $this->ajaxReturn(['error'=>500,'msg'=>'系统出现故障，暂时关闭此功能，请在4月11日后再操作！']);
            $id = $user_model->set_user_frozen(['user_id'=>$this->user_id], $data['cash_num'], 'hkhp', '汇客惠品转入SN:' . $data['cash_sn'] . ' 转出用户ID:' . $data['user_id'] . ' 金额:' . $data['cash_num']);
            $user_account_id = $user_model->add_user_account(
                ['user_id'=>$this->user_id],
                $data['cash_num'],
                ['desc'=>'KP券返现转入','type'=>PT_MONEY,'user_note'=>'转入SN：' . $data['cash_sn'],'stage'=>ST_RECHARGE]
            );
            $log_data['id']         = $data['cash_id'];
            $log_data['result_id']  = $id;
            $res = $this->hkhp_api('set_cash_log.html',$log_data);
            $this->logger('提现日志返回set_cash_log：'.print_r($res,1));
            if ($res['error'] != 200) {
                $result = $res;
            } else {
                $user_model->set_user_frozen(['user_id'=>$this->user_id], -$data['cash_num'], 'hkhp', '汇客惠品转入SN:' . $data['cash_sn'] . ' 转出用户ID:' . $data['user_id'] . ' 金额:' . $data['cash_num']);
                $user_model->set_user_money(['user_id'=>$this->user_id], $data['cash_num'], 'hkhp', '汇客惠品转入SN:' . $data['cash_sn'] . ' 转出用户ID:' . $data['user_id'] . ' 金额:' . $data['cash_num']);

                $user_model->set_user_account(
                    $user_account_id,
                    PS_PAYED,
                    ['pay_code'=>'hkhp','payment_name'=>'汇客惠品','pay_account'=>$data['user_id'],'pay_name'=>$data['user_name']]
                );

                $result['error'] = 202;
                $result['msg']   = '提现成功！是否需要需要查看您的余额！';
                $result['data']['url'] = U('Wallet/index');
            }
        } else {
            $result = $hkhp_info;
        }
        $this->ajaxReturn($result);
    }


    /**
     * KP 兑换微币
     */
    public function kp_covert(){
        $data['id'] = I('post.id', 0,'intval');
        $hkhp_info = $this->hkhp_api('kp_covert.html?debug=keheng',$data);
        if ($hkhp_info['error'] == 200) {
            $data = $hkhp_info['data'];
            $result['error'] = 200;
            $result['msg']   = $data['msg']  . '已兑换' . $data['kp_balance'] . '加入用户'.$this->_CFG['integral_name'].'！';
            //$result['data']['url'] = U('Wallet/index');
            $result['data']['kp_balance'] = $data['kp_balance'];
        } else {
            $result = $hkhp_info;
        }
        $this->ajaxReturn($result);
    }

    /**
     * 激活KP
     */
    public function active(){
        $step = I('get.step',0,'intval');
        $step = in_array($step,array(0,1,2)) ? $step : 0;
        if ($step == 1) {
            $hkhp_info = $this->hkhp_api('active.html',array('step'=>0));
            if ($hkhp_info['error'] == 200) {
                $data = array(
                    'kp_type'   => I('post.kp_type',0,'intval'),
                    'number'    => I('post.number',1,'intval'),
                    'province'  => I('post.province',0,'intval'),
                    'city'      => I('post.city',0,'intval'),
                    'area'      => I('post.area',0,'intval'),
                    'shop'      => I('post.shop'),
                );
                $data['number'] = ($data['number'] > 100 || $data['number'] < 0) ? 100 : $data['number'];
                if ($data['kp_type'] <= 0) {
                    $this->error('请选择KP劵类型！');
                } else if ($data['number'] <= 0) {
                    $this->error('请输入您将激活KP劵的数量！');
                } else if ($data['province'] <= 0 || $data['city'] <= 0) {
                    $this->error('请选择消费区域！');
                } else if ($data['shop'] == '') {
                    $this->error('请输入您消费的商家！');
                } else {
                    $type_list = $hkhp_info['data']['type_list'];
                    $ids = array();
                    $t = array();
                    foreach ($type_list as $v) {
                        $ids[] = $v['id'];
                        $t[$v['id']] = $v;
                    }
                    if (!in_array($data['kp_type'],$ids)) {
                        $this->error('KP劵类型无效！');
                    } else {
                        $data['kp_name'] = $t[$data['kp_type']]['nickname'];
                        session('active_data', $data);
                        $this->assign('active_data', $data);
                        $this->assign('user_head', array('title'=>'激活KP劵 - Step2','backUrl'=>U('User/index'),'backText'=>'首页'));
                        $this->display('step2');
                    }
                }
            } else {
                $this->error($hkhp_info['msg']);
            }
        } else if($step == 2) {
            $data = session('active_data');
            if (!$data) {
                $result['error'] = 1;
                $result['msg'] = '没有KP劵激活信息！';
                $result['data'] = U();
                //header('location:' . U());
            } else {
                $data['kp_num'] = I('post.numb');
                $data['kp_pass'] = I('post.pass');
                if (strlen($data['kp_num']) != 10) {
                    $result['error'] = 1;
                    $result['msg'] = 'KP劵号码输入错误！';
                } else if ($data['kp_pass'] == '') {
                    $result['error'] = 1;
                    $result['msg'] = '密码输入错误！';
                } else {
                    $data['number'] = $data['number'] > 0 ? $data['number'] - 1 : $data['number'];
                    $kp_data = array(
                        'kp_type'   => $data['kp_type'],
                        'province'  => $data['province'],
                        'city'      => $data['city'],
                        'area'      => $data['area'],
                        'shop'      => $data['shop'],
                        'kp_num'    => $data['kp_num'],
                        'kp_pass'   => $data['kp_pass'],
                        'step'      => $step
                    );
                    $res = $this->hkhp_api('active.html', $kp_data);
                    if ($res['error'] == 200) {
                        if ($data['number'] <= 0) {
                            session('active_data',null);
                        } else {
                            session('active_data.number', $data['number']);
                        }
                        $result['error'] = 0;
                        $result['msg'] = $res['msg'];
                        $result['data']['interval'] = $data['number'];
                        $result['data']['url'] = U();
                    } else {
                        $result = $res;
                    }
                }
            }
            exit(json_encode($result));
        } else {
            $hkhp_info = $this->hkhp_api('active.html',array('step'=>$step));
            if ($hkhp_info['error'] == 200) {
                session('active_data',null);
                $type_list = $hkhp_info['data']['type_list'];
                $this->assign('type_list', $type_list);
                $this->assign('user_head', array('title'=>'激活KP劵 - Step1','backUrl'=>U('User/index'),'backText'=>'首页'));
                $this->display('step1');
            } else {
                $this->error($hkhp_info['msg']);
            }
        }
    }

    /**
     * 执行KP劵查询
     */
    public function query_kp(){
        $data = [
            'type'		    => I('request.type',0,'intval'),
            'query'		    => I('request.query','','stripslashes'),
            'star_number'	=> I('request.star_number'),
            'end_number'	=> I('request.end_number'),
            'star_date'		=> I('request.star_date'),
            'end_date'		=> I('request.end_date'),
            'mobile'		=> I('request.mobile'),
            'cnname'		=> I('request.cnname','','stripslashes'),
        ];
        $hkhp_info = $this->hkhp_api('query.html?p=' . I('get.p',0,'intval'),$data);
        $this->assign('user_head',['title'=>'管理KP查询']);
        $this->assign('empty_kp','<tr><td colspan="6" style="height:5rem;">无数据！</td></tr>');
        if ($hkhp_info['error'] == 200) {
            $this->assign('data', $data);

            //print_r($hkhp_info);
            $res_data = $hkhp_info['data'];
            $this->assign('kp_list', $res_data['kp_list']['list']);
            $page['show_page'] = $this->replace_url($res_data['kp_list']['show_page'], $this->Array2Str($data));
            $page['info'] = "共 {$res_data['kp_list']['recordcount']} 条记录 共 {$res_data['kp_list']['pagecount']} 页 每页 {$res_data['kp_list']['pagesize']} 条";//print_r($data);
            if ($res_data['postdata']['query'] == 'US') {
                $page['info'] .= '<br /> 余额：￥' . $res_data['user_money'];
                $page['info'] .= ' 冻结：￥' . $res_data['free_money'];
                $page['info'] .= ' 未提：￥' . $res_data['kp_list']['brok'][0];
                $page['info'] .= ' 已提：￥' . $res_data['kp_list']['brok'][1];
                $page['info'] .= $this->_CFG['integral_name'].'：' . $res_data['member_points'];
            }
            //print_r($data);
            $this->assign('page', $page);
        } else {
            $this->logger($hkhp_info);
            if ($hkhp_info['msg']) {
                $this->error($hkhp_info['msg']);
            } else {
                $this->error('系统升级中,请稍候再试！');
            }
        }
        $this->assign('kp_type', $hkhp_info['data']['kp_type']);
        $this->display('Rebate:query');
    }

    private function replace_url($str,$thor = ''){
        $patten = '/(\/kpapi)?\/api\/(get_kp|query)\/p\/(\d+)\.html/i';
        $page = preg_replace($patten,U() . '?p=$3' . ($thor ? '&' . $thor : ''), $str);
        return str_replace('//','/', $page);
    }
    /**
     * 设置KP劵状态（KP劵作废或启用功能）
     */
    public function setKp(){
        $data['id'] = I('get.id',0,'intval');
        $hkhp_info = $this->hkhp_api('setkp.html' ,$data);
        $this->ajaxReturn($hkhp_info);
    }

    private function active_step1(){

    }

    private function active_step2(){

    }

} 