<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
#处理返佣事项
 function brokerage($memberid = null, $orderid = null) {
    if (empty($memberid) || empty($orderid)) {
        return;
    }
	//echo "brokerage";exit;
    #查询当前订单是否已经返过佣金
    $relevel = M('Userlevel')->field('orderid,openid')->where(array('orderid' => $orderid))->find();
    if (!$relevel) {
        #判断当前购买者是否有父级
        $user = M('Member')->field('inviter,store_id,member_name')->where(array("member_id" => $memberid))->find();
        if (!empty($user) && !empty($user['inviter'])) {
            #查询出来订单
            $where = array('order_id' => $orderid);
            $order = M('OrderGoods')->field('goods_id,goods_num,goods_price,goods_name')->where($where)->select();
            if (!empty($order)) {
                #循环同一订单 单个或多个商品  !!!!目前只支持数量为1件多件存在问题,待处理
                foreach ($order as $k => $v) {
                    if($v['goods_price'] > 0){
                        $product = M('Level')->field('level1,level2,level3')->where(array('goods_id'=>$v['goods_id']))->find();
                        #分配每层用户的佣金总额
                        #查询第一层用户
                        $user_one = leveluser($user['inviter']);
                        if (!empty($user_one)) {
                            $userone = $v['goods_price'] * $product['level1'] / 100 * $v['goods_num'];                          
                            #添加一级佣金
                            $data = array('orderid' => $orderid,
                                'shopid' => $v['goods_id'],
                                'shopname' => $v['goods_name'],
                                'openid' => $user_one['openid'],
                                'token' => $user_one['store_id'],
                                'uid' => $user_one['member_id'],
                                'member_name' => $user_one['member_name'],
                                'level' => 1,
                                'sum' => $v['goods_num'], #数量待完善
                                'money' => $userone,
                                'summoney' => $v['goods_price']*$v['goods_num'],
                                'addtime' => time()
                            );
                            $res = M('Userlevel')->add($data);
                            if (!empty($user_one['inviter'])) {
                                #查询第二层用户
                                $user_two = leveluser($user_one['inviter']);
                                if (!empty($user_two)) {
                                    $usertwo = $v['goods_price'] * $product['level2'] / 100 * $v['goods_num'];                          
                                    #添加二级佣金
                                    $data2 = array('orderid' => $orderid,
                                        'shopid' => $v['goods_id'],
                                        'shopname' => $v['goods_name'],
                                        'openid' => $user_two['openid'],
                                        'token' => $user_two['store_id'],
                                        'uid' => $user_two['member_id'],
                                        'member_name' => $user_two['member_name'],
                                        'level' => 2,
                                        'money' => $usertwo,
                                        'sum' => $v['goods_num'], #数量待完善
                                        'summoney' => $v['goods_price']*$v['goods_num'],
                                        'addtime' => time()
                                    );
                                   $res2 =  M('Userlevel')->add($data2);
                                    #判断当前二级是否和当前用户互换推荐号 即非法 修改得来的 需终止

                                    if ($user['member_id'] == $user_two['inviter']) {
                                        return;
                                    }
                                    if (!empty($user_two['inviter'])) {
                                        $user_three = leveluser($user_two['inviter']);
                                        if (!empty($user_three)) {
                                            $userthree = $v['goods_price'] * $product['level3'] / 100 * $v['goods_num'];
                                           
                                            #添加三级佣金
                                            $data3 = array('orderid' => $orderid,
                                                'shopid' => $v['goods_id'],
                                                'shopname' => $v['goods_name'],
                                                'openid' => $user_three['openid'],
                                                'token' => $user_three['store_id'],
                                                'uid' => $user_three['member_id'],
                                                'member_name' => $user_three['member_name'],
                                                'level' => 3,
                                                'money' => $userthree,
                                                'sum' => $v['goods_num'], #数量待完善
                                                'summoney' => $v['goods_price']*$v['goods_num'],
                                                'addtime' => time()
                                            );
                                            $res =  M('Userlevel')->add($data3);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

#查询用户层级

 function leveluser($uid = false) {
    if ($uid) {
        $userone =  M('Member')->field('inviter,member_id,store_id,openid,member_name')->where(array('member_id'=>$uid))->find();
        if ($userone) {
            $us = $userone;
        } else {
            $us = FALSE;
        }
        return $us;
    }
}

#待审核牛币

function niubi($openid, $token, $state = 0, $level = null) {
    $where['openid'] = $openid;
    $where['token'] = $token;
    $where['state'] = $state;
    if ($level) {
        $where['level'] = $level;
    }
    $money = M('Userlevel')->where($where)->sum('money');

    //echo $status;
    //echo M('Userlevel')->_sql();
    return $money ? $money : '0.00';
}

////提现待审核
//function cash($openid, $token, $state = 0, $paystate = 0){
//   $where['openid'] = $openid;
//    $where['token'] = $token;
//    $where['state'] = $state; 
//    $where['paystate'] = $paystate;
//    $info = M('PredepositCash')->where($where)->sum('price');
//    return $info ? $info: '0.00';
//}
//提现待审核
function cash($member_id, $store_id, $state = 0) {
    $where['pdc_member_id'] = $member_id;
    $where['store_id'] = $store_id;
    $where['pdc_payment_state'] = $state;
    $info = M('PdCash')->where($where)->sum('pdc_amount');
    //echo M('PdCash')->_sql();
    return $info ? $info : '0.00';
}

#查询购买金额

function sumorder($member_id, $token) {
    $where['buyer_id'] = $member_id;
    $where['store_id'] = touid($token, '-');
    $where['order_state'] = array('egt', 20);
    $mo = M('Order')->where($where)->sum('order_amount');

    return $mo ? $mo : 0;
}

function getcartnum($wecha_id, $wtoken) {
    $num = S('cartnum_' . $wecha_id);
    if (empty($num)) {
        $cart = M('Cart');
        $where = array('wecha_id' => $wecha_id, 'token' => $wtoken);
        $num = $cart->where($where)->count('distinct(goods_id)');
        empty($num) && $num = 0;
        S('cartnum_' . $wecha_id, $num, 604800);
    }
    return $num;
}

/*
 * 获取用户昵称
 * $orderid  字符串
 */

function getnickname($member_id) {
    $nickname = M('Member')->where(array('member_id' => $member_id))->getField('member_name');
    return $nickname;
}

/*
 * 会员头像
 * $orderid  字符串
 */

function getheadpic($member_id) {
    $headpic = M('Member')->where(array('member_id' => $member_id))->getField('member_avatar');
    return $headpic;
}

/*
 * 同时剔除两个数组中相同的数字元素(存数字数组)
 * @param $data  array()
 * @param $amxnum int 数组最大值 
 */

function ex_array($data,$maxnum) {
    for ($i = 1; $i < $maxnum; $i++) {
        if (!in_array($i, $data)) {
            $info[] = $i;
        }
    }
    return $info;
}


	function getMbSpecialImageUrl($image){
		return C('WEBSITE').'data/upload/mobile/special/s0/'.$image;
	}

/*
 * 缓解新版和旧版交替换代存在的问题
 * 
 *   */
function pathpic($pic){
    $token = touid(I('get.token'), '-');
    if(strpos($pic, 'goodsimguploads') === FALSE){
        $urlpic = C('WEBSITE').'data/upload/shop/store/goods/'.$token.'/'.$pic;
    }else{
        $urlpic = C('WEBSITE').'Uploads/'.$pic;
    }
    return $urlpic;
}