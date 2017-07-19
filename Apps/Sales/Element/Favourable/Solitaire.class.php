<?php
namespace Sales\Element\Favourable;

use Sales\Element\Activity;
use Sales\Element\Element;

class Solitaire extends Favourable {
    //当前子类中文名称
    public $default_id   = 64;
    public function getConfig(){
        return array(
            //'cate'      => $this->templateConfig['cate'],
            'item_name'      => array(
                'value'     => '万人接龙火热进行中',
                'title'     => '活动标题',
                'desc'      => '万人接龙活动标题',
                'validate'  => array(
                    array('man_num','/.?/','人数为1-1000之间的整数'),
                ),
            ),
            'limit'      => array(
                'value'     => '999',
                'title'     => '活动参加人数',
                'desc'      => '人数为1-100之间的整数',
                'validate'  => array(
                    array('man_num','/^[1-9]\d{0,2}$|^1000$/','人数为1-1000之间的整数'),
                ),
            ),
            'money'      => array(
                'value'     => '1',
                'title'     => '诚意定向金',
                'desc'       =>  '金额为1-1000之间的数字',
                'validate'  => array(
                    array('money','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),
            'guiz'      => array(
                'value'     => '1.点击“我要报名”，参加活动。

2.接龙总人数达标即可享受对应档优惠。

3.接龙失败诚意金原路退回，成功则诚意金不退。

4.加入接龙的人越多越优惠全员享受同一个优惠。',
                'title'     => '活动规则',
                'desc'       =>  '参与活动的规则',
                'type'      =>   'textarea',
                'validate'  => array(
                    array('guiz','/.?/','活动规则填写不规范'),
                ),
            ),
            'Receive'      => array(
                'value'     => '领奖时间：
领奖地址：
领奖电话：',
                'title'     => '领奖信息',
                'desc'       =>  '活动的领奖信息',
                'type'      =>  'textarea',
                'validate'  => array(
                    array('Receive','/.?/','领奖信息填写不规范'),
                ),
            ),
            'step'      => array(
                'value'     => '10-100',
                'title'     => '接龙层级',
                'desc'       =>  '人数-金额(10人100元)',
                'validate'  => array(
                    array('money','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),

            'costPrice'      => array(
                'value'     => '100',
                'title'     => '产品原价',
                'desc'      => '活动产品的原价',
                'validate'  => array(
                    array('man_num','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','人数为1-1000之间的整数'),
                ),
            ),
        );
    }

    public function attend(Activity $activity, $user_id)
    {
        parent::attend($activity, $user_id);
        $attend = $activity->getDataOfAttend($user_id);
        return !isset($attend['order_id']) && (count($activity->getDataOfUser()) < $activity->config['limit']['value']);
    }

    public function price(Activity $activity){
        $this->checkActivity($activity);
        return Element::createObject(array('class'=>'price', 'RMB'=>$activity->goods->price));
    }

    /**
     * 获取活动信息 作废11111
     * @param Activity $activity
     * @param $act_id  int  活动的id
     * @return array
     */
    public function getInfo11111(Activity $activity,$act_id){
        $cur_man = M('users_activity')->where(array('action_id'=>$act_id))->count();//参与活动的人数
        if($act_id != ''){
            $act_info  =  M('favourable_user_activity')->where(array('id'=>$act_id))->find();
            $act_info['config'] = unserialize($act_info['config']);
            $act_info['config_step']  = $this->actconfig($act_info['config']['step'],$cur_man)['config_step'];
            $act_info['final_price']  = $this->actconfig($act_info['config']['step'],$cur_man)['final_price'];
            $act_info['act_starttime'] = date('Y-m-d', $act_info['act_starttime']) . 'T' . date('H:i', $act_info['act_starttime']);
            $act_info['act_endtime'] = date('Y-m-d', $act_info['act_endtime']) . 'T' . date('H:i', $act_info['act_endtime']);
            $act_info['act_desc'] = $this->act_desc(unserialize($act_info['act_desc']));
            $act_info['project'] = $this->act_project($act_info['config']['project']);
            $act_info['config']['detail_img'] = img_url($act_info['config']['detail_img']);
            $result = $act_info;
        }else{
            $act_data = $activity->data;
            $config =  unserialize($act_data['act_config']);
            //基础配置
            $result['config']['prizeinfo'] =  $config['Receive']['value'];  //领奖信息
            $result['config']['limit'] = $config['limit']['value']; //最大参与人数
            $result['config']['act_rule'] = $config['guiz']['value']; // 活动规则
            $result['config']['deposit'] = $config['money']['value']; //意向金
            list($step_Arr[0]['man'],$step_Arr[0]['money']) = explode('-',$config['step']['value']);     //领奖阶层
            $result['config_step']  = $this->actconfig($step_Arr,$result['cur_man'])['config_step'];
            $result['config']['max_man']  = $this->actconfig($step_Arr,$result['cur_man'])['max_man'];
            $result['config']['step'] =  $step_Arr;
//            $result['act_starttime'] = date('Y-m-d', $act_data['sign_starttime']) . 'T' . date('H:i', $act_data['sign_starttime']);
//            $result['act_endtime'] = date('Y-m-d', $act_data['sign_endtime']) . 'T' . date('H:i', $act_data['sign_endtime']);
            $result['act_starttime'] = date('Y-m-d').'T'.date('H:i');
            $result['act_endtime'] = date('Y-m-d', strtotime('+7 day')).'T'.date('H:i');
            $result['act_type'] = $act_data->data['act_type'];
            $result['act_name'] = $act_data['act_name'];//活动名称
            $result['config']['detail_img'] = '/Public/Sales/images/Solitaire.jpg';
            $result['config']['detail'] = '请输入活动详情';
        }
           $result['nowtime'] = date('Y-m-d H:i');
           $result['cur_man'] = intval($cur_man);
           return $result;
    }

    /**
     * @param Activity $activity
     * @return int
     */
    public function step(Activity $activity){
        $count = count($activity->getDataOfUser());

        foreach ($activity->config['step'] as $value){
            if($value[1] <= $count && $value[2] >= $count){
                return 1111;
            }
        }
    }

    /**
     * 组装接龙层级
     * @param $data    array 活动的阶层
     * @param $curman  int   当前参与人数
     * @return array
     */
    public function actconfig($data,$curman){
        $people = 0;   //上一阶层的人数
        $money  = 0;   //上一阶层的金额
        $is_end = 0;   //是否最后一层
        $i = 0;
        $status = array();

        $curman_step = 0;
        $end_people = 0;//最后阶层的人
        //获取到当前满足的层级所需人数
        foreach ($data as $val){
            if($val['man'] > 0 && $curman >= $val['man']){
                $curman_step = $val['man'];
            }
            $end_people = $val['man'];
        }
        foreach($data as $k => $v){
            $money = $v['money'];   //当前阶层的价格
            $man = $v['man'];       //当前阶层的人数
            if(!isset($initial)) $initial = $v['man'];
            if(!$people) $people = $v['man'];//设置次进来的人数
                if($curman >= $people and $curman < $v['man']){   //进行中
                    $status[$k] = 'run';
                    $is_end = 1;
                    $final_man = $man;
                } elseif ($curman >= $v['man'] and !$is_end) {
                    $status[$k] = 'unlock';
                    $final_price = $money;
                } else {
                    if($curman <= $people && $k == 0){
                        $status[$k] = 'run';
                    }else{
                        $status[$k] = '';
                    }
                }
                /*if($curman_step && $curman_step > $v['man']){
                    $status[$k] = 'unlock';
                } elseif ($curman_step && ($curman_step >= $v['man'] or $curman >= $end_people)) {
                    $is_end = 1;
                    $final_price = $money;
                    $final_man = $man;
                    $status[$k] = 'run';
                } else {
                    $status[$k] = '';
                }*/
                $people = $v['man'];
            $i++;
            $result['max_man'] =  $v['man'];
        }
        $result['status'] = $status;
        if($curman >= $v['man']){
            $result['final_price']  = $v['money'];
        }else{
            $result['final_price']  = $final_price;
        }
        $result['final_man'] = $v['man'];

        return $result;
    }

    /**
    *  获取用户自定义表单
    * @param $data array 表单配置
    * @return string
    */
    public function act_project($data){
            $str = "";
            $inset_str = "";
            foreach($data as $k=>$v){
                if(isset($v['check']) && $v['check']=='on'){
                    $inset_str = "（必填项）";
                }else{
                    $inset_str = "";
                }
                if ($v['name']){
                    $str .= "<input type='text' name='".$k."' placeholder='".$v['name']."".$inset_str."'>";
                }
            }
           return $str;
    }

    /**
     *  获取机构介绍
     * @param $data array 表单配置
     * @return string
    */
    public function act_desc($data){
            $str = "";
            foreach($data as $k=>$v){
                if(substr($v , 0 , 7) == 'Uploads'){
                    $str.= '<img src="'.img_url($v).'"/>';
                }else{
                    $str.= '<p>'.$v.'</p>';
                }
            }
            return $str;
    }


    /**
     * 验证接龙层级 作废、、、、
     * @param array $data array
     * @return array|bool
     */
    public function checkStep111111($data){
         $i=0;
         $last_man = "";
         $last_money = "";
         $total=0;
         $stepArr = $data['step'];
         if(empty($data['deposit'])){
              return error_msg(__LINE__, "请配置诚意定金");
         }
         if(count($stepArr) >5 ){
             return error_msg(__LINE__, "最多设置5个层级");
         }

         foreach($stepArr as $k => $v ){
              if(!preg_match('/^[1-9]\d{0,5}$/', intval($v[man]))){
                  return error_msg(__LINE__, '第'.($i+1).'接龙层级的人数不是数字');
                    //$this->error = '第'.($i+1).'接龙层级的人数不是数字';
                    //return false;
              }

              if(!preg_match('/^[1-9]\d{0,5}$/', intval($v['money']))){
                     return error_msg(__LINE__, '第'.($i+1).'接龙层级的金额不是数字');
                    //$this->error = '第'.($i+1).'接龙层级的金额不是数字';
                   // return false;
              }

              if(!preg_match('/^[\w'.REG_ZH.']{0,10}$/u', $v['desc'])){
                     return error_msg(__LINE__, '配置项标题为2-10个中英文非特殊字符');
                     //$this->error = '配置项标题为2-10个中英文非特殊字符';
                     //return false;
              }

             if($i>0){
                    if($last_man >= $v['man']){ //验证接龙的等级
                             return error_msg(__LINE__, '第'.($i).'接龙层级的人数不能大于第'.($i+1).'层级');
                            //$this->error = '第'.($i).'接龙层级的人数不能大于第'.($i+1).'层级';
                                         //return false;
                    }
                    if($last_money <= $v['money']){ //验证接龙的等级
                           return error_msg(__LINE__, '第'.($i).'接龙层级的金额不能低于第'.($i+1).'层级');

                           // $this->error = '第'.($i).'接龙层级的金额不能低于第'.($i+1).'层级';
                            //return false;
                    }
             }
             $last_man=$v['man'];
             $last_money=$v['money'];
             $total = $total+$v['man'];

             if($total>$data['limit'] && $data['limit']['value']!=0){
                  return error_msg(__LINE__, '总人数超出限制');
                      //$this->error = '总人数超出限制';
                      //return false;
             }
             $i++;
         }
         return error_msg(0,'验证成功');
    }

    /**
     * 获取活动信息
     * @param Activity $activity
     * @param $act_id  int  活动的id
     * @return array
     */
    public function getInfo(Activity $activity,$act_id,$user_id, $attend_id){
        $cur_man = M('users_activity u')->join("wp_favourable_order o on u.id=o.attend_id")->where(array('o.act_id'=>$act_id,'o.pay_status'=>2))->count();//参与活动的人数

        if($act_id != ''){
            $act_info  =  M('favourable_user_activity')->where(array('id'=>$act_id))->find();
            $act_info['config'] = unserialize($act_info['config']);
            $act_info['act_desc'] = $this->checkStore(unserialize($act_info['act_desc']));
            $act_info['final_price']  = $this->actconfig($act_info['config']['step'],$cur_man)['final_price'];
            $act_info['final_man']  = $this->actconfig($act_info['config']['step'],$cur_man)['final_man'];
            $act_info['config']['status']  = $this->actconfig($act_info['config']['step'],$cur_man)['status'];
            if(!$act_info['final_price']){
                $act_info['final_price'] = $act_info['config']['costPrice'];
            }
            $act_info['config']['share_desc']   =   $this->share_msg($act_info['config']['detail'], 'txt');
            $act_info['config']['share_img'] = $this->share_msg($act_info['config']['detail'], 'img');
            $act_info['config']['detail'] = $this->checkStore($act_info['config']['detail']);
            $act_info['is_joined'] = M('favourable_order')->where(array('user_id'=>$user_id,'act_id'=>$act_id,'pay_status'=>2))->count();//本人是否参与了
            $result = $act_info;
        }else{
            $act_data = $activity->data;
            $config =  unserialize($act_data['act_config']);
            //基础配置
            $result['config']['prizeinfo'] =  $config['Receive']['value'];  //领奖信息
            $result['config']['limit'] = $config['limit']['value']; //最大参与人数
            $result['config']['act_rule'] = $config['guiz']['value']; // 活动规则
            $result['item_name'] = $config['item_name']['value']; //活动标题
            $result['config']['costPrice'] = $config['costPrice']['value']; //活动标题
            $result['config']['deposit'] = $config['money']['value']; //意向金
            list($step_Arr[0]['man'],$step_Arr[0]['money']) = explode('-',$config['step']['value']);     //领奖阶层
            $result['config_step']  = $this->actconfig($step_Arr,$result['cur_man'])['config_step'];
            $result['config']['max_man']  = $this->actconfig($step_Arr,$result['cur_man'])['max_man'];
            $result['config']['step'] =  $step_Arr;
//            $result['act_starttime'] = date('Y-m-d', $act_data['sign_starttime']) . 'T' . date('H:i', $act_data['sign_starttime']);
//            $result['act_endtime'] = date('Y-m-d', $act_data['sign_endtime']) . 'T' . date('H:i', $act_data['sign_endtime']);
            $result['act_starttime'] = time();
            $result['act_endtime'] = time()+24*3600*7;
            $result['act_type'] = $act_data->data['act_type'];
            $result['act_name'] = $act_data['act_name'];//活动名称
        }

        $result['nowtime'] = date('Y-m-d H:i');
        $result['cur_man'] = intval($cur_man);
//        print_r($result);exit;
        return $result;
    }


    /**
     * 重新组装接龙的阶层
     * @param array $data array
     * @return array|bool
     */
    public function deal_step($data){
        $i = 1;
        foreach($data as $k=>$v){
            $count = count($v);
            $i = $count>$i?$count:$i;
        }
        for($j=0;$j<$i;$j++){
            $result[$j]['man']   = $data['man'][$j];
            $result[$j]['money'] = $data['money'][$j];
            $result[$j]['desc']  = $data['desc'][$j];
        }
        return $result;
    }

    /**
     * 验证接龙配置参数
     * @param array $data array
     * @return array|bool
     */
    public function checkStep($data){
        if(intval($data['costPrice']    == 0 )){    //产品原价
            return error_msg(__LINE__, "请输入商品原价");
        }

        if(intval($data['deposit']    < 1  || intval($data['deposit'] >10 ))){    //诚意定向金
            return error_msg(__LINE__, "诚意定向金请控制在1~10元之间");
        }

        if(intval($data['limit']    == 0 )){    //接龙人数上限
            return error_msg(__LINE__, "请输入接龙人数上限");
        }

        $data['step']   = $this->deal_step($data['step']);
        //验证接龙层级
        $i=0;
        $last_man = "";     //最后一阶层人
        $last_money = "";   //最后阶层的金额
        $total=0;
        foreach($data['step'] as $k => &$v ){
            if(!preg_match( '/^[1-9]\d{0,5}$/' , intval( $v[man] ))){
                return error_msg(__LINE__, '第' . ( $i+1 ) . '接龙层级的人数不是数字');
            }

            if(!preg_match('/^[1-9]\d{0,5}$/', intval( $v['money'] ))){
                return error_msg(__LINE__, '第' . ( $i+1 ) . '接龙层级的金额不是数字');
            }

            if(!preg_match('/^[\s\S]{0,15}$/u', $v['desc'] )){
                return error_msg(__LINE__, '第'. ( $i+1 ) . '-接龙梯级的文字说明请输入1-15个中英文汉字和非特殊标点符号-');
                $v['desc']  = filter_str($v['desc']);
            }

            if( $i > 0 ){
                if($last_man >= $v['man'] ){ //验证接龙的等级
                    return error_msg(__LINE__, '第' . ( $i ) . '接龙层级的人数不能大于第' . ( $i+1 ) . '层级');
                }
                if($last_money <= $v['money'] ){ //验证接龙的等级
                    return error_msg(__LINE__, '第' . ( $i ) . '接龙层级的金额不能低于第' . ( $i+1 ) . '层级');
                }
            }
            $last_man   =   $v['man'];
            $last_money =   $v['money'];
            $total      =   $total + $v['man'];
            if($total > $data['limit'] && $data['limit']['value'] != 0 ){
                return error_msg(__LINE__, '总人数超出参与活动的上限');
            }
            $i++;
        }


        if(empty($data['detail'])){             //产品描述
            return error_msg(__LINE__, "请填写产品介绍信息");
        }else{
            $di = 0;
            foreach($data['detail'] as $dk=>$dv){
                if($dv['txt']){
                    $di++;
                    if(strlen($dv['txt']) > 600){
                        return error_msg(__LINE__, "请将产品描述的文字长度控制在600以内");
                    }
                }
            }
            if($di <= 0 ){
                return error_msg(__LINE__, "请至少填写一段产品描述文字");
            }
        }

        if(!$data['act_rule']){     //验证活动规则
            return error_msg(__LINE__, "请输入活动规则");
        }

        if(!preg_match('/^1[3|4|5|7|8]?\d{9}$/',$data['tel'])){     //验证联系电话
            return error_msg(__LINE__, "请填写正确的联系电话");
        }
        if( !$data['prizeinfo'] ){       //验证领奖信息
            return error_msg(__LINE__, "请输入领奖信息");
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
     *  验证商家介绍
     * @param  $data  array  机构介绍
     * @return array
     */
    public  function checkStore($data){
        $result = array();
        foreach($data as $key => $val){
            if($val['img']){
                $result[$key] = "<img class='img_d' src='".img_url($val['img'])."'>";
            }else{
                $result[$key] = "<div class='div_d'>".str_replace(["\n"],"<br/>",$val['txt'])."</div>";
            }
        }
        return $result;
    }

    /**
     *  微信分享信息
     * @param  $data  array  机构介绍
     * @param  $type  string  类型
     * @return array
     */
    public function share_msg($data, $type = 'txt'){
        foreach($data as $k=>$v){
            if($v[$type]){
                if($type == 'txt'){
                    return  str_replace(array("\r\n","\r","\n"),"",msubstr($v[$type],0, 30));
                }else{
                    return  'http:' . img_url($v[$type]);
                }
            }
        }

    }
}