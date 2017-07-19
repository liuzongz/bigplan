<?php

namespace Wap\Controller;

use Wap\Model;

class IndexController extends WapController {
    public function index() {

        $this->active_index();          //有活动则跳转活动首页
        //print_r($this->get_store());
        //$this->redirect('Brand/index');
        cookie('last_page',U('Index/index'));
        $this->redirect('index0106');
    } //

//    public function test_login(){
//        $user = 15072370026;
//        $pass = 'asdasd';
//        $res = D('users')->get_userinfo('user_name="' . $user . '" or email="' . $user . '" or mobile_phone="' . $user . '"','user_id,openid,user_name,nickname,password,last_login,last_time,last_ip,visit_count,email');
//        if ($res) {
//            if (/*$res['password'] != md5($pass)*/false) {
//                $this->error('用户或密码输入错误，请重新输入！NO.1');
//            } else {
//                $time = time();
//                $data = array(
//                    'visit_count'       => $res['visit_count'] + 1,
//                    'last_login'         => $time,
//                    'last_ip'           => get_client_ip(),
//                );
//                M('users')->where('user_id=' . $res['user_id'])->save($data);
//                $user_info = array(
//                    'user_id'           =>  $res['user_id'],
//                    'user_name'         =>  $res['user_name'],
//                    'nickname'          =>  $res['nickname'],
//                    'openid'            =>  $res['openid'],
//                    'email'             =>  $res['email'],
//                    'add_time'          =>  $time
//                );
//                session('login_info', $user_info);
//                $this->set_login_cookie($user_info);/*2016/9/14*/
//                header('location:' . $this->back_url);
//                //$this->success('登录成功！',$this->back_url);
//            }
//        } else {
//            $this->error('用户不存在');
//        }
//    }
    public function ajax_get_store(){
        $goods = new Model\GoodsModel();
        $result = array('error'=>0,'message'=>"",'contents'=>array());
        //ob_end_clean();
        $res = $goods->get_store('s.store_id,s.store_name,s.store_avatar,s.store_label');
        if ($res['list']) {
            $result['error'] = 0;
            $result['message'] = '获取成功！';
            $result['contents'] = $res;
        } else {
            $result['error'] = 1;
            $result['message'] = '获取失败！';
        }
        //ob_clean();
        $this->ajaxReturn($result);
    }

    public function index0809(){
        $this->set_dealer_status(0);
        $goods_model = new Model\GoodsModel();
        $cate_list = $goods_model->cat_list_all();
        $cate_list = $this->get_array_tree($cate_list,'cat_id','parent_id');
        foreach ($cate_list as &$item) {
            $ids = $this->get_array_value($item,'cat_id');
            $item['goods_list'] = $goods_model->goods_list($ids,4);

        }//print_r($cate_list);
        $this->assign('cur_page','index');
        $this->assign('goods_btn','点击购买');
        $this->assign('cate_list', $cate_list);
        $this->assign('godos_empty','<div  style="height:5rem;overflow:hidden;text-align:center;line-height:5rem;font-size:.6rem;">该分类下暂无产品！</div>');
        $this->assign('tkd',['title'=>$this->_CFG['shop_name'] . '商城首页','keywords'=>'','discription'=>'']);
        $this->display('Index:index0809');
    }

    public function index1124(){
        $this->set_dealer_status(0);
        $goods_model = new Model\GoodsModel();
        $cate_list = $goods_model->cat_list_all();
        $cate_list = $this->get_array_tree($cate_list,'cat_id','parent_id');
        //print_r($cate_list);
        foreach ($cate_list as &$item) {
            $ids = $this->get_array_value($item,'cat_id');
            $item['goods_list'] = $goods_model->goods_list($ids,4);
        }
        //print_r($cate_list);
        $this->assign('cate_list', $cate_list);
        $this->assign('cur_page','index');
        //$this->assign('goods_btn','点击购买');

        $this->get_active_goods();
        $this->assign('godos_empty','<div  style="height:5rem;overflow:hidden;text-align:center;line-height:5rem;font-size:.6rem;">该分类下暂无产品！</div>');
        $this->assign('tkd',['title'=>$this->_CFG['shop_name'] . '商城首页','keywords'=>'','discription'=>'']);
        $this->assign('notice', $this->get_notice(0));
        //echo M('notice')->getLastSql();
        $this->display();
    }

    public function index0106(){

        $goods_model = new Model\GoodsModel();

        if($location = session('location')) {
            $this->assign('location', $location);//设置位置
        }
        //$this->set_dealer_status(0);
        //设置推荐分类
        $cate_list = $goods_model->cat_list_all();
        $cate_list = $this->get_array_tree($cate_list, 'cat_id');
//        foreach ($cate_list as &$item) {
//            $ids = $item['cat_id'];
//            $item['goods_list'] = $goods_model->goods_list($ids, 4);
//        }
        $this->assign('cate_list', $cate_list);

        $act_goods_list = $goods_model->index_recommend_goods();
        $this->assign('act_goods', $act_goods_list);
        $this->assign('cur_page','index');
        $this->assign('godos_empty','<div  style="height:5rem;overflow:hidden;text-align:center;line-height:5rem;font-size:.6rem;">该分类下暂无产品！</div>');
        $this->assign('tkd',['title'=>$this->_CFG['shop_name'] . '商城','keywords'=>'','discription'=>'']);
        $this->assign('notice', $this->get_notice(0));
        $stock_model = new Model\StockModel();
        $this->assign('store_cate', $stock_model->get_store_category());
        $this->assign('city', $this->get_city());
        $this->display();
    }

    public function index0124(){
        $goods_model = new Model\GoodsModel();
        $cate_list = $goods_model->cat_list_all();
        $cate_list = $this->get_array_tree($cate_list, 'cat_id', 'parent_id');
        foreach ($cate_list as &$item) {
            $ids = $this->get_array_value($item, 'cat_id');
            $item['goods_list'] = $goods_model->goods_list($ids, 4);
        }
        $this->assign('cate_list', $cate_list);


        $this->display();
    }

    public function data(){
        $str = $this->fetch('data');
        $result = result_ajax(200,'',$str);
        $this->ajaxReturn($result);
    }

    public function get_store_list(){
            //$this->assign('location', $location);//设置位置
            //$point_list = return_square_point($location['lng'], $location['lat']);
        $point_list = [];
        $goods_model = new Model\GoodsModel();
        $store = $goods_model->get_nearby_store($point_list, 's.*', 4);//根据经纬度获取附件的店铺

        $this->is_login();
        $this->user_info['uer_rank'] = intval($this->user_info['uer_rank']) <= 0 ? 1 : intval($this->user_info['uer_rank']);
        $store_goods = $goods_model->get_index_store_goods($store['ids'], array(0, 1),'goods_id','store_id', $this->user_info['user_rank']);

        foreach ($store['list'] as &$list) {
            $i=0;
            foreach ($store_goods as $vvv) {
                if($list['store_id'] == $vvv['store_id'] && isset($vvv['act_data']) && $i < 3){
                    $vvv['url'] = U('Goods/index') . '?id=' . $vvv['goods_id'] . '&aid=' . AesEnCrypt($vvv['aid']);
                    $vvv['goods_price_format'] = $vvv['act_format_price'];
                    $list['goods_list'][] = $vvv;
                    $i++;
                }
            }
        }

        if($store['list']){
            $this->assign('store_list', $store['list']);
            $result = result_ajax(200,'获取成功',['str'=>$this->fetch('Index:index_store'),'pagecount'=>$store['total'],'curpage'=>$store['curpage']]);
        }else{
            $result = result_ajax(300,'获取失败','');
        }

        $this->ajaxReturn($result);
    }

    private function get_city(){
        $city_list = F('city_list');
        if(!$city_list){
            $city = M('Region')->where('region_type = 2')->select();
            foreach ($city as $k => $v){
                $letter = get_first_charter($v['region_name']);
                $data[$letter][] = $v['region_name'];
            }
            ksort($data);
            F('city_list',$data);
            $city_list = $data;
        }
        return $city_list;
    }

    private function get_active_goods(){
        $where = [
            'start_time' =>  ['ELT',$this->time],
            'end_time'   =>  ['EGT',$this->time],
        ];
        $goods = M('GoodsActivity')->alias('ga')
            ->field('ga.*,g.*')
            ->where('act_id IN ' . M('FavourableActivity')
                            ->field('act_id')
                            ->where($where)
                            ->buildSql())
            ->join('LEFT JOIN __GOODS__ g ON g.goods_id=ga.goods_id')
            ->group()
            ->select();
        // M('GoodsActivity')->getLastSql();
        //$fa = M('FavourableActivity')->where($where)->select();
        //print_r($goods);
        return $goods;
    }

    private function get_notice($rank){
        //if ($count <= 0) $count = 5;
        $where = [
            'starttime' =>  ['ELT',$this->time],
            'endtime'   =>  ['EGT',$this->time],
            'is_show'   =>  1,
            'rank'      =>  ['EGT', $rank]
        ];
        return M('notice')->where($where)->order('is_top desc,addtime desc')->select();
    }

    protected function active_index(){
        $time = time();
        if (date('i', $time) > 30) {
            $time = date('Y-m-d H:30:00', $time);
        } else {
            $time = date('Y-m-d H:00:00', $time);
        }
        $time = strtotime($time);
        $index_url = M('index')
            ->where('star_time < ' . $time . ' and ' . $time . ' < end_time ')
            ->order('star_time desc')
            ->getfield('index_url');
        if ($index_url) {
            header('location:' . $index_url);
        }
    }

    public function crond(){
        //return false;
        $_CFG['overtime_pay']             =  (60 * 60 * 24);//24小时内付款
        $_CFG['overtime_confirm']         =  (60 * 60 * 24 * 10);//10天内确认
        $_CFG['overtime_refund']          =  (60 * 60 * 24 * 3);//3天处理退款，不处理自动同意
        $_CFG['overtime_refunded']        =  (60 * 60 * 24 * 3);//3天处理退款，不处理自动关闭退款退货
        $_CFG['overtime_refunded_confirm'] = (60 * 60 * 24 * 3);//3天处理退款，不处理自动确认收到货
        $_CFG['overtime_brokerage']       =  (60 * 60 * 24 * 5);//确认收货3天后处理拥金

        $order_model = new Model\OrderModel();
        $order_model->auto_time = $_CFG;
        $order_model->auto_order(0,$this->_CFG['auto_cancel']);//关闭超时未付款
        $order_model->auto_order(1,$this->_CFG['auto_receive']);//关闭超时未确认收货
        //$order_model->auto_brokerage_audit($this->_CFG['auto_audit_brok']);//订单确认收货10天后自动返拥

        //关闭退款申请超时未处理
        //关闭同意退款但未退货处理
        //关闭关闭退款已退货但未确认收货
        //订单确认收货3天返拥审核
    }

    /*public function abc(){
        $order_model = new Model\OrderModel();
        $id = I('get.id',0,'intval');
        if ($id <= 0) exit('end');
        print_r($order_model->auto_brokerage_audit2(0,$id));//订单确认收货10天后自动返拥
    }*/

    public function share(){
        //$data = get_encrypt_str('rec','get',0);
        $data['uid'] = (int)I('get.rec','','AesDeCrypt');
        if ($data['uid'] && $this->store_token) {
            $user_model = new Model\UsersModel();
            $store_model = new Model\StockModel();
            $user_info = $user_model->get_user_wx('u.user_id=' . $data['uid']);
            if ($user_info) {
                $store_info = $store_model->get_store_info(['s.store_id'=>$this->store_token]);
                $user_order_money = $user_model->get_user_order_money($data['uid'], $this->store_token);
                $default_money = $store_info['conditional_amount'] === '' ? C('default_money') : $store_info['conditional_amount'];

                //设置用户等级
                if(intval($user_info['rank_id']) < 2 && (double)$user_order_money['money'] >= $default_money){
                    M('UserRelevance')->where(['user_id'=>$data['uid']])->save(['rank_id'=>2]);
                    $user_info['rank_id'] = 2;
                }
                if (intval($user_info['rank_id']) < 2) {
                    $this->assign('money', (double)$user_order_money['money']);
                    $this->assign('default_money', $default_money);
                    $this->assign('user_head',['title'=>'二维码分享']);
                    $this->display('User:card1');
                } else {
                    $info = $this->get_qr_info($user_info, $this->store_token);
                    if ($info['error'] > 0) {
                        $this->error($info['msg']);
                    } else {
                        $info = $info['data'];
                    }

                    $info['user_avatar'] = img_url($info['user_avatar']);
                    //print_r($info);exit;
                    $this->assign('info', $info);
                    $this->assign('user_head',array('title'=>"二维码专属名片"));
                    $this->display('User:share');
                }
            } else {
                $this->error('分享信息已过期，请在会员中心进行分享!NO:02');
            }
        } else {
            $this->error('分享信息已过期，请在会员中心进行分享!NO:01');
        }
    }

    /**
     * 邀请有礼页面
     */
    public function invite(){
        $invite_user = AesDeCrypt(I('get.rec','','trim'));
        if($login_info = $this->login_info){
            $total = M('UserRelevance')->where(['parent_id'=>$login_info['user_id'], 'store_id'=>$this->store_token])->count();
            $this->assign('invite_total', $total);
            $this->assign('share_url', get_server('WAP_SERVER','/Index/share',
                [   'rec'=>AesEnCrypt($login_info['user_id']),
                    'store_token'=>$this->store_token
                ]));
        }else{
            $this->assign('qr_url', get_server('WAP_SERVER','/Index/share',
                [   'rec'=>AesEnCrypt($invite_user),
                    'store_token'=>$this->store_token
                ]));
        }
        $this->assign('is_login', $login_info);
        $this->assign('user_head',array('title'=>"邀请有礼"));
        $this->display('User:invite');
    }

    /**
     * 微信分享二维码    2016-09-20作废  改Public为protected
     */
    protected function share2(){
        //ini_set('xdebug.remote_host','t0.jiniu.cc');
        $id = I('get.id','','trim');//$id='+XeZhNw5aIbskNY2g4HXRA==';
        $sid = intval(AesDeCrypt($id));//exit($sid . '');
        $this->logger($id . '@' . $sid,1);
        if (!$id or !is_numeric($sid) or $sid <= 0) {
            $this->error('分享信息已过期，请在会员中心进行分享!NO:01');
        }
        $user_model = new Model\UsersModel();
        $user_info = $user_model->get_userinfo('user_id=' . $sid,'*');
        if ($user_info) {
            if ($user_info['user_rank'] <= -1) {
                $user_rank = M('UserRank')
                    ->where('special_rank=0 and rank_id !=' . intval($user_info['user_rank']))
                    ->order('sort')
                    ->find();
                $this->assign('rank', $user_rank);
                //$this->assign('rank_text', '您必须成为v1用户才可以进行分享!');
                $this->display('User:share1');
            } else {
                $user_model = new Model\UsersModel();

                //$this->assign('qrcode_info', $info);
                $qr = $user_model->user_qr_get($user_info['user_id']);
                $time = time();//var_dump($qr);exit( $qr['add_time'] + $qr['expire_seconds'] . '=' . $time);
                if (empty($qr) or ($time - $qr['add_time']) > $qr['expire_seconds']) {
                    if (!empty($qr)) @unlink(__ROOT__ . $qr['img_address']);
                    $res = $this->get_share_qr($user_info['user_id'], $qr);
                    if ($res['error'] > 0) {
                        $this->error($res['msg']);
                    } else {
                        $info = $res['data'];
                    }
                    $info['user_avatar'] = $user_info['user_avatar'];
                    $info['user_name'] = $user_info['user_name'];
                    $info['nickname'] = $user_info['nickname'];
                } else {
                    $info = $qr;
                }
                $this->assign('info', $info);
                $this->assign('user_head',array('title'=>"专属名片",'backUrl'=>U('User/index')));
                $this->display('User:share');
            }
        } else {
            $this->error('分享信息已过期，请在会员中心进行分享!NO:02');
        }
    }



    /**
     * 获取微信二维码
     * @param $user_id
     * @param array $qr_info
     * @return array
     */
    protected function get_share_qr1($user_id, $qr_info = array()){
        $time = time();
        $user_model = new Model\UsersModel();
        if ($qr_info && $qr_info['id'] > 0) {       //存在记录
            //$user_model->user_qr_save($QR_info['id'], $QR_info['img_address']);
            $qr_md5 = $qr_info['md5'];
            $file = $qr_info['img_address'];
            $qr_id = $qr_info['id'];
        } else {                                    //新添加
            $qr_md5 = md5($this->get_rand_str(22,2) . $time);
            $file = $this->get_save_path(2) . $time . '_' . $qr_md5 . '.png';//生成文件名
            $qr_id = $user_model->user_qr_add($user_id, $qr_md5, $file);//保存到数据库
        }
        $info = $this->get_wxuser_qr($qr_id);//读取QR信息
        if ($info) {
            $this->save_net_file($file,'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $info['ticket']);
            $user_model->user_qr_save($qr_id, $info);
            $info['md5'] = $qr_md5;
            $info['user_id'] = $user_id;
            $info['id'] = $qr_id;
            $info['img_address'] = $file;
            $info['add_time'] = $time;
            $result = array(
                'error' =>  0,
                'msg'   =>  '',
                'data'  =>  $info
            );
        } else {
            $result = array(
                'error' =>  1,
                'msg'   =>  '没有读取到二维码信息',
            );
        }
        //$info = $info//array_merge($info, $user_id);
        return $result;
    }



    public function share1($expire = 604800){
        $s = I('s','','trim');
        if (strlen($s) != 32) {
            $this->error('扫码关注失败！',U('Index/index'));
        } else {
            $time = time();
            $user_model = new Model\UsersModel();
            $qr = $user_model->user_qr_get($s);
            if (empty($qr)) {
                $this->error('扫码关注失败！',U('Index/index'));
            } else {
                $this->logger('数据库中读取的二维码信息：' . print_r($qr,1));
                if ($qr['url'] == '' or ($time - $qr['add_time']) > ($qr['expire_seconds'] - 60)) {
                    if ($this->is_weixin) {
                        $info = $this->get_wxuser_qr($qr['id']);
                        $user_model->user_qr_save($qr['id'], $info);
                        //header('location:' . $info['url']);
                        $this->error('重新提取的',$info['url']);
                        //$info = array_merge($qr,$info);
                    } else {
                        $this->redirect('User/login?ref=' . $qr['user_id']);
                    }
                } else {
                    if ($this->is_weixin and $qr['url'] != '') {
                        //header('location:' . $qr['url']);
                        $this->display();
                        //$this->error('存在数据库的','weixin://contacts/profile/gh_34bd692a9835');
                    } else {
                        $this->redirect('User/login?ref=' . $qr['user_id']);
                    }
                }
            }
        }
    }

    public function api(){
        $echostr = md5($this->get_rand_str(8));
        $data = array(
            'timestamp' =>  time(),
            'nonce'     =>  md5($this->get_rand_str(10)),
            'token'     =>  '100006',
        );
        $data['signature'] = $this->get_Sign($data, 1);
        $data['echostr'] = $echostr;
        $result = $this->curlGet('http://www.weipincn.com/wx/100006','get',$this->Array2Str($data));
        if ($result == $echostr) {
            echo '正确';
            //$result = $this->curlGet('http://www.weipincn.com/wx/100006?echostr=');
        } else {
            echo '错误！';
        }
        $this->debug($result,1);
    }

    private function setSignature() {
        $signature = I("signature");
        $timestamp = I("timestamp");
        $nonce = I("nonce");
        $tmpArr = array(I('get.token'), $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    public function test_error(){
        $this->error('错误提示','',1000);
    }


    public function a(){
        $arr1 = array(1, 2, 3, 4, 5);
        foreach ( $arr1 as &$item ) {

            echo $item . "\n";

        }


        echo "\n--------------\n";

        $item = 13;
print_r($arr1);
        foreach ( $arr1 as $item ) {
            echo $item . "\n";
        }

        exit;
    }

    public function pay_test(){
        echo '<a href="weixin://wxpay/bizpayurl?appid=wx426b3015555a46be&mch_id=1225312702&nonce_st">扫码支付一</a><br />';
        echo '<a href="weixin://wxpay/bizpayurl?pr=RJKQKsd">扫码支付二</a><br />';
        exit('<a href="weixin://wap/pay?appid=wx2a416286e96100ed&timestamp=1439978269&noncestr=jncjwhvvynr0cvos8hamocxzgr5rlqhy&package=WAP&prepayid=wx2015081917574919cf7699c40592448564&sign=55AD30D6C11E84A6DBE40DAB3F51415C" class="weixin-wap-deeplink">支付</a>');
    }

    public function kehengLogin(){
        $id = I('get.uid',0,'intval');
        $p = I('get.p','');
        if ($p == 'keheng' && APP_DEBUG) {
            $user_model = new Model\UsersModel();
            $login_info = $user_model->get_userinfo('user_id=' . $id,'user_id,user_name,nickname,email');
            $login_info['add_time'] = $this->time;
            session('login_info_'.$this->store_token, $login_info);
        }
        $this->redirect('User/index');
    }

    //登录后回跳
    public function access_token(){
        $back_act = AesDeCrypt(I('get.back_act'));
        if($back_act != ''){
            header("Location:$back_act");
        }else{
            $this->redirect("index/index");
        }
    }

    public function get_session1(){
        echo session_id();
    }
    
    Public function wx_verify(){
        exit(I('get.key'));
    }
}

