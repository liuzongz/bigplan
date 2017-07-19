<?php
return array(
    //'配置项'=>'配置值'
    /*'DEFAULT_THEME' =>  'default',  // 默认模板主题名称
    'TMPL_FILE_DEPR'        =>  '_',*/
    /* 模板相关配置 */
    /*'TMPL_PARSE_STRING' => array(
        '__STATIC__' => __ROOT__ . '/Public/static',
        '__ADDONS__' => __ROOT__ . '/Public/' . MODULE_NAME . '/Addons',
        '__IMG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/images',
        '__CSS__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/css',
        '__JS__'     => __ROOT__ . '/Public/' . MODULE_NAME . '/js',
        '__LOG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/log',
        '__FONT__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/font'
    ),*/
    /* 调试配置 */
    'SHOW_PAGE_TRACE'       => 1,
    'URL_CASE_INSENSITIVE'  => true,
    'URL_MODEL'             => 2, //URL模式
    'TRACE_EXCEPTION'       => 0,
    'SHOW_ERROR_MSG'        => false ,    // 显示错误信息
    /* 数据库配置 */
    'DB_PARAMS'    =>    array(\PDO::ATTR_CASE => \PDO::CASE_NATURAL),
    'DB_DSN'    => 'mysql:host=t0.jiniu.cc;dbname=jiniu;charset=utf8',
    'DB_TYPE'   => 'mysqli', // 数据库类型
    'DB_USER'   => 'jn01', // 用户名
    'DB_PWD'    => 'Jiniu2013',  // 密码
    'DB_PORT'   => '3306', // 端口
    'DB_PREFIX' => 'wp_', // 数据库表前缀

    'UP_PATH'   =>  'Uploads/',
    'SMTP_SERVER'       => 'smtp.hkhp.net',			//邮件服务器
    'SMTP_PORT'         => 25,								//邮件服务器端口
    'SMTP_USER_EMAIL'   => 'service@hkhp.net',//SMTP服务器的用户邮箱(一般发件人也得用这个邮箱)
    'SMTP_USER'         => 'service@hkhp.net',		//SMTP服务器账户名
    'SMTP_PWD'          => 'Jiniu13697348868',			//SMTP服务器账户密码
    'SMTP_MAIL_TYPE'    => 'HTML',			//发送邮件类型:HTML,TXT(注意都是大写)
    'SMTP_TIME_OUT'     => 30,							//超时时间
    'SMTP_AUTH'         => true,
    'SMTP_FROM'         => '汇客惠品',
    // 开启路由
    'URL_ROUTER_ON'   => true,
    'URL_ROUTE_RULES'=>array(
        'wx/:token\d'               => 'Wap/Api/index',
        'pay'                      => 'sales/payment/pay'
    ),
    'KUIDI100_KEY'      =>  '40fa1d2921ea0510',

    'SESSION_TYPE'      =>  '',//SessionRedis
    'SESSION_EXPIRE'    =>  1200,
    'REDIS_HOST'        =>  '115.28.66.103',
    'REDIS_PORT'        =>  '7378',
    'REDIS_AUTH'        =>  'redis',
    'VAR_SESSION_ID'    => 'token',

    //'SESSION_AUTO_START'=> true,
    "LOAD_EXT_FILE"     =>  "const",
    'ERROR_MESSAGE'         =>  '系统升级中，请稍候再试！',//错误显示信息,非调试模式有效

    'SMS_HOST'          =>  '120.24.161.220',
    'SMS_PORT'          =>  '8800',
    'SMS_URI'           =>  '/SMS/Send',
    'SMS_USER'          =>  'EAC834D5BCE648DDB3A49C649548CF1D',
    'SMS_PASS'          =>  '6058cd1ad9d94caabdb940b83510aac8',
    'SMS_TPL'           =>  array(
        '407'=>'【汇客惠品】欢迎注册汇客惠品，您当前的验证码是：%s,本验证码10分钟内有效！',
        '433'=>'【汇客惠品】尊敬的用户，您正在进行手机找回密码操作，验证码为：%s。如非您本人操作，请勿泄露您的验证码！',
    ),
    'PAYINFO'   =>  array(
        'wxpay' =>  array(),
        'alipay' =>  array(
            'url'           =>  'https://openapi.alipay.com/gateway.do',
            'appid'         =>  '2016081001728673',
            'seller_id'     =>  '2088102172312083',
            'private_key'   =>  CONF_PATH  . 'rsa_private_key.pem',
            'Charset'       =>  'UTF-8',
            'public_key'=>'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDIgHnOn7LLILlKETd6BFRJ0GqgS2Y3mn1wMQmyh9zEyWlz5p1zrahRahbXAfCfSqshSNfqOmAQzSHRVjCqjsAw1jyqrXaPdKBmr90DIpIxmIyKXv4GGAkPyJ/6FTFY99uhpiq0qadD/uSzQsefWo0aTvP/65zi3eof7TcZ32oWpwIDAQAB'),
    ),


    //图片服务器域名[www.hkhp.net/img.hkhp.net/www/img..]//不设置就是[www]
    'IMG_SERVER'            =>  't0.jiniu.cc/img',               //图片服务器
    'UPLOAD_SERVER'         =>  't0.jiniu.cc',
    //'HELP_SERVER'         =>  'help.hkhp.net',        //帮助
    //'WAP_SERVER'          =>  'help.hkhp.net',        //前台
    //'SELLER_SERVER'       =>  'seller.hkhp.net',      //商家
    //'ADMIN_SERVER'        =>  'manage.hkhp.net',      //后台
    'KP_SERVER'             =>  't0.jiniu.cc',          //KP
    'WAP_SERVER'            =>  't5.jiniu.cc/Wap',          //商城前台
    //'SALES_SERVER'        =>  'Sales.hkhp.net',     //营销工具
    'SALES_SERVER'          =>  't6.jiniu.cc/Sales',    //营销工具
    'PASSPORT_SERVER'       =>  't5.jiniu.cc/Passport',      //会员
    //'ARTICLE_SERVER'      =>  'article.hkhp.net',     //文章
    //'YINXIAO_SERVER'      =>  'sales.hkhp.net',       //营销
    //'OPEN_SERVER'         =>  'open.hkhp.net',        //开放平台
    //'API_SERVER'          =>  'weixin.hkhp.net',      //API接口
    'API_SERVER'            =>  't0.jiniu.cc/Api',      //API接口
    'ADMIN_SERVER'          =>  't5.jiniu.cc/Admin', //用户中心


    //cookie相关(图片服务器登录共享)
    'COOKIE_PREFIX'     => 'wp_',           //cookie 名称前缀
    'COOKIE_EXPIRE'     => 0,               //cookie 保存时间
    'COOKIE_PATH'       => '/',             //cookie 保存路径
    'COOKIE_DOMAIN'     => '.jiniu.cc',     //cookie 有效域名
    'COOKIE_SECURE'     => false,          //cookie 启用安全传输
    'COOKIE_HTTPONLY'   => '',             //httponly设置

    //Redis Session配置
    /*'SESSION_AUTO_START'    =>  true,    // 是否自动开启Session
    'SESSION_TYPE'          =>  'SessionRedis',    //session类型
    'SESSION_PERSISTENT'    =>  1,        //是否长连接(对于php来说0和1都一样)
    'SESSION_CACHE_TIME'    =>  1,        //连接超时时间(秒)
    'SESSION_EXPIRE'        =>  0,        //session有效期(单位:秒) 0表示永久缓存
    'SESSION_REDIS_HOST'    =>  't0.jiniu.cc', //分布式Redis,默认第一个为主服务器
    'SESSION_REDIS_PORT'    =>  '6379',           //端口,如果相同只填一个,用英文逗号分隔
    'SESSION_REDIS_AUTH'    =>  'Jiniu2017**+_',*/    //Redis auth认证(密钥中不能有逗号),如果相同只填一个,用英文逗号分隔
    'SESS_ID_PREFIX'        =>  'wp_',
    'SESSION_PREFIX'        =>  'wp_',
    'SESSION_ALIVE'         =>  3600,
    //'CRYPT_KEY'         => md5('fdsafdsafdsaQYERWTREREWQGFFDSAFsLJJKL'),
    //'CRYPT_IV'          => base64_encode(openssl_random_pseudo_bytes(16)),
    'PUBLIC_KEY_PATH'   => CONF_PATH . 'rsa_public_key.pem',
    'PRIVATE_KEY_PATH'  => CONF_PATH . 'rsa_private_key.pem',
    //encrypt默认加密解密密匙     base64_encode(openssl_random_pseudo_bytes(32))
    'CRYPT_KEY'         => md5('fdsafdsafdsaQYERWTREREWQGFFDSAFsLJJKL'),
    'CRYPT_IV'          => base64_encode(substr(md5('fdsafdsafdsaQYERWTREREW'), 8, 16)),

    //图片跨域提交后,IS_AJAX丢失
    'VAR_AJAX_SUBMIT'   => '_ajax',

    //6df6e498 => admin
    //'URL_MODULE_MAP'    => array(substr(md5('netbum'),0,8) => 'admin'),
    'ajax_status' =>[
        '200'   =>  '成功！',
        '301'   =>  '跳转',
        '201'   =>  '选择'
    ],

    'weixin_config'     => array(            // //即牛公众号
        'appcode'       => 'gh_c3ea06a7a528',
        'appid'         => 'wxb5d1d38fe642be5a',
        'appsecret'     => 'a7b49779fdc6a49d43849aed8449d089',
        'appmchid'      => '1232514802',
        'paysignkey'    => 'Jiuzichunjiniu140814081408140814'
    ),

    /*'weixin_config'   => array(               //即牛公众号--测试
        'appcode'       => 'gh_42a691e8d59b',
        'appid'         => 'wx80ac0245c629eb56',
        'appsecret'     => '5ed77d331a0b13d628d913ae49101809',
        'appmchid'      => '1232514802',
        'paysignkey'    => 'Jiuzichunjiniu140814081408140814',
    ),*/

    /*'weixin_config'   => array(               //汇客惠品商城
        'appcode'       => 'gh_406057312000',
        'appid'         => 'wxb4ffa90cbb5d0f21',
        'appsecret'     => '6d55a2ca98365d0d95029ddabf5f5fe4',
        'appmchid'      => '1227404502',
        'paysignkey'    => 'qwertyuiopasdfghjklzxcvbnm123456'
    ),*/
    'wx_prefix'                  =>  'Jn_',     //微信授权注册用户名前缀
    'max_post'          =>  30720,    //1024*30字节

    /*'login_way'         =>  array(
        'wx_log'    =>  'wxlogin2',     //有账号微信登录
        'wx_reg'    =>  'wxlogin_log',     //没有账号注册登录

    )*/

    'default_qrcode'            => './Public/Static/images/purcode.jpg',     //默认个人名片背景
    'default_money'            => 200,                                      //默认消费100开启会员功能
    'store_token_name'              => 'store_token',
    'jzc_store'                     => 1  //官方指定店铺
);
