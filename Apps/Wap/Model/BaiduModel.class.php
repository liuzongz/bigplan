<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/1/19 0019
 * Time: 上午 11:02:47
 */

namespace Wap\Model;
class BaiduModel extends BaseModel {
    private $ak = 'ihDDHwCX7FaUlPhKV1o9iQa5OLBAu0lE';
    private $baidu_api_url = 'http://api.map.baidu.com';
    function _initialize(){
        parent::_initialize();
    }

    function get_potion(){
        $url = $this->baidu_api_url . '/location/ip';
        $res = curlGet($url,'get',$this->MergeStr(['ip'=>get_client_ip()]));
        print_r($res);
    }

    function get_gps_position(){
        $x_lat = I('get.x');
        $y_lng = I('get.x');
        if (is_float($x_lat) and is_float($y_lng)) {
            $res = $this->CovertGps($x_lat, $y_lng);
            if ($res['error'] == 200) {
                http://api.map.baidu.com/geocoder/v2/?callback=renderReverse&location=39.983424,116.322987&output=json&pois=1&ak=您的ak
                $url = $this->baidu_api_url . '/geocoder/v2/';
                $info =  $res = curlGet($url,'get',$this->MergeStr(['ip'=>get_client_ip()]));
            } else {
                $result = result_ajax(305,'地址转换错误！');
            }
        } else {
            $result = result_ajax(300,'参数错误！');
        }
        return $result;
    }

    /**
     * 转换为百度经纬度
     * @param $x_lat float GPS取的经度
     * @param $y_lng float GPS取的纬度
     */
    private function CovertGps($x_lat, $y_lng){
        $url = $this->baidu_api_url . 'geocoder/v2/';
        $res = curlGet($url,'get', $this->MergeStr(['pois'=>1,'output'=>'json','location'=>"$x_lat,$y_lng"]));
        if ($res)
        $result = result_ajax(200,'', $res);
    }

    /**
     * 组成百度密钥
     * @param $data
     * @return mixed
     */
    private function MergeStr($data){
        $data['ak'] = $this->ak;
        return $data;
    }

    private function BaiduApi($url, $data = array(), $method = 'get'){
        $result = curlGet($url, $method, $this->MergeStr($data));

            return $result['result'];
    }
}