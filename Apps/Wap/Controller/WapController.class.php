<?php

/**
 * 即牛 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2015 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: WapController.class.php 17156 2015-12-25 17:32:47Z keheng $
*/
namespace Wap\Controller;
use Wap\Model;
class WapController extends WeixinController {
    protected $hkhp_api_url;
    protected $api = array();
    protected function _initialize() {
        parent::_initialize();
        $this->hkhp_api_url = 'http://' . C('KP_SERVER') . '/api/';
        if ($this->is_web('hkhp')) {
            $this->api = array(     //汇客惠品手机端
                'appid'     =>  'hk53b289c1eb72d590',
                'appsecret' =>  '2ee665a1095eec3386699f926ae726f8',
            );
        } elseif ($this->is_web('jiniu')) {
            $this->api = array(       //美领会
                'appid'     =>  'hk9ba59abbe56e057f',
                'appsecret' =>  '37ae5cf8c0c0b4c742bdca3882b46ee1'
            );
            //$this->hkhp_api_url = $this->cur_domain . '/Kpapi/api/';
        } else {
            $this->api = array(       //美领会
                'appid'     =>  'hk9ba59abbe56e057f',
                'appsecret' =>  '37ae5cf8c0c0b4c742bdca3882b46ee1'
            );
            //$this->hkhp_api_url = 'http://t4.jiniu.cc/?act=weipin';
        }
        //self::wxlogin();
        //self::get_position();
        $this->set_store();
    }

    /**
     * 没有位置信息则跳转位置获取页面
     */
    private function get_position(){
        if (!$this->position_info) {
            $this->redirect('Select/index');
        } else {
            $this->assign('position_info', $this->position_info);
        }
    }

    /**
     * 微信自动授权
     */
    public function wxlogin() {
        if ($this->store_wx and $this->is_weixin and !$this->wx_token) {
            if ($result = $this->wx_login2($_GET['code'],$_GET['state'])) {
                header('location:' . $this->back_url);
            } else {
                $url = $this->cur_domain . U() . '?back_act=' . AesEnCrypt($this->cur_url);
                $this->Authorize($url);
            }
        } else {
            //
        }
    }

    /**
     * 跳转定位页面
     */
    protected function get_location(){
        $location = session('location');
        if (!$location) {
            $this->redirect('Select/index');
        } else {
            $this->assign('location', $location);
        }
    }

    private function is_web($type, $str = ''){
        if ($str == '') $str = $_SERVER['HTTP_HOST'];
        if (strpos($str, $type)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 经销商店铺检查
     * @param $user_id
     * @param string $url
     */
    protected function void_store($user_id, $url = ''){
        //$stock_model = new Model\StockModel();
        if (!D('stock')->is_store($user_id)) {
            if ($this->ajax) {
                exit(json_encode($this->result_ajax(1,'您没有店铺或店铺已过期')));
            } else {
                $this->error('您没有店铺或店铺已过期！',$url);
            }
        }else{
            session("stocking",true);
        }
    }

    /**
     * api支付微币
     * @param int $order_sn   支付订单号（取微币为自动为1）
     * @param int $wb    支付需要的微币
     * @return bool|int
     */
    protected function change_integral( $order_sn = 1, $wb = 0){
        $this->logger( "订单号：" . $order_sn . " = {$this->_CFG['integral_name']}：" . $wb);
        $hkhp_info = $this->hkhp_api('pay_wb.html',['id'=>$order_sn,'wb'=>$wb]);
        if ($hkhp_info['error'] == 200) {
            $data = $hkhp_info['data'];
            if ($order_sn == 1) {
                $wb = $data['user_points'];
                return $wb;
            } else {
                if ($data['pay'] == 'success') {
                    return true;
                } else {
                    return false;
                }
            }
        }
    }

    private function set_store(){
        if($this->is_weixin && $this->store_token != C('jzc_store')){
            //跳过文章和登录模块
            if(stripos($_SERVER['REQUEST_URI'], 'article') || stripos($_SERVER['REQUEST_URI'], 'login') || stripos($_SERVER['REQUEST_URI'], 'access_token')) return;
            if(!$this->store_wx && !$this->store_token){
                $this->error('请从店铺公众号打开！');
            }


            $goods_model = new Model\StockModel();
            $store_info = $goods_model->get_store_info(['s.store_id'=>$this->store_token]);

            if (!$store_info['store_state']) {
                $this->error('店铺暂不能开启！');
            } else {
                session('store_token', $this->store_token);
                session('store_info', $store_info);
            }
        }

    }
}
