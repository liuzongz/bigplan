<?php
return array(
	//'配置项'=>'配置值'
    'DEFAULT_THEME' =>  'default',  // 默认模板主题名称
    'DEFAULT_CONTROLLER'        => 'User',  //默认控制器名称
    'TMPL_FILE_DEPR'        =>  '_',
    'TMPL_PARSE_STRING' => array(
        '__STATIC__' => __ROOT__ . '/Public/Static',
        '__ADDONS__' => __ROOT__ . '/Public/' . MODULE_NAME . '/Addons',
        '__IMG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/images',
        '__CSS__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/css',
        '__JS__'     => __ROOT__ . '/Public/' . MODULE_NAME . '/js',
        '__LOG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/log',
        '__FONT__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/font'
    ),

    'CN_LIST' =>    [           //发送邮件或短信显示公司名称
        'hkhp'  =>  '汇客惠品',
        'jiniu' =>  '即牛营销'
    ],
    'SMS' =>    [               //短信配置
        'SMS_HOST'          =>  '120.24.161.220',
        'SMS_PORT'          =>  '8800',
        'SMS_URI'           =>  '/SMS/Send',
        'SMS_USER'          =>  'EAC834D5BCE648DDB3A49C649548CF1D',
        'SMS_PASS'          =>  '6058cd1ad9d94caabdb940b83510aac8',
        'SMS_TPL'           =>  [
            '407'=>'【%s】欢迎注册%s，您当前的验证码是：%s,本验证码10分钟内有效！',
            '433'=>'【%s】尊敬的用户，您正在进行手机找回密码操作，验证码为：%s。如非您本人操作，请勿泄露您的验证码！',
        ],
    ],
    'MAIL'  =>  [        //邮件发送配置
        'SMTP_SERVER'       => 'smtp.hkhp.net',			//邮件服务器
        'SMTP_PORT'         => 25,								//邮件服务器端口
        'SMTP_USER_EMAIL'   => 'service@hkhp.net',//SMTP服务器的用户邮箱(一般发件人也得用这个邮箱)
        'SMTP_USER'         => 'service@hkhp.net',		//SMTP服务器账户名
        'SMTP_PWD'          => 'Jiniu13697348868',			//SMTP服务器账户密码
        'SMTP_MAIL_TYPE'    => 'HTML',			//发送邮件类型:HTML,TXT(注意都是大写)
        'SMTP_TIME_OUT'     => 30,							//超时时间
        'SMTP_AUTH'         => true,
    ],
    // 开启路由
    'URL_ROUTER_ON'   => true,
    'URL_ROUTE_RULES'=>array(
        'wx/:token\d'               => 'Wap/Api/index',
        '/^MP_verify_([0-9a-zA-Z]{16})$/' => 'pay/index/wx_verify?key=:1'
    ),
    //跨域来源服务器域名设置
    'ALLOW_ORIGIN'      => ['sales.t.hkhp.net','seller.t.hkhp.net','admin.t.hkhp.net','wap.t.hkhp.net'],

    //可使api请求的模块名
    'ALLOW_MODULE'      => array('Admin','Seller','Wap','Sales'),
);