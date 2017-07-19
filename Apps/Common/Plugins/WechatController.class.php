<?php
namespace Common\Plugins;


abstract class WechatController extends BaseController{

    public  $wxuser = "";  //

    private $wx_doamin = array(
        0=>'https://api.weixin.qq.com/',
        1=>'https://open.weixin.qq.com/',
        2=>'https://mp.weixin.qq.com/',
        3=>'https://api.mch.weixin.qq.com/'
    );

    private $wx_api_url = array(
        0=>'connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s#wechat_redirect',
        1=>'sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code',
        2=>'sns/userinfo?access_token=%s&openid=%s&lang=zh_CN',
        3=>'sns/oauth2/refresh_token?appid=%s&grant_type=refresh_token&refresh_token=%s',
        4=>'cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s',
        5=>'cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN',
        6=>'pay/unifiedorder',           //统一下单（支付）
        7=>'pay/orderquery',             //查询订单
        8=>'pay/closeorder',             //关闭订单
        9=>'secapi/pay/refund',             //申请退款
        10=>'pay/refundquery',             //查询退款
        11=>'pay/downloadbill',             //下载对账单
        12=>'cgi-bin/message/custom/send?access_token=%s',   //向用户发送消息
        13=>'cgi-bin/qrcode/create?access_token=%s',         //获取二维码
        14=>'cgi-bin/ticket/getticket?type=jsapi&access_token=%s', //获取微信js接口ticket
        15=>'cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s', //获取微信js接口token
        16=>'cgi-bin/showqrcode?ticket=%s' //根据ticket获取二维码地址
    );

    protected function _initialize(){
        parent::_initialize();
        $store_id = I('request.store_token',0, 'intval');
        $this->wxuser = $this->get_config($store_id);
        session('store_id', $this->store_id = $this->wxuser['store_id']);
    }

    /**
     * 获取access_token
     * 'cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s'
     * @return mixed
     */
    public  function getAccessToken(){
        $url = sprintf($this->get_url(15,0), $this->wxuser['appid'],$this->wxuser['appsecret']);
        $rt = curlGet($url);
        $res = json_decode($rt, 1);
        return $res['access_token'];
    }

    /**
     * 组合成URL
     * @param $file_id
     * @param int $domain   0：api   1:open   2:mp   3:pay
     * @return string
     */
    protected function get_url($file_id, $domain = 0){
        if ($domain > count($this->wx_doamin) - 1 or $domain < 0) $domain = 0;
        return $this->wx_doamin[$domain] . $this->wx_api_url[$file_id];
    }

    /**
     * 获取微信配置
     * @param $store_id
     * @return mixed
     */
    public function get_config($store_id){
        if($store_id){
            $return = M('store_wx')->where(array('store_id'=>$store_id))->find();
            if(empty($return)){
                $return = C('weixin_config');
                $return['store_id'] = C('jzc_store');
                return $return;
            } else {
                $return['appcode'] = $return['gd_id'];
                $return['appmchid'] = $return['mchid'];
                return $return;
            }
        }else{
            $return = C('weixin_config');
            $return['store_id'] = C('jzc_store');
            return $return;
        }
    }


}