<?php
namespace Sales\Element\Favourable;

use Think\Model;
use Sales\Element\Base\Object;
use Sales\Element\Goods;
use Sales\Element\Price;
use Sales\Element\Activity;
use Sales\Element\Project;

abstract class Favourable extends Object {

    //当前子类中文名称
    public $title   = null;

    //当前子类的短名称标记
    public $name    = null;

    //营销工具是否可发布活动(默认可用,可配置)
    public $disabled = false;

    public $user_info = [];

    public function __toString(){
        return $this->title;
    }

    public function mergerConfig($config, $default=array()){
        //上层默认对比值,为空时使用营销工具默认配置
        $default        = $default ? (array)$default : $this->getConfig();
        //合并
        foreach($default as $key => &$each){
            foreach($each as $field => &$value){
                if('validate' === $field)    continue;
                if(isset($config[$key][$field]))  $value = $config[$key][$field];
            }
        }
        return $default;
    }


        //营销工具自定义配置数据验证(表单验证和数据库config字段验证)
    public function validateConfig($data, $default=array()){
        //上层默认对比值,为空时使用营销工具默认配置
        $default        = $default ? $default : $this->getConfig();
        //最终存入的差异性配置
        $config         = array();
        //参照配置中的验证规则
        $validate_rule  = call_user_func_array('array_merge', array_column($default, 'validate'));
        //参照配置中的配置值
        $validate_data  = array();
        //$data必须为有效的数组
        if(!is_array($data) || empty($data)){
            $this->error = "活动参数错误";
            return false;
        }
        foreach($default as $field => $each){
            //验证title
            if(isset($data[$field]['title']) && $data[$field]['title'] != $each['title']){
                if(!preg_match('/^[\w'.REG_ZH.']{2,5}$/u', $data[$field]['title'])){
                    $this->error = '配置项标题为2-5个中英文非特殊字符';
                    return false;
                }
                $config[$field]['title'] = $data[$field]['title'];
            }
            //验证desc
            if(isset($data[$field]['desc']) && $data[$field]['desc'] != $each['desc']){
                if(!preg_match('/^[\w'.REG_ZH.']{0,20}$/u', $data[$field]['desc'])){
                    $this->error = '配置项描述为0-20个中英文非特殊字符';
                    return false;
                }
                $config[$field]['desc'] = $data[$field]['desc'];
            }
            //验证value
            if(isset($data[$field]['value']) && $data[$field]['value'] != $each['value']){
                $validate_data[$field] = $data[$field]['value'];
                $config[$field]['value'] = $data[$field]['value'];
            }

            if(isset($data[$field]['level'])){
               $count=count($data[$field]['level']);
                for($i=0;$i<$count;$i++){
                    list($min1,$max1)=explode(',',$data[$field]['level'][$i]);
                    if(!$min1 || !$max1)     {$this->error = '格式错误，以英文“,”分割填写区间，如1,10';return false;}
                    if($min1<1 || $min1>100) {$this->error = '分配区间不合理，请在1,100之前选择';return false;}
                    if($max1<1 || $max1>100) {$this->error = '分配区间不合理，请在1,100之前选择';return false;}
                    for($j=$i+1;$j<$count;$j++){
                        list($min2,$max2)=explode(',',$data[$field]['level'][$j]);
                        if($min2>=$min1 && $min2<=$max1){$this->error = '分配区间不合理，请不要重复分配区间';return false;}
                        if($max2>=$min1 && $max2<=$max1){$this->error = '分配区间不合理，请不要重复分配区间';return false;}
                    }
                }
               $config[$field]['level'] = $data[$field]['level'];
            }
            //验证等级

            /*if($field == 'step'){
                $stepArr = $data[$field];
                $total=0;

                if($data['money']['value']<1 || $data['value']['money']>100){
                    $this->error = '诚意定向金请设置1-100之间的金额';
                    return false;
                }
                $i=0;
                $last_man = "";
                $last_money = "";
                foreach($stepArr as $k=>$v){
                    if(!preg_match('/^[1-9]\d{0,5}$/', intval($v[man]))){
                        $this->error = '第'.($i+1).'接龙层级的人数不是数字';
                        return false;
                    }
                    if(!preg_match('/^[1-9]\d{0,5}$/', intval($v['money']))){
                        $this->error = '第'.($i+1).'接龙层级的金额不是数字';
                        return false;
                    }
                    if(!preg_match('/^[\w'.REG_ZH.']{0,10}$/u', $v['desc'])){
                        $this->error = '配置项标题为2-10个中英文非特殊字符';
                        return false;
                    }

                    if($i>0){
                        if($last_man >= $v['man']){ //验证接龙的等级
                            $this->error = '第'.($i).'接龙层级的人数不能大于第'.($i+1).'层级';
                            return false;
                        }
                        if($last_money <= $v['money']){ //验证接龙的等级
                            $this->error = '第'.($i).'接龙层级的金额不能低于第'.($i+1).'层级';
                            return false;
                        }
                    }
                    $last_man=$v['man'];
                    $last_money=$v['money'];
                    $total = $total+$v['man'];
                    if($total>$data['limit']['value'] && $data['limit']['value']!=0){
                        $this->error = '总人数超出限制';
                        return false;
                    }
                    $i++;
                }
            }*/
            if(isset($data[$field]['percent'])){
                foreach($data[$field]['percent'] as $k=>$v){
                    if($v<0)     {$this->error = '分配比例不能低于0%';return false;}
                    if($v>100)   {$this->error = '分配比例不能高于100%';return false;}
                }
                $total_percent=array_sum($data[$field]['percent']);
                if($total_percent>100)   {$this->error = '分配比例加起来不能超过100%';return false;}
            }
            $config[$field]['percent'] = $data[$field]['percent'];
        }
        $model = new Model();
        C('TOKEN_ON', false);//关闭表单令牌
        $model->validate($validate_rule)->table('__FAVOURABLE_ACTIVITY__');//没有表名,无法获取字段,报notice错误
        if(false === $model->create($validate_data)){
            $this->error = $model->getError();
            return false;
        }else{
            return $config;
        }
    }

    protected function checkActivity(Activity $activity){
        if($activity->goods instanceof Goods) return;
        E('活动-商品未设置');
    }

    protected function checkProject(Activity $activity){
        if($activity->project instanceof Project) return;
        E('活动-商品未设置');
    }

    protected $templateConfig = array(
        'discount'  => array(
            'value'     => '100',
            'title'     => '折扣值',
            'desc'      => '折扣值为1-100之间的整数',
            'validate'  => array(
                array('discount','/^[1-9]\d{0,1}$|^100$/','折扣值为1-100之间的整数'),
            ),
        ),
        'cate'      => array(
            'value'     => 'index',
            'title'     => '分类样式',
            'desc'      => 'index,gift,stcok三种值之一',
            'validate'  => array(
                array('cate',array('index','gift','stock'),'分类样式值不存在','','in'),
            ),
        ),
        'rebate'     => array(
            'value'     => '0',
            'title'     => '开启超级返利',
            'desc'      => '0为不开启，返利比例在1-100之间',
            'validate'  => array(
                array('rebate','/^[0-9]\d{0,1}$|^100$/','返还比例过大，请不要超过100'),
            ),
        ),
    );

    /**
     * 营销工具配置
     * @return mixed
     */
    abstract public function getConfig();

    /**
     * 价格实例(和配置有关一定要子类实现)
     * @param Activity $activity
     * @return Price
     */
    abstract public function price(Activity $activity);


    /**
     * 验证活动配置
     * @param $data array
     * @return bool
     */
    public function checkStep($data){
        return true;
    }

    /**
     * 能否加入购物车
     * @param Activity $activity
     * @return bool
     */
    public function cart(Activity $activity){
        $this->checkActivity($activity);
        return true;
    }

    /**
     * 能否支付
     * @param Activity $activity
     * @return bool
     */
    public function pay(Activity $activity){
        $this->checkActivity($activity);
        return true;
    }

    /**
     * 支付完成回调
     */
    public function paid(Activity $activity, $order, $goods, $level){
        return array();
    }

    /**
     * 能否发货
     * @return bool
     */
    public function deliver(){
        return true;
    }

    /**
     * 能否参加活动
     * @param Activity $activity
     * @param $user_id
     * @return bool
     */
    public function attend(Activity $activity, $user_id){
        $this->checkProject($activity);
        return true;
    }

    /**
     * 参与活动优惠金额
     * @param Activity $activity
     * @param $attend_id
     * @return array
     */
    public function rebate(Activity $activity, $attend_id){
        //$this->checkProject($activity);
        return array();
    }

}


