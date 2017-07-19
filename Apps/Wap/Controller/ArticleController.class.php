<?php
namespace Wap\Controller;
use Wap\Model;
class ArticleController extends WapController {
    function index(){
        $id = I('request.id', 0, 'intval');
        $article_info = $this->get_article_info($id);//print_r($article_info);
        if ($id <= 0 or !$article_info) {
            $this->error('该文章不存在或已被删除！',U('Article/cat'));
            //$this->redirect('Article/cat');
        }
        //$article_info['content'] = $article_info['content'];//(replace_image_addr($article_info['content']));
        $article_info['add_time'] = date("Y-m-d" , $article_info['add_time']);
        $article_info['cat_url'] = U('Article/cat?id=' . $article_info['cat_id']);

        //$replaceStr = '<div class="keheng_hr_20130310">&nbsp;</div>';
        //$str = explode($replaceStr, $article_info['content']);
        //$article['content'] = str_replace($str[0]) . $replaceStr . $str[1];
        $article_info['content'] = ReplaceImgUrl($article_info['content']);

        $data['uid'] = AesDeCrypt(I('get.rec','','trim'));
        //$data = get_encrypt_str('rec','get',0);
        if ($data && $data['uid'] > 0){
            $user_model = new Model\UsersModel();
            $user_info = $user_model->get_userinfo('user_id=' . $data['uid'], '*');
            if ($user_info) {
                $info = $this->get_qr_info($user_info);
                if ($info['error'] > 0) {
                    $this->error($info['msg']);
                } else {
                    $info = $info['data'];
                }
                $this->assign('qrcode_info', $info);
            }
        }

        M('article')->where('article_id=' . $id)->setInc('click_count',1);      //添加文章点击次数，并获得当前点击次数
        $this->assign('article', $article_info);

        $this->assign('id', $id);
        $this->assign('is_full', isset($_GET['isfull']) ? 1 : 0);
        $this->assign('user_head',['title'=>$this->_CFG['shop_name']]);
        $this->assign('tkd',['title'=>$article_info['title'],'keywords'=>$article_info['keywords'],'discription'=>$article_info['discription']]);
        $this->display();
    }

    public function slist(){
        $id = I('get.id', 13, 'intval');
        $cat_info = $this->get_article_cat($id);
        if ($id <= 0 or !$cat_info) {
            $this->error('该分类不存在或已被删除！',U('index/index'));
        }
        $cat_info = $cat_info[0];
        $list = $this->get_article_list('a.cat_id=' . $id);
        $article_list   = $list['list'];
        $show           = $list['show'];
        $this->is_login();
        foreach ($article_list as $k => $v) {
            $article_list[$k]['add_time'] = date("Y-m-d", $v['add_time']);
            $article_list[$k]['url'] = U('Article/index') . '?id=' . $v['article_id'];
            if (isset($_GET['share']) and $this->user_id > 0) $article_list[$k]['url'] .= '&rec=' . AesEnCrypt($this->user_id);
            $article_list[$k]['cat_url'] = U('Article/cat?id=' . $v['cat_id']);
        }
        $this->assign('article_list',['list'=>$article_list,'show'=>$show]);
        $this->assign('empty_article','<li class="empty">未搜索到相关新闻</li>');
        $this->assign('user_head', ['title'=>$cat_info['cat_name']]);
        $this->assign('tkd',['title'=>$cat_info['cat_name']]);
        $this->display();
    }

    function cat(){
        $default_id = 13;
        $id = I('get.id', $default_id, 'intval');
        $is_full = I('request.isfull', 0, 'intval');
        $keywords = I('request.keywords','','htmlspecialchars');
        $res = $this->get_article_cat();

        $ids = array();
        foreach ($res as $k => $v) {
            $ids[] = $v['cat_id'];
            $res[$k]['cat_url'] = U('Article/cat?id=' . $v['cat_id']);
            if ($v['cat_id'] == $default_id) $default_cat = $v;
        }
        $this->assign('cat_list', $res);

        if (!in_array($id, $ids))   $id = $default_id;      //默认显示文章类型

        $title = '新闻中心';
        foreach ($res as $k => $v) {        //取类型ID及类型名称
            if ($v['cat_id'] == $id) {
                $title  = $v['cat_name'];
            }
        }

        $list = $this->get_article_list('a.cat_id=' . $id,$keywords);
        $article_list   = $list['list'];
        $show           = $list['show'];

        foreach ($article_list as $k => $v) {
            $article_list[$k]['add_time'] = date("Y-m-d H:i:s", $v['add_time']);
            $article_list[$k]['url'] = U('Article/index?id=' . $v['article_id']);
            $article_list[$k]['cat_url'] = U('Article/cat?id=' . $v['cat_id']);
        }
        $user_head = array(
            'title'             =>  $title,
            'backUrl'           =>  U('Index/index'),
            'backText'          =>  '首页',
            //'more'              =>  $this->fetch('more'),
            'searchUrl'         =>  U('Article/cat?id=' . $id . '&keywords='),
            'searchSpeckText'   => '请输入文章关词',
            'searchwords'       => $keywords
        );

        $this->assign('article_list', $article_list);
        $this->assign('article_empty', '<li class="empty">未搜索到相关新闻</li>');
        $this->assign('page', $show);
        $this->assign('is_full',$is_full);
        $this->assign('user_head', $user_head);
        $this->display();
    }

    private function get_article_info($id){
        $m = M('article');
        return $m->alias('a')
            ->field('a.*,ac.cat_name')
            ->where('article_id=' . $id . ' and is_open=1')
            ->join('LEFT JOIN __ARTICLE_CAT__ ac ON ac.cat_id=a.cat_id')
            ->find();
    }

    private function get_article_cat($id = 0){
        if ($id <= 0) {
            return false;
        } else {
            $where  = ['cat_id'=>$id];
            $result = M('article_cat')->where($where)->order('sort_order')->select();
            return $result;
        }
    }

    private function get_article_list($where, $searchword = '') {

        $m              = M('article');
        $where          = " is_open=1 and " . $where;
        if ($searchword != '') $where .= ' and title like "%' . $searchword . '%" or content like "%' . $searchword . '%"';
        $count          = $m->alias('a')->where($where)->count();
        $Page           = new \Think\Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数
        $Page->rollPage = 5;
        $show           =   $Page->show();
        $article_list   = M('article')->alias('a')
            ->field('ac.cat_name,a.article_id,a.cat_id,a.title,a.add_time,a.author,a.description,a.click_count,a.sort')
            ->where($where)
            ->join('LEFT JOIN __ARTICLE_CAT__ ac ON ac.cat_id=a.cat_id')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('add_time desc,sort')
            ->select();

        $result = array(
            'show'  =>  $show,
            'list'  =>  $article_list
        );
        return $result;
    }
}