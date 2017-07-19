<?php

namespace Wap\Controller;
class MemberController extends WapController {
    //用户数据保存
    public $user_data = array();

    public function login() {
        $this->display();
    }

    public function register() {
        $this->display();
    }

    public function member() {
        $menu = M('MemberMenu');
        //$res = $menu->where(array('store_id' => touid(I('token'), '-'),'status'=>1))->order('sort asc')->select();
//        $menus = '';
//        foreach ($res as $v) {
//            if(preg_match ("/Member\/shop/i", $v['url'])){
//                $v['url'] = $v['url'].'/memberid/'.$this->memberInfo['member_id'];
//            }
//            $menus[] = $v;
//        }
        
        
        $info = M('Member')->field('member_id,member_avatar,member_name,available_predeposit,member_points')->where(array('member_id' => $this->memberInfo['member_id']))->find();
        $info['member_avatar'] = strpos($info['member_avatar'], 'http') === false ?__ROOT__ . '/data/upload/shop/avatar/avatar_' . $info['member_id'] . '.jpg': $info['member_avatar'];
        //$this->assign('menus', $res);
        $model_proxy_check = M('store_proxy_check');
        $item = $model_proxy_check->where(array('store_id' => $this->wtoken, 'openid' => $this->wecha_id))->find();
        $status = $item ? $item['status'] : 0;
        $this->assign('status', $status);
        $this->assign('memberInfo', $info);
        $this->display();
    }

    public function favorites() {
        $this->display();
    }

    public function voucherlist() {
        $this->display();
    }

    public function viewslist() {
        $this->display();
    }
    
    /**
     *  会员列表
     *  默认读取三级会员用户
     *  author：yc
     */
    public function userlist() {
		if(IS_POST){
            $msg = array('state' => 0);
            $state = M('Member')->where(array('member_id' => I('id')))->setField(array('is_vip' => 1));
            if($state){
                $msg = array('state' => 1);
            }
            $this->ajaxReturn($msg);
            exit;
        }
		
        $user_list = $this->select($this->memberInfo['member_id']);
        $this->assign('user_list', $user_list);
        $this->display();
    }
    
    
    /**
     *  修改代理商状态
     *  author:yc
     */
    public function proxystate() {
        $up_data = array();
        $json_array = array('state'=>0, 'msg'=>"修改失败");
        $up_data['proxy_state'] = 1;
        $state = M('member')->where(array("member_id"=>$this->memberInfo['member_id']))->save($up_data);
        if($state) {
            $json_array['state'] = 1;
            $json_array['msg']   = "修改成功";
        }
        echo json_encode($json_array);
        exit;
    }
    
    
    /**
     *  免费商品列表
     *  读取最新活动
     *  author：yc
     */
    public function goodslist() {
        $goods_list = array();
        $store_id = touid(I('get.token'), '-');
        $model_activity = M("proxy_activity");
        $model_check = M("store_proxy_check");
        $model_proxy = M("store_proxy");
        $res = $model_proxy->where(array('store_id'=>$store_id))->order('end_time desc')->find();
        // todo   商户购买商品确定这个  $res['id'];
        if($res){
            //判断人数是否满员, 判断活动时间是否符合
            $member_num = $model_check->where(array('active_id'=>$res['id']))->count();
            if($member_num < $res['type_value']){
                $now = time();
                if($now <= $res['end_time'] && $now >= $res['start_time']){
                    $condition = array();
                    $condition['store_id'] = $store_id;
                    $goods_list = $model_activity->where($condition)->select();
                }
            }
        }
        $this->assign('goods_list', $goods_list);
        $this->display();
    }

    public function addressOpera() {
        $this->display();
    }

    public function addressEdit() {
        $this->display();
    }

//    public function share() {
//        if (!file_exists('Uploads/userqrcode/' . I('openid') . '.jpg')) {
//            E('用户带参数的二维码生成失败!');
//        }
//        if (!file_exists('Uploads/userphoto/nick_' . I('openid') . '.jpg')) {
//            E('用户图像生成失败!');
//        }
//        $this->picurl = "/Uploads/userinviter/pic_" . I('openid') . ".jpg";
//        if (!file_exists($this->picurl)) {
//            //实例化图像类  thumb(500, 877,\Think\Image::IMAGE_THUMB_FIXED)->
//            $image = new \Think\Image();
//            $userpic = './Uploads/shareimg/' . $this->gtoken . '.jpg';
//            $img = !file_exists($userpic) ? './Public/Wap/img/my.jpg' : $userpic;
//            if (file_exists($userpic)) {
//                $image->open($img)->text('我是' . $this->nickname, './Public/static/font/MSYH.TTF', 20, '#85562e', array(160, 34))->water('./Uploads/userphoto/nick_' . I('openid') . '.jpg.81_81.jpg', array(50, 27))->water('./Uploads/userqrcode/' . I('openid') . '.jpg.196_196.jpg', array(159, 501))->save("./Uploads/userinviter/pic_" . I('openid') . ".jpg", null);
//            } else {
//                $image->open($img)->text('我是' . $this->nickname, './Public/static/font/MSYH.TTF', 24, '#85562e', array(160, 34))->text('我为' . $this->wxuser['wxname'] . '代言', './Public/static/font/MSYH.TTF', 18, '#ca8b53', array(160, 84))->water('./Uploads/userphoto/nick_' . I('openid') . '.jpg.81_81.jpg', array(50, 27))->water('./Uploads/userqrcode/' . I('openid') . '.jpg.196_196.jpg', array(159, 501))->save("./Uploads/userinviter/pic_" . I('openid') . ".jpg", null);
//            }
//        } else {
//            E('生成个人名片失败!');
//        }
//    }

    //余额充值
    public function cashcharge() {
//        $model = M('Member');
//        $store_id = touid(I('get.token'), '-');
//        $openid = $this->wecha_id;        
//        $mycash = $model->where(array('store_id'=>$store_id,'openid'=>$openid))->find();
        $this->assign('mycash', $this->memberInfo);
        $this->display();
    }

    //充值记录
    public function recharge() {
        $model = M('PdRecharge');
        $member = M('Member');
        $store_id = touid(I('get.token'), '-');
        $openid = $this->wecha_id;
        $member_id = $member->where(array('store_id' => $store_id, 'openid' => $openid))->getField('member_id');
        $data['pdr_member_id'] = $member_id;
        $data['store_id'] = $store_id;
        $data['pdr_payment_state'] = 1;
        $count = $model->where($data)->count();
        $Page = new \Think\Page($count, 5);
        $res = $model->where($data)->order('pdr_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $Page->setConfig('prev', '<div class="btn" data-ajax="false">上一页</div>');
        $Page->setConfig('next', '<div class="btn" data-ajax="false">下一页</div>');
        $Page->setConfig('theme', '%UP_PAGE% %DOWN_PAGE%');
        $show = $Page->show();
        $this->assign('page', $show);        
        $this->assign('res', $res);
        $this->display();
    }

    //我要提现
    public function setting() {

        $this->display();
    }

    /**
     * 生成编号
     * @return string
     */
    public function makeSn() {
        return mt_rand(10, 99)
                . sprintf('%010d', time() - 946656000)
                . sprintf('%03d', (float) microtime() * 1000)
                . sprintf('%03d', (int) $_SESSION['member_id'] % 1000);
    }

    //提现记录
    public function cashlists() {
        $model = M('PdCash');
        $member = M('Member');
        $openid = $this->wecha_id;
        $store_id = touid(I('get.token'), '-');
        if (IS_GET) {
            $data['pdc_payment_state'] = I('get.paystate');
            $data['state'] = I('get.state');
            $data['store_id'] = $store_id;
            $member_id = $member->where(array('openid' => $openid, 'store_id' => $store_id))->getField('member_id');
            $data['pdc_member_id'] = $member_id;
            $res = $model->where($data)->order('pdc_add_time desc')->select();
        }
        $this->assign('info', $data);
        $this->assign('res', $res);
        $this->display();
    }

    //提现需知
    public function tips() {
        $this->display();
    }

    //修改资料
    public function bindmobile() {
         $this->display();
    }

    //设置提现密码
    public function pwd() {
        $this->display();           
    }

    //我的佣金
    public function commission() {
        $member = M('Member');
        $model = M('Userlevel');
        $member_id = $this->memberInfo['member_id'];
        $store_id = $store_id = touid(I('get.token'), '-');
        $money = $member->where(array('member_id' => $member_id, 'store_id' => $store_id))->find();
        $op = $model->where(array('token' => $store_id, 'uid' => $member_id))->find();
        //$num = $model->where(array('token' => $store_id, 'uid' => $member_id, 'state' => 3))->sum('money');
        //$this->assign('num', $num);
        $this->assign('member_id', $member_id);
        $this->assign('openid', $money['openid']);
        $this->assign('op', $op['openid']);
        $this->assign('money', $money);
        $this->display();
    }

    //我的微店
    public function shop() {
       
        $this->display();
    }
    
    /**
     *  代理商栏目
     *  author：yc
     * 
     */
    public function agent() {
       
        $this->display();
    }

    //我的会员
    public function userlevel() {
        $this->display();
    }

    //已删除佣金
    public function deletecash() {
        $model = M('Userlevel');
        $data['uid'] = $this->memberInfo['member_id'];
        $data['token'] = touid(I('get.token'), '-');
        $data['state'] = 3;
        $count = $model->where($data)->count();
        $Page = new \Think\Page($count, 10);
        $list = $model->where($data)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $Page->setConfig('prev', '<div class="btn" data-ajax="false">上一页</div>');
        $Page->setConfig('next', '<div class="btn" data-ajax="false">下一页</div>');
        $Page->setConfig('theme', '%UP_PAGE% %DOWN_PAGE%');
        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->display();
    }

    //会员等级
    public function guest() {
        $uid = $this->wecha_id;
        $levelid = I('level', '', 'intval');
        $user = M('Member');
        $u = S('u' . $uid);
        if (!$u) {
            $u = $user->field('member_id')->where(array('openid' => $uid))->find();
        }

        #一级用户
        if ($levelid == 1) {
            $ulist = $user->field('member_id,member_name,member_avatar,member_time')->where(array('inviter' => $u['member_id']))->select();
            foreach ($ulist as $k => $v) {
                $id[] = $v['member_id'];
            }

            $this->ztitle = '一';
            $this->lists = $ulist;
            #二级用户
        } else if ($levelid == 2) {
            $ulist1 = $user->field('member_id')->where(array('inviter' => $u['member_id']))->select();

            if (!empty($ulist1)) {
                foreach ($ulist1 as $k => $v) {
                    $id[] = $v['member_id'];
                }
                $ulist = $user->field('member_id,member_name,member_avatar,member_time')->where(array('inviter' => array('in', implode(',', $id))))->select();
            }

            $this->ztitle = '二';
            $this->lists = empty($ulist) ? '' : $ulist;
            #三级用户
        } elseif ($levelid == 3) {
            #第一层
            $ulist1 = $user->field('member_id')->where(array('inviter' => $u['member_id']))->select();
            if (!empty($ulist1)) {
                foreach ($ulist1 as $k => $v) {
                    $id[] = $v['member_id'];
                }
                //              
                $ulist2 = $user->field('member_id')->where(array('inviter' => array('in', implode(',', $id))))->select();
                if (!empty($ulist2)) {
                    foreach ($ulist2 as $k => $v2) {
                        $id2[] = $v2['member_id'];
                    }
                    $ulist = $user->field('member_id,member_name,member_avatar,member_time')->where(array('inviter' => array('in', implode(',', $id2))))->select();
                }
            }
            $this->ztitle = '三';
            $this->lists = $ulist;
        }
        $this->display();
    }
    
        /**
         *  递归查询用户
         *  author:yc
         *  param $userid 用户id
         *  param $deep 默认展示层级
         *  内存操作
         */
    	private function select($userid, $deep = 3)
	{
            $childs = $this->get_child($userid);
            $this->user_data = array_merge($this->user_data, $childs);
            if($deep > 1){
                foreach ($childs as $k=>$r)
                {
                    $this->select($k, $deep-1);
                }
            }
            return $this->user_data;
	}
        
        /**
         *  单个用户查询
         *  author:yc
         *  todo   待添加索引机制  
         *  虽然查询次数增多但是还是速度由于没有索引的查询
         */
    	private function get_child($id)
	{
            $return = array();
            if (is_numeric($id)) $id = intval($id);
            $data = M("member")->where(array("inviter"=>$id))->select();
            if($data){
                foreach($data as $k=>$v)
                {
                    $return[$v['member_id']] = $v;
                }
            }
            return $return;
	}
        

}
