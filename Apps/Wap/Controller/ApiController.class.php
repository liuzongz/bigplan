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
 * $Id: ApiController.class.php 17156 2015-12-25 17:32:47Z keheng $
*/
namespace Wap\Controller;
use Wap\Model;

class ApiController extends WeixinController {
    public $token;
    private $fun;
    public $data = array();
    public $mydata;

    protected function _initialize() {
        parent::_initialize();
    }

    public function index() {
        $this->logger('该文件被访问了' . print_r($_SESSION,1) . "\n" );
        if (empty($_GET["echostr"])) {
            echo $this->responseMsg();
        } else {
            $this->valid();
        }
    }

    private function valid() {
        $this->logger('验证:' . print_r($_GET,1));
        $echoStr = $_GET["echostr"];
        if ($this->checkSignature()) {
            exit($echoStr);
        }
    }

    private function checkSignature() {
        $signature = I("signature");
        $timestamp = I("timestamp");
        $nonce = I("nonce");
        $tmpArr = array(I('get.token'), $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }


    public function responseMsg() {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr)) {
            $this->logger("获取源数据：". $postStr);
            $postObj = $this->xml2array($postStr);
            switch ($postObj['MsgType']) {
                case "event":       //事件
                    $result = $this->receiveEvent($postObj);
                    break;
                case "text":        //文字消息
                    $result = $this->receiveText($postObj);
                    break;
                case "image":       //图片
                    $result = $this->receiveImage($postObj);
                    break;
                case "location":        //位置信息
                    $result = $this->receiveLocation($postObj);
                    break;
                case "voice":       //公告
                    $result = $this->receiveVoice($postObj);
                    break;
                case "video":       //视频
                    $result = $this->receiveVideo($postObj);
                    break;
                case "link":        //链接
                    $result = $this->receiveLink($postObj);
                    break;
                default:
                    $result = "unknow msg type: " . $postObj['MsgType'];
                    break;
            }
            $this->logger("输入的数据：" . $result);
            return $result;
        } else {
            return "";
        }
    }

    /**
     * 微信事件
     * @param $object
     * @return string
     */
    private function receiveEvent($object) {
        $result = null;
        switch ($object['Event']) {
            case "subscribe":       //关注事件
                $content = $this->receiveEvent_subscribe($object);
                break;
            case "unsubscribe":     //取消关注事件
                $content = $this->receiveEvent_unsubscribe($object);
                break;
            case "SCAN":            //扫描关注事件
                $content = $this->receiveEvent_scan($object);
                break;
            case "CLICK":           //点事事件
                $content = $this->receiveEvent_click($object);
                break;
            case "LOCATION":            //取位置事件
                $content = $this->receiveEvent_location($object);
                break;
            case "VIEW":            //微信菜单点击事件
                $content = $this->receiveEvent_view($object);
                break;
            default:
                $content = "receive a new event: " . $object['Event'];
                $this->logger('选到这里！' . $content);
                break;
        }
        if (is_array($content)) {
            if (isset($content[0]['PicUrl'])) {
                $result = $this->transmitNews($object, $content);
            } else if (isset($content['MusicUrl'])) {
                $result = $this->transmitMusic($object, $content);
            }
        } else {
            $result = $this->transmitText($object, $content);
        }
        return $result;
    }

    private function receiveText($object) {
        $keyword = trim($object['Content']);
        //$content = M()->contents($object);
        switch ($keyword) {
            case "测试": $content = "ToUserName:".$object['ToUserName']." FromUserName:".$object['FromUserName']." CreateTime:".$object['CreateTime']." MsgType".$object['MsgType']." Content".$object['Content']."";break;
            case "我的分享":
                $content = array(
                    array(
                        "Title" => "我的分享二维码",
                        "PicUrl" => "http://www.jiniu.cc/Uploads/userinviter/pic_".$object['FromUserName'].".jpg",
                        "Url" => "http://www.jiniu.cc/Wap/Share/index/openid/".$object['FromUserName']."/token/100001.html"
                    )
                );
                break;
            case "单图文":
                $content = array(
                    array(
                        "Title" => "单图文标题",
                        "Description" => "单图文内容--".$this->token,
                        "PicUrl" => "http://discuz.comli.com/weixin/weather/icon/cartoon.jpg", "Url" => "http://m.cnblogs.com/?u=txw1958"
                    )
                );
                break;
            case "多图文":
                $content[] = array(
                    "Title" => "多图文1标题",
                    "Description" => "",
                    "PicUrl" => "http://discuz.comli.com/weixin/weather/icon/cartoon.jpg",
                    "Url" => "http://m.cnblogs.com/?u=txw1958"
                );
                $content[] = array(
                    "Title" => "多图文2标题",
                    "Description" => "",
                    "PicUrl" => "http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" => "http://m.cnblogs.com/?u=txw1958"
                );
                $content[] = array(
                    "Title" => "多图文3标题",
                    "Description" => "",
                    "PicUrl" => "http://g.hiphotos.bdimg.com/wisegame/pic/item/18cb0a46f21fbe090d338acc6a600c338644adfd.jpg",
                    "Url" => "http://m.cnblogs.com/?u=txw1958"
                );

                break;
            case "笑话":
                $url = "http://apix.sinaapp.com/joke";
                $content = file_get_contents($url);
                break;
            case "天气"://有bug
                // $url = "http://apix.sinaapp.com/weather/?appkey=".$object['ToUserName'].d"&city=".urlencode($entity);
                $url = "http://apix.sinaapp.com/weather/?appkey=trialuser&city=" . urlencode($entity);
                $output = file_get_contents($url);
                $weather = json_decode($output, true);
                //var_dump($weather['results'][0]['weather_data']);
                foreach ($weather['results'][0]['weather_data'] as $k => $v) {
                    $content[] = array("Title" => $v['date'], "Description" => $v['weather'] . $v['wind'] . $v['temperature'], "PicUrl" => date('H') > 18 ? $v['nightPictureUrl'] : $v['dayPictureUrl'], "Url" => "");
                }

                break;
            case "音乐":
                $content = array(
                    "Title" => "最炫民族风",
                    "Description" => "歌手：凤凰传奇",
                    "MusicUrl" => "http://121.199.4.61/music/zxmzf.mp3",
                    "HQMusicUrl" => "http://121.199.4.61/music/zxmzf.mp3"
                );
                break;
            case '首页':
            case '主页':
                $home = M('Home')->where(array('token' => $this->token))->find();
                if ($home == false) {
                    $content = '商家未做首页配置，请稍后再试';
                } else {
                    if ($home['apiurl'] == false) {
                        $url = C('site_url') . U('Wap/Index/index?token=' . $this->token . '&wecha_id=' . $object['FromUserName']);
                    } else {
                        $url = $home['apiurl'];
                    }
                    $content = array(
                        array(
                            "Title" => $home['title'],
                            "Description" => $home['info'],
                            "PicUrl" => $home['picurl'],
                            "Url" => $url
                        )
                    );
                }
                break;
            case 'help':
            case '帮助':
                $data = M('Areply')->where(array('token' => $this->token))->find();
                $content = $data['content'];
                break;
            case '会员':
            case '会员卡':
                $content[] = $this->member($object);
                break;
            case '商城':
                $pro = M('reply_info')->where(array(
                    'infotype' => 'Shop',
                    'token' => $this->token
                ))->find();
                if (!$pro) {
                    $content = '暂无商品！';
                } else {
                    $content[] = array('Title' => $pro['title'], 'Description' => strip_tags(htmlspecialchars_decode($pro['info'])), 'PicUrl' => $pro['picurl'], "Url" => C('site_url') . U('Wap/Product/index?token=' . $this->token . '&wecha_id=' . $object['FromUserName']));
                }

                break;
            case '留言':
                $pro = M('reply_info')->where(array(
                    'infotype' => 'Liuyan',
                    'token' => $this->token
                ))->find();

                if (!$pro) {
                    $content = '商家无配置！';
                } else {
                    $content[] = array('Title' => $pro['title'], 'Description' => strip_tags(htmlspecialchars_decode($pro['info'])), 'PicUrl' => $pro['picurl'], "Url" => C('site_url') . U('Wap/Liuyan/index?token=' . $this->token . '&wecha_id=' . $object['FromUserName']));
                }
                break;

            case '团购':
                $pro = M('product')->where(array(
                    'groupon' => '1',
                    'token' => $this->token
                ))->find();
                if (!$pro) {
                    $content = '商家无配置！';
                } else {
                    $content[] = array('Title' => $pro['title'], 'Description' => strip_tags(htmlspecialchars_decode($pro['info'])), 'PicUrl' => $pro['picurl'], "Url" => C('site_url') . U('Wap/Groupon/grouponIndex?token=' . $this->token . '&wecha_id=' . $object['FromUserName']));
                }

                break;
            default:
                if ($img == false) {
                    //$content =  '无此图文信息,请提醒商家，重新设定关键词';
                    /*$textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[transfer_customer_service]]></MsgType>
<Content><![CDATA[%s]]></Content>
</xml>";
                    $result = sprintf($textTpl, $object['FromUserName'], $object['ToUserName'], time(), $keyword);
                    return $result;*/
                    //$this->transmitText($object,$keyword);
                    $content = array('transfer'=>$keyword);
                } else {
                    foreach ($img as $keya => $infot) {
                        if ($infot['url'] != false) {
                            $url = $infot['url'];
                        } else {
                            $url = rtrim(C('site_url'), '/') . U('Wap/Index/content', array(
                                    'token' => $this->token,
                                    'id' => $infot['id']
                                ));
                        }

                        $content[] = array("Title" => $infot['title'], "Description" => $infot['text'], "PicUrl" => $infot['pic'], "Url" => $url);
                    }
                }
                break;
        }
        if (is_array($content)) {
            if (isset($content[0]['PicUrl'])) {
                $result = $this->transmitNews($object, $content);
            } else if (isset($content['MusicUrl'])) {
                $result = $this->transmitMusic($object, $content);
            } else if (isset($content['transfer'])) {
                $result = $this->transmitText($object, $content['transfer'], 'transfer_customer_service');
            } else {
                $result = '';
            }
        } else {
            $result = $this->transmitText($object, $content);
        }
        return $result;
    }

    /**
     * 图文
     * @param $object
     * @return string
     */
    private function receiveImage($object) {
        $content = array("MediaId" => $object['MediaId']);
        $result = $this->transmitImage($object, $content);
        return $result;
    }

    private function receiveLocation($object) {
        $content = "你发送的是位置，纬度为：" . $object['Location_X'] . "；经度为：" . $object['Location_Y'] . "；缩放级别为：" . $object['Scale'] . "；位置为：" . $object['Label'];
        $result = $this->transmitText($object, $content);
        return $result;
    }

    private function receiveVoice($object) {
        if (empty($object['Recognition'])) {
            $content = array("MediaId" => $object['MediaId']);
            $result = $this->transmitVoice($object, $content);
        } else {
            $content = "你刚才说的是：" . $object['Recognition'];
            $result = $this->transmitText($object, $content);
        }

        return $result;
    }

    private function receiveVideo($object) {
        $content = array("MediaId" => $object['MediaId'], "ThumbMediaId" => $object['ThumbMediaId'], "Title" => "", "Description" => "");
        $result = $this->transmitVideo($object, $content);
        return $result;
    }

    private function receiveLink($object) {
        $content = "你发送的是链接，标题为：" . $object['Title'] . "；内容为：" . $object['Description'] . "；链接地址为：" . $object['Url'];
        $result = $this->transmitText($object, $content);
        return $result;
    }

    private function transmitText($object, $content, $type = 'text') {
        $data = array(
            'ToUserName'    =>  $object['FromUserName'],
            'FromUserName'  =>  $object['ToUserName'],
            'CreateTime'    =>  time(),
            'MsgType'       =>  $type,
            'Content'       =>  $content,
        );
        return $this->Array2Xml($data);
    }

    private function transmitImage($object, $imageArray) {
        /*$itemTpl = "<Image>
    <MediaId><![CDATA[%s]]></MediaId>
</Image>";

        $item_str = sprintf($itemTpl, $imageArray['MediaId']);

        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[image]]></MsgType>
$item_str
</xml>";

        $result = sprintf($textTpl, $object['FromUserName'], $object['ToUserName'], time());
        //return $result;*/
        $result = array(
            'ToUserName'    =>  $object['FromUserName'],
            'FromUserName'    =>  $object['ToUserName'],
            'CreateTime'    =>  time(),
            'MsgType'    =>  'image',
            'Image'    =>  array(
                'MediaId'   =>  $imageArray['MediaId']
            )
        );
        return $this->Array2Xml($result);
    }

    private function transmitVoice($object, $voiceArray) {
        /*$itemTpl = "<Voice>
    <MediaId><![CDATA[%s]]></MediaId>
</Voice>";

        $item_str = sprintf($itemTpl, $voiceArray['MediaId']);

        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[voice]]></MsgType>
$item_str
</xml>";

        $result = sprintf($textTpl, $object['FromUserName'], $object['ToUserName'], time());
        return $result;*/

        $result = array(
            'ToUserName'    =>  $object['FromUserName'],
            'FromUserName'    =>  $object['ToUserName'],
            'CreateTime'    =>  time(),
            'MsgType'    =>  'voice',
            'Voice'    =>  array(
                'MediaId'   =>  $voiceArray['MediaId']
            )
        );
        return $this->Array2Xml($result);
    }

    private function transmitVideo($object, $videoArray) {
        /*$itemTpl = "<Video>
    <MediaId><![CDATA[%s]]></MediaId>
    <ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
    <Title><![CDATA[%s]]></Title>
    <Description><![CDATA[%s]]></Description>
</Video>";

        $item_str = sprintf($itemTpl, $videoArray['MediaId'], $videoArray['ThumbMediaId'], $videoArray['Title'], $videoArray['Description']);

        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[video]]></MsgType>
$item_str
</xml>";

        $result = sprintf($textTpl, $object['FromUserName'], $object['ToUserName'], time());
        return $result;*/

        $result = array(
            'ToUserName'    =>  $object['FromUserName'],
            'FromUserName'    =>  $object['ToUserName'],
            'CreateTime'    =>  time(),
            'MsgType'    =>  'video',
            'Video'    =>  array(
                'MediaId'   =>  $videoArray['MediaId'],
                'ThumbMediaId'   =>  $videoArray['ThumbMediaId'],
                'Title'   =>  $videoArray['Title'],
                'Description'   =>  $videoArray['Description'],
            )
        );
        return $this->Array2Xml($result);
    }

    private function transmitNews($object, $newsArray) {
        if (!is_array($newsArray)) {
            return;
        }
        $itemTpl = "    <item>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
        <PicUrl><![CDATA[%s]]></PicUrl>
        <Url><![CDATA[%s]]></Url>
    </item>
";
        $item_str = "";
        foreach ($newsArray as $item) {
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $newsTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[news]]></MsgType>
<Content><![CDATA[]]></Content>
<ArticleCount>%s</ArticleCount>
<Articles>
$item_str</Articles>
</xml>";

        $result = sprintf($newsTpl, $object['FromUserName'], $object['ToUserName'], time(), count($newsArray));
        return $result;

        $item_str = array();
        foreach ($newsArray as $item) {
            $item_str[] = array(
                'item'  =>  array(
                    'Title' =>  $item['Title'],
                    'Description' =>  $item['Description'],
                    'PicUrl' =>  $item['PicUrl'],
                    'Url' =>  $item['Url']
                )
            );
        }

        $result = array(
            'ToUserName'    =>  $object['FromUserName'],
            'FromUserName'    =>  $object['ToUserName'],
            'CreateTime'    =>  time(),
            'MsgType'    =>  'news',
            'Content'    =>  '',
            'ArticleCount'    =>  count($newsArray),
            'Articles'    =>  $item_str
        );
        return $this->Array2Xml($result);
    }

    private function transmitMusic($object, $musicArray) {
        $itemTpl = "<Music>
    <Title><![CDATA[%s]]></Title>
    <Description><![CDATA[%s]]></Description>
    <MusicUrl><![CDATA[%s]]></MusicUrl>
    <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
</Music>";

        $item_str = sprintf($itemTpl, $musicArray['Title'], $musicArray['Description'], $musicArray['MusicUrl'], $musicArray['HQMusicUrl']);

        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[music]]></MsgType>
$item_str
</xml>";

        $result = sprintf($textTpl, $object['FromUserName'], $object['ToUserName'], time());
        return $result;
    }



    #----------------------------------------
    #-------------

    function member($object) {
        $card = M('member_card_create')->where(array(
            'token' => $this->token,
            'wecha_id' => $object['FromUserName']
        ))->find();
        $cardInfo = M('member_card_set')->where(array(
            'token' => $this->token
        ))->find();
        $data = array();
        if ($card == false) {

            $data['Title'] = '会员卡,省钱，打折,促销，优先知道,有奖励哦';
            $data['Description'] = '尊贵vip，是您消费身份的体现,会员卡,省钱，打折,促销，优先知道,有奖励哦';
            $data['PicUrl'] = rtrim(C('site_url'), '/') . '/Public/static/images/member.jpg';
            $data['Url'] = rtrim(C('site_url'), '/') . U('Wap/Card/get_card', array('token' => $this->token, 'wecha_id' => $object['FromUserName']
                ));
        } else {

            $data['Title'] = $cardInfo['cardname'];
            $data['Description'] = $cardInfo['msg'];
            $data['PicUrl'] = rtrim(C('site_url'), '/') . '/Public/static/images/vip.jpg';
            $data['Url'] = rtrim(C('site_url'), '/') . U('Wap/Card/vip', array('token' => $this->token, 'wecha_id' => $object['FromUserName']));
        }
        return $data;
    }

    #------------------------------------------
    //请求数量
    private function requestdata($field) {
        //Todo: 待完善,看需求
//        $data['year'] = date('Y');
//        $data['month'] = date('m');
//        $data['day'] = date('d');
//        $data['token'] = touid($this->token, '-');
//        $mysql = M('Requestdata');
//        $check = $mysql->field('id')->where($data)->find();
//        if ($check == false) {
//            $data['time'] = time();
//            $data[$field] = 1;
//            $mysql->add($data);
//        } else {
//            $mysql->where($data)->setInc($field);
//        }
    }

    //用户关注 行为 信息
    private function behaviordata($field, $id = '', $type = '') {
        //Todo：待完善,具体看需求
        $data['date'] = date('Y-m-d', time());
        $data['token'] = touid($this->token, '-');
        $data['openid'] = $this->data['FromUserName'];
        $data['keyword'] = $this->data['Content'];
        if (!$data['keyword']) {
            $data['keyword'] = '用户关注';
        }
        $data['model'] = $field;
        if ($id != false) {
            $data['fid'] = $id;
        }
        if ($type != false) {
            $data['type'] = 1;
        }
//        $mysql = M('Behavior');
//        $check = $mysql->field('id')->where($data)->find();
//        $this->updateMemberEndTime($data['openid']);
//        if ($check == false) {
//            $data['num'] = 1;
//            $data['enddate'] = time();
//            $mysql->add($data);
//        } else {
//            $mysql->where($data)->setInc('num');
//        }
    }

    private function updateMemberEndTime($openid) {
        $mysql = M('Wehcat_member_enddate');
        $id = $mysql->field('id')->where(array('openid' => $openid))->find();
        $data['enddate'] = time();
        $data['openid'] = $openid;
        $data['token'] = touid($this->token, '-');
        if ($id == false) {
            $mysql->add($data);
        } else {
            $data['id'] = $id['id'];
            $mysql->save($data);
        }
    }

    //---------------------------
    function _response_text($object,$content){
        $textTpl = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[text]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					<FuncFlag>%d</FuncFlag>
					</xml>";
        $resultStr = sprintf($textTpl, $object['FromUserName'], $object['ToUserName'], time(), $content, $flag);
        return $resultStr;
    }


    private function receiveEvent_subscribe($object) {
        $content = '';
        if ($object['EventKey']) {
            $qid = intval(str_replace("qrscene_", "", $object['EventKey']));
            $userqr = M('UserQr')
                ->alias('mq')
                ->field('mq.user_id as uid,u.*')
                ->join('LEFT JOIN __USERS__ u ON u.user_id=mq.user_id')
                ->cache('user_'.$qid, 7200)
                ->where('mq.id=' . $qid)->find();
            $this->logger('二维码及用户数据：'.print_r($userqr,1));
            $user_model = new Model\UsersModel();
            if (intval($userqr['uid']) <= 0 or intval($userqr['user_id']) <= 0) {
                $content = '欢迎您关注！';
            } else {
                $data = "由 %s [ ID:%s ] 推荐了您！";
                $content .= sprintf($data, $userqr['nickname'], $userqr['user_id']);
            }
            //查询店铺设置的关注用语
            //$data = M('Areply')->field('home,keyword,content')->where(array('token' => touid($this->token, '-')))->find();
            //$content = $data['content'];
            //此处需要判断用户是否存在
            $user_info = $user_model->get_userinfo("openid='{$object['FromUserName']}'");
            if (empty($user_info)) {
                $user = $this->obj2array($this->get_api_userinfo($object['FromUserName']));
                $this->adddUserInfo($user,$userqr['user_id']);
            } else {
                $data = "\n您用户信息已存在，注册信息将保持不变！";
                $content .= $data;
            }
        } else {
            $content = '欢迎您回来！';
            $this->logger('***');
        }
        return $content;
    }

    private function receiveEvent_unsubscribe($object) {
        session(null);
        $this->requestdata('unfollownum'); #取消关注数量
        return '';
    }

    private function receiveEvent_scan($object){
        return "您已关注本微信，扫描关注 " . $object['EventKey'];
    }

    private function receiveEvent_click($object) {
        switch ($object['EventKey']) {
            case '砸金蛋':
                $content = C('site_url') . U('Wap/Zadan/index', array(
                        'token' => $this->token,
                        'wecha_id' => $this->data['FromUserName'],
                        'id' => $urlInfos[1]
                    ));
                break;
            case '抽奖':
            case '大转盘':
                //$content = C('site_url') . U('Wap/Lottery/index', array(
//                          'token' => $this->token,
//                          'wecha_id' => $this->data['FromUserName'],
//                          'id' => $urlInfos[1]
//                      ));
                $lott = M('Lottery')->where(array('token' => $this->token))->find();
                $content[] = array("Title" => "活动开始，请进入页面开始抽奖", "Description" => "亲，请点击进入幸运大转盘活动页面，祝您好运气喔！~", "PicUrl" => C('site_url') . '/Public/Wap/css/guajiang/images/activity-lottery-start.jpg', "Url" => C('site_url') . U('Wap/Lottery/index', array('token' => $this->token, 'wecha_id' => $object['FromUserName'], 'id' => $lott['id'])));
                break;
            case '商家订单':
                $content = C('site_url') . '/index.php?g=Wap&m=Host&a=index&token=' . $this->token . '&wecha_id=' . $this->data['FromUserName'] . '&hid=' . $urlInfos[1];
                break;
            case '优惠券':
                //$content = C('site_url') . U('Wap/Coupon/index', array(
//                          'token' => $this->token,
//                          'wecha_id' => $this->data['FromUserName'],
//                          'id' => $urlInfos[1]
//                      ));
                $Coupon = M('Lottery_record')->where(array('token' => $this->token, 'type' => 3, 'status' => 1))->find();
                $content[] = array("Title" => "优惠券免费发放中，抓紧领取", "Description" => "每次购物只限使用一次，多张优惠券不叠加使用，使用前联系客服确认！~", "PicUrl" => C('site_url') . '/Public/Wap/css/guajiang/images/activity-coupon-start.jpg', "Url" => C('site_url') . U('Wap/Coupon/index', array('token' => $this->token, 'wecha_id' => $object['FromUserName'], 'id' => $Coupon['id'])));
                break;
            case '首页':
                $home = M('Home')->where(array('token' => $this->token))->find();
                if ($home == false) {
                    $content = '商家未做首页配置，请稍后再试';
                } else {

                    if ($home['apiurl'] == false) {
                        $url = C('site_url') . U('Wap/Index/index?token=' . $this->token . '&wecha_id=' . $object['FromUserName']);
                    } else {
                        $url = $home['apiurl'];
                    }
                    $content[] = array("Title" => $home['title'], "Description" => $home['info'], "PicUrl" => $home['picurl'], "Url" => $url);
                }
                return $this->transmitNews($object, $content);
                break;
            case '会员':
                $content[] = $this->member($object);
                break;
            default:
                //$content = "点击菜单：".$object['EventKey'];
                ///=======
                $img = M('Img')->field('id,text,pic,url,title')->limit(8)->order('id desc')->where(array(
                    'token' => $this->token,
                    'classid' => $object['EventKey']
                ))->select();

                if ($img == false) {
                    $content = '无此图文信息,请提醒商家，重新设定关键词';
                } else {
                    foreach ($img as $keya => $infot) {
                        if ($infot['url'] != false) {
                            $url = $infot['url'];
                        } else {
                            $url = rtrim(C('site_url'), '/') . U('Wap/Index/content', array(
                                    'token' => $this->token,
                                    'id' => $infot['id']
                                ));
                        }

                        $content[] = array("Title" => $infot['title'], "Description" => $infot['text'], "PicUrl" => $infot['pic'], "Url" => $url);
                    }
                }
                break;
        }
    }

    private function receiveEvent_location($object) {
        return "上传位置：纬度 " . $object['Latitude'] . ";经度 " . $object['Longitude'];
    }

    private function receiveEvent_view($object) {
        $str = $object['FromUserName'] . '_' . $object['EventKey'] . '-' . $object['CreateTime'] . '-' . $object['Latitude'];
        return true;
    }
}
 