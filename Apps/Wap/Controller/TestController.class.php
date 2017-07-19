<?php

/**
 * 即牛 - ${readme}
 * ============================================================================
 * 版权所有 2011-2015 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: GoodsController.class.php 17156 2015-12-11 16:10:47Z keheng $
 */

namespace Wap\Controller;
use Think\Model;
use Think\Controller;
use Org\Util;
class TestController extends Controller {
    protected function _initialize(){
        //parent::_initialize();
        //$this->void_user();
    }

    public function index(){
        echo get_cur_domain() . "<br/>\n";
        echo get_cur_url();
        exit;
        $this->display();
    }


    public function aa(){
        $desc = '<p><img src="/Uploads/Agency/92/2016-10/5805d0b92ad36.jpg" title="XX15.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d0a2e5dd6.jpg" title="XX12.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d0a02dabb.jpg" title="XX10.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d09869539.jpg" title="XX4.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d09c8762a.jpg" title="XX11.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d09712e4e.jpg" title="XX8.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d09042129.jpg" title="XX2.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d0a0ae09a.jpg" title="XX9.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d09c55385.jpg" title="XX13.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d098aa5fb.jpg" title="XX7.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d092735f8.jpg" title="XX5.jpg"/></p><p><img src="/Uploads/Agency/92/2016-10/5805d09552c10.jpg" title="XX6.jpg"/></p>';
        //$desc = '<p><img class="bigger" src="Uploads/system/1/2016-11/583b8a04d0bce.jpg" title="kp_tell_01.jpg"/></p><p><img class="bigger" src="Uploads/system/1/2016-11/583b8a05b1f38.jpg" title="kp_tell_02.jpg"/></p><p><img class="bigger" src="Uploads/system/1/2016-11/583b8a0505c04.jpg" title="kp_tell_03.jpg"/></p><p><img class="bigger" src="Uploads/system/1/2016-11/583b8a068df91.jpg" title="kp_tell_04.jpg"/></p><p><img class="bigger" src="Uploads/system/1/2016-11/583b8a0523d95.jpg" title="kp_tell_05.jpg"/></p><p><img src="Uploads/system/1/2016-11/583b8a05978f3.jpg" title="kp_tell_06.jpg"/></p><p><img class="bigger" src="Uploads/system/1/2016-11/583b8a067363f.jpg" title="kp_tell_07.jpg"/></p><p><img class="bigger" src="Uploads/system/1/2016-11/583b8a08714b8.jpg" title="kp_tell_08.jpg"/></p><p><img class="bigger" src="Uploads/system/1/2016-11/583b8a07a1af3.jpg" title="kp_tell_09.jpg"/></p><p><img class="bigger" src="Uploads/system/1/2016-11/583b8a088fcd1.jpg" title="kp_tell_10.jpg"/></p>';
        //echo $desc;
        echo $this->ReplaceImgUrl($desc);
        exit;
    }

    private function ReplaceImgUrl($text){
        $patten = '/\<img[^src]*src=[\"\']?([^\'\"]+)[\"\']?[^>]+\>/i';
        $patten = '/<img^[?!src]+src=[\"\']?([^\'\"]+)[\"\']?[^>]+\>/i';
        $patten = '/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i';
        $patten = '/<img\s+[^>]*?src=(\'|\")(.*?)\\1[^>]+>/i';
        //$patten = '/<img\s.+src=[\"\']?([^\"]+)[\"\']?[^>]+\>/is';
        preg_match_all($patten, $text, $s);
        print_r($s);
        if (count($s) == 3) {
            foreach ($s[2] as $v) {
                $text = str_replace($v,img_url($v),$text);
            }
        }
        return $text;
    }

    function crypt(){

        echo "AES:\n";
        //header('Content-Type: text/plain;charset=utf-8');
        $data = 'phpbest';
        $key = 'oScGU3fj8m/tDCyvsbEhwI91M1FcwvQqWuFpPoDHlFk='; //echo base64_encode(openssl_random_pseudo_bytes(32));
        $iv = 'w2wJCnctEG09danPPI7SxQ=='; //echo base64_encode(openssl_random_pseudo_bytes(16));
        echo '内容: '.$data."\n";

        /*$encrypted = openssl_encrypt($data, 'aes-256-cbc', base64_decode($key), OPENSSL_RAW_DATA, base64_decode($iv));
        echo '加密: '.base64_encode($encrypted)."\n";*/
        $encrypted = AesEnCrypt($data);
        echo '加密: '. $encrypted . "\n";

        /*$encrypted = base64_decode('To3QFfvGJNm84KbKG1PLzA==');
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', base64_decode($key), OPENSSL_RAW_DATA, base64_decode($iv));
        echo '解密: '.$decrypted."\n";*/
        $decrypted = AesDeCrypt($encrypted);
        echo '解密: '. $decrypted . "\n";
        ?>

        RSA:
        用openssl生成rsa密钥对(私钥/公钥):
        openssl genrsa -out rsa_private_key.pem 1024
        openssl rsa -pubout -in rsa_private_key.pem -out rsa_public_key.pem

        <?php
        //header('Content-Type: text/plain;charset=utf-8');
        $data = 'phpbest';
        echo '原始内容: '.$data."\n";
        $key_pass = "Apps/Common/Conf";
        //openssl_public_encrypt($data, $encrypted, file_get_contents($key_pass . '/rsa_public_key.pem'));
        $encrypted = RsaEnCrypt($data);
        echo '公钥加密: '.$encrypted."\n";

        //$encrypted = base64_decode('nMD7Yrx37U5AZRpXukingESUNYiSUHWThekrmRA0oD0=');
        //openssl_private_decrypt($encrypted, $decrypted, file_get_contents($key_pass . '/rsa_private_key.pem'));
        $decrypted = RsaDeCrypt($encrypted);
        echo '私钥解密: '.$decrypted."\n";
        exit;
    }

    function abc() {
        $str = 'qXhs3JhB3aX6jOQ7Jd1//TcRPPJwYHNHkX2qGwFH1gsFcVmVk0SxnBpcp7QtpQ2mI8dPk69cB5dILOPtFN/m6v7JppTGBTbZb0qn5//yIG95ebDq3YQhdFHeJhAuorSsIn/LnUP/IGaU0ROdbGgl3oKAsTm08r6i4273nz9hBck=';
        $str = 'http://t0.jiniu.cc/Sales/active/detail.html?aid=tYz4/clVyp6XKIeHSdnFFQ==&status=mmZTTYnyTrlRmmjJ06AeOg==';
        echo $str;
        print_r(U('User/login') . '?back_act=' . RsaEnCrypt($str));

    }

    function redis(){
        import('Org.Util.redis');
        $redis = new \Org\Util\redis();
        $redis->connect('t0.jiniu.cc',6379,'Jiniu2016**+_');
        $key = get_rand_str(5,1);
        $redis->set($key,md5($key));
    }

    function aaaa(){
        $s = 'a:6:{s:7:"deposit";s:1:"1";s:5:"limit";s:3:"100";s:4:"step";a:1:{i:0;a:3:{s:3:"man";s:2:"10";s:5:"money";s:3:"100";s:4:"desc";s:15:"包括老会员";}}s:8:"act_rule";s:66:"女欧尼
vmowm门票vorem
麻婆女热
麻婆人么
麻婆热";s:3:"tel";s:11:"18707195251";s:7:"project";a:3:{s:6:"name_1";s:0:"";s:6:"name_2";s:0:"";s:6:"name_3";s:0:"";}}';
        $s = unserialize($s);
        print_r($s);

        $ss = [
            'deposit' => 1,
            'limit' => 100,
            'step' => [
                0 => [
                    'man' => 10,
                    'money' => 100,
                    'desc' => '包括老会员',
                ]
            ],
            'act_rule' => "2017春季班大放“价”
原价1200元春季班，总计12次课，接龙太划算啦，你们敢喊人一起接龙，我就陪你们疯狂到底~
优惠不断：
[表情]前30名接龙用户，赠送清风教育定制书包一个。
[表情]诚意金100元，超过500人，不需再补费用，即可上课！！
[表情]鸡年大吉，遇“7”赠礼：凡是排名含7的用户赠送画笔套装一副。",
            'tel' => '18707195251',
            'project' => [
                'name_1' => '',
                'name_2' => '',
                'name_3' => ''
            ]
        ];
        print_r($ss);
        echo serialize($ss);
        $s = '{s:100:"发热外面跑
没法破二维码
，评分为
，【】陪我
，【】陪我
，【】而我";}';
        $s = serialize('1.点击“我要报名”，参加活动。
2.接龙总人数达标即可享受对应档优惠。
3.接龙失败诚意金原路退回，成功则诚意金不退。
4.加入接龙的人越多越优惠全员享受同一个优惠。');
        print_r($s);
    }


    function showImg(){
        $this->show('<html><head><title>测试微信分享</title></head><body><img id="shareImage" src="/timg.jpg" style="display: none;width:300px;height:300px;"><div style="background:blue;height:100px;"></div><div class="imgWarp"><img src="/Public/Sales/images/Solitaire.jpg"></div></body></html>');

        exit;
    }
}