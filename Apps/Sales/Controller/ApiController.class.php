<?php
namespace Sales\Controller;
use Sales\Element\Activity;
use Sales\Element\Base\Di\ServiceLocator;
use Sales\Element\Element;
use Sales\Element\Favourable\Favourable;
use Sales\Element\Goods;

class ApiController extends BaseController {
    protected function _initialize(){
        parent::_initialize();
        $module = I('get.module', '', ['strtolower','ucwords']);
        if(in_array($module, C('ALLOW_MODULE'))){
            //$this->checkSign();//验证签名
            C('CURRENT_MODULE', $module);
        }else{

            $this->ajaxReturn(result_json(501, '请求失败！', $module));
        }
    }

    //验证签名
    private function checkSign() {
        $appid = I("post.appid", '', 'trim');
        $appsecret = I("post.appsecret", '', 'trim');
        if (!I("post.sign") || !I("post.timestamp") || !$appid || !$appsecret) {
            $this->ajaxReturn(result_json(302, '缺少签名参数！'));
        }
        $appinfo = M('FavourableAppinfo')->where(['appid'=>$appid, 'appsecret'=>$appsecret, 'is_open'=>true])->find();
        if(!$appinfo){
            $this->ajaxReturn(result_json(401, 'appid参数错误！'));
        }
        if(!test_sign(I('post.sign','','trim'))) {
            $this->ajaxReturn(result_json(301, '签名失败！'));
        }
        return;
    }


    //获取营销工具种类列表
    public function get_favourables(){
        $instance = (boolean)I('post.instance');//false 时返不返回活动相关配置
        $all = (boolean)I('post.all');
        /**@var Element $favourables*/
        $favourables = Element::getFavourables($instance, $all);
        /**@var Favourable $val*/
        $data = [];
        if(!$instance){//不存在时候不返回配置
            $data = $favourables;
        }else{
            foreach($favourables as $key => $val){
                $data[$key]['title'] = $val->title;
                $data[$key]['name'] = $val->name;
                $data[$key]['config'] = $val->getConfig();
            }
        }
        $this->ajaxReturn(result_json(200, '获取成功！', $data));
    }

    //验证营销工具和相关配置
    public function act_validate(){
        $act_type = I('post.act_type','','trim');
        $config = json_decode(I('post.config','','trim'),true);
        if(!$act_type || !$config) $this->ajaxReturn(result_json(303, '缺少签名必要参数！'));
        /**@var Favourable $favourable*/
        $favourable = Element::$serviceLocator->get($act_type, false);
        if(!$favourable){
            $this->ajaxReturn(result_json(601, '营销工具不存在！'));
        }
        if($favourable->disabled){
            $this->ajaxReturn(result_json(602, '营销工具不发可布活动！'));
        }
        if (!$favourable->validateConfig($config)){
            $this->ajaxReturn(result_json(610, "验证失败:{$favourable->error}"));
        }
        $this->ajaxReturn(result_json(200, '验证通过！', ['code'=>'success']));
    }

    //获取活动相关配置
    public function get_act_info(){
        $id = AesDeCrypt(I('post.act_id','','trim'));//传过来时候需加密
        $act_data = json_decode(I('post.act_data','','trim'),true);//不存在时候查询数据库
        $is_sign = (int)I('post.is_sign');
        $paid = json_decode(I('post.paid','','trim'),true);
        if(!$id && !$act_data) $this->ajaxReturn(result_json(303, '缺少签名必要参数！'));

        if(isset($paid['order_info']) && isset($paid['order_goods']) && isset($paid['inviter'])){
            /**@var Activity $active*/
            $active = Element::createObject('activity');
            $active->setData($id, true);
            $data = $active->data;
            $data['paid'] = $active->paid($paid['order_info'], $paid['order_goods'], $paid['inviter']);
        }else{
            /**@var Activity $active*/
            $active = Element::createObject('activity', array($id ,$act_data));
            if(!$active->isExist()) $this->ajaxReturn(result_json(601, '营销工具不存在！'));
            if($is_sign && !$active->isSign()) $this->ajaxReturn(result_json(603, '活动报名时间已过期！'));
            $data = $active->data;
        }
        $data['config'] = $active->getConfig($act_data);
        $data['auth_store'] = $active->getAuthStore();//获得店铺权限
        $data['auth_cate'] = $active->getAuthCate();//获取分类权限
        $data['auth_user'] = $active->getAuthUser();//获取用户权限

        $this->ajaxReturn(result_json(200, '获取成功！', $data));
    }

    /**获取单个商品相关活动*/
    public function get_goods_act(){
        $goods_id = I('post.goods_id',0,'intval');//传过来时候需加密
        $aid = I('post.aid',0,['AesDeCrypt','intval']);//传过来时候需加密
        $goods_info = json_decode(I('post.goods_info','','trim'), true);//不存在时候查询数据库
        if(!$goods_id && !$goods_info) $this->ajaxReturn(result_json(303, '缺少必要参数！'));
        /**@var Goods $goods*/
        $goods = Element::createObject('goods', array($goods_id,$goods_info));

        if(!isset($goods->ActivityList[$aid])) $this->ajaxReturn(result_json(603, '活动不存在或已过期！'));
        /**@var Activity $act*/
        $act = $goods->ActivityList[$aid];
        $data['auth_user'] = $act->getAuthUser();//获得商品权限
        $data['data'] = $act->data;
        $data['goods_info'] = $goods->data;
        $data['act_html'] = $act->view('goods/default');
        $data['act_button'] = $act->view('goods/button');
        $data['is_cart'] = $act->cart();
        $data['RMB'] = $act->price()->RMB;
        $data['WB'] = $act->price()->WB;
        $data['is_pay'] = $act->pay();
        $this->ajaxReturn(result_json(200, '获取成功！', $data));
    }

    /**获取多个商品相关活动*/
    public function get_goods_list(){
        $goods_list = json_decode(I('post.goods_list','','trim'), true);
        $act_id = I('post.act_id',0,['AesDeCrypt', 'intval']); //活动id
        if(!is_array($goods_list) && !$goods_list) $this->ajaxReturn(result_json(303, '缺少必要参数！'));
        /**@var Goods $goods*/
        $goods = Element::createObject('goods', array(''));

        $activity = array();
        foreach ($goods_list as $val){
            $goods->setData($val);
            /* @var Activity $act */
            $act = $goods->getActivityList();
            $each = array();
            if($act_id && isset($act[$act_id])){
                $each['act_config'] = $act->config;
                $each['act_data']   = $act->data;
                $each['act_price']  = $act->price()->RMB;
                $each['act_format_price'] = (string)$act->price();
                $each['show_price'] = $act->price()->WB;
                $each['auth_user'] = $act->getAuthUser();
            }elseif($act){
                $act = reset($act);
                $each['act_config'] = $act->config;
                $each['act_data']   = $act->data;
                $each['act_price']  = $act->price()->RMB;
                $each['act_format_price'] = (string)$act->price();
                $each['show_price'] = $act->price()->WB;
                $each['auth_user'] = $act->getAuthUser();
            }

            if($each){
                $activity[$val['goods_id']] = $each;
            }
        }
        $this->ajaxReturn(result_json(200, '获取成功！', $activity));
    }

    //获取所有活动相关的数据
    public function get_act_allGoods(){
        $id = I('post.act_id',0,['AesDeCrypt', 'intval']);//传过来时候需加密
        $act_data = json_decode(I('post.act_data','','trim'));//不存在时候查询数据库
        if(!$id && !$act_data) $this->ajaxReturn(result_json(303, '缺少签名必要参数！'));

        /**@var Activity $active*/
        $active = Element::createObject('activity', array($id, $act_data));
        if(!$active->isExist()) $this->ajaxReturn(result_json(601, '营销工具不存在！'));

        $this->ajaxReturn(result_json(200, '获取成功！', $active->getDataOfGoods()));
    }

}