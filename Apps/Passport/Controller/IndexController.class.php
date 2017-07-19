<?php
namespace Passport\Controller;
use Passport\Model;
use Common\Plugins;


class IndexController extends BaseController {
    protected function _initialize(){
        parent::_initialize();
    }

    public function index(){

    }

    /**
     * 清除session （测试）
     */
    public function delsk(){
        unset($_SESSION['wp_']);
        print_R($_SESSION);
        print_R($_COOKIE);
    }


    // ->field('b.*, b.id, b.act_id, c.act_name, c.act_image')
//->join('LEFT JOIN __FAVOURABLE_USER_ACTIVITY__ b ON a.action_id=b.id')store_wx
    public function check_common(){
        $where = array();
        $user_id = I('get.user_id', '', 'intval');
        if($user_id){
            $where['u.user_id'] = $user_id;
        }else{
            $where='r.store_id != s.store_id';
        }
        $Arr = M('users')->where($where)->alias('u')
                ->field('u.*, w.*, r.store_id rstid, s.*')
                ->join('LEFT JOIN __USER_WX__ w on u.user_id=w.user_id')
                ->join('LEFT JOIN __USER_RELEVANCE__ r on u.user_id=r.user_id')
                ->join('LEFT JOIN __STORE_WX__ s on s.gd_id=w.gd_id')
                ->select();

        print_R($Arr);
    }

    Public function wx_verify(){
        exit(I('get.key'));
    }

    /**
     * 测试 和微信通信验证token
     * @return bool
     */
    public function checkSignature() {
        //define("TOKEN", "100111");
        $signature = I("signature");
        $timestamp = I("timestamp");
        $token = I("token");
        $nonce = I("nonce");
        //$token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        \Think\Log::record($_REQUEST);
        \Think\Log::record(file_get_contents('php://input', 'r'));
        echo $tmpStr;
        echo '<br>';
        echo $signature;
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    public function test(){
        $uid = intval(AesDeCrypt(urldecode($_GET['rec'])));
        $data['uid'] = intval(I('get.rec','','AesDeCrypt'));
        $a = 123;
        $b = AesEnCrypt($a);
        $c = AesDeCrypt($b);
        debug($a, 1);
        debug($b, 1);
        debug($c, 1);
        echo $a;
        echo '<br>';
        echo $b;
        echo '<br>';
        echo $c;
        echo '<br>';
        echo $data['uid'];
        echo '<br>';
        echo $uid;
        echo '<br>';
        echo urldecode($_GET['rec']);
    }

    /**
     * 用户个人二维码(可以被分享出去)
     */
    public function share(){
        $menber_rank = 2;   //会员等级123
        //$data = get_encrypt_str('rec','get',0);
        $data['uid'] = intval(I('get.rec','','AesDeCrypt'));
        //print_R($data);exit;
        $user_model = new Model\UsersModel();
        $store_model = new Model\StockModel();
        $fields = 'u.user_id,u.nickname,u.user_name,u.user_avatar,u.user_areainfo,u.sex,u.user_money,u.frozen_money,ur.store_id sid,ur.parent_id,ur.rank_id,ur.is_vip,ur.is_delete';
        $user_info = $user_model->get_user_wx('u.user_id=' . $data['uid'], $fields);
        if ($data['uid'] && $user_info) {
                $store_info = $store_model->get_store_info(['s.store_id'=>$user_info['sid']]);
                $user_order_money = $user_model->get_user_order_money($data['uid'], $user_info['sid']);
                $default_money = intval($store_info['conditional_amount']) <= 0 ? C('default_money') : intval($store_info['conditional_amount']);
                //设置用户等级
            debug('用户消费金额' . print_R($user_order_money['money'], 1), 1);
            debug('系统默认金额' . print_R($default_money, 1), 1);
            debug('店铺信息' . print_R($store_info, 1), 1);
            debug('用户信息' . print_R($user_info, 1), 1);
                if(intval($user_info['rank_id']) < $menber_rank && (double)$user_order_money['money'] >= $default_money){
                    M('UserRelevance')->where(['user_id'=>$data['uid']])->save(['rank_id'=>$menber_rank]);
                    $user_info['rank_id'] = $menber_rank;
                }
                if (intval($user_info['rank_id']) < $menber_rank) {
                    $this->assign('money', (double)$user_order_money['money']);
                    $this->assign('default_money', $default_money);
                    $this->assign('user_head',['title'=>'二维码分享']);
                    $this->assign('tkd',['title'=>'二维码分享']);
                    $this->assign('store_token',$user_info['sid']);
                    $this->display('User:card1');
                } else {
                    $info = $user_model->get_qr_info($user_info, $user_info['sid']);
                    if ($info['error'] > 0) {
                        $this->error($info['msg']);
                    } else {
                        $info = $info['data'];
                    }
                    $info['user_avatar'] = img_url($info['user_avatar']);
                    //print_r($info);exit;
                    $this->assign('info', $info);
                    $this->assign('user_head',array('title'=>"二维码专属名片"));
                    $this->assign('store_token',$user_info['sid']);
                    $this->assign('tkd',['title'=>'二维码专属名片']);
                    $this->display('User:share');
                }
        } else {
            $this->error('分享信息已过期，请在会员中心进行分享!NO:01');
        }
    }



}