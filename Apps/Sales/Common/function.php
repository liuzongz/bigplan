<?php

/**
 * 递归删除文件夹和文件
 * @param $dir
 * @return bool
 */
function del_dir($dir){
    //不是有效路径,直接返回
    if(!is_dir($dir)){
        trace('删除无效路径');
        return false;
    }
    if(is_link($dir)){
        unlink($dir);
        return true;
    }else{

        if(!($handle = opendir($dir))){
            trace('打开文件句柄失败');
            return false;
        }
        while(($file = readdir($handle)) !== false){

            if($file === '.' || $file === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            if(is_dir($path)){
                //递归
                if(!del_dir($path)){
                    return false;
                }
            }else{
                if(!del_file($path)){
                    return false;
                }
            }
        }
        closedir($handle);
        rmdir($dir);
        return true;
    }
}

/**
 * 删除文件
 * @param $file
 * @return bool
 */
function del_file($file){
    if(is_file($file)){
        try{
            unlink($file);
            return true;
        }catch(ErrorException $e){
            trace("待删除文件[{$file}]删除失败({$e->getMessage()})");
            return false;
        }
    }else{
        trace("待删除文件[{$file}]不存在");
        return false;
    }
}

/**
 * 标准错误格式
 * @param int $error
 * @param string $msg
 * @param null $data
 * @param array $tag
 * @return array
 */
function error_msg($error=-1, $msg='', $data=null, $tag=array()){
static $_error_msg = null;

    if(0 > $error)          return $_error_msg;
    if(!is_num($error))     E(L('MSG_FATAL_ERROR'));
    if(APP_DEBUG)           $msg .= "([{$error}])";

    $error_msg = array('error' => (string)$error, 'msg' => $msg, 'data' => $data);

    //执行标签行为扩展
    if(isset($tag['tag'])){
        $tags = explode(',', $tag['tag']);
        foreach($tags as $tag_name){
            $tag['tag'] = $tag_name;
            $tag = array_merge($tag, $error_msg);
            tag($tag['tag'], $tag);
        }
    }

    //记录缓存返回(防止在上面的行为中被重置)
    return $_error_msg = $error_msg;
}

/**
 * 以or方式合并多个正则表达式
 * @param $regs
 * @return string
 */
function reg_or($regs){
    array_walk($regs, function(&$v){
        preg_match('/\/(.+)\//', $v, $m);
        $v = $m[1];
    });
    return '/'.join('|', $regs).'/';
}

/**
 * 获取当前请求完整地址
 * @return string
 */
function request_uri(){
    if(isset($_SERVER['REQUEST_URI'])){
        $request_uri = $_SERVER['REQUEST_URI'];
    }elseif(isset( $_SERVER['argv'])){
        $request_uri = $_SERVER['PHP_SELF']."?".$_SERVER['argv'][0];
    }else{
        $request_uri = $_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING'];
    }
    return IS_AJAX ? $_SERVER['HTTP_REFERER'] : $request_uri;
}

/**
 * 获取来源url,直接从post/get中获取参数
 * 解析失败返回后台首页
 * 需要写入表单,加入标签过滤
 * @return string
 */
function get_ref_url(){
    $ref_url = AesDeCrypt(I('ref_url'));
    if('' === $ref_url || '/' === $ref_url || (preg_match(REG_URI, $ref_url) !== 1 && preg_match(REG_URL, $ref_url) !== 1)){
        //未知来源跳后台首页(后面2个正则是中的URL是由于ajax调用时获取来源页面时得到的完整URL)
        $ref_url =  U('Home/index');
    }
    return $ref_url;//总是用于跳转,即使在js中也无需htmlspecialchars
}

/**
 * 在字符串中指定位置插入字符串
 * @param $str
 * @param $insert
 * @param $pos
 * @return string
 */
function str_insert($str, $insert, $pos){
    $pos = (int)$pos;
    $len = strlen($str);
    $pos = $pos>$len||$pos<0?0:$pos;
    return substr($str, 0, $pos).$insert.substr($str, $pos, $len - $pos);
}

/**
 * 为url动态增加查询变量
 * @param $url
 * @param $var              \数组
 * @return string
 */
function add_url_var($url, $var){
    $pos = strpos($url, '?');
    if(false === $pos){
        return $url.'?'.http_build_query($var);
    }else{
        return str_insert($url, http_build_query($var).'&', $pos + 1);
    }
}

/**
 * 禁用分页
 */
function pages_disabled(){
    unset($_REQUEST['_r'], $_GET['_r'], $_POST['_r']);
    C('LIST_ROWS', 0);
    $_GET[C('VAR_PAGE')] = 1;
}

/**
 * 子关联合并
 * @param array $arr
 * @param $key
 * @param string $child
 * @return array
 */
function associate_combin(array $arr, $key, $child="_child"){
    if(!is_array($arr)) $arr = array();
    $r = array();
    foreach($arr as $a){
        $k = $a[$key];
        if(isset($r[$k])){
            $r[$k][$child][] = $a;
        }else{
            $a[$child][] = $a;
            $r[$k] = $a;
        }
    }
    return $r;
}

/**
 * 将字符串按固定长度截取,多余部分显示(...)
 * @param $str
 * @param int $leng
 * @return string
 */
function str_cut($str, $leng=5){
    if(strlen($str) <= $leng){
        return $str;
    }else{
        return mb_substr($str, 0, $leng).'...';
    }
}

/**
 * 发送邮件
 * @param $to
 * @param string $body
 * @param string $subject
 * @return bool
 */
function send_email($to, $body, $subject){
    $data['mailto'] 	= $to;
    $data['subject'] 	= $subject;
    $data['body'] 		= $body;
    //todo:文件配置被数据库下载的配置覆盖了(需要设置和调整数据库配置,发送短信原因相同)
    C(array(
        'SMTP_SERVER'       => 'smtp.163.com',					//邮件服务器
        'SMTP_PORT'         => 25,								//邮件服务器端口
        'SMTP_USER_EMAIL'   => 'huigoucc@163.com', 	            //SMTP服务器的用户邮箱(一般发件人也得用这个邮箱)
        'SMTP_USER'         => 'huigoucc@163.com',			    //SMTP服务器账号名
        'SMTP_PWD'          => 'wwwhuigou.cc',					//SMTP服务器账号密码
        'SMTP_MAIL_TYPE'    => 'HTML',						    //发送邮件类型:HTML,TXT(注意都是大写)
        'SMTP_TIME_OUT'     => 30,							    //超时时间
        'SMTP_AUTH'         => true,							//邮箱验证(一般都要开启)
    ));
    $mail = new \Vendor\Email\Email();
    return $mail->send($data);
}

/**
 * 发送短信
 * @param $to
 * @param $body
 * @param $tempid
 * @return mixed
 */
function send_sms($to, $body, $tempid){
    $sms_info       = array(
        'HOST'                  => '120.24.161.220',
        'PORT'                  => '8800',
        'ACCOUNT'               => 'EAC834D5BCE648DDB3A49C649548CF1D',
        'TOKEN'                 => '6058cd1ad9d94caabdb940b83510aac8',
    );
    $url            = "http://{$sms_info['HOST']}:{$sms_info['PORT']}/SMS/Send";
    $data           = array(
        'account'   =>  $sms_info['ACCOUNT'],
        'token'     =>  $sms_info['TOKEN'],
        'mobile'    =>  $to,
        'content'   =>  $body,
        'tempid'    =>  $tempid,
        'type'      =>  0,//固定返回json
        'mToken'    =>  '',
        'extno'     =>  '',
    );
    $data   = http_build_query($data);
    $curl   = new \Vendor\Curl();
    $result = $curl->post($url, $data);
    $result = json_decode($result, true);
    return ($result && $result['Code'] == 0)?true:false;
}

/**
 * 商品规格属性转换html表单
 * @param $type_attr
 * @param $goods_attr
 * @return string
 */
function html_goods_attr($type_attr, $goods_attr){

    //唯一值没有添加按钮和价格属性(模型中定义的常量,2个后台是一致的)
    if($type_attr['attr_type'] == \Admin\Model\AttributeModel::ATTR_ALONE){
        $add    = '';
        $price  = '';
    }else{
        $add    = '<a class="dt_add" href="javascript:;"><i class="icon-plus-sign"></i></a>';
        $price  = '<input type="text" placeholder="属性价格" class="w50 price" name="attr[price]['. $type_attr['attr_id'] .'][]">';
    }

    //三种类型的值获取方式
    switch($type_attr['attr_input_type']){
        case \Admin\Model\AttributeModel::ATTR_TEXT:
            $input = '<input type="text" name="attr[value]['. $type_attr['attr_id'] .'][]">';
            break;
        case \Admin\Model\AttributeModel::ATTR_SELECT:
            $input = '<option value="">请选择...</option>';
            foreach(explode("\r\n", $type_attr['attr_values']) as $each){
                $input .= '<option value="'. $each .'">'. $each .'</option>';
            }
            $input = '<select name="attr[value]['. $type_attr['attr_id'] .'][]">'. $input .'</select>';
            break;
        case \Admin\Model\AttributeModel::ATTR_AREA:
            $input = '<textarea name="attr[value]['. $type_attr['attr_id'] .'][]"></textarea>';
            break;
        default:
            $input = '';
    }

    $input = "<li><fieldset>{$input}{$price}</fieldset></li>";

    //多属性单元重复,具体回显留给前台
    $goods_attr = array_count_values($goods_attr);
    if(isset($goods_attr[$type_attr['attr_id']])){
        $input = str_repeat($input, $goods_attr[$type_attr['attr_id']]);
    }

    return "<dl><dt>{$type_attr['attr_name']}{$add}：</dt><dd><ul>{$input}</ul></dd></dl>";
}

/**
 * 图片地址转换成图片服务器模式
 * @param $img
 * @return null|string
 */
/*function img_server($img){
static $_server = null;
    if(null === $_server){
        $host       = C('UPLOAD_SERVER');
        $host       = $host ? $host : $_SERVER['HTTP_HOST'];
        $host       = rtrim($host, '/');
        $_server    = (is_ssl()?'https://':'http://').$host.(strpos($host,'.')?'':strstr($_SERVER['HTTP_HOST'],'.'));
    }

    return $_server.'/'.ltrim($img, '/');
}*/

/**
 * 读写excel文件
 * 互换数据类似$arr[x][y]数组
 * @param $excel
 * @return array
 */
function read_excel($excel){
    try{
        vendor('PHPExcel.PHPExcel');
        $reader = PHPExcel_IOFactory::createReader('Excel5');   //设置以Excel5格式(Excel97-2003工作簿)
        $excel  = $reader->load($excel);                        //载入excel文件
        $sheet  = $excel->getSheet(0);                          //读取第一個工作表
        $rows   = $sheet->getHighestRow();                      //取得总行数
        $cols   = $sheet->getHighestColumn();                   //取得总列数
        $data   = array();
        for($row = 1; $row <= $rows; $row++){
            for ($col = 'A'; $col <= $cols; $col++){
                //内层单元格循环(一行一行,横向读取)
                $data[$row][$col] = $sheet->getCell($col.$row)->getValue();
                if(is_object($data[$row][$col])) $data[$row][$col] = $data[$row][$col]->__toString();
                $data[$row][$col] = (string)$data[$row][$col];
            }
            //过滤整行为空的
            if(!array_filter($data[$row])){
                unset($data[$row]);
            }
        }
        return $data;
    }catch(\Exception $e){
        trace($e->getMessage());
        return array();
    }
}
function write_excel($data){
    try {
        vendor('PHPExcel.PHPExcel');
        $excel = new \PHPExcel();
        //设置基础属性
        $excel->getProperties()
            ->setCreator('weipin')
            ->setLastModifiedBy('weipin')
            ->setTitle('weipin')
            ->setSubject('weipin')
            ->setDescription('weipin')
            ->setKeywords('weipin')
            ->setCategory('weipin');
        //设置获得工作表,准备写入
        $sheet = $excel->setActiveSheetIndex(0)->setTitle('sheet1');
        $row = '1';
        foreach ($data as $line) {
            $col = 'A';
            foreach ($line as $cell) {
                //$sheet->setCellValue($col.$row, $cell);
                $sheet->setCellValueExplicit($col.$row,$cell,\PHPExcel_Cell_DataType::TYPE_STRING);
                $col++;
            }
            $row++;
        }
        //具体输出到哪里由外部调用代码决定
        return PHPExcel_IOFactory::createWriter($excel, 'Excel5');
    }catch(\Exception $e){
        E($e->getMessage());
    }
}

/**
 * 佣金审核函数
 * @param $id
 * @return array
 */
function brok_exchange($id){

    $user_level     = new \Admin\Model\UserLevelModel();
    $user_account   = new \Admin\Model\UserAccountModel();

    //基础数据验证
    $record = M('user_level')->where(array('id'=>(int)$id))->find();
    if(!$record)                                                            return error_msg(__LINE__, '佣金记录不存在');
    //if(0 > $record['money'])                                                return error_msg(__LINE__, '佣金记录异常(佣金小于0)');
    if(!in_array($record['stype'], array_keys($user_level->typeLists)))     return error_msg(__LINE__, '佣金记录类型不存在');
    if(!in_array($record['state'], array_keys($user_level->statusLists)))   return error_msg(__LINE__, '佣金记录状态不存在');

    //资金审核与反审核判断(不验证金额的正负,也就是正负都可以正反向操作)
    $data_edit_user_level = array(
       'id'            => $record['id'],
       'updatetime'    => NOW_TIME
    );
    switch($record['state']){
        case $user_level::YJ_UNAUDIT:
        case $user_level::YJ_HANDLE_AUDIT:

            //临时合并在这里方便集中调用,也可以单独写在控制器层
            if(I('sid')){
                //已逻辑删除
                $data_edit_user_level['state'] = $user_level::YJ_DELETED;
                if($user_level->save($data_edit_user_level)){
                    return error_msg(0, '操作成功');
                }else{
                    return error_msg(__LINE__, L('MSG_DB_SAVE'));
                }
            }

            //等待自动审核和手动审核状态可以进行正项操作
            $recover = 1;
            //最终成为完成状态
            $data_edit_user_level['state'] = $user_level::YJ_AUDIT;
            break;
        case $user_level::YJ_AUDIT:
            //只有已审核状态可以进行反向操作
            $recover = -1;
            //反审核后变成手动审核状态
            $data_edit_user_level['state'] = $user_level::YJ_HANDLE_AUDIT;
            break;
        default:
            return error_msg(__LINE__, "佣金状态[{$user_level->statusLists[$record['state']][0]}]不可操作");
            break;
    }
    //sql中永远做加操作
    $money = $record['money'] * $recover;

    $data_add_user_account  = array(
        'admin_id'      => IS_LOGIN,
        'add_time'      => NOW_TIME,
        'paid_time'     => NOW_TIME,
        'payment'       => '系统处理',
        'is_paid'       => $user_account::OPT_SUCCESS,
        'cash_sn'       => create_sn('CSN'),
        'pay_code'      => 'system',
        'pay_name'      => 'admin',
        'amount'        => $money,
        'stage'         => $user_account::ST_INCOME,
        'user_id'       => '',
        'store_id'      => '',
        'pay_account'   => '',
        'process_type'  => '',
        'desc'          => '',

        'admin_note'    => '',
        'user_note'     => '',
    );
    $data_add_cash_log      = array(
        'admin_id'      => &$data_add_user_account['admin_id'],
        'cash_sn'       => &$data_add_user_account['cash_sn'],
        'store_id'      => &$data_add_user_account['store_id'],
        'user_id'       => &$data_add_user_account['user_id'],
        'price'         => &$data_add_user_account['amount'],
        'addtime'       => &$data_add_user_account['add_time'],
        'desc'          => &$data_add_user_account['desc'],
        'type'          => (int)($money > 0),
        'stage'         => '',
        'old_num'       => '',
        'new_num'       => '',

        'user_name'     => '',
        'admin_name'    => '',
    );


    if($user_level::TYPE_4 == $record['stype']){

        $store = M('store')->where(array('store_id'=>$record['store_id']))->find();
        if(!$store) return error_msg(__LINE__, '店铺不存在');

        //店铺商家佣金
        $data_add_user_account['store_id']      = $record['store_id'];
        $data_add_user_account['pay_account']   = $record['store_id'];
        $data_add_user_account['process_type']  = $user_account::TYPE_MONEY;
        $data_add_user_account['desc']          = '销售收入'.($recover<0?'[反审核]':'');

        $data_add_cash_log['stage']             = 'income';
        $data_add_cash_log['old_num']           = $store['store_money'];
        $data_add_cash_log['new_num']           = $data_add_cash_log['old_num'] + $money;
        $sql[] = M('store')->fetchSql()->where(array('store_id'=>$record['store_id']))->setInc('store_money', $money);
    }else{

        $user   = M('users')->where(array('user_id'=>$record['uid']))->find();
        if($record['uid'] && !$user) return error_msg(__LINE__, '用户不存在');

        //判断员工,区分现金/红包/离职
        $is_vip = $user['is_vip'];

        if($is_vip < 0){
            //离职员工不分佣金,直接改状态返回
            if($user_level->save($data_edit_user_level)){
                return error_msg(0, '操作成功');
            }else{
                return error_msg(__LINE__, L('MSG_DB_SAVE'));
            }
        }

        $data_add_user_account['user_id']       = $record['uid'];
        $data_add_user_account['pay_account']   = $record['uid'];

        switch($record['stype']){
            case $user_level::TYPE_3://Kp返点

                $data_add_user_account['process_type']  = $user_account::TYPE_KP;
                $data_add_user_account['desc']          = 'Kp返点'.($recover<0?'[反审核]':'');

                $data_add_cash_log['stage']             = 'kp_num';
                $data_add_cash_log['old_num']           = $user['consume_total'];
                $data_add_cash_log['new_num']           = $data_add_cash_log['old_num'] + $money;
                $sql[] = M('users')->fetchSql()->where(array('user_id'=>$record['uid']))->setInc('consume_total', $money);
                break;

            case $user_level::TYPE_2://代理
            case $user_level::TYPE_1://董事
            case $user_level::TYPE_0://分销

                //$is_vip = 1;//todo:临时修改,都是员工,全部进余额

                $data_add_user_account['process_type']  = $is_vip ? $user_account::TYPE_MONEY : $user_account::TYPE_RED;
                $data_add_user_account['desc']          = ($is_vip?'余额:':'红包:').'三级分销与董事收入'.($recover<0?'[反审核]':'');

                $data_add_cash_log['stage']             = $is_vip?'income':'bonus';
                $data_add_cash_log['old_num']           = $user[$is_vip?'user_money':'user_bonus'];
                $data_add_cash_log['new_num']           = $data_add_cash_log['old_num'] + $money;
                $sql[] = M('users')->fetchSql()->where(array('user_id'=>$record['uid']))->setInc($is_vip?'user_money':'user_bonus', $money);
                break;
            default:
                return error_msg(__LINE__, "佣金类型[{$user_level->typeLists[$record['stype']][0]}]不可操作");
                break;
        }
    }

    $sql[] = M('user_account')->fetchSql()->add($data_add_user_account);
    $sql[] = M('cash_log')->fetchSql()->add($data_add_cash_log);
    $sql[] = M('user_level')->fetchSql()->save($data_edit_user_level);
    if(count($sql) === count(array_filter($sql))){
        try{
            foreach($sql as $each){
                M()->execute($each);
            }
            return error_msg(0, '操作成功');
        }catch(Exception $e){
            return error_msg(__LINE__, $e->getMessage());
        }
    }else{
        trace(print_r($sql, 1));
        return error_msg(__LINE__, '未知错误');
    }
}

/**
 * 判断整数或整数字符串
 * @param $value
 * @return bool
 */
function is_num($value){
    return is_numeric($value) && is_int($value + 0);
}

/**
 * 判断字符串时间格式是否是有效的日期时间
 * @param $strtime
 * @param string $format
 * @return bool
 */
function is_date($strtime, $format='Y-m-d'){
    return $strtime === date($format, strtotime($strtime));
}

/**
 * 安全的,无业务逻辑的
 * 模版输出函数
 */
function _date($format, $timestamp, $default='<i class="icon-minus"></i>'){
    if($timestamp){
        return date($format, $timestamp);
    }else{
        return $default;
    }
}
function _I($name, $default='', $filter=null, $datas=null){
    //todo:安全过滤
    return htmlspecialchars(I($name, $default, $filter, $datas), ENT_QUOTES);
}


function object_to_array($e){
    $e=(array)$e;
    foreach($e as $k=>$v){
        if( gettype($v)=='resource' ) return null;
        if( gettype($v)=='object' || gettype($v)=='array' )
            $e[$k]=(array)object_to_array($v);
    }
    return $e;
}

/**
 * 返回固定格式的json
 * @param int $error
 * @param string $msg
 * @param array $data
 * @param array $extends
 * @return array
 */
function result_json($status = 0,$msg = '',$data = [], $extends = []){
    $result = ['status'=>$status,'msg'=> $msg, 'data'=> $data];
    if ($extends) {
        $result = array_merge($result, $extends);
    }
    return $result;
}

/**
 * 前台显示图片，用于模板
 * @param $img
 * @param int $is_return
 * @return string
 */
function echo_img($img, $is_return = 0) {
    $img = img_url($img);
    $error_img =  C('DEFAULT_IMG');
    if ($img) {
        $result = $img . '" onerror="this.src=\'' . $error_img . '\'';
    } else {
        $result = $error_img;
    }
    if ($is_return) {
        return $result;
    } else {
        echo $result;
    }
}

/**
 *  随机指定范围获取随有小数的机数
 * @param int $min 最小值
 * @param int $max 最大值
 * @return float
 */
function randomFloat($min = 0, $max = 1) {
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}