<?php

/**
 * 即牛 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2015 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: UsersModel.class.php 17156 2015-12-31 15:22:47Z keheng $
*/
namespace Passport\Model;
use Think\Exception;
use Think\Think;
use Common\Plugins;

class UsersModel extends BaseModel{
    protected $tableName = 'Users';
    public function _initialize(){
        parent::_initialize();
    }

    protected $_validate = array(
        //密码修改
        array('oldpass','check_old_pass','原密码错误',1,'function',4),
        array('newpass','require','请输入新密码',1,'',4),
        array('newpass','/[\w\d_]{6,20}/','请输入新密码',1,'regex',4),
        array('repass','newpass','确认密码不正确',1,'confirm',4),


    );
    public $re;
    protected function return_re(){
        return $this->re;
    }


    /**
     * 获取当前公众号内绑定用户ID及openid
     * @param $user_id  int     用户ID
     * @param $openid  string   openid
     * @param $gd_id   string   微信公众号ID
     * @return mixed
     */
    public function get_userwx_info($user_id, $openid, $gd_id) {
        return M('UserWx')->where(['user_id'=>$user_id,'openid'=>$openid,'gd_id'=>$gd_id])->find();
    }

    /**
     * 添加当前公众号内绑定用户ID及openid
     * @param $user_id  int     用户ID
     * @param $openid  string   openid
     * @param $gd_id   string   微信公众号ID
     * @return mixed
     */
    public function add_userwx_info($user_id, $openid, $gd_id) {
        $data = [
            'user_id'   =>  $user_id,
            'openid'    =>  $openid,
            'gd_id'     =>  $gd_id,
            'add_time'  =>  time(),
            'add_ip'    =>  get_client_ip()
        ];
        return M("UserWx")->add($data);
    }


    /**
     * 支付后赠送KP记录
     * @param $user_id integer
     * @param $order_id integer
     * @param $kp_num integer
     */
    public function gaving_kp($user_id, $order_id, $kp_num){
        if (intval($user_id) > 0 and intval($kp_num) > 0) {
            $data = [
                'type_id'   =>  0,
                'user_id'   =>  $user_id,
                'order_id'  =>  $order_id,
                'kp_num'    =>  $kp_num,
            ];

            if (M('user_kpnum_log')->where(array_merge($data,['status'=>1]))->count() <= 0) {
                if (M('Users')->where('user_id=' . $user_id)->setInc('consume_total', $kp_num)) {
                    //添加成功日志
                    $data['desc'] = '添加成功';
                    $data['status'] = 1;
                } else {
                    //添加失败日志
                    $data['desc'] = '添加失败';
                    $data['status'] = 0;
                }
                $this->user_kp_log($data);
            }
        }
    }


    /**
     * 使用KP劵
     * @param $data
     * @return mixed
     */
    private function user_kp_log($data) {
        $add_data = [
            'type_id'   =>  $data['type_id'],
            'user_id'   =>  $data['user_id'],
            'order_id'  =>  $data['order_id'],
            'kp_num'    =>  $data['kp_num'],
            'status'    =>  $data['status'],
            'desc'      =>  $data['desc'],
            'add_time'  => time(),
        ];
        return M('user_kpnum_log')->data($add_data)->add();
    }


    /**
     * 手机或邮箱验证码验证
     * @param $obj
     * @param $verify
     * @return bool
     */
    public function authcode_check($obj,$verify){
        $time = time();
        $m = M("AuthCode");
        $authcode = $m->where('obj="' . $obj . '"')->find();
        if ($authcode) {
            if ($time - $authcode['add_time'] > $authcode['expires']) {
                return false;
            } elseif($verify != $authcode['verify']) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
    /**
     * 邮件或手机验证码记录
     * @param $obj
     * @param $verify
     * @param $type_id
     */
    public function authcode_add($obj,$verify,$type_id){
        $time = time();
        $m = M("AuthCode");
        $m->where('add_time + expires < ' . $time)->delete();
        if ($id = $m->where('obj="' . $obj . '"')->getfield('id')) {
            $data = array(
                'verify'    =>  $verify,
                'add_time'  =>  $time,
                'ip'        =>  get_client_ip()
            );
            $m->where('id=' . $id)->save($data);
        } else {
            $data = array(
                'type_id'   =>  $type_id,
                'obj'       =>  $obj,
                'verify'    =>  $verify,
                'add_time'  =>  $time,
                'expires'   =>  600,
                'ip'        =>  get_client_ip()
            );
            $m->add($data);
        }
    }
    /**
     * 团队订单
     * @param $user_id
     * @return mixed
     */
    public function get_myteam_order_count($user_id) {
        $m = M('OrderInfo');
        $where = ' and ' . $this->get_order_status_sql(8,2,2);
        if (!is_array($user_id)) {
            $user_id = array($user_id);
        }
        $result = $m->where($this->db_create_in($user_id,'user_id') . $where)->count();
        //echo $m->getLastSql();
        return $result;
    }

    /**
     * 获取我的用户团队
     * @param $user_id
     * @param $max
     * @return array|mixed
     */
    public function get_myteam1($user_id, $max = 5){

        if(empty($user_id)) return [];
        if (!is_array($user_id)) {
            $user_id = array($user_id);
        }
        //查询所有父级id的所属店铺便于后面区分用户多层关系归属问题
        $users = M('UserRelevance')->where(['user_id'=>['in',$user_id]])->select();
        $store_ids = [];
        foreach ($users as $user){
            if($user['store_id'])
            $store_ids[$user['user_id']][] = $user['store_id'];
        }

        $in = $this->db_create_in($user_id,'ul.parent_id');
        $sql = $this->alias('u')
            ->field('u.user_id')
            ->join('LEFT JOIN __USER_RELEVANCE__ ul ON u.user_id=ul.user_id')
            ->where($in)->buildSql();// 查询满足要求的总记录数
        $map = 'ul.user_id IN' . $sql;
        //把所有跟父级id有关系的用户查出用于后期有没多层关系
        $all = $this->alias('u')
            ->join('LEFT JOIN __USER_RELEVANCE__ ul ON u.user_id=ul.user_id')
            ->where($map)->select();
        $user_all = $ids = $rid = [];
        //把用户的所有关系记录放在同一个数组
        foreach($all as $v){
            $user_all[$v['user_id']][] = $v;
        }
        //把归属为所有归属父的id统计出来便于查询
        foreach($user_all as $vv){
            //如果大于1的就表明有多重关系
            if(count($vv) > 1){
                foreach($vv as $vvv){
                    //如果店铺id和父级店铺id是相同的就归属到父级id
                    if(intval($vvv['store_id'] && in_array($vvv['store_id'], $store_ids[$vvv['parent_id']]))){
                        $id = $vvv['id'];
                    }
                    $rid[] = $vvv['id'];
                }
                if(isset($id)){
                    $ids[] = $id;
                }else{
                    $ids[] = min($rid);
                }
                unset($id);
                continue;
            }
            $ids[] = $vv[0]['id'];
        }
        if(empty($ids)) return [];
        $count = count($ids);
        $Page       = new \Think\Page($count, $max);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $Page->show();// 分页显示输出

        $where['ul.id'] = ['in', $ids];
        $result = $this->alias('u')
            ->field('u.user_id,u.user_name,u.nickname,u.user_avatar,u.reg_time,ur.rank_name,count(oi.order_id) order_count,ul.parent_id parent_id,ul.is_vip,u1.user_name parent_name')
            ->join('LEFT JOIN __USER_RANK__ ur ON ur.rank_id=u.user_rank')
            ->join('LEFT JOIN __USER_RELEVANCE__ ul ON u.user_id=ul.user_id')
            ->join('LEFT JOIN __USERS__ u1 ON ul.parent_id=u1.user_id')
            ->join('LEFT JOIN __ORDER_INFO__ oi ON oi.user_id=u.user_id')
            ->where($where)  //. ' and ' . $this->get_order_status_sql(8,2,2)
            ->group('u.user_id')
            ->order('u.user_id desc')
            //->cache($cache,3600)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $order_count = 0;
        if ($result) {
            foreach ($result as &$v) {
                if(is_email($v['user_name'])){
                    $v['user_name'] = $this->set_hidden_str($v['user_name'],2);
                }else if($this->is_mobile_num($v['user_name'])){
                    $v['user_name'] = $this->set_hidden_str($v['user_name'],4);
                }else{
                    if(mb_strlen($v['user_name']) > 8){
                        $v['user_name'] =  mb_substr($v['user_name'],0,8).'...';
                    }
                }
                if(is_email($v['parent_name'])){
                    $v['parent_name'] = $this->set_hidden_str($v['parent_name'],2);
                }else if($this->is_mobile_num($v['parent_name'])){
                    $v['parent_name'] = $this->set_hidden_str($v['parent_name'],4);
                }else{
                    if(mb_strlen($v['parent_name'])>8){
                        $v['parent_name'] =  mb_substr($v['parent_name'],0,8).'...';
                    }
                }
                $v['user_avatar'] = img_url($v['user_avatar']);
                $v['reg_time'] = date('Y-m-d H:i', $v['reg_time']);
                if ($v['is_vip'] > 0) {
                    $v['rank_name'] = '在职员工';
                } elseif ($v['is_vip'] < 0) {
                    $v['rank_name'] = '离职员工';
                } else {
                    $v['rank_name'] = '会员';
                }
                $order_count += $v['order_count'];
            }
        }
        return array('list'=>$result,'page'=>$show,'total'=>$Page->totalPages,'count'=>$count,'ids'=>$ids,'order_count'=>$order_count);

    }

    /**
     * 获取我的用户团队
     * @param $user_id
     * @param $max
     * @return array|mixed
     */
    public function get_myteam($user_id, $max = 5){
        //$user_id = intval($user_id);
        if(empty($user_id)) return [];
        $p = I('get.p',0,'intval');
        if (!is_array($user_id)) {
            $user_id = array($user_id);
        }
        $in = $this->db_create_in($user_id,'ul.parent_id');
        $where = $in;
        $cache = 'user_team_' . md5($where . '_' . $p);

        $all = $this->alias('u')
            ->join('LEFT JOIN __USER_RELEVANCE__ ul ON u.user_id=ul.user_id')
            ->where($where)->select();// 查询满足要求的总记录数

        if(empty($all)) return [];
        $count  = count($all);
        //echo $this->getLastSql() . "@@@@@@ $count\n";
        $Page       = new \Think\Page($count, $max);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $Page->show();// 分页显示输出
        $result = $this->alias('u')
            ->field('u.user_id,u.user_name,u.nickname,u.user_avatar,u.reg_time,ur.rank_name,count(oi.order_id) order_count,ul.parent_id parent_id,ul.is_vip,u1.user_name parent_name')
            ->join('LEFT JOIN __USER_RANK__ ur ON ur.rank_id=u.user_rank')
            ->join('LEFT JOIN __ORDER_INFO__ oi ON oi.user_id=u.user_id')
            ->join('LEFT JOIN __USER_RELEVANCE__ ul ON u.user_id=ul.user_id')
            ->join('LEFT JOIN __USERS__ u1 ON ul.parent_id=u1.user_id')
            ->where($where)  //. ' and ' . $this->get_order_status_sql(8,2,2)
            ->group('u.user_id')
            ->order('u.user_id desc')
            //->cache($cache,3600)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $ids = [];
        $order_count = 0;
        foreach ($all as $v) {
            $ids[] = $v['user_id'];
//            $order_count += $v['order_count'] ;
        }
        if ($result) {
            foreach ($result as &$v) {
                //加***
                if($this->is_email($v['user_name'])){
                    $v['user_name'] = $this->set_hidden_str($v['user_name'],2);
                }else if($this->is_mobile_num($v['user_name'])){
                    $v['user_name'] = $this->set_hidden_str($v['user_name'],4);
                }else{
                    if(mb_strlen($v['user_name']) > 8){
                        $v['user_name'] =  mb_substr($v['user_name'],0,8).'...';
                    }
                }
                if($this->is_email($v['parent_name'])){
                    $v['parent_name'] = $this->set_hidden_str($v['parent_name'],2);
                }else if($this->is_mobile_num($v['parent_name'])){
                    $v['parent_name'] = $this->set_hidden_str($v['parent_name'],4);
                }else{
                    if(mb_strlen($v['parent_name'])>8){
                        $v['parent_name'] =  mb_substr($v['parent_name'],0,8).'...';
                    }
                }
                $v['user_avatar'] = $this->img_url($v['user_avatar']);
                $v['reg_time'] = date('Y-m-d H:i', $v['reg_time']);
                if ($v['is_vip'] > 0) {
                    $v['rank_name'] = '在职员工';
                } elseif ($v['is_vip'] < 0) {
                    $v['rank_name'] = '离职员工';
                } else {
                    $v['rank_name'] = '会员';
                }
            }
        }
        return array('list'=>$result,'page'=>$show,'total'=>$Page->totalPages,'count'=>$count,'ids'=>$ids,'order_count'=>$order_count);
    }
    /**
     * 获取最佳收货时间
     * @return mixed
     */
    public function get_best_time(){
        return M('besttime')->select();
    }

    /**
     * 获取支入方式的各种图标
     * @param $type
     * @return string
     */
    public function get_account_icon($type){
        switch ($type) {
            case 'alipay':  $result = '';  break;
            case 'hkhp':  $result = '';  break;
            case 'weipin':  $result = '';  break;
            default: $result = '';
        }
        return $result;
    }

    /**
     * 添加用户账户记录
     * @param $id
     * @param $price
     * @param string $data
     * @return string
     */
    public function add_user_account($id,$price,$data = ''){
        $add_data = [
            'user_id'       =>  intval($id['user_id']),
            'store_id'      =>  intval($id['store_id']),
            'amount'        =>  $price,
            'add_time'      =>  time(),
            'stage'         =>  $data['stage'],
            'desc'          =>  $data['desc'],
            'process_type'  =>  $data['type'],
            'user_note'     =>  $data['user_note'],
            'cash_sn'       =>  create_sn('CSN'),
            'admin_note'    =>  ''
        ];
        $model = M('UserAccount');
        $model->data($add_data)->add();
        return $model->getLastInsID();
    }

    /**
     * 设置账户信息
     * @param $account_id
     * @param $status
     * @param $data
     * @return bool
     */
    public function set_user_account($account_id,$status,$data){
        $data = [
            'pay_code'  =>  $data['pay_code'],
            'payment'  =>  $data['payment_name'],
            'pay_account'  =>  $data['pay_account'],
            'pay_name'  =>  $data['pay_name'],
            'is_paid'   =>  $status,
            'paid_time' =>  time(),
            'cash_sn'   => create_sn(PREFIX_CASH)
        ];
        return M('UserAccount')->where(['id'=>$account_id])->data($data)->save();
    }
    /**
     * 添加现金日志
     * @param $data
     * @param $desc
     * @return mixed
     */
    public function add_account_log($data,$desc){
        $da = array(
            'user_id'           =>  $data['user_id'],
            'user_money'        =>  $data['user_money'],
            'frozen_money'      =>  $data['frozen_money'],
            'rank_points'       =>  $data['rank_points'],
            'pay_points'        =>  $data['pay_points'],
            'change_time'       =>  time(),
            'change_desc'       =>  $desc,
            'change_type'       =>  $data['change_type'],
        );
        return M('AccountLog')->add($da);
    }

    /**
     * 保存用户二维码信息
     * @param $id
     * @param $data
     * @return bool
     */
    public function user_qr_save($id, $data) {
        $data = array(
            'ticket'            =>  $data['ticket'],
            'expire_seconds'    =>  $data['expire_seconds'],
            'url'               =>  $data['url'],
            'add_time'          =>  time()
        );
        return M('UserQr')->where('id=' . $id)->save($data);
    }

    /**
     * 添加用户二维码信息
     * @param $user_id
     * @param $md5
     * @param $address
     * @param $expire_seconds
     * @return bool|string
     */
    public function user_qr_add($user_id, $md5, $address, $expire_seconds) {
        $data = array(
            'md5'           =>  $md5,
            'user_id'       =>  $user_id,
            'img_address'   =>  $address,
            'expire_seconds'=>  $expire_seconds,
            'add_time'      =>  time()
        );
        $m = M('UserQr');
        if ($m->add($data)) {
            return $m->getLastInsID();
        } else {
            return false;
        }
    }

    /**
     * 修改用户二维码信息
     * @param $qr_id
     * @param $md5
     * @param $address
     * @param $expire_seconds
     * @return bool|string
     */
    public function user_qr_edit($qr_id, $md5, $address, $expire_seconds=604800) {
        $data = array(
            'md5'           =>  $md5,
            'img_address'   =>  $address,
            'expire_seconds'=>  $expire_seconds,
            'add_time'      =>  time()
        );
        $m = M('UserQr');
        if ($m->where(['id'=>$qr_id])->save($data)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * 获取二维码信息
     * @param $user_id
     * @return mixed
     */
    public function user_qr_get($user_id){
        if (is_numeric($user_id)) {
            $where = "uq.user_id={$user_id}";
        } else {
            $where = "uq.md5='{$user_id}'";
        }
        return M('UserQr')->alias('uq')
            ->field('u.*,uq.*')
            ->where($where)
            ->join('LEFT JOIN __USERS__ u ON u.user_id=uq.user_id')
            ->find();
    }
    /**
     * 注册赠送操作(保留，后期添加)
     * @param $user_id
     */
    public function regist_giving($user_id){

    }
    /**
     * 添加用户
     * @param $classData
     * @param int $uid
     * @return mixed|string
     */
    public function add_user($classData, $uid = 0) {
        //$this->logger(print_r($classData,1) . "\n" . $uid . "===========");
        $data['store_id'] = 0;
        //$data['inviter'] = null;
        //$data['subscribe'] = $classData['subscribe'];
        //$data['groupid'] = $classData['groupid'];
        //$data['g_id'] = $json->groupid? $json->groupid: 0;
        if (is_email($classData['user'])) {
            $data['email']      = $classData['user'];
            $data['mobile_phone']      =  "";
        } elseif ($this->is_mobile_num($classData['user'])) {
            $data['email']      = "";
            $data['mobile_phone']      = $classData['user'];
        } else {
            //return false;
        }
        $data['user_name']  = $classData['user_name'];
        $data['openid']         = trim($classData['openid']);
        $data['nickname']       = $classData['nickname']?:'';
        $data['password']       = md5($classData['pass']);
        $data['sex']            = $classData['sex'] ? 1 : 0;
        $data['user_areainfo']  = $classData['city'].' '.$classData['province'];
        $data['user_avatar']    = $classData['headimgurl'];
        $data['reg_time']       = (isset($classData['subscribe_time']) and !empty($classData['subscribe_time'])) ? $classData['subscribe_time'] : time();
        $data['alias']          = '';
        $data['msn']            = '';
        $data['qq']             = '';
        $data['office_phone']   = '';
        $data['home_phone']     = '';
        //$data['mobile_phone']   = '';
        $data['credit_line']    = 0;
        $data['rank_points']    = 0;
        //$item = $this->cache('user_id_'.$data['openid'])->field('openid,store_id')->where(array('store_id' => $data['store_id'], 'openid' => $data['openid']))->find();
        /*if (APP_DEBUG) {
            echo print_r($data,1);
        }*/
        /*if ($uid > 0 and $this->where('user_id=' . $uid)->count() > 0) {

        } else {
            $uid = 0;
        }*/

        if (empty($item)) {
            if ($uid > 0) {
                $data['parent_id'] = $uid;
            }
            $this->data($data)->add();
            $insertID = $this->getLastInsID();
            M('UserVerifcat')->add(array('user_id'=>$insertID));
            return $insertID;
        } else {
            $where = array('store_id' => $data['store_id'], 'openid' => $data['openid']);
            $this->where($where)->save($data);
            return $this->where($where)->getfield('user_id');
        }
    }
    /**
     * 验证注册用户是否存在
     * @param $user
     * @return array
     */
    public function check_user($user) {
        $result = array('error'=>0,'message'=>'');
        if (empty($user)) {
            $result['message'] = '用户或密码不能为空';
            $result['error']   = 2;
        } else {
            $user_info = $this->get_userinfo("user_name='{$user}' or email='{$user}' or mobile_phone='{$user}'");
            if (!empty($user_info)) {
                $result['message'] = '该用户已存在';
                $result['error']   = 1;
            } else  {
                $result['message'] = '';
                $result['error']   = 0;
            }
        }
        return $result;
    }

    /**
     * 取用户等级表
     * @param int $id
     * @return array
     */
    public function get_user_rank($id = 0){
        $res = M('UserRank')->select();
        $result = array();
        foreach($res as $k => $v) {
            $result[$v['rank_id']] = $v;
        }
        if ($id > 0) {
            return $result[$id];
        } else {
            return $result;
        }
    }

    /**
     * 获取用户信息
     * @param $where
     * @param string $fields
     * @return mixed
     */
    public function get_userinfo($where, $fields = ''){
        if (!$fields) {
            $fields = 'user_id,openid,user_name,nickname,password,user_avatar,mobile_phone,user_money';
        }
        return M('users')->field($fields)->where($where)->find();
    }

    /**
     * 读取会员以上N级
     * @param int $user_id    会员ID
     * @param int $store_id 店铺ID
     * @param int $level    控制读取级别
     * @param int $cache_time
     * @return array
     */
    public function get_user_inviter2($user_id, $store_id = null, $level = 3, $cache_time = 3600) {

        if ($level <= 0) return [];
        try{
            $user_level = user_level($user_id,$store_id,$level);
        }catch (Exception $e){
            trace($e->getMessage());
            return [];
        }

        if (!empty($user_level)) {
            $ids = [];
            foreach ($user_level as $v){
                $ll = explode('_', $v);
                $ids[] = $ll[1];
            }

            $ss = $this->alias('a')
                ->field('a.user_id,a.parent_id,a.openid,a.user_name,a.store_id,a.user_rank,ur.layered rank_brok')
                ->join('left join __USER_RANK__ ur ON a.user_rank=ur.rank_id')
                ->where($this->db_create_in($ids,'a.user_id'))
                ->select();

            return $this->mysort($ss,$ids,'user_id');
        } else {
            return [];
        }
    }

    /**
     * 读取会员以上N级
     * @param int $user_id    会员ID
     * @param int $level    控制读取级别
     * @param int $cache_time
     * @return array
     */
    public function get_user_inviter1($user_id, $level = 3, $cache_time = 3600) {
        if ($level <= 0) return [];
        $res = $this->alias('u0');
        $field = '';
        for ($i = 0; $i < $level; $i++) {
            if ($field) $field .= "," ;
            $s = $i + 1;
            $field .= "u" . $s . '.user_id leve' . $s . '_id';
            $res = $res->join('left join __USERS__ u' . $s . ' ON u' . $i . '.parent_id = u' . $s . '.user_id');
        }
        $result = $res->field($field)->where('u0.user_id = ' . $user_id)->find();
        if ($result) {
            $ids = [];
            foreach ($result as $v) {
                if (!$v) break;
                $ids[] = $v;
            }
            $ss = $this->alias('a')
                ->field('a.user_id,a.parent_id,a.openid,a.user_name,a.store_id,a.user_rank,ur.layered rank_brok')
                ->join('left join __USER_RANK__ ur ON a.user_rank=ur.rank_id')
                ->where($this->db_create_in($ids,'a.user_id'))
                ->select();
            return $this->mysort($ss,$ids,'user_id');
        } else {
            return [];
        }
    }

    /**
     * 读取会员以上N级
     * @param int $user_id    会员ID
     * @param int $level    控制读取级别
     * @param int $cache_time
     * @return array
     */
    public function get_user_inviter($user_id, $level = 3, $cache_time = 3600) {
        static $cur_level = 0 ;
        static $ids = array();
        if ($cur_level < $level) {
            $inviter = $this
                ->alias('a')
                ->where(array("a.user_id" => $user_id))
                ->cache('user_parent_info_' . $user_id,$cache_time)
                ->join('LEFT JOIN __USERS__ b ON a.parent_id = b.user_id')
                ->field('b.user_id,b.parent_id,b.openid,b.user_name,b.store_id,b.user_rank')
                ->find();
            $cur_level++;
            if ($inviter['parent_id']) {
                $ids = array_merge($ids,array($inviter),$this->get_user_inviter($inviter['user_id']));
            } else {
                $ids = array_merge($ids,array($inviter));
            }
        }
        arsort($ids);
        return $ids;
    }

    /**
     * 获取用户拥金     //未审核
     * @param $user_id
     * @return array
     */
    public function get_user_agency($user_id){
        $where = [
            'uid'       => intval($user_id),
            'state'     => YJ_UNAUDIT,
        ];
        $return = M('UserLevel')->where($where)->getfield('sum(money)');
        return sprintf("%.2f", $return);
    }




    /**
     * 获取现金日志
     * @param $user_id
     * @param $pagesize
     * @return array
     */
    public function get_user_account($user_id, $pagesize){
        $m = M('UserAccount');
        //$where = 'user_id=' . $user_id;
        $where = array();
        $where['user_id'] = array('eq',$user_id);
        $where['process_type'] = array('eq',PT_MONEY);
        $Page = new \Think\Page($m->where($where)->count(), $pagesize);
        $Page->rollPage = 5;
        $Page->setConfig('prev','上一页');
        $Page->setConfig('next','下一页');
        $show = $Page->show();
        $res = $m
            ->where($where)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('add_time desc')
            ->select();
        /*foreach ($res as $k => $v) {
            $res[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
//            $res[$k]['stage_name'] = $this->get_cash_type($v['stage']);
        }*/
        return array('list'=>$res,'show'=>$show,'total'=>$Page->totalPages);
    }


    public function get_yongji($user_id, $pagesize = 10) {
        $m = M("UserLevel");
        $where = 'uid=' . $user_id . " and state in(" . YJ_UNAUDIT . "," . YJ_CLOSED . ")";
        $Page = new \Think\Page($m->where($where)->count(), $pagesize);
        $show = $Page->show();
        $res = $m
            ->where($where)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('addtime desc')
            ->select();
        foreach ($res as $k => $v) {
            $res[$k]['addtime'] = date('Y-m-d H:i:s', $v['addtime']);
            $res[$k]['state_name'] = $this->get_yongjin_state($v['state']);
        }
        return array('list'=>$res,'show'=>$show,'total'=>$Page->totalPages);
    }

    public function get_yongjin_state($str){
        switch($str) {
            case YJ_UNAUDIT:  $result = '待审核';break;
            case YJ_AUDIT:    $result = '已审核';break;
            case YJ_CLOSED:   $result = '已退款';break;
            case YJ_DELETED:  $result = '已删除';break;
            case YJ_EXTRACT:  $result = '已提取';break;
            default:            $result = '未知状态';
        }
        return $result;
    }

    public function get_cash_account($id) {
        if (intval($id) <= 0) return false;
        return M('CashAccount')->where('user_id=' . $id)->find();
    }

    /*
     * 获取用户红包记录 user_bonus
     */
    public function get_hongbao($user_id,$pageSize=10){
        $m = M('UserAccount');
        $map = array();
        $map['user_id'] = array('eq',$user_id);
        $map['process_type'] = array('eq',PT_RED);
        $Page = new \Think\Page($m->where($map)->count(), $pageSize);
        $show = $Page->show();
        $res = $m
            ->where($map)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('add_time desc')
            ->select();
        return array('list'=>$res,'show'=>$show,'total'=>$Page->totalPages);
    }

    /**
     * 检查值是否存在于指定字段中
     * @param $val
     * @param $filed
     * @return bool
     */
    public function is_exists($val,$filed){
        $fields = $this->getDbFields();
        if( !in_array($filed,$fields) ){
            return false;
        }
        $count = $this->where(array($filed=>$val))->count();
        return $count?true:false;
    }

    /**
     * 密码验证
     * @param $user_id
     * @param $pass
     * @return bool
     */
    public function check_pass($user_id,$pass){
        $count = $this->where('user_id=%d and password="%s"',$user_id,$pass)->count();
        return $count ? true : false;
    }

    /**
     * 获取用户身份
     * @param $user_info
     * @return array
     */
    public function get_vip_name($user_info){
        $names = array(
            -1   => '离职',
            0   => '会员',
            1   => '员工',
            2   => '企业主'
        );
        $default_status     =   0;
        $status             =   $default_status;
        $is_vip             =   intval($user_info['is_vip']);
        $store_id           =   intval($user_info['store_id']);
        $store_master_id    =   M('store')->where('store_id=%d',$store_id)->getField('user_id');

        if(!$store_master_id) return array('name'=>$names[$default_status],'status'=>$default_status);//店铺不存在 返回默认

        if($is_vip === -1)       $status = -1;
        if($is_vip ===  0)       $status =  0;
        if($is_vip  >   0){

            if($store_master_id!=$user_info['user_id']){

                $status = 1;

            }else{

                $status = 2;
            }

        }

        return array('name'=>$names[$status],'status'=>$status);
    }

    /**
     * 获取kp日志
     * @param $user_id
     * @param $pagesize
     * @return array
     */
    public function get_user_kp($user_id, $pagesize=10){

        $account_where['user_id'] = array('eq',$user_id);
        $account_where['process_type'] = array('eq',PT_KP);
        $account_where['is_paid'] = array('eq',PS_PAYED);

        $level_where['uid'] = array('eq',$user_id);
        $level_where['stype'] = array('eq',YJ_TYPE_3);
        $level_where['state'] = array('eq',YJ_UNAUDIT);

        $user_account = M('UserAccount')->where($account_where)->select();
        $user_level = M('UserLevel')
            ->alias('ul')
            ->field('ul.*,oi.order_sn')
            ->join(' LEFT JOIN __ORDER_INFO__ oi on ul.orderid=oi.order_id')
            ->where($level_where)->select();

        $data = [];
        foreach ($user_account as $v){
            $res['user_id'] = $v['user_id'];
            $res['amount'] = $v['amount'];
            $res['is_paid'] = $v['is_paid'];
            $res['desc'] = $v['desc'];
            $res['add_time'] = $v['add_time'];
            $data[] = $res;
        }

        foreach($user_level as  $vv){
            $res1['user_id'] = $vv['uid'];
            $res1['amount'] = $vv['money'];
            $res1['is_paid'] = PS_PAYING;
            $res1['desc'] = '订单：'.$vv['order_sn'].'返利收入';
            $res1['add_time'] = $vv['addtime'];
            $data[] = $res1;
        }

        $Page = new \Think\Page(count($data),$pagesize);
        $show = $Page->show();

        $flag=array();
        foreach($data as $arr2){
            $flag[]=$arr2["add_time"];
        }
        array_multisort($flag, SORT_DESC, $data);
        $data = array_slice($data, $Page->firstRow, $Page->listRows);
        return array('list'=>$data,'show'=>$show,'total'=>$Page->totalPages);
    }

    /**
     * 获取用户信息
     * @param $where
     * @param string $fields
     * @return mixed
     */
    public function get_user_wx($where, $fields = ''){
        if (!$fields) {
            $fields = 'u.*, w.*, ur.store_id sid';
        }
        return M('Users')->field($fields)
            ->alias('u')
            ->join('LEFT JOIN __USER_WX__ w on u.user_id=w.user_id')
            ->join('LEFT JOIN __USER_RELEVANCE__ ur on u.user_id=ur.user_id')
            ->where($where)
            ->find();
    }

    /**
     * 获取用户总消费金额
     * @param $user_id
     * @param $store_id
     * @return mixed
     */
    public function get_user_order_money($user_id, $store_id){
        $where = [
            'user_id' =>$user_id,
            'store_id'=>$store_id,
            //'order_status'=>OS_SUCCESS,
            'pay_status'=>PS_PAYED,
        ];
        return M('OrderInfo')->field('sum(order_amount) as money')->where($where)->find();
    }

    /**
     * 获取新更新二维码信息
     * @param $user_info
     * @param $store_id
     * @return array
     */
    public  function get_qr_info($user_info,$store_id) {
        $qr = $this->user_qr_get($user_info['user_id']);
        debug($qr, 1);
        if(empty($qr)){   // 空数据  需要请求数据
            $res = $this->get_share_qrcode($user_info['user_id'], $qr, $store_id);
            debug($res, 1);
            if ($res['error'] > 0) {
                return $res;
            } else {
                $info = $res['data'];
            }
            $info['user_avatar'] = img_url($user_info['user_avatar']);
            $info['user_name'] = $user_info['user_name'];
            $info['nickname'] = $user_info['nickname'];
        } else {
            if($qr['expire_seconds'] == 0 && $qr['img_address']){  //永久的  不需要请求
                $info = $qr;
            }else{
                if(($this->time - $qr['add_time']) > $qr['expire_seconds'] || !$qr['img_address']){ //过期了
                    $res = $this->get_share_qrcode($user_info['user_id'], $qr, $store_id);
                    debug($res, 1);
                    if ($res['error'] > 0) {
                        return $res;
                    } else {
                        $info = $res['data'];
                    }
                    $info['user_avatar'] = img_url($user_info['user_avatar']);
                    $info['user_name'] = $user_info['user_name'];
                    $info['nickname'] = $user_info['nickname'];
                }else{
                    $info = $qr;
                }
            }
        }
       /* if (empty($qr) or (($this->time - $qr['add_time']) > $qr['expire_seconds'] && $qr['expire_seconds'] !=0 )) {
            $res = $this->get_share_qrcode($user_info['user_id'], $qr, $store_id);
            debug($res, 1);
            if ($res['error'] > 0) {
                return $res;
            } else {
                $info = $res['data'];
            }
            $info['user_avatar'] = img_url($user_info['user_avatar']);
            $info['user_name'] = $user_info['user_name'];
            $info['nickname'] = $user_info['nickname'];
        } else {
            $info = $qr;
        }*/
        if($info){//获取店铺信息
            $info['store_config'] = M('store_qrconfig')->where(['store_id'=>$store_id])->find();
            debug($info, 1);
            $info['store_config']['qr_config'] = unserialize($info['store_config']['qr_config']);
            if($info['store_config']['bg_img']){
                $info['store_config']['bg_img'] = img_url($info['store_config']['bg_img']);
            }else{
                $info['store_config']['bg_img'] = img_url(C('default_qrcode'));
            }
        }
        $info['img_address'] = img_url($info['img_address']);
        return result_ajax(0,'获取成功！', $info);
    }

    /**
     * 获取二维码
     * @param $user_id
     * @param array $qr_info
     * @param $store_id
     * @return array
     */
    protected function get_share_qrcode($user_id, $qr_info = array(), $store_id){
        $time = $this->time;
        $expire_seconds = 0;    //二维码有效期  若为0 则是永久二维码
        if ($qr_info && $qr_info['id'] > 0) {       //存在记录
            $qr_md5 = $qr_info['md5'];
            $file = $qr_info['img_address'];
            $qr_id = $qr_info['id'];
        } else {                                    //新添加
            $qr_md5 = md5(get_rand_str(22,2) . $time);
            $qr_id = $this->user_qr_add($user_id, $qr_md5, '', $expire_seconds);//保存到数据库
        }
        $result = json_decode($this->get_wx_qrcode($store_id, $qr_id, $expire_seconds), 1);//读取QR信息
        debug($result, 1);
        if ($result && $result['error'] == 200) {
            $filepath = $result['data']['filepath'] . $result['data']['filename'];
            if(isset($qr_id)){
                $this->user_qr_edit($qr_id, $qr_md5, $filepath, $expire_seconds);//保存到数据库
            }
            $info['id'] = $qr_id;
            $info['md5'] = $qr_md5;
            $info['user_id'] = $user_id;
            $info['img_address'] = $filepath;
            $info['add_time'] = $time;
            $result = result_ajax(0, '', $info);
        } else {
            $result = result_ajax(302,'没有读取到二维码信息');
        }
        return $result;
    }

    protected function img_qrcode($qr_data, $level = 'L', $icon = 0, $store_id=0) {
        //$url = C('UPLOAD_SERVER') . U('img/Uploader/qrcode') . '?module=Wap&' . C('VAR_AJAX_SUBMIT') . '=1';
        $url =  get_server('IMG_SERVER', '/Uploader/qrcode',
            [
                'module'                =>  'Wap',
                C('VAR_AJAX_SUBMIT')    =>  1,
                C("VAR_SESSION_ID")     =>  AesEnCrypt(session_id()),
                C("store_token_name")   =>  $store_id
            ],  1);
        $data = array(
            'data'      => urlencode($qr_data),
            'level'     =>  $level,
            'icon'      =>  $icon
        );
        return json_decode(curlGet($url,'post',$data),1);
    }

    public function get_wx_qrcode($store_id, $qrcode_id, $expire_seconds){
        $controller = new Plugins\QrcodeController();
        debug($controller, 1);
        $controller->wxuser = get_config($store_id);
        $result = $controller->get_wxqrcode($qrcode_id, $expire_seconds);
        return $result;
        /*$url =  get_server('IMG_SERVER', '/Uploader/wx_qrcode',
            [
                'module'                =>  'Wap',
                C('VAR_AJAX_SUBMIT')    =>  1,
                C("VAR_SESSION_ID") =>  AesEnCrypt(session_id()),
                C("store_token_name") =>  $data['store_id']
            ],  1);
        return json_decode(curlGet($url,'post',$data),1);
        return $result;*/
    }
}
 