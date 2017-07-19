<?php
/**
 * 生成二维码
 * @param $data
 * @param $file
 * @param string $level
 * @param int $size
 */
function create_qr($data, $file, $level = 'L', $size = 10) {
    vendor("qrcode.phpqrcode");
    \Vendor\qrcode\QRcode::png($data, $file, $level, $size);
}
/**
 * 替换文本内的图片
 * @param $text
 * @return mixed
 */
function ReplaceImgUrl($text){
    $patten = '/<img\s+[^>]*?src=(\'|\")(.*?)\\1[^>]+>/i';
    preg_match_all($patten, $text, $s);
    if (count($s) == 3) {
        foreach ($s[2] as $v) {
            $ss = str_replace(['https://m.hkhp.net','http://m.hkhp.net'],'',$v);
            $text = str_replace($v, img_url($ss), $text);
        }
    }
    return $text;
}

/**
 * 去掉图片前的/
 * @param $url
 * @return string
 */
function img_url($url) {
    $img_http = C('UPLOAD_SERVER');
    $img_http = $img_http != '' ? ('//' . $img_http . '/') : '/';
    if (empty($url) || substr($url,0,2) == '//' || substr($url,0,4) == 'http') {

    } elseif (substr($url,0,1) == '/') {
        $url = $img_http . substr($url, 1, strlen($url));
    } else {
        $url = $img_http . $url;
    }
    return $url;
}

/**
 * 生成唯一标识符
 * @param $prefix
 * @param int $len
 * @param int $type
 * @return string
 */
function create_sn($prefix, $len = 8, $type = 1){
    return $prefix . date('Ymd') . get_rand_str($len, $type);
}


/**
 * 字符串截取，支持中文和其他编码
 * static 
 * access public
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $charset 编码格式
 * @param string $suffix 截断显示字符
 * return string
 */
function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true) {
    if(function_exists("mb_substr"))
        $slice = mb_substr($str, $start, $length, $charset);
    elseif(function_exists('iconv_substr')) {
        $slice = iconv_substr($str,$start,$length,$charset);
        if(false === $slice) {
            $slice = '';
        }
    }else{
        $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice.'...' : $slice;
}

/**
 * 生成随机字符口串
 * @param int $length
 * @param int $str_type
 * @return string
 */
function get_rand_str($length = 8,$str_type = 0){
    $chars[0] = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
    $chars[1] = '0123456789';
    $chars[2] = '0123456789abcdefghijklmnopqrstuvwxyz';
    $password = '';
    for ( $i = 0; $i < $length; $i++ ) {
        $password .= $chars[$str_type][ mt_rand(0, strlen($chars[$str_type]) - 1) ];
    }
    return $password;
}


/**
 * 返回固定格式的json
 * @param int $error
 * @param string $msg
 * @param array $data
 * @param array $extends
 * @return array
 */
function result_ajax($error = 0,$msg = '',$data = [], $extends = []){
    $result = ['error'=>$error,'msg'=> $msg, 'data'=> $data];
    if ($extends) {
        $result = array_merge($result, $extends);
    }
    return $result;
}

/**
 * xss过滤
 * @param $data
 * @return string
 */
function xss_filter($data){
    $data = str_replace(array('&','<','>'),array('&amp;','&lt;','&gt;'),$data);
    $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u','$1;',$data);
    $data = preg_replace('/(&#x*[0-9A-F]+);*/iu','$1;',$data);
    $data = html_entity_decode($data,ENT_COMPAT,'UTF-8');
    $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu','$1>',$data);
    // Remove javascript: and vbscript: protocols
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu','$1=$2nojavascript...',$data);
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu','$1=$2novbscript...',$data);
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u','$1=$2nomozbinding...',$data);
     // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i','$1>',$data);
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i','$1>',$data);
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu','$1>',$data);
    $data = preg_replace('#</*\w+:\w[^>]*+>#i','',$data);
    do {
         $old_data = $data;
         $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i','',$data);
    } while ( $old_data !== $data );
    return $data;
}

/**
 * 验证Sign
 * @param array $obj
 * @param string $key     加密密钥
 * @param int $is_weixin
 * @param string $sign_text
 * @return bool
 */
function test_sign($obj, $key = '', $is_weixin = 0, $sign_text = 'sign') {
    if (empty($obj) or $obj[$sign_text] == '') return false;
    if (!$key) $key = C('CRYPT_KEY');
    $sign = trim($obj[$sign_text]);
    if (isset($obj[$sign_text])) unset($obj[$sign_text]);
    if ($sign == get_Sign($obj, $key)) {
        if ($is_weixin == 1) {
            if ($obj['appid'] == $this->wxuser['appid'] and $obj['mch_id'] == $this->wxuser['appmchid']) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    } else {
        return false;
    }
}

/**
 * Sign加密
 * @param $Obj
 * @param int $type     需要加密的数组
 * @param string $key   加密密钥
 * @return string
 */
function get_sign($Obj, $key = '', $type = 0){
    if (!$key) $key = C('CRYPT_KEY');
    foreach ($Obj as $k => $v){
        $Parameters[$k] = $v;
    }
    ksort($Parameters);
    $String = formatBizQueryParaMap($Parameters, false);
    $String .= "&key=" . $key;
    if ($type == 0) {
        $String = md5($String);
    } else {
        $String = sha1($String);
    }
    $result = strtoupper($String);
    return $result;
}


/**
 * 作用：格式化参数，签名过程需要使用
 * @param $paraMap
 * @param $urlencode
 * @return string
 */
function formatBizQueryParaMap($paraMap, $urlencode){
    $buff = "";
    ksort($paraMap);
    foreach ($paraMap as $k => $v){
        if($urlencode){
            $v = urlencode($v);
        }
        $buff .= $k . "=" . $v . "&";
    }
    $reqPar = '';
    if (strlen($buff) > 0){
        $reqPar = substr($buff, 0, strlen($buff)-1);
    }
    return $reqPar;
}

/**
 * 广告位显示
 * @param $ad_name
 * @return string
 */
function get_ad($ad_name){
    $result = '';
    if (trim($ad_name) != '') {   //2245   16299
        $pos_info = M('AdPosition')->where(['position_name'=>$ad_name])->find();
        if ($pos_info) {
            $mini = date('i') > 30 ? 30 : 0;
            $time = strtotime(date("Y-m-d H:$mini:00"));
            $where = [
                'position_id'   =>  $pos_info['position_id'],
                'enabled'       =>  1,
                'start_time'    =>  ['lt', $time],
                'end_time'      =>  [['gt',$time],['eq',0],'or'],
            ];
            $ads = M('Ad')->where($where)->order('sort')->select();
            if ($ads) {
                $_view  = Think\Think::instance('Think\View');
                $_view->assign('ads', $ads);
                $result = $_view->fetch('',$pos_info['position_style']);
            }
        }
    }
    return $result;
}

/**
 * 线性列表转树形结构
 * [需要注意的关键问题]
 * -- 父ID中出现不存在的ID,会自行忽略(实现中仅记录日志)
 * -- 自循环树型结构需要自行在业务逻辑中进行约束
 * -- 一旦出现自循环结构,由于树型结构的上级单向性,会出现如下两种情况
 * -- [1-自循环结构中有root]       自循环会在root处自行断开形成正常树结构
 * -- [2-自循环结构中没有root]     自循环结构会完全脱离原有树(原list数据的自循环部分会出现无限自循环引用)
 * @param $list
 * @param string $pk
 * @param string $pid
 * @param string $child
 * @param int $root
 * @param array $except
 * @param bool $all
 * @return array
 */
function list_to_tree($list, $pk='id', $pid='pid', $child='_child', $root=0, $except=array(), $all=false) {
    //创建Tree
    $tree = array();
    if(is_array($list)){
        //创建基于主键的数组引用
        $refer = array();
        foreach($list as $key => $data){
            $refer[$data[$pk]] = &$list[$key];
        }
        foreach($list as $key => $data){

            if(in_array($list[$key][$pk], $except)) continue;

            $parentId = $data[$pid];
            if($root == $parentId){
                //判断是否存在parent
                $tree[] = &$list[$key];
            }elseif(isset($refer[$parentId])){
                $refer[$parentId][$child][] = &$list[$key];
            }else{
                //pid中有不在树中的节点自动过滤,这里仅记录日志
                if($parentId) trace('list_to_tree中的父ID在ID中不存在['.serialize($data).']');
            }
        }
    }
    return $all ? $list : $tree;
}

/**
 * 将list_to_tree的树还原成列表
 * @param $tree
 * @param string $pk
 * @param string $child
 * @param bool $order
 * @param int $level
 * @param int $max
 * @param bool $index
 * @return array
 */
function tree_to_list($tree, $pk='id', $child='_child', $order=true, $level=0, $max=0, $index=true){

    $level++;
    $t = array();
    if(!is_array($tree) || ($max && $level > $max)) $tree = array();

    foreach($tree as $currnet){
        //当前层级(暂未用到)
        $currnet['_level'] = $level;
        //判断是否有下级
        if(isset($currnet[$child])){
            $next = $currnet[$child];
            unset($currnet[$child]);
        }else{
            $next = false;
        }

        //不在上面的isset判断中进行递归就能用两种方式排序
        if(true === $order)     $t[$currnet[$pk].($index?'':'_'.$level)] = $currnet;
        //array_merge对数字索引会重新从0索引,所以必须用+号合并,由于默认主键,不会产生覆盖的情况
        if($next)               $t = $t + tree_to_list($next, $pk, $child, $order, $level, $max, $index);
        if(true !== $order)     $t[$currnet[$pk].($index?'':'_'.$level)] = $currnet;
    }

    //todo:按字段排序(最好能只排序一次)
    return $t;
}

/**
 * 传入的是个用array_column生成的二维数组,id为键,其他数据为一维数组值
 * 返回第2参数的面包屑id路径
 * @param $list
 * @param $currnet
 * @param string $pid
 * @return array
 */
function list_to_bread(&$list, $currnet, $pid='pid'){
    if(isset($list[$currnet])){

        //菜单需要
        $list[$currnet]['_current'] = 'current';

        if(0 != $list[$currnet][$pid]){
            return array_merge(list_to_bread($list, $list[$currnet][$pid], $pid), array($list[$currnet]));
        }else{
            return array($list[$currnet]);
        }
    }else{
        return array();
    }
}

/**
 * 对查询结果集进行排序
 * @param $list
 * @param $field
 * @param string $sortby
 * @return array|bool
 */
function list_sort_by($list,$field, $sortby='asc') {
    if(is_array($list)){
        $refer = $resultSet = array();
        foreach ($list as $i => $data)
            $refer[$i] = &$data[$field];
        switch ($sortby) {
            case 'asc': // 正向排序
                asort($refer);
                break;
            case 'desc':// 逆向排序
                arsort($refer);
                break;
            case 'nat': // 自然排序
                natcasesort($refer);
                break;
        }
        foreach ( $refer as $key=> $val)
            $resultSet[] = &$list[$key];
        return $resultSet;
    }
    return false;
}



/**
 * 商品价格格式化（小数点后面为0就去掉小数点）
 * @param $price float 价格
 * @param $is_rmb int 是否人民币
 * @param bool $change_price  四舍五入
 * @return float|string
 */
function price_format($price, $is_rmb = 1, $change_price = false){
    if ($is_rmb) {
        if($change_price){
            $price = floor($price * 100) / 100;
            $price = sprintf('¥%.2f元',$price);
        }else{
            $price = floor($price * 100) / 100;
        }
    } else {
        //$price .= 'V币';
    }
    return $price;
}

/**
 * 通用分页列表数据集获取方法(可能重置where,order,field三个参数)
 *
 * 可以通过url参数传递where条件,例如:  index.html?name=asdfasdfasdfddds
 * 可以通过url空值排序字段和方式,例如: index.html?_field=id&_order=asc
 * 可以通过url参数r指定每页数据条数,例如: index.html?_r=5
 *
 * @param sting|Model  $model   模型名或模型实例
 * @param array        $where   where查询条件(优先级: $where>$_REQUEST>模型设定)//todo:[暂不实现url参数查询]
 * @param array|string $order   排序条件,传入null时使用sql默认排序或模型属性(优先级最高);
 *                               请求参数中如果指定了_order和_field则据此排序(优先级第二);
 *                               否则使用$order参数(如果没有$order参数,且模型也没有设定过order,则取主键降序);
 * @param string       $field   模型以字符串方式提供时,可以用此参数指定字段(其他情况下,单表可在模型中设置)
 *
 * @return array|false
 * 返回数据集
 */
function pages($model, $where=array(), $order='', $field=''){

    $request    =   (array)I('get.');//todo:考虑分页暂定get(原request)
    if(is_string($model)){
        $model  =   M($model);
    }

    //反射模型(可在模型层外设置模型options参数)
    $opt        =   new \ReflectionProperty($model, 'options');
    $opt->setAccessible(true);
    $options    =   (array)$opt->getValue($model);

    //***设置排序规则
    $pk         =   $model->getPk();
    if($order===null){
        //强制默认排序
    }elseif(isset($request['_o']) && isset($request['_f']) && in_array(strtolower($request['_o']),array('desc','asc')) && in_array($request['_f'], $model->getDbFields())){
        //url指定排序
        $options['order'] = '`'.$request['_f'].'` '.$request['_o'];
    }elseif($order){
        //参数排序
        $options['order'] = $order;
    }elseif($order==='' && empty($options['order']) && !empty($pk) && !is_array($pk)){
        //不提供参数按模型默认排序,没有模型排序,按主键降序(必须为唯一主键)
        $options['order'] = $pk.' desc';
    }

    //***设置条件规则(status>0被删除)todo:url传条件暂不实现
    if(!empty($where)){
        //指定条件会覆盖模型设定
        $options['where'] = $where;
    }

    //***设置查询字段
    if(!empty($field)){
        $options['field'] = $field;
    }

    //开始分页
    $total        =   $model->where($options['where'])->count();
    if(isset($request['_r']) ){
        //优先参数分页
        $listRows = (int)$request['_r'];
    }else{
        $listRows = C('LIST_ROWS') > 0 ? C('LIST_ROWS') : $total;
    }
    $page = new \Think\Page($total, $listRows, $request);
    if($total > $listRows){
        $page->rollPage     = 3;//todo:参数化
        $page->lastSuffix   = false;
        $page->setConfig('first',   '<i title="首页" class="icon-double-angle-left"></i>');
        $page->setConfig('last',    '%TOTAL_PAGE%');//<i title="末页" class="icon-double-angle-right"></i>
        $page->setConfig('prev',    '<i title="上一页" class="icon-angle-left"></i>');
        $page->setConfig('next',    '<i title="下一页" class="icon-angle-right"></i>');
        $page->setConfig('header',  '<span><i class="icon-hand-right"></i><input maxlength="3" data-total="%TOTAL_PAGE%" value="'.I(C('VAR_PAGE'),1).'"></span>');
        $page->setConfig('theme',   '%FIRST%%UP_PAGE%%LINK_PAGE%%DOWN_PAGE%%END%%HEADER%');
    }

    $options['limit'] = $page->firstRow.','.$page->listRows;
    $model->setProperty('options',$options);

    return array($model->select(), $page->show(), $page);
}

if(!function_exists('array_column')){
    /**
     * php5.5+array_column自定义实现
     * @param $arr
     * @param $col
     * @param null $index
     * @return array|bool|null
     */
    function array_column($arr, $col, $index=null){
        if(!is_array($arr))                                             return null;
        if(!(is_int($col)   || is_string($col)   || is_null($col)))     return false;
        if(!(is_int($index) || is_string($index) || is_null($index)))   return false;
        $result = array();
        foreach($arr as $key => $row){
            if(!is_array($row))                                         continue;
            if(!(isset($row[$col]) || is_null($col)))                  continue;
            $key = isset($row[$index]) ? $row[$index]  : $key;
            $row = is_null($col)        ? $row          : $row[$col];
            $result[$key] = $row;
        }
        return $result;
    }
    trace('使用了自定义实现[array_column]函数');
}

/**
 * AES加密
 * @param $input string 需要加密的字符串
 * @param $key  string  解密密钥
 * @return string
 */
function AesEnCrypt($input, $key = ''){
    if ($key == '') $key = md5(C('CRYPT_KEY'));
    $encrypted = openssl_encrypt($input, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, base64_decode(C('CRYPT_IV')));
    $encrypted = base64_encode($encrypted);
    return str_replace(['+'],['~'], $encrypted);
}

/**
 * AES解密
 * @param $input string 需要解密的字符串
 * @param $key string  解密密钥
 * @return string
 */
function AesDeCrypt($input, $key = ''){
    if ($key == '') $key = md5(C('CRYPT_KEY'));
    $decrypted = str_replace(['~'],['+'], trim($input,"\0"));
    $decrypted = base64_decode($decrypted);
    return openssl_decrypt($decrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, base64_decode(C('CRYPT_IV')));
}


/**
 * RSA加密
 * @param $input  string 需要解密的字符串
 * @param $key_path string  解密密钥
 * @return string
 */
function RsaEnCrypt($input, $key_path = ''){
    if (!$key_path) $key_path = C("PUBLIC_KEY_PATH");
    if (!file_exists($key_path)) {
        return false;
    } else {
        openssl_public_encrypt($input, $encrypted, file_get_contents($key_path));
        return str_replace(['+'],['~'],base64_encode($encrypted));
    }
}

/**
 * RSA解密
 * @param $input  string 需要解密的字符串
 * @param $key_path string  解密密钥
 * @return string|boolean
 */
function RsaDeCrypt($input, $key_path = ''){
    if (!$key_path) $key_path = C("PRIVATE_KEY_PATH");
    if (!file_exists($key_path)) {
        return false;
    } else {
        $decrypted = base64_decode( str_replace(['~'],['+'],$input ));
        openssl_private_decrypt($decrypted, $encrypted, file_get_contents($key_path) );
        return $encrypted;
    }
}

/**
 * 加密时的字节填充，保持和java 一致
 * @param $text
 * @param $blocksize
 * @return string
 */
function pkcs5_pad ($text, $blocksize) {
    $pad = $blocksize - (strlen($text) % $blocksize);
    return $text . str_repeat(chr($pad), $pad);
}

/**
 * //加密
 * @param $input
 * @param $key
 * @return string
 */
function EnCrypt($input, $key = '')  {
    if ($key == '') $key = md5(C('CRYPT_KEY'));
    $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
    $input = pkcs5_pad($input, $size);
    $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
    $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
    mcrypt_generic_init($td, $key, $iv);
    $data = mcrypt_generic($td, $input);
    mcrypt_generic_deinit($td);
    mcrypt_module_close($td);
    $data = base64_encode($data);
    $data = str_replace('+','~',$data);
    return $data;
}

/**
 * //解密
 * @param $str
 * @param $key
 * @return string
 */
function DeCrypt($str,$key = '')  {
    if ($key == '') $key = md5(C('CRYPT_KEY'));
    $str = str_replace('~','+',$str);
    $decrypted = mcrypt_decrypt(
        MCRYPT_RIJNDAEL_128,
        $key,
        base64_decode($str),
        MCRYPT_MODE_ECB
    );

    $dec_s = strlen($decrypted);
    $padding = ord($decrypted[$dec_s - 1]);
    $decrypted = substr($decrypted, 0, -$padding);
    return $decrypted;
}

/**
 * 制作加密字符串
 * @param $data
 * @return string
 */
function set_encrypt_str($data) {
    if (!is_array($data)) return false;
    $keys = array_keys($data);//echo $keys[0] . '-' . $data[$keys[0]];
    //return EnCrypt($keys[0] . '-' . $data[$keys[0]]);
    return AesEnCrypt($keys[0] . '-' . $data[$keys[0]]);
}

/**
 * 获取get and cookie字符串
 * @param $str string
 * @param string $method
 * @param int $is_cookie
 * @return array
 */
function get_encrypt_str($str, $method = 'get', $is_cookie = 0){
    $rec = I($method . '.' . $str, '');
    $result = array();
    if ($rec) {
        $de_rec = AesDeCrypt($rec);
        if ($de_rec) {
            $recom = explode('-',$de_rec);
            $result[$recom[0]] = $recom[1];
            if ($is_cookie) {cookie($str, $rec, array('expire'=>60 * 60 * 24,'prefix'=>C('COOKIE_PREFIX')));/*exit('fjkdlsa;fjdsa');*/}
        } elseif ($de_rec = AesDeCrypt(cookie($str))) {
            $recom = explode('-',$de_rec);
            $result[$recom[0]] = $recom[1];
        } else {
            //$result = array();
        }
    } elseif ($de_rec = AesDeCrypt(cookie($str))) {
        $recom = explode('-',$de_rec);
        $result[$recom[0]] = $recom[1];
    } else {
        //$result = array();
    }
    return $result;
}
function user_level_store($user_id, $store_id=null, $level=3){
    //获取上级层数不能少于1级(默认三级)
    $level = (int)$level; if(1 > $level) return array();

    $user_id = (int)$user_id;
    $store_id=intval($store_id);
    if(null === $store_id){
        //不提供store_id时,查找所有可能的分岔口,然后按id-limit1
        $where = '';
    }else{
        $store_id = (int)$store_id;
        $sql="SELECT user_id from wp_store where store_id=$store_id";
        $store_info=M()->query($sql);

    }

    $field = $join = array();
    for($i = 1; $i <= $level; $i++){
        $field[]    = 'u'. ($level-$i+1) .'.user_id level'. ($level-$i+1) .',';
        $join[]     = "LEFT JOIN wp_user_relevance u{$i} ON u". ($i - 1) .".parent_id = u{$i}.user_id ";
    }
    $field  = join(' ', $field);
    $join   = join(' ', $join);
    $sql = <<<EOF
        SELECT
        {$field}
        u0.store_id
        FROM wp_user_relevance u0
            {$join}
                WHERE u0.user_id = {$user_id} {$where}
                ORDER BY u0.id
                LIMIT 1
EOF;

    $list = M()->query($sql);

    $list[0]['main_user_id']=$store_info['user_id'];

    if($list){

        //数据分离
        $list           = reset($list);
        $store_id       = array_pop($list);
        $main_user_id   = array_pop($list);
        $list           = array_values(array_filter($list));
        $count          = count($list);

        //循环组装店铺信息
        foreach($list as &$each){
            $each = "{$store_id}_{$each}";
        }

        //差一个用主管理员凑($store_id是无意义的),主管理员必须存在
        if($level === ($count + 1) && $main_user_id){
            $count++;
            array_unshift($list, "-{$store_id}_{$main_user_id}");
        }

        //不够数量,进行递归获取
        if($level === $count){
            return $list;
        }else{
            if($main_user_id){
                //外部店铺关联关系(排除平台转弯和店铺主管理员在wp_user_relevance中不存在的情况,就中断继续递归)
                //暂转弯节点一定在主管理员上,不能在非主管理员上转弯
                $extend     = user_level($main_user_id, null, $level - $count - 1);
                $extend[]   = "-{$store_id}_{$main_user_id}";
                $list = array_merge($extend, $list);
            }
            return $list;
        }

    }else{
        //传入的ID在当前wp_user_relevance.store_id下,没有上三级关系
        return array();
    }

}
/**
 * 获取指定user_id跨N个店铺的上级关系
 * @param $user_id
 * @param null $store_id
 * @param int $level
 * @return array|mixed
 */
function user_level($user_id, $store_id=null, $level=3){
    //获取上级层数不能少于1级(默认三级)
    $level = intval($level); if(1 > $level) return array();
    $user_id=intval($user_id);
    if(null === $store_id){
        //不提供store_id时,查找所有可能的分岔口,然后按id-limit1
        $where = '';
    }else{
        $store_id = (int)$store_id;
        $where = "AND u0.store_id = {$store_id}";

    }

    $field = $join = array();
    for($i = 1; $i <= $level; $i++){
        $field[]    = 'u'. ($level-$i+1) .'.user_id level'. ($level-$i+1) .',';
        $join[]     = "LEFT JOIN wp_user_relevance u{$i} ON u". ($i - 1) .".parent_id = u{$i}.user_id AND u0.store_id = u{$i}.store_id";
    }
    $field  = join(' ', $field);
    $join   = join(' ', $join);

    $sql = <<<EOF
        SELECT
        {$field}
        s.user_id main_user_id,
        u0.store_id
        FROM wp_user_relevance u0
            LEFT JOIN wp_store s on u0.store_id = s.store_id
            {$join}
                WHERE u0.user_id = {$user_id} {$where}
                ORDER BY u0.id
                LIMIT 1
EOF;
    $list = M()->query($sql);
    if($list){

        //数据分离
        $list           = reset($list);
        $store_id       = array_pop($list);
        $main_user_id   = array_pop($list);
        $list           = array_values(array_filter($list));
        $count          = count($list);

        //循环组装店铺信息
        foreach($list as &$each){
            $each = "{$store_id}_{$each}";
        }

        //差一个用主管理员凑($store_id是无意义的),主管理员必须存在
        if($level === ($count + 1) && $main_user_id){
            $count++;
            array_unshift($list, "-{$store_id}_{$main_user_id}");
        }

        //不够数量,进行递归获取
        if($level === $count){
            return $list;
        }else{
            if($main_user_id){
                //外部店铺关联关系(排除平台转弯和店铺主管理员在wp_user_relevance中不存在的情况,就中断继续递归)
                //暂转弯节点一定在主管理员上,不能在非主管理员上转弯
                $extend     = user_level($main_user_id, null, $level - $count - 1);
                $extend[]   = "-{$store_id}_{$main_user_id}";
                $list = array_merge($extend, $list);
            }
            return $list;
        }

    }else{
        //传入的ID在当前wp_user_relevance.store_id下,没有上三级关系
        return array();
    }

}

function debug($data, $is_tj = 0 ) {
    if (APP_DEBUG || $is_tj) {
        if (is_array($data)) {
            $str = print_r($data,1);
        } else {
            $str = $data;
        }
        \Think\Log::record($str);
    }
}

/**
 * 计算某个经纬度的周围某段距离的正方形的四个点
 * @param radius 地球半径 平均6371km
 * @param lng float 经度
 * @param lat float 纬度
 * @param distance float 该点所在圆的半径，该圆与此正方形内切，默认值为1千米
 * @return array 正方形的四个点的经纬度坐标
 */
function return_square_point($lng, $lat, $distance = 1, $radius = 6371)
{
         $dlng = 2 * asin(sin($distance / (2 * $radius)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);

         $dlat = $distance / $radius;
        $dlat = rad2deg($dlat);

       return array(
                     'left-top' => array(
                            'lat' => $lat + $dlat,
                            'lng' => $lng - $dlng
                            ),
                     'right-top' => array(
                            'lat' => $lat + $dlat,
                            'lng' => $lng + $dlng
                            ),
                     'left-bottom' => array(
                            'lat' => $lat - $dlat,
                            'lng' => $lng - $dlng
                            ),
                     'right-bottom' => array(
                            'lat' => $lat - $dlat,
                            'lng' => $lng + $dlng
                            )
       );
}

/**
 * 计算两个个经纬度的之间的距离
 * @param lng1 float 经度
 * @param lat1 float 纬度
 * @param lng float 对比经度
 * @param lat float 对比纬度
 * @return float 返回距离米
 */
function get_distance($lng1, $lat1, $lng2, $lat2){
    $earthRadius = 6378138; //近似地球半径米
    // 转换为弧度
    $lat1 = ($lat1 * pi()) / 180;
    $lng1 = ($lng1 * pi()) / 180;
    $lat2 = ($lat2 * pi()) / 180;
    $lng2 = ($lng2 * pi()) / 180;
    // 使用半正矢公式  用尺规来计算
    $calcLongitude = $lng2 - $lng1;
    $calcLatitude = $lat2 - $lat1;
    $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
    $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
    $calculatedDistance = $earthRadius * $stepTwo;
    return round($calculatedDistance);
}


/**
 * 取汉字的第一个字的首字母
 * @param type $str
 * @return string|null
 */
function get_first_charter($str){
    if(empty($str)) return '';
    $fchar=ord($str{0});
    if($fchar>=ord('A')&&$fchar<=ord('z')) return strtoupper($str{0});
    $s1=iconv('UTF-8','gb2312',$str);
    $s2=iconv('gb2312','UTF-8',$s1);
    $s=$s2==$str?$s1:$str;
    $asc=ord($s{0})*256+ord($s{1})-65536;
    if($asc>=-20319&&$asc<=-20284) return 'A';
    if($asc>=-20283&&$asc<=-19776) return 'B';
    if($asc>=-19775&&$asc<=-19219) return 'C';
    if($asc>=-19218&&$asc<=-18711) return 'D';
    if($asc>=-18710&&$asc<=-18527) return 'E';
    if($asc>=-18526&&$asc<=-18240) return 'F';
    if($asc>=-18239&&$asc<=-17923) return 'G';
    if($asc>=-17922&&$asc<=-17418) return 'H';
    if($asc>=-17417&&$asc<=-16475) return 'J';
    if($asc>=-16474&&$asc<=-16213) return 'K';
    if($asc>=-16212&&$asc<=-15641) return 'L';
    if($asc>=-15640&&$asc<=-15166) return 'M';
    if($asc>=-15165&&$asc<=-14923) return 'N';
    if($asc>=-14922&&$asc<=-14915) return 'O';
    if($asc>=-14914&&$asc<=-14631) return 'P';
    if($asc>=-14630&&$asc<=-14150) return 'Q';
    if($asc>=-14149&&$asc<=-14091) return 'R';
    if($asc>=-14090&&$asc<=-13319) return 'S';
    if($asc>=-13318&&$asc<=-12839) return 'T';
    if($asc>=-12838&&$asc<=-12557) return 'W';
    if($asc>=-12556&&$asc<=-11848) return 'X';
    if($asc>=-11847&&$asc<=-11056) return 'Y';
    if($asc>=-11055&&$asc<=-10247) return 'Z';
    if($asc == -9767) return 'D';
    if($asc == -7703) return 'Q';
    if($asc == -6928 or $asc = -7182) return 'L';
    return null;
}

/**
 * 过滤字符串中的html标签,js代码,css样式标签
 * @param type $str
 * @return string|null
 */
function filljscode($str){
    $str = preg_replace( "@<script(.*?)</script>@is", "", $str );
    $str = preg_replace( "@<iframe(.*?)</iframe>@is", "", $str );
    $str = preg_replace( "@<style(.*?)</style>@is", "", $str );
    $str = preg_replace( "@<(.*?)>@is", "", $str );
}

/**
 * 向营销工具请求营销的列表
 * @param function string  请求方法名
 * @param data array 请求接口的参数（必要参数，appid，appsecret）
 * @return array
 */
function curlpost($function,$data){
    import('Vendor.Curl');
    $Curl = new \Vendor\Curl();
    $wap_server = get_server("SALES_SERVER");
    $data['timestamp'] = NOW_TIME;
    $data['sign']   = get_sign($data);
    \Think\Log::record("curlpost\n" . 'URL:' . $wap_server . '/Api/' . $function . '/module/'  . MODULE_NAME);
    \Think\Log::record('Data:' . http_build_query($data));//
    $result = json_decode($Curl->post($wap_server . '/Api/' . $function . '/module/'  . MODULE_NAME ,http_build_query($data)) ,true);
  /* $result = $Curl->post($wap_server . '/Api/' . $function . '/module/'  . MODULE_NAME ,http_build_query($data));
    print_R(json_decode($result));exit;*/
    return $result;
}

/**
 * 获取返回地址
 * @param $default_url
 * @param string $str
 * @return string
 */
function get_back_url($default_url, $str = 'back_act'){
    if (isset($_REQUEST[$str]) and trim($_REQUEST[$str]) != '') {
        $back_url = AesDeCrypt(trim($_REQUEST[$str]));
    } else {
        if (isset($_SERVER['HTTP_REFERER']) and $_SERVER['HTTP_REFERER'] != '') {
            $back_url = $_SERVER['HTTP_REFERER'];
        } else {
            $back_url = $default_url;
        }
    }
    return $back_url;
}

/**
 * 获取当前url
 * @param int $is_http 是否需要协议头
 * @return string
 */
function get_cur_url($is_http = 0){
    return get_cur_domain($is_http) . $_SERVER["REQUEST_URI"];
}

/**
 * 获取当前域名
 * @param int $is_http  是否需要协议头
 * @return string
 */
function get_cur_domain($is_http = 0){
    $scheme = $_SERVER["REQUEST_SCHEME"];
    $scheme = $scheme != '' ? $scheme . '://' : $scheme;
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") {
        $agreem = 'http://';
    } else {
        $agreem = 'https://';
    }
    $agreement = ($scheme == '' ? $agreem : $scheme)  ;
    if ($is_http) {
        return $agreement . $_SERVER['HTTP_HOST'];
    } else {
        return $_SERVER['HTTP_HOST'];
    }
}

/**
 * 获取配置中的各类Server服务器头
 * @param $str string 配置中的Server
 * @param string $path  路径
 * @param string $param 参数
 * @param int $is_http  是否需要协议头
 * @return mixed|null|string
 */
function get_server($str, $path = '', $param = '', $is_http = 1) {
    $http = ['http://','https://'];
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") {
        $agreement = $http[0];
    }else{
        $agreement = $http[1];
    }
    $server = C($str);
    if ($server) {
        if ($is_http) {
            if(!strpos($server,$http[0]) && !strpos($server,$http[1])) {
                $result = $agreement . $server;
            } else {
                $result = $server;
            }
        } else {
            if(!strpos($server,$http[0]) && !strpos($server,$http[1])) {
                $result = $server;
            } else {
                $result = str_replace($http,'',$server);
            }
        }
    } else {
        $result = get_cur_domain($is_http);
    }
    $result .= $path;
    if(is_array($param)){
        $result .= '?' . http_build_query($param);
    } elseif ($param && is_string($param)) {
        $result .= '?' . $param;
    }
    return $result;
}

/**
 * 是否为一个合法的email
 * @param $email string
 * @return boolean
 */
function is_email($email){
    if (preg_match("/^([a-z0-9+_-]+)(.[a-z0-9+_-]+)*@([a-z0-9-]+.)+[a-z]{2,6}$/ix",$email)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 是否是手机号码
 * @param string $phone 手机号码
 * @return boolean
 */
function is_mobile($phone) {
    if (strlen ( $phone ) != 11 || !preg_match ( '/^1[3|4|5|8|7][0-9]\d{4,8}$/', $phone )) {
        return false;
    } else {
        return true;
    }
}



function Array2Str($arr) {
    if (is_array($arr)) {
        $result = '';
        $i = 0;
        foreach($arr as $k => $v) {
            if ($i > 0) $result .= '&';
            $result .= $k . '=' . $v;
            $i++;
        }
        return $result;
    } else {
        return false;
    }
}
/**
 * 作用：将xml转为array
 * @param $xml
 * @return mixed
 */
function Xml2Array($xml){
    //将XML转为array
    $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $array_data;
}

/**
 * 作用：array转xml
 * @param $arr
 * @return string
 */
function Array2Xml($arr){
    $xml = "<xml>";
    foreach ($arr as $key=>$val){
        if (is_numeric($val)){
            $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
        }else{
            $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
    }
    $xml .= "</xml>";
    return $xml;
}

/**
 * 对像转为数组
 * @param $obj
 * @return mixed
 */
function obj2array($obj){
    $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
    $arr = array();
    foreach ($_arr as $key => $val) {
        $val = (is_array($val)) || is_object($val) ? obj2array($val) : $val;
        $arr[$key] = $val;
    }
    return $arr;
}
/**
 * 对像转数组
 * @param $obj
 * @return array
 */
function ob2ar($obj) {
    if (is_object($obj)) {
        $obj = (array) $obj;
        $obj = ob2ar($obj);
    } elseif (is_array($obj)) {
        foreach ($obj as $key => $value) {
            $obj[$key] = ob2ar($value);
        }
    }
    return $obj;
}



/**
* curl
* @param $url
* @param string $method
* @param string $data
* @param string $is_cookie
* @return mixed
*/
function curlGet($url, $method = 'get', $data = '', $is_cookie = '') {
    $method = strtoupper($method) == 'GET' ? 'GET' : 'POST';
    $data = is_array($data) ? Array2Str($data) : $data;
    $ch = curl_init();
    try {
        $header = array("Accept-Charset"=>"UTF-8");
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,10);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); //强制协议为1.0
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect: ')); //头部要送出'Expect: '
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
        if($is_cookie){
            curl_setopt($ch, CURLOPT_COOKIE,$is_cookie);
        }
        $temp = curl_exec($ch);
        curl_close($ch);
        return $temp;
    } catch (\Think\Exception $e) {
        \Think\Log::record("Curl超时：" .
            "\n错误号：" . curl_errno($ch) .
            "\n错误描述：" . curl_error($ch) .
            "\n提交URL："  . $url .
            "\n提交数据：" . $data .
            "\n提交方式：" . $method );
        curl_close($ch);
        return false;
    }
}

function get_query($url){
    $url_info = parse_url($url);
    if($url_info['query']){
        return  $url . "&";
    }else{
        return  $url . "?";
    }
}


/**
 * 判断是否微信浏览器登录
 * @return bool
 */
function is_weixin(){
    if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
        return true;
    }
    return false;
}


/**
* 将unicode转换成字符
* @param int $unicode
* @return string UTF-8字符
**/
function unicode2Char($unicode){
    if($unicode < 128)     return chr($unicode);
    if($unicode < 2048)    return chr(($unicode >> 6) + 192) .
                                 chr(($unicode & 63) + 128);
    if($unicode < 65536)   return chr(($unicode >> 12) + 224) .
                                     chr((($unicode >> 6) & 63) + 128) .
                                     chr(($unicode & 63) + 128);
    if($unicode < 2097152) return chr(($unicode >> 18) + 240) .
                                     chr((($unicode >> 12) & 63) + 128) .
                                    chr((($unicode >> 6) & 63) + 128) .
                                     chr(($unicode & 63) + 128);
     return false;
   }

/**
* 将字符转换成unicode
* @param string $char 必须是UTF-8字符
* @return int
**/
function char2Unicode($char){
    switch (strlen($char)){
        case 1 : return ord($char);
        case 2 : return (ord($char{1}) & 63) |
                           ((ord($char{0}) & 31) << 6);
        case 3 : return (ord($char{2}) & 63) |
                          ((ord($char{1}) & 63) << 6) |
                         ((ord($char{0}) & 15) << 12);
        case 4 : return (ord($char{3}) & 63) |
                           ((ord($char{2}) & 63) << 6) |
                        ((ord($char{1}) & 63) << 12) |
                          ((ord($char{0}) & 7)  << 18);
        default :
                trigger_error('Character is not UTF-8!', E_USER_WARNING);
        return false;
    }
}

/**
* 全角转半角
* @param string $str
* @return string
**/
function sbc2Dbc($str){
    // 编码转换
// 0x3000是空格，特殊处理，其他全角字符编码-0xfee0即可以转为半角
    return preg_replace('/[x{3000}x{ff01}-x{ff5f}]/ue','($unicode=char2Unicode(\'\')) == 0x3000 ? " " : (($code=$unicode-0xfee0) > 256 ? unicode2Char($code) : chr($code))',$str);
}

/**
* 半角转全角
* @param string $str
* @return string
**/
function dbc2Sbc($str){
    // 编码转换
    // 0x0020是空格，特殊处理，其他半角字符编码+0xfee0即可以转为全
    // 半角字符
    return preg_replace('/[x{0020}x{0020}-x{7e}]/ue', '($unicode=char2Unicode(\'\')) == 0x0020 ? unicode2Char（0x3000） : (($code=$unicode+0xfee0) > 256 ? unicode2Char($code) : chr($code))', $str  );
 }


function make_semiangle($str) {
    $arr = array('０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
        '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
        'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
        'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
        'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
        'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
        'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
        'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
        'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
        'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
        'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
        'ｙ' => 'y', 'ｚ' => 'z',
        '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
        '】' => ']', '〖' => '[', '〗' => ']', '“' => '[', '”' => ']',
        '‘' => '[', '’' => ']', '｛' => '{', '｝' => '}', '《' => '<',
        '》' => '>',
        '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
        '：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
        '；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
        '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
        '　' => ' ');

    return strtr($str, $arr);
}

/**
 * 所有字符半角转为全角
 * @param $str
 * @return string
 */
function filter_str($str){
    $arr = ['（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
        '】' => ']', '〖' => '[', '〗' => ']', '“' => '[', '”' => ']',
        '‘' => '[', '’' => ']', '｛' => '{', '｝' => '}', '《' => '<',
        '》' => '>',
        '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
        '：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
        '；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
        '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
        '　' => ' ', '＊' => '*'];
    /*$data = [];
    foreach ($arr as $k => $v) {
        $data[$v] = $k;
    }*/
    return strtr($str, array_flip($arr));
}

/**
 * 刷新登录时间
 * @param $login_sess
 * @param $store_id
 * @return bool
 */
function is_login($login_sess, $store_id){
    $time = time();
    if ($login_sess and $login_sess['user_id'] > 0) {
        if ($time - $login_sess['add_time'] < C('SESSION_ALIVE')) {
            session('login_info_' . $store_id . '.add_time', $time);
            return true;
        }else{
            session('login_info_' . $store_id,null);
            return false;
        }
    }else{
        return false;
    }
}

/**
 * 通用判断登录方法
 * @param $store_id
 * @param $url
 */
function void_user($store_id, $url){
     $login_info = session('login_info_' . intval($store_id));
    if (!is_login($login_info, $store_id)){
        if(!$url){
            $url = get_cur_url(1);
        }
        $url = get_server('PASSPORT_SERVER', '/User/login',
            [
                'back_act'  =>   AesEnCrypt(get_cur_domain(1) . U('index/access_token') . '?back_act=' . AesEnCrypt($url)),
                //TODO:: U('index/access_token') 可能会获取模块名称
                'back_module'    =>   AesEnCrypt(strtoupper(MODULE_NAME)),
                C('store_token_name')    => $store_id,
            ], 1);
        if (I('request.' . C('VAR_AJAX_SUBMIT'), 0, 'intval')) {
            $result = result_ajax(301,'请先登录！', ['url'=>$url]);
            exit(json_encode($result));
        } else {
            header('Location: ' . $url);
            exit;
        }
    }

}

/**
 * 给链接添加默认店铺id
 * @param $url
 * @param $store_id
 * @return string
 *
 */
function check_storeid($url, $store_id){
    $url_info = parse_url($url);
    parse_str($url_info['query'], $quer_info);
    $quer_info[C('store_token_name')] = intval($store_id);
    return $url_info['scheme'] . '://'. $url_info['host'] . $url_info['path'] . '?' .http_build_query($quer_info);
}

/**
 * 获取微信配置信息
 * @param $store_id
 * @return mixed
 */
function get_config($store_id){
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