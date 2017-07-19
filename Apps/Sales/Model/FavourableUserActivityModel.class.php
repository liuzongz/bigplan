<?php
namespace Sales\Model;

use Sales\Element\Activity;
use Sales\Element\Element;
use Sales\Element\Favourable\Favourable;

class FavourableUserActivityModel extends BaseModel
{
    protected $tableName = 'favourable_user_activity';

    /**
     *  获取活动列表  作废、、、、、
     * @param array $data 要插入的数据
     * @return array
     */
    public function addd11111($data){
        $v = array(
            'item_name'   => array($data['item_name'],'/^[\w'.REG_ZH.']{1,20}$/u',__LINE__.'-活动名称请输入1-20个字符-'),
        );
        $result = $this->confirm($v);
        if($result['error'] != 0){
            return error_msg($result['error'], $result['msg']);
            //return $result;
        }else{
            //验证时间
            $result = $this->checkTime($data['act_starttime'], $data['act_endtime']);
            if($result['error'] != 0){
                return error_msg($result['error'], $result['msg']);
            }
            /**@var Favourable $favourable */
            //验证活动配置
            $favourable = Element::$serviceLocator->get('solitaire', false);
            if(!$favourable)
                return error_msg(__LINE__, "营销工具[{'solitaire'}]不存在");
            if($favourable->disabled)
                return error_msg(__LINE__, "营销工具[{$favourable}]已禁用");
            $result = $favourable->checkStep($data['config']);
            if($result['error']!=0){
                return error_msg(__LINE__, $result['msg']);
            }
            //验证商家介绍
            $result = $this->checkStore($data['desc_img'],$data['desc_content']);
            if($result['error'] != 0 ){
                return error_msg($result['error'], $result['msg']);
            }
            $act_desc = $result;

            //验证活动规则
            $result = $this->checkRule($data['act_rule']);
            if($result['error']!=0){
                return error_msg($result['error'], $result['msg']);
            }
            $data['config']['act_rule'] = $data['act_rule'];

            //验证咨询电话
            $result = $this->checkPhone($data['tel']);
            if($result['error']!=0){
                return error_msg($result['error'], $result['msg']);
            }
            $data['config']['tel'] = $data['tel'];

            //验证自定义表单
            $result = $this->checkProject($data['project']);
            if($result['error'] !=0 ){
                return error_msg($result['error'], $result['msg']);
            }
            if(!$data['detail']){
                return error_msg(__LINE__, '请至请输入详情内容');
            }
            if(!$data['detail_img']){
                return error_msg(__LINE__, '请至少上传一张活动详情图片');
            }
            $data['config']['project'] = $data['project'];
            $data['config']['detail'] = $data['detail'];
            $data['config']['detail_img'] = $data['detail_img'];
            $InsertArr = array(
                'act_id'        => (int)$data['aid'],  //  营销id
                'user_id'       => (int)$data['user_id'],//  用户ID
                'config'        => serialize($data['config']),//  活动配置
                'item_name'     => $data['item_name'],//  活动名称
                'act_desc'      => serialize($act_desc),//  商家介绍
                'act_starttime' => $data['act_starttime'],// 开始时间
                'act_endtime'   => $data['act_endtime'],//  结束时间
                'prizeinfo'     => $data['prize_info'],//  领奖信息
            );
            $result = $this->add($InsertArr);
            if($result){
                //return error_msg(0, '新增成功',array('act_id'=>AesEnCrypt($result),'aid'=>AesEnCrypt($data['aid'])));
                return error_msg(200, '新增成功',array('redirect_url'=>U('Sales/Active/detail').'?aid='.AesEnCrypt($data['aid']) . '&act_id=' . AesEnCrypt($result)));
            }else{
                return error_msg(__LINE__, L('MSG_DB_ADD'));
            }
        }
    }

    /**验证活动规则*/
    private function checkRule($act_rule){
        $v = array(
            //'act_rule'  => array($act_rule,'/^[\w'.REG_ZH.']{1,500}$/u',__LINE__.'-活动规则为1-500个中英文非特殊字符'),
        );
        return $this->confirm($v);
    }

    private function checkTime(&$stime1, &$etime1){
        $stime1 = (int)strtotime($stime1);
        $etime1 = (int)strtotime($etime1);

        if(!$stime1)                                    return error_msg(__LINE__, '活动开始时间不能为空');
        if(!$etime1)                                    return error_msg(__LINE__, '活动结束时间不能为空');
        if($stime1 >= $etime1)                          return error_msg(__LINE__, '活动开始时间不能小于结束时间');
        if($stime1 < strtotime(date('Y-m-d')))          return error_msg(__LINE__, '活动开始时间不能小于当天时间');
        if(($etime1 - $stime1) > 2*12*30*24*3600)          return error_msg(__LINE__, '活动间隔时间不能超过2年');
        return error_msg(0, '验证通过');
    }

    /**
     *  验证活动详情
     * @param $desc array
     * @return array
     */
    private function checkDesc($desc){
        $v = array(
                'name'  => array($desc['name'],'/^[\w'.REG_ZH.']{1,20}$/u',__LINE__.'-活动名称为1-20个中英文非特殊字符'),
                'content'  => array($desc['content'],'/^[\w'.REG_ZH.']{1,100}$/u',__LINE__.'-活动描述为1-100个中英文非特殊字符'),
        );
        return $this->confirm($v);
    }

    public function checkPhone($phone){
        $v = array(
            'phone'  => array($phone,'/^1[3|4|5|7|8]?\d{9}$/',__LINE__.'-咨询电话不对，请输入手机号'),
        );
        return $this->confirm($v);
    }

    /**
     * 验证自定义表单名称
     * @param $project array
     * @return array
     */
    private function checkProject($project){
        $data = [];
        foreach ($project as $key => $val){

            if(!empty($val['name']))
            $key = array($val['name'],'/^[\w'.REG_ZH.']{1,20}$/u',__LINE__.'-表单名称为1-20个中英文非特殊字符');
        }
        if(count($data) > 3) return error_msg(__LINE__, '自定义表单不得超过三个');
        return $this->confirm($data);
    }

    /**
     *  验证商家介绍   作废、、、
     * @param  $img  array  商家图片介绍
     * @param  $desc array  商家文字介绍
     * @return array
     */
    private function checkStore11111($img,$desc){
//        if(empty($img)) return error_msg(__LINE__, '请至少上传一张活动介绍图片');
        $data = [];
        foreach($img as $key => $val){
            $data[] = trim($val);
            $data[] = strip_tags($desc[$key]);
        }
        return $data;
    }

    /**
     *  获取活动列表
     *
     */
    public function get_act_list(){

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
     *  获取活动列表
     * @param array $data 要插入的数据
     * @return array
     */
   public function addd($data, $store_id){
        if(strlen($data['item_name']) > 90){
            return error_msg(__LINE__, '-活动名称请输入1-90个中英文汉字和非特殊标点符号-');
        }
       /* $v = array(
            'item_name'   => array($data['item_name'],'/^[\s\S]{1,90}$/u',__LINE__.'-活动名称请输入1-90个中英文汉字和非特殊标点符号-'),
        );
        $result = $this->confirm($v);*/
       /* if($result['error'] != 0){
            return error_msg($result['error'], $result['msg']);
        }else{*/
            //验证时间
            $result = $this->checkTime($data['act_starttime'], $data['act_endtime']);
            if($result['error'] != 0){
                return error_msg($result['error'], $result['msg']);
            }
            /**@var Favourable $favourable */
            //验证活动配置
            $act_data = Element::createObject('activity', array($data['aid']));
            if(!$act_data){
                return error_msg(__LINE__, "营销工具不存在");
            }
            if($act_data->favourable->disabled){
                return error_msg(__LINE__, "营销工具[{$favourable}]已禁用");
            }
            $result = $act_data->favourable->checkStep($data['config']);
            if($result['error']!=0){
                return error_msg($result['error'], $result['msg']);
            }
            $data['config'] = $result['data'];

            //验证机构介绍
            $result = $this->checkStore($data['act_desc']);
            if($result['error'] != 0 ){
                return error_msg($result['error'], $result['msg']);
            }
            $data['act_desc'] = $result['data'];

            //将数据写入数据库
            $InsertArr = array(
                'act_id'        => (int)$data['aid'],  //  营销id
                'item_name'     => $data['item_name'],//  活动名称
                'user_id'       => (int)$data['user_id'],//  用户ID
                'act_starttime' => $data['act_starttime'],// 开始时间
                'act_endtime'   => $data['act_endtime'],//  结束时间
                'act_desc'      => serialize($data['act_desc']),//  商家介绍
                'config'        => serialize($data['config']),//  活动配置
                'prizeinfo'     => $data['prizeinfo'],//  领奖信息
            );
            debug($InsertArr);
            $result = $this->add($InsertArr);
            debug($this->getLastSql());
            if($result){
                return error_msg(0, '新增成功',array('redirect_url'=>get_server('SALES_SERVER', '/Active/detail',
                    [
                        'aid'                   => AesEnCrypt($data['aid']),
                        'act_id'                => AesEnCrypt($result),
                        C('store_token_name')   => $store_id
                    ], 1)));
            }else{
                return error_msg(__LINE__, L('MSG_DB_ADD'));
            }
       //}
   }

    /**
     *  验证商家介绍
     * @param  $data  array  机构介绍
     * @return array
     */
    private function checkStore($data){
        if(empty($data)){
            return error_msg(__LINE__, '请填写机构信息');
        }
        foreach($data as $key => $val){
            if($val == ""){
                unset($data[$key]);
            }
            if($val['txt'] && strlen($val['txt']) > 600){
                return error_msg(__LINE__, '机构介绍的文字描述请控制在600以内');
            }
        }
        //print_R(strlen(serialize($data)));exit;
        if(strlen(serialize($data)) > C('max_post')){
            return error_msg(__LINE__, '机构信息的数据太多，请删除一部分再提交');
        }
        return error_msg(0, '',$data);
    }

}