<?php
return array(

    'MSG_REMOTE_ERROR'  => '服务器未响应',                                    //一般是ajax操作时服务器响应失败

    //数据操作
    'MSG_DB_ADD'        => '系统升级中,请稍后再试(add)',                      //数据库add操作错误
    'MSG_DB_SAVE'       => '系统升级中,请稍后再试(save)',                     //数据库save操作错误

    //数据验证
    'MSG_VA_STRUCT'     => '数据结构不完整',                                  //不是正常的页面提交数据,造成的数据结构不正确的验证错误
    'MSG_VA_MULTI'      => '错误明细未设置',                                  //在验证方法中没有使用setDetailError设置错误明细

    'MSG_SYSTEM_UPDATE' => '系统升级中,请稍后再试',                           //一般性错误,未细化的错误类型
    
    'MSG_FATAL_ERROR'   => '发生致命错误,请联系管理员',                       //代码中明显疏漏

);


