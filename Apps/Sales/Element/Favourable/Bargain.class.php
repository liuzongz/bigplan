<?php
namespace Sales\Element\Favourable;

use Sales\Element\Activity;
use Sales\Element\Element;
use Sales\Element\Project;

class Bargain extends Favourable {
    public function getConfig(){
        return array(
            'cate'      => $this->templateConfig['cate'],
            'item_name'      => array(
                'value'     => '疯狂砍价火热进行中',
                'title'     => '活动标题',
                'desc'      => '全民疯狂砍价',
                'validate'  => array(
                    array('man_num','/.?/','人数为1-1000之间的整数'),
                ),
            ),
            'limit'      => array(
                'value'     => '999',
                'title'     => '活动参加人数',
                'desc'      => '人数为1-100之间的整数',
                'validate'  => array(
                    array('limit','/^[1-9]\d{0,2}$|^1000$/','人数为1-1000之间的整数'),
                ),
            ),
            'prize_nub'      => array(
                'value'     => '100',
                'title'     => '奖品数量',
                'desc'       =>  '金额为1-1000之间的数字',
                'validate'  => array(
                    array('prize_nub','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),
            'costPrice'      => array(
                'value'     => '100',
                'title'     => '商品原价',
                'desc'       =>  '金额为1-1000之间的数字',
                'validate'  => array(
                    array('costPrice','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),
            'minimumPrice'      => array(
                'value'     => '10',
                'title'     => '商品最低价',
                'desc'       =>  '金额为1-1000之间的数字',
                'validate'  => array(
                    array('minimumPrice','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),
            'random_number'      => array(
                'value'     => 0,
                'title'     => '最终砍完次数',
                'desc'       =>  '金额为1-1000之间的数字',
                'validate'  => array(
                    array('random_number','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),
            'random_most'      => array(
                'value'     => '1',
                'title'     => '每次最低砍价金额',
                'desc'       =>  '金额为1-1000之间的数字',
                'validate'  => array(
                    array('money','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),
            'random_least'      => array(
                'value'     => '10',
                'title'     => '每次最高砍价金额',
                'desc'       =>  '金额为1-1000之间的数字',
                'validate'  => array(
                    array('money','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),
            'act_rule_desc'      => array(
                'value'     => "1.点击“我要报名”，参加活动。\n\n2.接龙总人数达标即可享受对应档优惠。\n\n3.接龙失败诚意金原路退回，成功则诚意金不退。\n\n4.加入接龙的人越多越优惠全员享受同一个优惠。",
                'title'     => '活动规则',
                'desc'       =>  '参与活动的规则',
                'type'      =>   'textarea',
                'validate'  => array(
                    array('act_rule','/.?/','活动规则填写不规范'),
                ),
            ),
            'act_rule_name'      => array(
                'value'     => '活动规则',
                'title'     => '活动规则名称',
                'desc'       =>  '参与活动的规则',
                'type'      =>   'textarea',
                'validate'  => array(
                    array('act_rule_name','/.?/','活动规则名称填写不规范'),
                ),
            ),
            'act_desc_name'      => array(
                'value'     => '机构介绍',
                'title'     => '机构介绍',
                'desc'       =>  '参与活动的规则',
                'type'      =>   'textarea',
                'validate'  => array(
                    array('act_rule_name','/.?/','活动规则名称填写不规范'),
                ),
            ),
        );
    }

    public function price(Activity $activity){
        $this->checkActivity($activity);
        return Element::createObject(array('class'=>'price', 'RMB'=>$activity->goods->price));
    }

    public function attend(Activity $activity, $user_id)
    {
        parent::attend($activity, $user_id);
        if(!$activity->project) return false;
        $config = unserialize($activity->project->data['config']);
        if($config['prize_nub'] - count($activity->getDataOfAttend($user_id)) <= 0)
            return false;

        return !M('UsersActivity')->where(['user_id'=>$user_id,'action_id'=>$activity->project->data['id'], 'parent_id'=>0])->find();
    }

    public function getInfo(Activity $activity,$act_id,$user_id,$attend_id){
        $time = time();
        if($act_id){
            /**@var Project $project*/
            $project = Element::createObject('project', array($act_id));
            $data = $project->data;
            $data['config'] = unserialize($project->data['config']);
            $data['act_desc'] = $this->checkStore(unserialize($data['act_desc']));
            $data['config']['detail_img'] = img_url($data['config']['detail_img']);
            $data['rebate_info'] = $this->rebateCount($act_id, $user_id);//减价信息
            $my_join_info = M('users_activity')->where(['user_id'=>(int)$user_id, 'action_id'=>(int)$act_id, 'parent_id'=>0])->find();
            $attend_info = M('users_activity')->where(['id'=>(int)$attend_id, 'parent_id'=>0])->find();
            $data['rebate'] = 0;
            if($attend_id){
                $data['join_user_name'] = $attend_info['user_name'];
                $data['act_money'] = $data['config']['costPrice'] - M('UsersActivity')->where("id={$attend_id} or parent_id={$attend_id}")->getField('sum(money)');
            }else{
                $data['act_money'] = $data['config']['costPrice'];
            }

            //奖品剩余个数
            $data['prize_part'] = $data['config']['prize_nub'] - M('UsersActivity')->where(array('action_id'=>$act_id, 'parent_id'=>0))->count();

            $data['my_act_url'] = get_server('SALES_SERVER', '/active/detail',
                [
                    'aid'    => AesEnCrypt($activity->data['act_id']),
                    'act_id' => AesEnCrypt($act_id),
                    'attend_id' => AesEnCrypt($my_join_info['id']),
                ], 1);
            $data['config']['share_desc'] = str_replace(array("\r\n","\r","\n"),"",msubstr($data['config']['detail'],0, 30));
            $data['config']['share_img'] = 'http:' . img_url($data['config']['detail_img']);
            $data['config']['detail'] = $this->checkStore($data['config']['detail']);
            $data = array_merge($data, $this->joinStatus($user_id, $act_id, $attend_id));
        }else{
            $data = [];
            foreach($activity->config as $key => $val){
                $data['config'][$key] = $val['value'];
            }
            $data['item_name'] = $data['config']['item_name']; //活动标题
            $data['act_starttime'] = $time;
            $data['act_endtime'] = $time+24*3600*7;//初始结束化时间
            $data['config']['share_desc'] = str_replace(array("\r\n","\r","\n"),"",msubstr($data['config']['detail'],0, 30));
            $data['config']['detail_img'] = img_url($data['config']['detail_img']);
        }
        return $data;
    }

    /**
     * 设置用户的参与状态
     * @param $user_id int
     * @param $act_id int
     * @param $attend_id int
     * @return array
     */
    public function joinStatus($user_id, $act_id, $attend_id){

        $data = M('UsersActivity')->where(array('user_id'=>(int)$user_id,'action_id'=>$act_id, 'parent_id'=>0))->find();
        if(!$data && $attend_id){
            $status = 4;
        }elseif($data && $data['id'] == $attend_id){
            $status = 3;
        }elseif($data && $data['id'] != $attend_id){
            $status = 2; //不在自己的主活动状态情况下
        }else{
            $status = 1;
        }

        return ['is_join'=> isset($data), 'join_status'=>$status];
    }

    /**
     * 设置用户的参与状态
     * @param $user_id int
     * @param $attend_id int
     * @return array
     */
    public function joinStatus1($user_id, $attend_id){
        //获取参与主发布人
        $data = M('UsersActivity')->where(array('id'=>$attend_id, 'parent_id'=>0))->find();
        if($user_id && $data && $data['user_id'] == $user_id){
            $status = 3;
        }elseif($data){
            $status = 2; //不在自己的主活动状态情况下
        }else{
            $status = 1;
        }

        return ['is_join'=> isset($data), 'join_status'=>$status];
    }
    /**
     * 好友帮忙砍价统计
     * @param $act_id int
     * @param $user_id int
     * @return array
     */
    public function rebateCount($act_id, $user_id){
        $result =  M('UsersActivity')->where(array('action_id'=>$act_id, 'parent_id'=>$user_id))->select();
        $money = $count = 0;
        foreach ($result as $val){
            $count++;
            $money += $val['money'];
        }
        return ['money'=>$money, 'count'=>$count];
    }

    /**
     * 商家介绍
     * @param  $data  array  机构介绍
     * @return array
     */
    public  function checkStore($data){
        $result = array();
        foreach($data as $key => $val){
            if($val['img']){
                $result[$key] = "<img class='img_d' src='".img_url($val['img'])."'>";
            }else{
                $result[$key] = "<div class='div_d'>".$val['txt']."</div>";
            }
        }
        return $result;
    }

    /**
     * 验证砍价配置参数
     * @param array $data array
     * @return array|bool
     */
    public function checkStep($data){

//        if(empty($data['ceilingPrice']) || empty($data['minimumPrice'])){
//            return error_msg(__LINE__, "请输入减价范围值");
//        }

        if(!preg_match( '/^[1-9]\d{0,5}$/' , intval( $data['costPrice'] ))){
            return error_msg(__LINE__, '商品原价取值范围有误');
        }

        if(!preg_match('/^[1-9]\d{0,5}$/', intval($data['minimumPrice']))){
            return error_msg(__LINE__, '商品底价取值范围有误');
        }

        if(intval( $data['costPrice']) <= intval($data['minimumPrice'])){
            return error_msg(__LINE__, '商品原价必须大于最底价');
        }

        if(!preg_match( '/^[1-9]\d{0,5}$/' , intval( $data['random_most'] ))){
            return error_msg(__LINE__, '商品最高减价有误');
        }

        if(!preg_match( '/^[1-9]\d{0,5}$/' , intval( $data['random_least'] ))){
            return error_msg(__LINE__, '商品最低减价有误');
        }

        if(intval( $data['random_most']) >= intval($data['random_least'])){
            return error_msg(__LINE__, '商品最少减价不能大于最多减价');
        }

        if(!preg_match( '/^[1-9]\d{0,5}$/' , intval($data['prize_nub'] ))){    //奖品数量
            return error_msg(__LINE__, "请输入正确的奖品数量");
        }

//        if(!preg_match( '/^[1-9]\d{0,5}$/' , intval( $data['random_number'] ))){
//            return error_msg(__LINE__, '商品砍完次数有误');
//        }

        if(!preg_match( '/^[\w'.REG_ZH.']{1,10}$/u',$data['prizeinfo']['name'])){       //验证领奖信息
            return error_msg(__LINE__, "领奖信息标题格式不正确,请输入1-10个中文字符");
        }

        if(!preg_match( '/.?/',$data['prizeinfo']['date'])){
            return error_msg(__LINE__, "领奖信息格式不正确1");
        }

        if(!preg_match( '/.?/',$data['prizeinfo']['address'])){
            return error_msg(__LINE__, "领奖信息格式不正确2");
        }

        if(!preg_match( '/.?/',$data['prizeinfo']['tel'])){
            return error_msg(__LINE__, "领奖信息格式不正确3");
        }

        if(!$data['detail']){       //验证活动文字介绍
            return error_msg(__LINE__, "请输入活动介绍");
        }

        if(!preg_match( '/^[\w'.REG_ZH.']{1,10}$/u',$data['act_desc_name'])){
            return error_msg(__LINE__, "机构介绍标题格式不真正,请输入1-10个中文字符");
        }

//        if(!$data['detail_img']){   //验证活动图片介绍
//            return error_msg(__LINE__, "请输入上传一张产品介绍图片（最大2M）");
//        }
        if(!$data['act_rule_name'] || !preg_match( '/^[\w'.REG_ZH.']{1,20}$/u',$data['act_rule_name'])){     //验证活动规则
            return error_msg(__LINE__, "请输入正确的活动规则名称");
        }

        if(!$data['act_rule_desc']){     //验证活动规则
            return error_msg(__LINE__, "请输入活动规则详情");
        }
        $data['act_rule_desc']  = filter_str($data['act_rule_desc']);

        if(!preg_match('/^1[3|4|5|7|8]?\d{9}$/',$data['tel'])){     //验证联系电话
            return error_msg(__LINE__, "请填写正确的联系电话");
        }

        if(empty($data['detail'])){             //活动描述
            return error_msg(__LINE__, "请填写产品介绍信息");
        }else{
            $di = 0;
            $dv_img = array_column($data['detail'], 'img');
            foreach ($dv_img as $ax){
                if(!preg_match('/[^\s]+\.(jpg|gif|png|bmp)$/',$ax)){
                    return error_msg(__LINE__, "产品描述图片格式错误");
                }
            }
            foreach($data['detail'] as $dk=>&$dv){
                if($dv['txt']){
                    $di++;
                    if(strlen($dv['txt']) > 600){
                        return error_msg(__LINE__, "请将产品描述的文字长度控制在600以内");
                    }
                    $dv['txt']  = filter_str($dv['txt']);
                }
            }
            if($di <= 0 ){
                return error_msg(__LINE__, "请至少填写一段产品描述文字");
            }
        }

        //验证自定义表单
        foreach ($data['project'] as $key => $val ){
            if(!empty( $val['name'] )){
                if(!preg_match( '/^[\w'.REG_ZH.']{1,20}$/u',$val['name'] )){
                    return error_msg(__LINE__, "自定义表单名称为1-20个中英文非特殊字符");
                }
            }else{
                unset($data['project'][$key] );
            }
        }
        return error_msg(0, '验证成功',$data);
    }

    /**
     * 获取砍价的随机数
     * @param $config array
     * @param $attend_id int 参与或的ID
     * @return double|null
     */


    /**
     * 获取砍价的随机数
     * @param $activity Activity
     * @param $attend_id int 参与或的ID
     * @return double|null
     */
    public function rebate(Activity $activity, $attend_id){
        parent::rebate($activity, $attend_id);
        if(!$activity->project) return null;
        $config = unserialize($activity->project->data['config']);
        if(!$attend_id) return null;
        $data = M('UsersActivity')->field('count(id) total, sum(money) money')->where("parent_id={$attend_id} or id={$attend_id}")->find();
        //最终砍完次数
        $sum = (int)$config['random_number'];
        //已经参加人数
        $total = (int)$data['total'];
        //剩余金额
        $money = (double)$config['costPrice'] - (double)$config['random_most']- (double)$data['money'];

        if($money <= 0) return null;

        /**最终砍完人数不存在就按照配置区间设置值*/
        if(!$sum){
            if($money < $config['random_most']){
                $rand = $money;
            }else {
                $rand = floor(randomFloat($config['random_most'], $config['random_least']) * 100) / 100;
            }
        }else{
            if(($sum - $total) < 0) return null;
            if($sum == $total) {
                $rand = $money;
            }else{
                $max = ($money / ($sum - $total)) * 2;
                if(($sum - $total) == 2){
                    $max = $max - ($max / 10);
                }
                $rand = floor(randomFloat(0.1, $max) * 100)/100;
            }
        }
        return $rand;
    }
}