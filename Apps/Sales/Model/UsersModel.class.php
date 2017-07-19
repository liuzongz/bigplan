<?php
namespace Sales\Model;

class UsersModel extends BaseModel {


    protected $tableName = 'users';

    public function login(){

    }

    public function register(){

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
     * 添加用户
     * @param $classData
     * @param int $uid
     * @return mixed|string
     */
    public function add_user($classData, $uid = 0) {
        $data['user_name']      = $classData['user_name'];
        $data['nickname']       = $classData['nickname']?:'';
        $data['password']       = md5($classData['pass']);
        $data['user_areainfo']  = $classData['user_areainfo'];
        $data['province']       = $classData['province'];
        $data['city']           = $classData['city'];
        $data['district']       = $classData['district'];
        $this->data($data)->add();
        $insertID = $this->getLastInsID();
        return $insertID;
    }

    /**
     * 获取用户信息
     * @param $where
     * @param string $fields
     * @return mixed
     */
    public function get_userinfo($where, $fields = ''){
        if (!$fields) {
            $fields = 'user_id,openid,user_name,nickname,password,user_avatar,mobile_phone';
        }
        return M('users')->field($fields)->where($where)->find();
    }

    /**
     * 用户余额变动
     * @param $id
     * @param $money
     * @param $stage
     * @param $desc
     * @return bool|string
     */
    public function set_user_money($id, $money, $stage, $desc){
        $base_money = M('users')->where(['user_id'=>intval($id)]);
        if ($money >= 0) {
            $base_money->setInc('user_money', $money);
        } else {
            $base_money->setDec('user_money', abs($money));
        }
        return $this->add_cash_log([
            'store_id'      =>  intval($id['user_id']),
            'user_id'       =>  intval($id['user_id']),
            'stage'         =>  $stage,
            'price'         =>  $money,
            'type'          =>  0,
            'desc'          =>  $desc,
        ]);
    }

    /**
     * 添加现金日志
     * @param $data array(
     *      obj_type    记录类型   0用户日志  1店铺日志
     *      obj_id      obj_type=0 ? obj_id=user_id : obj_id=store_id
     *      cash_sn     日志序列号
     *      stage       日志类型 枚举 system 系统自动 recharge 充值 cash 提现 order 订单 admin 管理员手动修改 income收入 hkhp汇客惠品转入
     *      price       变动金额    减少为负数
     *      type        金额类型    0表示可用金额 1表示冻结金额
     *      desc        描述
     * )
     * @return bool|string
     */
    public function add_cash_log($data) {
        $sn = $this->create_cash_sn();
        $add_data = [
            'store_id'  =>  $data['store_id'],
            'user_id'   =>  $data['user_id'],
            'cash_sn'   =>  $sn,
            //'user_name' =>  $user_info['user_name'],
            'stage'     =>  $data['stage'],
            'type'      =>  $data['type'],
            'price'     =>  $data['price'],
            'addtime'   =>  time(),
            'desc'      =>  $data['desc']
        ];
        $m = M('CashLog');
        if ($m->data($add_data)->add()) {
            return $sn;
        } else {
            return false;
        }
    }

    /**增加用户支付记录*/
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

    /**设置记录支付状态*/
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
     * 获取二维码配置
     */
    public function getCard($store_id){
        $store_id  = $store_id ? $store_id : 92;
        $where = array('store_id'=>$store_id);
        $info = M('store_qrconfig')->where($where)->find();
        $info['qr_config'] = unserialize($info['qr_config']);
        $info['user_info'] = M('users')->where(array('a.user_id'=>$this->_login_info['user_id']))->alias('a')
            ->join('LEFT JOIN __USER_QR__ b ON a.user_id=b.user_id')
            ->find();
        return $info;


    }

}