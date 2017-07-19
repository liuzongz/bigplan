<?php
namespace Sales\Controller;

class TestController extends WechatController {

    public function index(){
        $data = [
            'time' => time(),
            'str'  => '111',
            'sks'  => 'ssf'
        ];
        $data['sign'] = get_sign($data);
    }

    public function aa(){
        if (isset($_GET['debug']) and $_GET['debug'] == 'netbum') {
            print_R($_SESSION);
        } else {

        }
    }
}