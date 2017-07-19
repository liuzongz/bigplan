<?php
namespace Sales\Model;

use Sales\Element\Activity;
use Sales\Element\Element;
use Sales\Element\Project;

class UsersActivityModel extends BaseModel{
    protected $tableName = 'users_activity';

    /**
     * 获取参与相关的信息
     * @param int $id
     * @return array|null
     */
    public function get_attend_info($id){
        return M('users_activity')->where(['id'=>(int)$id])->find();
    }

    /**
     *  用户参与活动添加
     * @param $data array  用户填写的表单
     * @return array
     */
    public function join($data){
        if(!preg_match('/^[\w'. REG_ZH .']{2,10}$/u',$data['user_name'])){
            return error_msg(__LINE__, '用户名称为2-10个中英文字符');
        }

        if(!preg_match('/^1[3|4|5|7|8]?\d{9}$/',$data['phone_number'])){
                return error_msg(__LINE__, '-请输入正确的联系电话-');
        }
        foreach($data['data_config']['project'] as $k=>$v){
                if($v['check']=='on' && $data['data_msg'][$k]==''){
                     return error_msg(__LINE__, $v['name'].'必需填写-');
                    //$this->ajaxReturn(result_ajax(406,$v['name'].'必需填写-'));
                }else{
                    $data['config'][$k] = I('POST.'.$k);
                }
        }

        $ActInfo = $this->where(array('user_id'=>$data['user_id'],'action_id'=>$data['action_id']))->find();
        //验证活动规则
        $result = $this->checkConfig($data['config']);
        if($result['error']!=0){
            return error_msg($result['error'], $result['msg']);
        }
        $insertArr = array(
            'phone_number'=> $data['phone_number'],
            'user_name' => $data['user_name'],
            'action_id'  =>  intval($data['action_id']),
            'user_id'  =>  intval($data['user_id']),
            'act_updatetime' => time(),
            'config'  => serialize($data['config']),
        );
        if($ActInfo){
            $data['click'] = (int)($ActInfo['click'])+1;
            $result = $this->where(array('user_id'=>intval($data['user_id']),'action_id'=>intval($data['action_id'])))->save($insertArr);
            if($result) $result = $ActInfo['id'];//支付时需要参与id
        }else{
            $insertArr['act_addtime'] = time();
            $insertArr['click'] = 1;
            $result = $this->add($insertArr);
        }
        if($result){
            return error_msg(200, '成功参与活动',$result);
        }else{
            return error_msg(__LINE__, L('MSG_DB_ADD'));
        }

    }

    /**
     *  获取用户参加的活动列表
     * @param $user_id int 用户ID
     * @param $act_status string 活动状态
     * @param $page_size int 活动状态
     * @return mixed|array
     */
    public function get_act_list($user_id, $act_status, $page_size=10){
        $where['a.user_id'] = (int)$user_id;
        $time = time();
        if($act_status == 'doing'){//进行中
            $where['b.act_starttime'] = array('elt', $time);
            $where['b.act_endtime'] = array('egt', $time);
        }elseif($act_status == 'done') {//已完成
            $where['b.act_endtime'] = array('elt', $time);
        }elseif($act_status == 'will'){//未开始
            $where['b.act_starttime'] = array('gt', $time);
        }elseif($act_status == 'all'){

        }else{
            return false;
        }

        $count = M('UsersActivity')->alias('a')->where($where)
            ->join(' LEFT JOIN __FAVOURABLE_USER_ACTIVITY__ b ON a.action_id=b.id')
            ->count();

        $Page = new \Think\Page($count, $page_size);
        $curpage = empty($_GET['p']) ? 1 : max(intval($_GET['p']), 1);
        $Page->show();
        $info = M('UsersActivity')->where($where)->alias('a')
            ->field('b.*, b.id, b.act_id, c.act_name, c.act_image')
            ->join('LEFT JOIN __FAVOURABLE_USER_ACTIVITY__ b ON a.action_id=b.id')
            ->join('LEFT JOIN __FAVOURABLE_ACTIVITY__ c ON b.act_id=c.act_id')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('a.user_id desc,a.id desc')
            ->select();

        foreach ($info as &$val){
            $val['url'] = U('active/detail') . '?aid=' . AesEnCrypt($val['act_id']). '&act_id='.AesEnCrypt($val['id']);
            $val['is_my_act'] = $user_id != $val['user_id'] ? 0 : 1;
            $val['chart_url'] = U('active/chart') . '?aid=' . AesEnCrypt($val['act_id']). '&act_id='.AesEnCrypt($val['id']);
            $val['active_status'] = $this->active_status($val['act_starttime'],$val['act_endtime']);
            $val['act_image'] = img_url($val['act_image']);
        }
        return array('pagecount'=>$Page->totalPages, 'curpage'=>$curpage, 'asume'=>$info);
        //return $info;
//        $curpage = empty($_GET['p']) ? 1 : max(intval($_GET['p']),1);
//        return array('str'=>$info, 'pagecount'=>$Page->totalRows, 'curpage'=>$curpage);
    }

    /**
     *  获取用户参加的活动列表
     * @param $user_id int 用户ID
     * @param $act_status string 活动状态
     * @param $page_size int 活动状态
     * @return mixed|array
     */
    public function get_publish_act($store_token, $user_id, $act_status, $page_size=10){
        $where['a.user_id'] = (int)$user_id;
        $time = time();
        if($act_status == 'doing'){//进行中
            $where['a.act_starttime'] = array('elt', $time);
            $where['a.act_endtime'] = array('egt', $time);
        }elseif($act_status == 'done') {//已完成
            $where['a.act_endtime'] = array('elt', $time);
        }elseif($act_status == 'will'){//未开始
            $where['a.act_starttime'] = array('gt', $time);
        }elseif($act_status == 'all'){

        }else{
            return false;
        }

        $count = M('FavourableUserActivity')->alias('a')
            ->join('LEFT JOIN __FAVOURABLE_ACTIVITY__ b ON a.act_id=b.act_id')
            ->where($where)->count();

        $Page = new \Think\Page($count, $page_size);
        $curpage = empty($_GET['p']) ? 1 : max(intval($_GET['p']), 1);
        $Page->show();
        $info = M('FavourableUserActivity')->alias('a')->where($where)
            ->field('a.*, b.act_name, b.act_image')
            ->join('LEFT JOIN __FAVOURABLE_ACTIVITY__ b ON a.act_id=b.act_id')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('a.id desc')
            ->select();

        foreach ($info as &$val){
            $val['url'] = get_server("SALES_SERVER" , '/active/detail' ,
                [   'aid'                   => AesEnCrypt($val['act_id']),
                    'act_id'                => AesEnCrypt($val['id']),
                    C('store_token_name')   => $store_token,
                ] , 1);
            $val['is_my_act'] = 0;
            $val['active_status'] = $this->active_status($val['act_starttime'],$val['act_endtime']);
            //$val['act_image'] = img_url($val['act_image']);
            $val['act_image'] = img_url($val['act_image']);
            $val['chart_url'] = get_server("SALES_SERVER" , '/user/chart' ,
                [
                    'act_id'    => AesEnCrypt($val['id']),
                    C('store_token_name')   => $store_token,
                ] , 1);
            $val['id'] = AesEnCrypt($val['id']);

        }
        return array('pagecount'=>$Page->totalPages, 'curpage'=>$curpage, 'asume'=>$info);
        //return $info;
    }

    /**
     *  获取活动状态
     * @param $act_starttime string 活动开始时间
     * @param $act_endtime string 活动结束时间
     * @return string
     */
    public function active_status($act_starttime,$act_endtime){
        $status = "";
        $nowtime = time();
        if($act_starttime <= $nowtime && $act_endtime >= $nowtime){
            $status = "进行中";
        }elseif($act_endtime < $nowtime){
            $status = "已结束";
        }elseif($act_starttime > $nowtime){
            $status = "未开始";
        }
        return $status;
    }


    /**
     *  验证用户参与活动
     * @param $config array 用户自定义表单
     * @return array
     */
    public function checkConfig($config){
        foreach($config as $k=>$v){
            if($v)
                $V = array($v,'/^[\w'.REG_ZH.']{1,20}$/u',__LINE__.'-表单名称为1-20个中英文非特殊字符');
            }
        return $config;
        //return $this->confirm($v);
    }

    /**
     *  获取用户参加的活动列表
     * @param $act_id int 用户ID
     * @param $page_size int 活动状态
     * @param $type string 活动状态
     * @param $is_pay string paid|已付款是否判断付款
     * @return array
     */
    public function get_user_list($act_id, $is_pay='', $type ='', $is_hide, $page_size = 10){
        if(!$act_id) return array('str'=>[], 'pagecount'=>1, 'curpage'=>1);
        $where['action_id'] = (int)$act_id;
        $where['parent_id']  =  0;

        /**@var Project $act_info*/
        $act_info =  Element::createObject('project', array($act_id));
        $act_config = unserialize($act_info->data['config']);

        if($is_pay== 'paid'){
            $where['b.pay_status'] = PS_PAYED;
        }
        $count = $info = M('UsersActivity')->alias('a')
            ->join(' LEFT JOIN __FAVOURABLE_ORDER__ b ON a.id = b.attend_id')
            ->where($where)
            ->count();
        $Page = new \Think\Page($count, $page_size);

        $info = M('UsersActivity')->alias('a')
            ->join(' LEFT JOIN __FAVOURABLE_ORDER__ b ON a.id = b.attend_id')
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('a.id DESC')
            ->select();
        $str = '';

        $curpage = empty($_GET['p']) ? 1 : max(intval($_GET['p']), 1);
        $Page->show();
        $act_list = M('UsersActivity')->where(['action_id'=>(int)$act_id, 'parent_id'=>['gt',0]])->select();

        foreach($info as $k => &$val){
            //设置隐藏姓名和手机号
            $mb_string2 = '';
            $mb_string1 = mb_substr($val['user_name'], 0, 1, 'utf-8');
            if( mb_strlen($val['user_name'], 'utf-8') >2){
                $mb_string2 = mb_substr($val['user_name'], -1, 1, 'utf-8');
            }
            $val['money_format'] = $act_config['costPrice'];
            foreach($act_list as $v){
                if($v['parent_id'] == $val['id']) {
                    $val['money_format'] -= $v['money'];
                }
            }
            $val['money_format'] = $val['money_format'] . '元';
            if($val['money_format'] == $act_config['minimumPrice']) $val['money_format'] = '已减至底价';
            if($is_hide){
                $val['user_name'] = $mb_string1 . '*' . $mb_string2;
                $val['phone_number'] =  substr_replace($val['phone_number'], '****', 3, 4);
            }
            $str .= '<li class="flex"><div>' . ($k + 1 + $page_size * ($curpage - 1)) . '</div><div>' . $val['user_name'] . '</div><div>' . $val[$type] . '</div></li>';

        }

        return array('str'=>$str, 'pagecount'=>$Page->totalPages, 'curpage'=>$curpage, 'asume'=>$info);
    }

    /**
     * 统计参与活动的人数
     * @param $attend_id int 参与id
     * @param $pay_status bool 支付状态
     * @return mixed
     */
    public function get_attend_total($attend_id, $pay_status=false){
        if(!$attend_id) return null;
        if(!$pay_status){
            $total = M('UsersActivity')->where(['action_id'=>(int)$attend_id])->count();
        }else{
            $total = M('FavourableOrder')->where(['attend_id'=>(int)$attend_id,'pay_status'=>PS_PAYED])->count();
        }
        return $total;
    }

    /**
     * 帮好友获得优惠数据
     * @param $user_id int 用户id
     * @param $act_id  int 活动id
     * @param $money  float 金额
     * @param $attend_id int 参与id为0时为活动发布者
     * @return bool|mixed
     */
    public function set_rebate($user_id, $act_id, $money = 0.00, $attend_id=0){
        $time = time();
        $array = array(
            'user_id'       => !$user_id ? 0 :$user_id,
            'action_id'     => $act_id,
            'act_addtime'   => $time,
            'act_updatetime'=> $time,
            'money'         => $money,
            'parent_id'     => $attend_id,
        );
        return $this->add($array);
    }
}