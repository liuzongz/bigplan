<?php
namespace Passport\Model;

use Think\Model;

abstract class BaseModel extends Model{

    //执行严格字段检查
    protected $options  = array('strict' => true);

    //login_info
    protected $_login_info = array();

    //一次请求只进行一次表单令牌检查
    private static $checkToken = false;

    protected $patchValidate = false;

    private $detailError = array();

    protected $store_id = '';
    // 最近错误信息
    protected $error = '';

    protected $time = '';

    protected function _initialize(){
        $this->store_id = session('store_id');
        //login_info
        $this->_login_info = session('login_info_' . $this->store_id);
        L(
            array(
                '_TOKEN_ERROR_'         => __LINE__.'-页面已过期或不合法',
                '_DATA_TYPE_INVALID_'   => __LINE__.'-'.L('_DATA_TYPE_INVALID_').'(tp)'
            )
        );
        $this->time = NOW_TIME;
    }

    /**
     * thinkphp自动验证书写规则
     * 1-验证字段名称
     * 2-正则表达式、thinkphp内置标识符、本模型方法、函数(根据第5个参数的意义而定)
     * 3-错误信息
     * 4-验证时机(插入/更新/等情况下才匹配验证,留空则必须验证)
     * 5-确定第2参数意义的规则,默认留空是regex(regex,unique,function,callback,confirm,expire,between等)
     * 6-验证时机2(必须验证、值不为空验证、默认留空时值isset就验证)
     * 7-为验证方法或函数提供附加参数,可以用数组方式提供多个参数
     *
     * 自定义验证规则书写方式
     * thinkphp各个验证条件顺序不变,数量可少不可多
     * 键代表验证字段名,第一个验证参数用字段值代替,一个字段有多个验证条件的,键用不同的大小写区分
     *
     * @param $validate
     * @return array
     */
    private function varSeparate($validate){
        $field = array();
        foreach($validate as $k => &$v){
            $kk = strtolower($k);
            $field[$kk] = $v[0];//收集需要验证的数据集
            $v[0] = $kk;//将验证规则的第一参数还原为字段名
            $v = $this->varPass($v);
        }
        return array($field, $validate);
    }

    private function varPass($validate){
        if(isset($validate[4]) && in_array($validate[4], array(/*'function',*/'callback'))) {//todo:某些系统函数(is_file)参数个数已定,不能乱加参数,这里暂去掉function
            //存在回调的情况下[1字段值,2字段名,3原始错误提示]
            $msg = explode('-', $validate[2]);
            if(isset($validate[6])){
                $var = (array)$validate[6];
                array_unshift($var, $validate[0], $msg[1]);
            }else{
                $var = array($validate[0], $msg[1]);
            }
            //即使没有[5]跳过也没事
            $validate[6] = $var;
        }
        return $validate;
    }

    public function confirm($data, $validate=null, $type=''){
        //有可能在模型中使用_validate属性来设置验证规则(现统一显示设置验证规则)
        if(empty($validate)){
            $data       = $this->varSeparate($data);
            $validate   = $data[1];
            $data       = $data[0];
        }else{
            //TP原生验证数组使用普通索引,避免二次转换
            foreach($validate as $field => &$valid){
                if(is_numeric($field)){
                    $valid  = $this->varPass($valid);
                }
            }
        }
        $this->validate($validate);

        //添加表单令牌数据自动验证
        $token_origin   = C('TOKEN_ON');
        $token          = (IS_GET || IS_AJAX) ? false : $token_origin;
        C('TOKEN_ON', $token);
        if($token){
            $token_name         = C('TOKEN_NAME', null, '__hash__');
            $data[$token_name]  = I($token_name);
        }

        //开始create验证
        //type代表当前的验证时机,当前的验证时机要和验证规则匹配才会验证
        //但当验证规则的验证时机为空时,则总是需要验证
        $this->detailError  = null;//验证之前清空错误
        $this->error        = null;

        //二次进入,不再验证表单令牌
        if(self::$checkToken){
            C('TOKEN_ON', false);
        }
        $result = $this->create($data, $type);
        self::$checkToken = true;

        //还原表单令牌,防止影响正常post页面表单的生成
        C('TOKEN_ON', $token_origin);

        if(false === $result){
            list($error, $msg) = $this->formatError();
            return error_msg($error, $msg);
        }else{
            return error_msg(0, '验证通过');
        }
    }

    private function formatError(){
        if($this->patchValidate){
            //批量验证时error是数组,键名验证字段名,键值是错误信息(xxx-具体错误)
            foreach($this->error as $field => $error){
                $detail = $this->getDetailError($field);
                if($detail){
                    //用明细重置原始错误
                    $this->error[$field] = $this->resetError($error, $detail);
                }
            }
            //todo:批量验证情况下错误如何显示待定
            return array(__LINE__, '批量验证情况下错误如何显示待定');
        }else{
            //非批量情况下,错误只有一个,无需field名称
            if($detail = $this->getDetailError()){
                $this->error = $this->resetError($this->error, $detail);
            }
            return explode('-', $this->error, 2);
        }
    }

    protected function getDetailError($field=''){
        if(count($this->detailError) == 1){
            return reset($this->detailError);
        }elseif(isset($this->detailError[$field])){
            return $this->detailError[$field];
        }else{
            return null;
        }
    }

    private function resetError($error, $detail){
        list($error) = explode('-', $error, 2);
        return $error.'-'.$detail;
    }

    /**
     * 生成不重复订单号并插入ID
     * @return string
     */
    protected function create_order(){
        do {
            $order_sn = create_sn(PREFIX_ORDER);
        } while ($this->where('order_sn="' . $order_sn . '"')->count() > 0);
        return $order_sn;
    }

    /**
     * 新建一个订单号
     * @param string $star
     * @return string
     */
    protected function create_order_sn($star = ''){
        if ($star == '') $star = date('Ym',time());
        return $star . get_rand_str(8,1);
    }


    /**
     * 新建一个支付序列号
     * @param string $star
     * @return string
     */
    protected function create_cash_sn($star = '') {
        if ($star == '') $star = date('Ym',time());
        return $star . get_rand_str(8,1);
    }

    /**
     * 数据库动态连接
     * @param $data  数据库连接信息
     * @return Model
     */
    public function  db_connect($data){
        return $this->db(1,$data);
    }

    /**
     * 判断是否手机号码
     * @param $mobile       $手机号码
     * @return bool
     */
    function is_mobile_num($mobile){
        $regex = '/13[0-9]{9}|15[0|1|2|3|5|6|7|8|9]\d{8}|18[0|5|6|7|8|9]\d{8}|17[0-9]\d{8}/';
        $arr = array('13','14','15','18','17');
        if(is_numeric($mobile) and strlen($mobile) == 11 and (in_array(substr($mobile,0,2), $arr))){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 用户余额变动 记录
     * @param $id
     * @param $money
     * @param $stage
     * @param $desc
     * @return bool|string
     */
    public function set_user_money($id, $money, $stage, $desc){
        $base_money = M('users')->where('user_id="' . intval($id['user_id']) . '"');
        if ($money >= 0) {
            $base_money->setInc('user_money', $money);
        } else {
            $base_money->setDec('user_money', abs($money));
        }
        return $this->add_cash_log([
            'store_id'      =>  intval($id['store_id']),
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

    /**
     * 用户冻结余额变动记录
     * @param $id
     * @param $money
     * @param $stage
     * @param $desc
     * @return bool|string
     */
    public function set_user_frozen($id, $money, $stage, $desc){
        $base_money = M('users')->where('user_id="' . intval($id['user_id']) . '"');
        if ($money >= 0) {
            $base_money->setInc('frozen_money', $money);
        } else {
            $base_money->setDec('frozen_money', abs($money));
        }
        record("修改用户余额功能：" . M('users')->getlastsql());
        return $this->add_cash_log([
            'store_id'      =>  intval($id['store_id']),
            'user_id'       =>  intval($id['user_id']),
            'stage'         =>  $stage,
            'price'         =>  $money,
            'type'          =>  1,
            'desc'          =>  $desc,
        ]);
    }

    /**
     * 去掉图片前的/
     * @param $url
     * @return string
     */
    function img_url($url) {
        return img_url($url);
    }

}


