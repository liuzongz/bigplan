<?php

//当前请求ip
define('NOW_IP',                get_client_ip(0, true));

//三种用户名进行区分判别的正则表达式
define('REG_EMAIL',             '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/');
define('REG_PHONE',             '/^(13[0-9]|15[012356789]|17[678]|18[0-9]|14[57])[0-9]{8}$/');
define('REG_NAME',              '/^[a-zA-Z0-9_]{6,15}$/');//'/^[a-zA-Z][a-zA-Z0-9_-]{5,10}$/'首字符为字母,6-10位长度

//常用正则表达式
define('REG_URL',               '@(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))@');
define('REG_URI',               '/^(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/');
define('REG_PSWD',              '/^[a-zA-Z0-9\*+\-_]{6,20}$/');
define('REG_ID',                '/^\d{1,8}$/');
define('REG_OPENID',            '/^[a-zA-Z0-9]{32}$/');
define('REG_APPID',             '/^wp[a-zA-Z0-9]{16}$/');
define('REG_TIMESTAMP',         '/^\d{10}$/');
define('REG_NOEMPTY',           '/^\S+$/');
define('REG_ZH',                '\x{4e00}-\x{9fa5}');
define('REG_MD5',               '/^[a-zA-Z0-9]{32}$/i');
define('REG_QQ',                '/^\d{5,13}$/');
define('REG_KEYWORDS',          '/^[\w'.REG_ZH.',，]{1,100}$/u');
define('REG_DESCRIPTION',       '/^[\w'.REG_ZH.',，]{1,200}$/u');

define('REG_IMG_NAME',          '[\w\.'. REG_ZH .']{1,20}');//todo:独立其他各种名称,描述的正则

//信息发生模版ID
define('TEMPLATE_REGISTER',     407);//注册验证(与短信平台有关,不能任意值)
define('TEMPLATE_RESET',        433);//密码重置(与短信平台有关,不能任意值)
define('TEMPLATE_BIND',         458);//账号绑定

//短信间隔时间错误标志
define('ERRNO_COUNTDOWN',       __LINE__);










/**
 * 与查询相关的钩子
 * 非查询常量在模型中设置,与具体模型绑定
 * 可随意随时改变,不唯一即可
 * 如设置了查看权限,需要更新规则表,某些位置与视图模版有关(如商品)
 */


/**
 * [订单状态钩子]
 */
//未知订单状态
define('ORDER_INVALID',         0);
//综合订单状态【支付阶段】
define('ORDER_UNCONFIRMED',     1);
define('ORDER_CONFIRMED',       2);
define('ORDER_PAYING',          3);
define('ORDER_FAILED',          4);
define('ORDER_CANCELED',        5);
define('ORDER_PAYED',           6);
//综合订单状态【仓库接单阶段】
define('ORDER_SPLITING_PART',   7);
define('ORDER_SPLITED',         8);
//综合订单状态【发货阶段】
define('ORDER_PREPARING',       9);
define('ORDER_SHIPPED_ING',     10);
define('ORDER_SHIPPED_PART',    11);
define('ORDER_SHIPPED',         12);
define('ORDER_RECEIVED',        13);
define('ORDER_SUCCESS',         14);
//综合订单状态[退货阶段]
define('ORDER_RETURNED',        15);
define('ORDER_CLOSED',          16);


/**
 * [商品状态钩子]
 */
define('GOODS_INVALID',         0);//未知
define('GOODS_SALE',            1);//销售
define('GOODS_STORE',           2);//仓库
//define('GOODS_CHECK',       3);//审核
define('GOODS_BREAK',           4);//违规
define('GOODS_TRASH',           5);//回收站(逻辑删除)



//todo:转换成统一的状态数据结构

//商品关键状态标识符(同上 + 修改了之后相应的模版名称也要更改)
define('GOODS_ONLINE',      0);//出售中的
define('GOODS_OFFLINE',     1);//仓库中的
define('GOODS_ILLEGAL',     2);//违规的商品
define('GOODS_CHECK',       3);//审核中
define('GOODS_UNKNOWN',     4);//未知的不合法状态



