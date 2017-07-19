<?php
return array(

    /**
     * 登录时:协同模块[Admin/Seller]
     * session对应的cookie被设置在顶级域名中,造成多个模块共用一个[session_name,session_id]
     * 将[session_name,session_id]存到一个本模块能拿到的cookie域名中即可
     * 设置本模块session.name和session.domain即可实现自动加载,并利用[模块名]获取到多模块登录信息
     *
     * 登出时:
     * 只能删除对应[模块名]下的信息,全部删除会造成集体下线
     */

    //'配置项'=>'配置值'
    /* 主题设置 */
    'DEFAULT_THEME' =>  'default',  // 默认模板主题名称
    'TMPL_FILE_DEPR'        =>  '_',
    /* 模板相关配置 */
    'TMPL_PARSE_STRING' => array(
        '__STATIC__' => __ROOT__ . '/Public/Static',
        '__ADDONS__' => __ROOT__ . '/Public/' . MODULE_NAME . '/Addons',
        '__IMG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/images',
        '__CSS__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/css',
        '__JS__'     => __ROOT__ . '/Public/' . MODULE_NAME . '/js',
        '__LOG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/log',
        '__FONT__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/font',
        //'__PLUGINS__'     => __ROOT__ . __APP__ . '/Plugins'
    ),

    //跨域来源服务器域名设置
    'ALLOW_ORIGIN'      => ['passport.t.hkhp.net','seller.t.hkhp.net','admin.t.hkhp.net','wap.t.hkhp.net'],

    //可使api请求的模块名
    'ALLOW_MODULE'      => array('Admin','Seller','Wap','Sales'),

    //扩展常量配置
    'LOAD_EXT_FILE'     => 'define',

    //'IMG_UPLOAD_DRIVER'         => 'Local',
    'UPLOAD_LOCAL_CONFIG'       => array(),
    'DEFAULT_IMG'   =>  __ROOT__ . '/Public/' . MODULE_NAME . '/images/img-error.png',


    //营销工具图片上传分类
    'IMG_UPLOAD_CATE'   =>'1426',

    // 开启路由
    'URL_ROUTER_ON'   => true,
    'URL_ROUTE_RULES'=>array(
        'wx/:token\d'               => 'Wap/Api/index',
        '/^MP_verify_([0-9a-zA-Z]{16})$/' => 'index/wx_verify?key=:1'
    ),


);


