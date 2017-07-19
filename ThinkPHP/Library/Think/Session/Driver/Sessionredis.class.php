<?php

/**
 * 自定义redis处理 驱动
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: SessionRedis.class.php 17156 2016-03-19 15:47:47Z keheng $
*/
namespace Think\Session\Driver;

Class SessionRedis {
    //	REDIS连接对象
    Private $redis;
    //	SESSION有效时间
    Private $expire;

    //	functions.php有定义默认执行方法为execute
    //	具体代码可参考Common/functions.php中，搜索session，可查询到相关session自动执行的方法
    Public function execute () {
        session_set_save_handler(
            array(&$this,"open"),
            array(&$this,"close"),
            array(&$this,"read"),
            array(&$this,"write"),
            array(&$this,"destroy"),
            array(&$this,"gc"));
    }

    //打开Session
    Public function open ($path, $name) {
        $this->expire = C('SESSION_EXPIRE') ? C('SESSION_EXPIRE') : ini_get('session.gc_maxlifetime');
        $this->redis = new \Org\Util\Redis();
        return $this->redis->connect(C('SESSION_REDIS_HOST'),C('SESSION_REDIS_PORT'),C('SESSION_REDIS_AUTH'));
    }

    Public function close () {
        //if (isset($_GET['debug']))echo '关闭session';
        return $this->redis->close();
    }

    Public function read ($id) {
        //if (isset($_GET['debug']))echo '读取session';
        $id = C('SESSION_PREFIX') . $id;
        $data = $this->redis->get($id);
        return $data ? $data : '';
    }

    //写入session
    Public function write ($id, $data) {
        //if (isset($_GET['debug']))echo '写入session';
        $id = C('SESSION_PREFIX') . $id;
        return $this->redis->set($id, $data, $this->expire);
    }

    //	销毁SESSION
    Public function destroy ($id) {
        $id = C('SESSION_PREFIX') . $id;
        return $this->redis->delete($id);
    }

    Public function gc ($maxLifeTime) {
        /*foreach (glob("$this->savePath/sess_*") as $file) {
            if (filemtime($file) + $maxLifeTime < time() && file_exists($file)) {
                unlink($file);
            }
        }*/
        return true;
    }
}