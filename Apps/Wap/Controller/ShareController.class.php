<?php

/**
 * 即牛 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: ShareController.class.php 17156 2016-04-19 17:15:47Z keheng $
*/
namespace Wap\Controller;
class ShareController extends WapController {
    protected function _initialize(){
        parent::_initialize();
        $this->void_user();
    }

    public function index(){
        $res = $this->get_share_list(13);
        $this->assign('share_list', $res);
        $this->assign('empty_share', '<li class="empty">暂无分享信息！</li>');
        $this->assign('user_head', array('title'=>'今日分享','backUrl'=>U('User/index'),'backText'=>'会员首页'));
        $this->display();
    }

    public function show(){
        $id = I('get.id',0,'intval');
        $article = M('Article')->where('article_id=%d',$id)->find();
        $this->assign('article',$article);
        $this->assign('empty_article', '<p class="empty">文章已被删除</p>');
        $this->assign('user_head', array('title'=>'今日分享','backUrl'=>U('index'),'backText'=>'会员首页'));
        $this->display();
    }

    protected function get_share_list($cat_id = 0, $pagesize = 10){
        $m = M("Article");
        $where = 'is_open=1 and cat_id=' . $cat_id;
        $Page = new \Think\Page($m->where($where)->count(), $pagesize);
        $show = $Page->show();
        $res = $m
            ->where($where)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('add_time desc')
            ->select();
        foreach ($res as $k => $v) {
            $res[$k]['add_time'] = date('Y-m-d', $v['add_time']);
            $res[$k]['url'] = U('Share/show?id=' . $v['article_id']);
        }
        return array('list'=>$res,'show'=>$show,'total'=>$Page->totalPages);
    }

    /**
     * 获取分类树形结构
     * @param int $pid
     * @return array
     */
    protected function get_cats($pid = 0){
        static $cats;
        if(!$cats){
            $cats  = M("article_cat ac")->select();
        }
        $tmp = array();
        foreach($cats as $c){
            if($c['parent_id']==$pid){
                $tmp[] = $c;
            }
        }
        if($tmp){
            foreach($tmp as &$t){
                $x = $this->get_cats($t['cat_id']);
                if($x){
                    $t['children'] = $x;
                }
            }
        }
        return $tmp;
    }

} 