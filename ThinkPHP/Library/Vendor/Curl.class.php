<?php
namespace Vendor;

class Curl {


    private function commonConfig($url){
    //get/post公告配置参数

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);                                  // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);                          // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);                          // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);     // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);                          // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);                             // 自动设置Referer
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);                                // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0);                                  // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);                          // 获取的信息以文件流的形式返回



        return $curl;
    }

    private function execute($curl){
        $tmpInfo = curl_exec($curl);
        if (curl_errno($curl)) {
            curl_close($curl);
            return false;
        }else{
            curl_close($curl);
            return $tmpInfo;
        }
    }

    function get($url) {

        //初始化
        $curl = $this->commonConfig($url);
        curl_setopt($curl, CURLOPT_HTTPGET, 1);                                 //恢复默认的get请求
        return $this->execute($curl);

    }

    function post($url, $data) {

        //初始化
        $curl = $this->commonConfig($url);
        curl_setopt($curl, CURLOPT_POST, 1);                                    // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);                          // Post提交的数据包
        return $this->execute($curl);

    }



}