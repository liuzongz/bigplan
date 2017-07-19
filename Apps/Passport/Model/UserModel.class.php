<?php
namespace Passport\Model;
class UserModel extends BaseModel {
    const SEND_EMAIL = 1;
    const SEND_SMS = 2;
    protected $tableName = 'Auth_Code';
    /**
     * 邮件或手机验证码记录
     * @param $obj    //对像
     * @param $verify   //内容
     * @param $type_id   //验证信息类型
     * @return string
     */
    public function authcode_add($obj, $verify, $type_id){
        $token = md5(get_rand_str(8,2));
        $this->where('add_time + expires < ' . $this->time)->delete();
        if ($id = $this->where('obj="' . $obj . '"')->getfield('id')) {
            $data = array(
                'verify'    =>  $verify,
                'add_time'  =>  $this->time,
                'ip'        =>  $this->ip,
                'token'     =>  md5($token),
            );
            \Think\Log::record('修改发送短信：' . print_r($data,1) . "\nid=" . $id);
            $this->where(['id' => $id])->save($data);
        } else {
            $data = array(
                'type_id'   =>  $type_id,
                'obj'       =>  $obj,
                'verify'    =>  $verify,
                'add_time'  =>  $this->time,
                'expires'   =>  600,
                'ip'        =>  $this->ip,
                'token'     =>  md5($token),
            );
            \Think\Log::record('添加发送短信：' . print_r($data,1));
            $this->add($data);
        }
        return $token;
    }

    /**
     * 获取数据库验证码
     * @param $obj
     * @return mixed
     */
    public function authcode_get($obj){
        $where = ['obj'=>$obj];
        /*if (is_mobile($obj)) {
            $where['type_id'] =  $this::SEND_SMS;
        } else {
            $where['type_id'] = $this::SEND_EMAIL;
        }*/
        return $this->where($where)->find();
    }

    public function authcode_set($id) {
        return $this->where(['id'=>$id])->save(['expires'=>0]);
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
}