<?php
namespace Common\Plugins;


class QrcodeController extends WechatController{

    //请求ticket的数组
    private $qr_arr = array();

    protected function _initialize(){
        parent::_initialize(); //
    }


    /**
     * 微信生成二维码
     * @param $qrid  数据库二维码id
     * @param int $expiry_time  二维码有效期
     * @return mixed
     */
    public function get_wxqrcode($qrid, $expiry_time = 604800){
        $accrss_token = $this->getAccessToken();
        $url = sprintf($this->get_url(13,0), $accrss_token);
        if(intval($expiry_time) >0 ){     //临时二维码
            $this->qr_arr = [
                'expire_seconds' => $expiry_time>2592000 ? 2592000 : $expiry_time,
                'action_name'    => 'QR_SCENE',
                'action_info'   => array(
                    'scene'    => array(
                        'scene_id' => $qrid,
                    )
                ),
            ];
        } else {                          //永久二维码
            $this->qr_arr = [
                'action_name'    => 'QR_LIMIT_STR_SCENE',
                'action_info'   => array(
                    'scene'    => array(
                        'scene_str' => $qrid,
                    )
                ),
            ];
        }
        $result = json_decode(curlGet($url, 'POST', json_encode($this->qr_arr))); //根据access_token获取ticket
        debug($result, 1);
        $ticket = UrlEncode($result->ticket);   //  根据ticket获取临时二维码
        $get_url = sprintf($this->get_url(16,2), $ticket);
        $data = [
            'user_id'       =>  1,
            'user_name'     =>  '微信二维码',
            'login_module'  =>  'Common',
            'add_time'      =>  NOW_TIME
        ];
        $cookie = 'wp_' . strtoupper(MODULE_NAME) . '=' . RsaEnCrypt(json_encode($data));
        /*$result = curlGet($get_url, 'GET', '', $cookie);
        $file = file_get_contents($result, FILE_USE_INCLUDE_PATH);*/
        $uploads_url =get_server(
            'IMG_SERVER',
            '/Uploader/wx_qrcode',
            ['url'=>$get_url,'cat_id'=>2,'module'=>MODULE_NAME],
            1
        );
        debug($uploads_url, 1);
        debug($cookie, 1);
        $result = curlGet($uploads_url, 'POST', '', $cookie);
        return $result;
    }

}