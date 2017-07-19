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
            'limit'      => array(
                'value'     => '999',
                'title'     => '活动参加人数',
                'desc'      => '人数为1-100之间的整数',
                'validate'  => array(
                    array('man_num','/^[1-9]\d{0,2}$|^1000$/','人数为1-1000之间的整数'),
                ),
            ),
            'money'      => array(
                'value'     => '100',
                'title'     => '诚意定向金',
                'desc'       =>  '金额为1-1000之间的数字',
                'validate'  => array(
                    array('money','/^(([1-9]\d{0,2}$|^1000)|0)(\.\d{1,2})?$/','金额为1-1000之间的数字'),
                ),
            ),
            'guiz'      => array(
                'value'     => '活动规则请自行编辑',
                'title'     => '活动规则',
                'desc'       =>  '参与活动的规则',
                'type'      =>   'textarea',
                'validate'  => array(
                    array('guiz','/.?/','活动规则填写不规范'),
                ),
            ),
            'Receive'      => array(
                'value'     => '领奖信息请自行编辑',
                'title'     => '领奖信息',
                'desc'       =>  '活动的领奖信息',
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
     * 获取活动信息
     * @param Activity $activity
     * @param $act_id  int  活动的id
     * @return array
     */
    public function getInfo(Activity $activity,$act_id){
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
            $act_info['config']['detail_img'] = img_server($act_info['config']['detail_img']);
            $result = $act_info;
        }else{
            $act_data = $activity->data;
            $config =  unserialize($act_data['act_config']);
            //基础配置
            $result['prizeinfo'] =  $config['Receive']['value'];  //领奖信息
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
        $str = "";     //阶层拼接数组
        $people = 0;   //上一阶层的人数
        $is_end = 0;   //是否最后一层
        $i = 0;
        foreach($data as $k => $v){
            if(intval($v['man']) !=0){
                if($curman >= $people and $curman < $v['man']){
                    $status = 'run';
                    $is_end = 1;
                    $final_price = $v['money'];
                } elseif ($curman >= $v['man'] and !$is_end) {
                    $status = 'unlock';

                } else {
                    $status = '';
                }
                $people = $v['man'];
                $str .= "<li data-id='".$i."' class='".$status."'><div class='show'>接龙人数满<em class='input1'>".$v['man']."</em>人每人只需";
                $str .= "<em class='input2'>".$v['money']."</em>元/人</div><div class='input3 text flex'>".$v['desc']."</div></li>";
            }
            $i++;
            $result['max_man'] =  $v['man'];
        }
        $result['config_step']  = $str;
        $result['final_price']  = $final_price;
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
                    $str.= '<img src="'.img_server($v).'"/>';
                }else{
                    $str.= '<p>'.$v.'</p>';
                }
            }
            return $str;
    }


    /**
     * 验证接龙层级
     * @param array $data array
     * @return array|bool
     */
    public function checkStep($data){
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
}