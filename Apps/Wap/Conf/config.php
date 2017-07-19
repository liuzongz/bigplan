<?php
return array(
	//'配置项'=>'配置值'
	 /* 主题设置 */
    'DEFAULT_THEME' =>  'default',  // 默认模板主题名称
	'TMPL_FILE_DEPR'        =>  '_',
	 /* 模板相关配置 */
    'TMPL_PARSE_STRING' => array(
        '__STATIC__' => __ROOT__ . '/Public/static',
        '__ADDONS__' => __ROOT__ . '/Public/' . MODULE_NAME . '/Addons',
        '__IMG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/images',
        '__CSS__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/css',
        '__JS__'     => __ROOT__ . '/Public/' . MODULE_NAME . '/js',
	    '__LOG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/log',
        '__FONT__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/font',
        //'__PLUGINS__'     => __ROOT__ . __APP__ . '/Plugins'
    ),

	'UPLOAD_TYPE' => 'local',
    'UP_BUCKET' => '',
    'UP_FORM_API_SECRET' => '',
    'UP_USERNAME' => '',
    'UP_PASSWORD' => '',
    'UP_DOMAINNAME' => '',
    'UP_EXTS' => 'jpeg,jpg,png,php,txt',
    'UP_SIZE' => '2048',
    //'UP_PATH' => './data/upload',
    //"LOAD_EXT_FILE"     =>  "const",
    'CONNECTNUM' => '系统维护中',
    'ORDER_STATE_NEW' => 10,//已产生但未支付
    'ORDER_STATE_PAY' => 20,//已支付
    'ORDER_STATE_SEND' => 30,//已发货

    //跨域来源服务器域名设置
    'ALLOW_ORIGIN'      => ['http://t0.jiniu.cc','http://t5.jiniu.cc','http://t6.jiniu.cc','http://t3.jiniu.cc'],
    //可使api请求的模块名
    'ALLOW_MODULE'      => array('admin', 'seller','wap'),

    'VAR_ADDON'         =>    'Plugins',
    'AUTOLOAD_NAMESPACE' => array('Addons' => './Plugins/'),
    #'TMPL_ACTION_ERROR'     =>   'Public:showbox', // 默认错误跳转对应的模板文件
    #'TMPL_ACTION_SUCCESS'   =>  'Public:showbox', // 默认成功跳转对应的模板文件
    //'TMPL_EXCEPTION_FILE'   =>  MODULE_PATH. 'View/default/Public_exception.html',// 异常页面的模板文件
    //'TMPL_EXCEPTION_FILE'   =>  'Public:exception',// 异常页面的模板文件
    'ERROR_MESSAGE'         =>  '系统升级中，请稍候再试！',//错误显示信息,非调试模式有效
    //'ERROR_PAGE' =>'/Public_showbox.html'
    'DEFAULT_IMG'   =>  __ROOT__ . '/Public/' . MODULE_NAME . '/images/logox2.png',
    'API_APPINFO' => array(
                      'appid'       => 'hk53b289c1eb72d590',
                      'appsecret'   => '2ee665a1095eec3386699f926ae726f8'
                     ),
    // 开启路由
    'URL_ROUTER_ON'   => true,
    'URL_ROUTE_RULES'=>array(
        '/^MP_verify_([0-9a-zA-Z]{16})$/' => 'pay/index/wx_verify?key=:1'
    ),
);
