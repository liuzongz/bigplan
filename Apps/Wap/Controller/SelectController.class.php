<?php

/**
 * 定位 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: SelectController.class.php 17156 2016-12-07 17:31:47Z keheng $
*/

namespace Wap\Controller;
class SelectController extends WeixinController {
    private static $_instance;
    const REQ_GET = 1;
    const REQ_POST = 2;

    protected function _initialize() {
        parent::_initialize();
    }

    public function index(){

        if(!F('city_list')){
            $city = M('Region')->where('region_type = 2')->select();
            foreach ($city as $k => $v){
                $letter = get_first_charter($v['region_name']);
                $data[$letter][] = $v['region_name'];
            }
            ksort($data);
            F('city_list',$data);
        }
        $city_list =  F('city_list');
        $this->assign('city', $city_list);
        $this->display();
    }

    public function search(){
        $address = I('post.keyword','','trim');
        $city = I('post.city','','trim');

        if(empty($address) and empty($city)){
            return $this->echo_ajax(300,'缺少地址信息！');
        }

        $api = 'http://api.map.baidu.com/place/v2/suggestion';
        $api .= '?query='.$address;
        $api .= '&region='.$city;
        $api .= '&output=json';
        $api .= '&ak=ihDDHwCX7FaUlPhKV1o9iQa5OLBAu0lE';

        $resp = $this->curlGet($api, false, $api);
        $data = json_decode($resp, true);
        //有错误
        if ($data['status'] != 0)
        {
            $result = $this->echo_ajax(300,'地址获取失败！');
        } else {
            $result = $this->echo_ajax(200,'地址获取成功！',$data['result']);
        }

        //返回地址信息
        $this->ajaxReturn($result);
    }

    public function setcity(){
        $this->display();
    }

    public function map(){
        $this->display();
    }

    /**
     * 单例模式
     * @return map
     */
    public static function instance() {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * ip定位
     * @param string $ip
     * @return array
     * @throws \Exception
     */
    public function locationByIP($ip) {
        //检查是否合法IP
        if (!filter_var($ip, FILTER_VALIDATE_IP))  {
            throw new \Exception('ip地址不合法');
        }
        $params = array(
            'ak' => 'ihDDHwCX7FaUlPhKV1o9iQa5OLBAu0lE',
            'ip' => $ip,
            'coor' => 'bd09ll'//百度地图GPS坐标
        );
        $api = 'http://api.map.baidu.com/location/ip';
        $resp = $this->curlGet($api, $params);
        $data = json_decode($resp, true);
        //有错误
        if ($data['status'] != 0)
        {
            throw new \Exception($data['message']);
        }
        //返回地址信息
        return array(
            'address' => $data['content']['address'],
            'province' => $data['content']['address_detail']['province'],
            'city' => $data['content']['address_detail']['city'],
            'district' => $data['content']['address_detail']['district'],
            'street' => $data['content']['address_detail']['street'],
            'street_number' => $data['content']['address_detail']['street_number'],
            'city_code' => $data['content']['address_detail']['city_code'],
            'lng' => $data['content']['point']['x'],
            'lat' => $data['content']['point']['y']
        );
    }

    /**
     * GPS定位
     * @param $lng
     * @param $lat
     * @return array
     * @throws \Exception
     */
    public function locationByGPS($lng, $lat)    {
        $params = array(
            'coordtype' => 'wgs84ll',
            'location' => $lat . ',' . $lng,
            'ak' => 'ihDDHwCX7FaUlPhKV1o9iQa5OLBAu0lE',
            'output' => 'json',
            'pois' => 0
        );
        $resp = $this->curlGet('http://api.map.baidu.com/geocoder/v2/', $params, false);
        $data = json_decode($resp, true);
        if ($data['status'] != 0)    {
            throw new \Exception($data['message']);
        }
        return array(
            'address' => $data['result']['formatted_address'],
            'province' => $data['result']['addressComponent']['province'],
            'city' => $data['result']['addressComponent']['city'],
            'street' => $data['result']['addressComponent']['street'],
            'street_number' => $data['result']['addressComponent']['street_number'],
            'city_code'=>$data['result']['cityCode'],
            'lng'=>$data['result']['location']['lng'],
            'lat'=>$data['result']['location']['lat']
        );
    }

    public function uplocation(){
        $lat = (float)I('lat');
        $lng = (float)I('lng');
        $name = I('name', '', 'trim');

        $params = array(
            'location' => $lat . ',' . $lng,
            'ak' => 'ihDDHwCX7FaUlPhKV1o9iQa5OLBAu0lE',
            'output' => 'json',
            'pois' => 0
        );
        $resp = $this->curlGet('http://api.map.baidu.com/geocoder/v2/', false, $params);
        $data = json_decode($resp, true);
        if($data['status'] != 0){
            $result = $this->echo_ajax(300,'位置设置失败！');
        }else{
            $city = $data['result']['addressComponent']['city'];
            $location = ['lat' => $lat ,'lng'=> $lng, 'name'=> $name, 'city' => $city];
            session('location', $location);
            $result = $this->echo_ajax(200,'位置设置成功！', $data['result']);
        }

        $this->ajaxReturn($result);
    }
} 