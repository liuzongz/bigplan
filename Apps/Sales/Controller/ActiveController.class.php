<?php
namespace Sales\Controller;
use Sales\Element\Activity;
use Sales\Element\Element;
use Sales\Element\Project;
use Sales\Model;
class ActiveController extends WechatController {

    /**
     * 工具列表
     * @param
     */
    public function index(){
        $model = new Model\FavourableActivityModel();
        $this->assign('list', $model->get_act_list($this->store_id, array()));
        $this->assign('user_head', ['title' => '活动列表']);
        $this->assign('active_empty', '<img id="img"  style="width: 100%;margin-top: 2rem" src="/Public/sales/images/noajax.jpg">');
        $this->display();
    }

    /**
     * 活动详情页面(编辑|展示)
     * @param get.aid     int 工具ID
     * @param get.act_id  int 活动ID
     * @param get.status  int 页面状态
     */
    public function detail(){
        //如果为编辑状态就需要判断登录
        if(I('get.status',0, 'int')){
            void_user($this->store_id, '');
        }
        $aid = intval(I('get.aid', 0, ['AesDeCrypt','intval']));//营销工具ID
        $act_id = I('get.act_id', 0, ['AesDeCrypt','intval']);   //活动id
        if($act_id <= 220 && intval($this->store_id) <= 0){
            header('Location:' . check_storeid($this->cur_url, C('jzc_store')));
        }
        $status = I('get.status', 0, 'intval');   //编辑状态  1 新建活动  0 展示活动
        $attend_id = I('get.attend_id', 0, ['AesDeCrypt','intval']);   //参与ID区分用户信息

        /**@var Activity $act_data*/
        $act_data = Element::createObject('activity', array($aid));
        if(!$act_data->isExist()) $this->error('活动不存在或已过期！');
        $act_type = $act_data->data['act_type'];
        $favourable = Element::$serviceLocator->get($act_type, false);
        $result['data'] = $favourable->getinfo($act_data, intval($act_id), $this->login_info['user_id'],$attend_id);
        $result['data']['status'] = 0;
        if($status == 1){
            $result['data']['status'] = 1;   //编辑状态
            $result['data']['config']['tel'] = $this->login_info['user_name'];
        }

        $this->assign('user_head' , ['title' => '活动详情']);
        $this->assign('act_info' , $result['data']);
        $this->assign('aid' , I('get.aid')); //营销id
        $this->assign('act_id' , I('get.act_id')); //活动id
        $this->assign('attend_id' , I('get.attend_id')); //参与id
        $this->assign('store_token' , intval($this->store_id)); //参与id
        $this->assign('preview' , $result['data']['test']); //活动示例
        $this->assign('img_url' , get_server('IMG_SERVER' , '/Uploader/index' ,
            [   'module'                =>  MODULE_NAME,
                C('VAR_AJAX_SUBMIT')    =>  1,
                'cat_id'                =>  C('IMG_UPLOAD_CATE'),
                C('store_token_name')   =>  intval($this->store_id),
                'test'                  =>  1,
            ] , 1));
        $this->set_form_token('pay_form_token');
        //$this->assign('pay_form_token', $this->set_form_token('pay_form_token'));//支付token

        $this->display($act_type);
    }

    /**
     * 活动详情 编辑 展示页面  作废
     * @param get.aid     int    营销工具id
     * @param get.act_id  int    活动id
     * @param get.status  string 编辑状态
     * @return array
     */
    public function detail11111(){
        $aid = intval(I('get.aid', 0, ['AesDeCrypt','intval']));//营销工具ID
        $status = I('get.status', 0, ['AesDeCrypt']);
        $act_id = I('get.act_id', 0, ['AesDeCrypt','intval']);
        $this->assign('user_head', ['title' => '活动详情']);
        /**@var Activity $act_data*/
        $act_data = Element::createObject('activity', array($aid));
        if(!$act_data->isExist()) $this->error('活动不存在或已过期！');
        $act_type = $act_data->data['act_type'];
        $favourable = Element::$serviceLocator->get($act_type, false);
        $result['data'] = $favourable->getinfo($act_data,intval($act_id));
        $result['data']['status'] = 0;   //页面的现实状态
        if($status === 'edit'){
            $this->void_user($this->store_id, '');     //验证登录
            $result['data']['status'] = 1;   //编辑状态
            $result['data']['config']['tel'] = $this->login_info['user_name'];
        }

        $join_model = new Model\UsersActivityModel();
        $this->assign('join_user', json_encode($join_model->get_user_list($act_id, 'paid')));
        $this->assign('act_info', json_encode($result['data']));//活动基本数据
        $this->assign('act_data',$result['data']);//活动基本数据
        $this->assign('pay_form_token', $this->set_form_token('pay_form_token'));//支付
        $this->assign('aid', AesEnCrypt($aid));//营销id
        $this->assign('back_act',AesEnCrypt($this->cur_url));//上个页面url        $this->assign('act_id', AesEnCrypt($act_id));//活动id

        $this->assign('edit_status',$result['data']['status']);//页面展示状态
        $this->display("Active:".$act_type);
    }

    /**
     * 活动详情 添加页面
     * @param  POST  array  表单数据
     * return array
     */
    public function edit(){
        $data = array(
            'aid' => I('POST.aid', 0, 'AesDeCrypt'),   //营销id
            'item_name' => I('POST.item_name', '', ['filter_str','trim']), //活动名称
            'act_starttime' => I('POST.act_starttime'),//开始时间
            'act_endtime' => I('POST.act_endtime'),//结束时间
            'user_id' => $this->login_info['user_id'],//用户ID
            'act_desc' => I('POST.act_desc'),//用户ID
            'config' => I('POST.config'), //活动配置
            'prizeinfo' => I('POST.prizeinfo', '', 'stripslashes'),//领奖信息
        );
        void_user($this->store_id, $this->cur_domain . U('detail') .  '?aid=' .  AesEnCrypt($data['aid']) . '&status=1');
        //if ($this->is_login()) {
            $model = new Model\FavourableUserActivityModel();
            $result = $model->addd($data, $this->store_id);
            if ($result['error'] != 0) {
                $result = $this->result_ajax($result['error'], $result['msg']);
            } else {
                $result = $this->result_ajax(200, '', $result['data']);
            }
        $this->ajaxReturn($result);
    }

    /**
     * 活动详情 参与活动
     * @param  POST  array  表单数据
     */
    public function join(){
       /* $login_url = get_server('PASSPORT_SERVER', '/user/login', ['back_act'=>AesEnCrypt($this->back_url)], 1);*/
        /*if (!$this->is_login()) {
            $result = result_ajax(301, '您还没有登录，请先登录！', ['url'=>$login_url]);
        } else {*/
            $aid = I('POST.aid', 0, 'AesDeCrypt');
            $act_id = I('POST.act_id', 0, 'AesDeCrypt');
            $this->ajax = 1;
            $this->void_user($this->store_id,$this->cur_domain . U('detail') .  '?aid=' .  AesEnCrypt($aid) . '&act_id=' . AesEnCrypt($act_id));
            $model = new Model\FavourableActivityModel();
            $info = $model->get_act_info($aid);
            if ($info and $info['act_type']) {
                    /** @var Project $Project */
                    $Project = Element::createObject('project', array($act_id));
                    /** @var Activity $act */
                    $act = $Project->getActivity();
                    if(empty($act)){
                        $result = result_ajax(404,'当前不在活动期内！');
                    }else{
                        $result = $act->attend($this->login_info['user_id']);
                        if($result){
                            $data_config = unserialize($Project->data['config']);
                            $check_form = array(
                                'phone_number' => I('POST.phone_number'),//用户填写参与活动填写的手机号信息
                                'user_name' => I('POST.user_name'),      //用户填写参与活动填写的姓名信息
                                'action_id' => $act_id,
                                'user_id'   =>$this->login_info['user_id'],
                                'data_config' => $data_config,
                                'data_msg'    => I('POST.msg'),
                            );
                            $model = new Model\UsersActivityModel();
                            $result = $model->join($check_form);
                            if($result['error']!=200){
                                $result = result_ajax(302,$result['msg']);
                            }
                        }else{
                            $result = result_ajax(405,'你已经参与过该活动！');
                        }
                    }
            } else {
                $result = result_ajax(404,'当前活动不存在或不在活动之间期内！');
            }
        /*}*/
        $this->ajaxReturn($result);
    }

    /**
     * 验证活动
     * @param POST.aid     int    营销工具id
     * @param POST.act_id  int    活动id
     * @param POST.status  string 编辑状态
     * @return array
     */
    public function chechjoin(){
        /*$login_url = get_server('PASSPORT_SERVER', '/user/login', ['back_act'=>AesEnCrypt($this->back_url)], 1);
        if (!$this->is_login()) {
            $result = result_ajax(301,'您还没有登录，请先登录！',['url'=>$login_url]);
        }else{*/
            $aid = I('POST.aid',0,'AesDeCrypt');
            $act_id = I('POST.act_id',0,'AesDeCrypt');
            $this->ajax = 1;
            void_user($this->store_id,$this->cur_domain . U('detail') .  '?aid=' .  AesEnCrypt($aid) . '&act_id=' . AesEnCrypt($act_id));
            $model = new Model\FavourableActivityModel();
            $info = $model->get_act_info($aid);
            if ($info and $info['act_type']) {
                /** @var Project $Project */
                $Project = Element::createObject('project', array($act_id));
                /** @var Activity $act */
                $act = $Project->getActivity();
                if(empty($act)){
                    $result = result_ajax(__LINE__,'当前不在活动期内！');
                }else{
                    $result = result_ajax(200,'可以参与改活动');
                }
            } else {
                $result = result_ajax(__LINE__,'当前活动不在活动期内！');
            }
       /* }*/
        $this->ajaxReturn($result);
    }

    /**
     * 活动详情 获取参与活动的用户列表
     * @param POST.act_id  int    活动id
     * @return array
     */
    public function get_join_list(){
        $act_id = I('GET.act_id',0,'AesDeCrypt');
        $is_pay = I('GET.is_pay', '', 'trim');
        $type = I('GET.type', 'phone_number', 'trim');
        $join_model = new Model\UsersActivityModel();
        $result = result_ajax('200', '' ,  $join_model->get_user_list($act_id,$is_pay,$type,0, 30));
        /*}*/
        /** @var Project $Project */
        //$Project = Element::createObject('project', array($act_id));
        /** @var Activity $act */
        //$act = $Project->getActivity();
        /*if(empty($act)){
                $result = result_ajax(__LINE__,'当前不在活动期内！');
        }else{*/

        $this->ajaxReturn($result);
    }

    /**
     * 活动详情 获取参与活动的用户列表
     * @param POST.type  string   获取的类型
     * @return array
     */
    public function get_my_act(){
        $type = I('get.type');
        void_user($this->store_id,'');
        //if($this->is_login()) {
            if ($type) {
                $model = new Model\UsersActivityModel();
                if($type == 'cur'){
                    $info = $model->get_act_list($this->user_info['user_id'], 'all');
                }else{
                    $info = $model->get_publish_act($this->store_id, $this->user_info['user_id'], $type);
                }
                if (!$info) {
                    $result = result_ajax(401, '没有找到相关数据！');
                } else {
                    $result = result_ajax(200, '获取成功！', $info);
                }
            } else {
                $result = result_ajax(301, '获取失败，请求类型错误！');
            }
        /*}else{
            $result = result_ajax(301, '请登录后再重新操作！');
        }*/
        $this->ajaxReturn($result);
    }

    /**
     * 减价
     * @param POST.act_id       int   活动id
     * @param POST.attend_id    int   参与的id
     * @return array
     */
    public function rebate(){
        $act_id = I('post.act_id',0,['AesDeCrypt','intval']);
        $attend_id = I('post.attend_id',0,['AesDeCrypt','intval']);

        if(!$act_id || !$attend_id){
            $this->ajaxReturn(result_ajax(301,'缺少参数！'));
        }
        /**@var Project $Project*/
        $Project = Element::createObject('project', array($act_id));
        /**@var Activity $Activity */
        $Activity = $Project->Activity;
        if(!isset($Activity)){
            $this->ajaxReturn(result_ajax(302,'活动不存在或已过期！'));
        }
        $money = $Activity->rebate($attend_id);
        if(!$money) $this->ajaxReturn(result_ajax(601,'活动已减至底价！'));

        $model = new Model\UsersActivityModel();
        if($this->user_info['user_id']){
            $act_info = M('users_activity')->where(['user_id'=>$this->user_info['user_id'], 'parent_id'=>$attend_id])->find();
        }

        $temporary_user = cookie("temporary_user_info_{$attend_id}");
        if(isset($act_info) || isset($temporary_user)){
            $this->ajaxReturn(result_ajax(602,'您已帮好友减过价啦！'));
        }

        $result = $model->set_rebate($this->user_info['user_id'], $act_id, $money, $attend_id);
        if($result > 0 && !isset($temporary_user) && $temporary_user['attend_id'] != $attend_id){
            cookie("temporary_user_info_{$attend_id}",[
                'addTime'=>time(),
                'rebate_money'=>$money,
                'attend_id'=>$attend_id,
                'users_activity_id'=>$result
            ], 24*60*60 *30);

            $this->ajaxReturn(result_ajax(200,"您已成功帮好友{$money}元过价啦！", ['money'=>$money]));
        }
        $this->ajaxReturn(result_ajax(303,"减价失败！"));
    }


    /**
     * 批量生成活动参与人数
     * @param GET.act_id   int   活动id
     * @param GET.number   int   数量
     * @return array
     */
    public function test(){
        if(!APP_DEBUG){
            exit();
        }
        $act_id = I('get.act_id' , 0, 'AesDeCrypt');
        $number = I('get.number' , 0, 'intval');
        $number = ($number > 100 or $number < 5) ? 5 : $number;
        $time = time();
        $result = M('favourable_user_activity') ->where(array('id' =>$act_id))->find();
        if($act_id && $result && $number >= 5){
            for($i = 0; $i < $number ; $i++){
                $user_data = []; $order_data = [];
                $user_data = [
                    'user_id'        =>  0,
                    'action_id'      =>  $act_id,
                    'act_addtime'    =>  $time,
                    'act_updatetime' =>  $time,
                    'phone_number'   =>  $this->get_phone(),
                    'user_name'      =>  $this->get_name(),
                    'parent_id'      =>  0
                ];
                $order_data = [
                    'attend_id'      =>  M('users_activity')->add($user_data),
                    'act_id'         =>  $act_id,
                    'user_id'        =>  0,
                    'pay_status'     =>  2,
                    'order_amount'   =>  0,
                    'pay_time'       =>  $time,
                ];
                M('favourable_order')->add($order_data);
            }
            echo '生成完成'. "\n";
        }else{
            echo '生成失败' . '\n';
        }
    }

    /**
     * 生成随机姓名
     * @return string
     */
    private function get_name(){
        $first_name = ['赵','钱','孙','王','李','张','刘','周','王','李','刘','吴','郑','王','刘','程','冯','陈','王','褚','卫','张','蒋','沈','韩','杨','朱','秦','刘','许','王','何','吕','李','张','孔','曹','严','刘','金','李','魏','王','陶','张','姜','谢','王','邹','窦','章','张','苏','潘','葛','王','范','彭','鲁','韦','马','苗','方','俞','任','王','袁','刘','柳'];

        $last_name = ['灵洲','的','的','的','馨敏','菲洁','芙琪','萱菡','雅正','俊萱','锦桃','鹤帆','锦鸿','媛钰','彩媛','旭初','瑶琪','昭栀','楠灵','俊初','怡楠','雪凡','涵彩','锦霞','静琪','的','的','的','心妍','初涵','的','的','的','馨欣','倩梦','呈枫','玥彦','静蔚','萱妍','淑锦','梦怡','静玥','梦娜','的','的','的','茜颖','欣娅','栀弦','菲梅','花洁','柔彦','的','的','的','婧寒','美凌','芳云','琬柏','晨彦','璐初','春梅','璐妍','梓凌','云云','格丽','雪梅','静慧','梦敏','锦琬','静寒','采萱','琪楠','萱舒','呈旭','的','的','的','寒莉','雨惠','曼莲','婧欣','婧颖','明明','婧帛','祥菲','钰雅','月彦','彩彤','杉雯','花彦','琪婧','美初','寒妍','茹珍','冰梦','可楠','静可','驰杰','栋逸','驰逸','的','的','的','星运','稷龙','然骞','驰腾','的','的','的','运振','起尧','运良','槐禧','信泽','琛祯','骏锟','运乘','辰沛','日桀','振邦','梁家','骞震','强骞','强家','欣尧','仕仕','辰博','鹏轩','晨辰','裕弘','韦运','盛骏','辰桀','辰柔','轩礼','骏强','晓远','诚骏','休华','斌树','振远','的','的','的','震彬','俊驰','骞初','运骞','宇骞','良锟','峰震','楷树','浩逸','轩运','柏辰','腾海','泽辰','天运','辰运','的','的','的','星骏','铭晓','辰栋','振运','逸沛','振逸','锟腾','卓梓','辰骞','鑫仕','骏驰','加芃','博骏','皓国','然运','运树','辰仕','博天','的','的','的','爵泽','烁星','骏振','枫蔓','振子','骞辰','振涛','卓腾','的','的','的','的','的','的','的','的','的','的','的','的','的','的','的','的','的','的','的','的','的','的'];
        $first = rand(0, count($first_name) - 1);
        $second = rand(0, count($last_name) - 1);
        return $first_name[$first] . $last_name[$second];
    }

    /**
     * 生成随机手机号
     * @return string
     */
    private function get_phone(){
        $number = ['13', '15', '17', '18',];
        $first =  rand(0, count($number) - 1);
        return $number[$first]. get_rand_str(9,1);
    }

    /**
     * 获取活动参与的人
     * @param   get.act_id int 活动id
     * @param   get.is_pay int 是否支付
     * @param   get.type  int  类型
     */
    public function ajax_join_list(){
        $act_id = I('GET.act_id',0,'AesDeCrypt');
        $is_pay = I('GET.is_pay', '', 'trim');
        $type = I('GET.type', 'phone_number', 'trim');
        $join_model = new Model\UsersActivityModel();
        $result = result_ajax('200', '' ,  $join_model->get_user_list($act_id,$is_pay,$type, 1));
        /*}*/
        /** @var Project $Project */
        //$Project = Element::createObject('project', array($act_id));
        /** @var Activity $act */
        //$act = $Project->getActivity();
        /*if(empty($act)){
                $result = result_ajax(__LINE__,'当前不在活动期内！');
        }else{*/

        $this->ajaxReturn($result);
    }

    /**
     * 测试专用方法
     */
    public function dotest(){
       echo AesDeCrypt("5HcgmJ7q6fHMpoquEyYX9g==");

    }
}