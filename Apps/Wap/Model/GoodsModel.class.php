<?php

/**
 * 即牛 - PhpStorm
 * ============================================================================
 * 版权所有 2011-2015 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: GoodsModel.class.php 17156 2015-12-26 09:17:47Z keheng $
*/
namespace Wap\Model;
use Think\Page;

class GoodsModel extends BaseModel{
    private $goods_where = [
        'is_on_sale'    =>  1,      //是否上线销售
        'is_alone_sale' =>  1,      //是否独立销售
        'is_delete'     =>  0,      //是否删除
        'is_check'      =>  0,      //是否正在审核
        'is_illegal'    =>  0       //是否违规
    ];
    /**
     * 获取产品属性价格
     * @param $spec
     * @return mixed
     */
    public function get_attr_price($spec) {
        $res = M('GoodsAttr')->where($this->db_create_in($spec,'goods_attr_id'))->getfield('sum(attr_price) as price');
        //echo M('GoodsAttr')->getLastSql();
        return $res;
    }

    /**
     * 比较获得的属性是否正确
     * @param $goods_id
     * @param $spec
     * @return array
     */
    public function check_spec($goods_id, $spec) {
        $result = array('error'=>0,'message'=>'');
        if (empty($spec)) {
            $result = array('error'=>0,'message'=>'产品属性为空！');
        } else {
            $spec_arr = explode(',',$spec);
            $spec_ok = 0;
            foreach ($spec_arr as $v){
                if (!is_numeric($v)){
                    $spec_ok = 1;
                    break;
                }
            }
            if ($spec_ok != 0){
                $result['error']   = 7;
                $result['message'] = '产品属性非法！';
            }else if (is_array($spec_arr) and !empty($spec_arr)){
                $arr = $this->get_goods_attr1($goods_id, $spec);
                $back_spec_arr = $spec_arr;
                $spec_arr_str = '';
                foreach ($arr as $k => $v){
                    $spec_arr_str .= $v['attr_name'] . ':' . $v['attr_value'] . ';';
                    foreach ($back_spec_arr as $kk => $vv){
                        if ($vv == $v['goods_attr_id']){
                            unset($back_spec_arr[$kk]);
                        }
                    }
                }       //需要进一步完善必选项
                if (!empty($back_spec_arr)){
                    $result['error']   = 8;
                    $result['message'] = '存在非法产品属性！';
                }else{
                    $result['error']   = 0;
                    $result['message'] = '产品属性没有问题！';
                }
            }
        }
        return $result;
    }

    /**
     * 获取品牌下所有产品
     * @param $id
     * @return mixed
     */
    public function get_brand_goods($id) {
        return M('goods')->where('is_on_sale=1 and is_check=0 and is_delete=0 and brand_id=' . $id)->select();
    }

    /**
     * 获取品牌信息
     * @param $id
     * @return mixed
     */
    public function get_brand_info($id) {
        return M('brand')->where('brand_id=' . $id)->find();
    }

    /**
     * 获取所有品牌列表
     * @return mixed
     */
    public function get_brand(){
        $m = M('Brand');
        $res = $m->alias('b')
                ->field('b.*,count(g.goods_id) as goods_count,bc.cat_name,bc.cat_img')
                ->join('LEFT JOIN (select * from __GOODS__ g where (is_on_sale=1 and is_check=0 and is_delete=0)) g ON g.brand_id=b.brand_id')
                ->join('LEFT JOIN __BRAND_CATE__ bc ON b.cat_id=bc.id')
                ->group('b.brand_id')
                ->where('b.is_show=1')
                ->order('sort_order')
                ->select();
        return $res;
    }

    /**
     * 加入购物车
     * @param int       $goods_id
     * @param int       $num
     * @param array     $spec
     * @param string    $spec_arr_str
     * @param int       $parent
     * @param int       $buynow
     * @param int       $act_id
     * @param int       $aid
     * @return bool
     */
    public function addto_cart($goods_id, $num = 1, $spec = array(), $spec_arr_str = '', $parent = 0, $buynow = 0, $act_id, $aid){
        $spec_arr = implode(',',$spec);
        $goods_info = $this
            ->alias('g')
            ->where("g.goods_id='$goods_id' and g.is_delete=0 and g.is_on_sale=1")
            ->field('g.goods_id,g.goods_name,g.goods_sn,g.market_price,g.deposit_price,g.shop_price,g.shop_price as goods_price,g.is_real,p.product_id,g.store_id,g.is_dealer,g.stock_price,g.integral')
            ->join('LEFT JOIN __PRODUCTS__ p on p.goods_id = g.goods_id')
            ->find();
        $product_info = M('products')->where("goods_id = '$goods_id'")->find();
        if (!$goods_info){
            $result = result_ajax(1,'该产品已经下架或不能加入购物车。');//array('error' => 1,'msg' =>  '该产品已经下架或不能加入购物车。');
        } else {
            $user_id = intval($this->login_info['user_id']);//exit($goods_info['is_dealer'] . '');
            if ($goods_info['is_dealer'] == 1 and !D('stock')->is_store($user_id) and isset($_GET['stock'])) {
                //$result = array('error' => 11,'msg' =>  '您没有开通店铺，此产品不能加入购物车。');
                $result = result_ajax(10,'您没有开通店铺，此产品不能加入购物车。');
            } else {
                $data = [
                    'user_id'       =>  $user_id,
                    'store_id'      =>  $goods_info['store_id'],
                    'goods_id'      =>  $goods_info['goods_id'],
                    'goods_sn'      =>  $goods_info['goods_sn'],
                    'goods_name'    =>  $goods_info['goods_name'],
                    'goods_number'  =>  $num,
                    'product_id'    =>  intval($product_info['product_id']),
                    'goods_attr'    =>  empty($spec_arr_str) ? '' : implode(',',$spec_arr_str) ,
                    'is_real'       =>  $goods_info['is_real'],
                    'is_gift'       =>  0,//$goods_info['is_dealer'],
                    'rec_type'      =>  CART_GENERAL_GOODS,
                    'parent_id'     =>  $parent,
                    'goods_attr_id' =>  $spec_arr,
                    'market_price'  =>  $goods_info['market_price'],
                ];
                if ($goods_info['is_dealer'] == GOODS_STOCK) {
                    if(isset($_GET['stock'])){
                        $data['market_price'] = $goods_info['goods_price'];
                        $data['goods_price'] = $goods_info['stock_price'];
                        $data['goods_price'] += $this->get_attr_price($spec);//添加产品属性价格
                    } else {//旗舰商品普通售卖
                        if($goods_info['goods_price'] == 0){
                            return result_ajax(11,'该商品不能当做普通商品售卖');
                        }
                        $data['market_price'] = $goods_info['market_price'];
                        $data['goods_price'] = $goods_info['goods_price'];
                        $data['goods_price'] += $this->get_attr_price($spec);//添加产品属性价格
                    }
                }

                //从接口获得商品活动价格
                $actData= curlpost('get_goods_act', array_merge(['goods_info'=>json_encode($goods_info), 'aid'=>AesEnCrypt($act_id)], C('API_APPINFO')));
                if($actData['status'] == 200){
                    $data['goods_price'] = $actData['data']['RMB'];
                    $data['integral'] = $actData['data']['WB'];
                    $data['extension_code'] = $aid; //添加活动id

                    if($data['integral'] > 0){
                        $data['integral'] += $this->get_attr_price($spec);
                    }else{
                        $data['goods_price'] += $this->get_attr_price($spec);//添加产品属性价格
                    }
                } else{
                    $data['goods_price'] = $goods_info['goods_price'];
                    $data['goods_price'] += $this->get_attr_price($spec);//添加产品属性价格
                }

                if(intval($goods_info['deposit_price']) > 0) {

                    $data['goods_price'] = $goods_info['deposit_price'];
                }



                $where = array(
                    'user_id'       =>  $user_id,
                    'store_id'      =>  $goods_info['store_id'],
                    'goods_id'      =>  $goods_id,
                    'goods_attr_id' =>  $spec_arr//array('in',$spec_arr)
                );

                if($aid > 0){$where['extension_code'] = $aid;}
                //检查该商品是否存在购物车
                $m = M('cart');
                $count = $m->where($where)->field('count(rec_id) as goods_count,rec_id,goods_number')->find();
                if ($buynow){
                    $s = $num;
                } else {
                    $s = $count['goods_number'] + $num;
                }
                $data['goods_number'] = $num;
                if ($count['goods_count'] > 0){
                    $m->where('rec_id=' . $count['rec_id'])->save(array('goods_number' => $s));
                    $result = result_ajax(0,'success',$count['rec_id']);
                } else {
                    $key = $value = [];
                    foreach ($data as $k => $v) {
                        $key[] = $k;
                        $value[] = $v;
                    }
                    $sql = "insert into `wp_cart` (`" . implode('`,`',$key) . "`)values(\"" . implode('","', $value) . "\")";
                    if ($m->execute($sql)){
                        $id = $m->getLastInsID();
                        $insert = array();
                           foreach ($spec as $v){
                            $insert[] = array('goods_attr_id' => $v,'rec_id'=>$id);
                        }
                        M('cart_attr')->addAll($insert);        //添加属性
                        $result = result_ajax(0,'success',$id);
                    }else{
                        $result = result_ajax(2,'加入购物车失败');
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 获取产品分类   //'index','stock','gift'
     * @param $field  string  分类类型
     * @return mixed|array
     */
    public function get_cate_list($field) {
        if (!in_array($field,['index','stock','gift'])) $field = 'index';
        $where = [
            'is_show'       =>  1,
            'is_' . $field  =>  1
        ];
        $res = M('category')->where($where)->order('parent_id desc')->select();
        foreach($res as $k => $v) {
            $res[$k]['cat_logo'] = img_url($v['cat_logo']);
            $res[$k]['stock_icon'] = img_url($v['stock_icon']);
            $res[$k]['stock_banner'] = img_url($v['stock_banner']);
        }
        return $res;
    }


    /**
     * 获取产品分类型表
     * @param int $is_dealer 是否经销商商城产品读取
     * @return mixed
     */
    public function cat_list_all($is_dealer = 0, $aid = ''){
        $ids = $this->get_sub_cat_ids(0);
        if(!$ids) return null;
        $m = M('category');
        //条件
        $map = array();
        $map['c.is_show'] = array('eq',1);
        $map['c.cat_id'] = array('in',$ids);
        switch($is_dealer){
            case 0 :
                $url = U('Fenlei/index').'?id=%d&normal';
                break;
            case 1 :
                $url = U('Fenlei/index').'?id=%d&stock';
                break;
            case 2 :
                $url = U('Gift/fenlei').'?id=%d&gift';
                break;
            case 3 :
                $url = U('Gift/fenlei').'?id=%d&aid='.AesEnCrypt($aid);
                break;
            default:
                $url = U('Fenlei/index').'?id=%d&normal';
        }
        $res = $m
            ->alias('c')
            ->field('c.*,count(goods.cat_id) AS goods_count,c1.cat_name parent_name')
            //->cache('cate_list', 3600)
            ->where($map)
            ->join('left join __CATEGORY__ c1 on c.parent_id=c1.cat_id')
            ->join('left join (SELECT g.goods_id,g.cat_id FROM __GOODS__ g WHERE (g.is_on_sale=1 and g.is_check=0 and g.is_delete=0) ) goods ON goods.cat_id=c.cat_id')
            ->group('c.cat_id')
            ->order('c.parent_id desc')
            ->select();
        foreach ($res as &$v) {
            $v['url'] = sprintf($url, $v['cat_id']);
            $v['cat_logo'] = $this->img_url($v['cat_logo']);
            $v['stock_icon'] = $this->img_url($v['stock_icon']);
            $v['stock_banner'] = $this->img_url($v['stock_banner']);
        }
        //print_r($res);
        return $res;
    }

    /**
     * 获取分类数量
     * @param $where
     * @return mixed
     */
    public function cat_num($where){
        return M('category')->where($where)->count();
    }

    /**
     * 获取产品相册
     * @param $goods_id
     * @return mixed
     */
    function get_GoodsGallery($goods_id) {
        $res = M('GoodsGallery')->where('goods_id=' . $goods_id)->order('`default` desc,`sort` desc')->select();
        foreach ($res as &$v) {
            $v['thumb_url'] = $this->img_url($v['thumb_url']);
            $v['img_original'] = $this->img_url($v['img_original']);
        }
        return $res;
    }

    /**
     * 获取产品类型
     * @param $goods_id
     * @return mixed
     */
    function get_goods_type($goods_id){
        $grp = M('GoodsType')
            ->alias('gt')
            ->join('LEFT JOIN __GOODS__ g ON gt.cat_id=g.goods_type')
            ->where('g.goods_id=' . $goods_id)
            ->getfield('attr_group');
        return $grp;
    }

    /**
     * 获取产品属性
     * @param $goods_id
     * @param int $attr_type
     * @return mixed
     */
    function get_goods_attr($goods_id, $attr_type = 0){
        $attr = '';
        if ($attr_type != 0){
            $attr = ' and a.attr_type != 0 ';
        }
        $res = M('goods_attr')
            ->alias('ga')
            ->field('a.attr_id, a.attr_name, a.attr_group, a.is_linked, a.attr_type,ga.goods_attr_id, ga.attr_value, ga.attr_price')
            ->join('LEFT JOIN __ATTRIBUTE__ a ON a.attr_id=ga.attr_id')
            ->where('ga.goods_id=' . $goods_id . $attr)
            ->order('a.sort_order, ga.attr_price, ga.goods_attr_id')
            ->select();
        return $res;
    }

    /**
     * 获取产品属性
     * @param $goods_id
     * @param $attr
     * @return mixed
     */
    function get_goods_attr1($goods_id, $attr){
        $arr = M('goods_attr')
            ->alias('ga')
            ->field('ga.goods_attr_id,ga.attr_value,a.attr_name')
            ->where('ga.goods_id=' . $goods_id . ' and ga.goods_attr_id in(' . $attr . ') and a.attr_type>0')
            ->group('ga.goods_attr_id')
            ->join('LEFT JOIN __ATTRIBUTE__ a ON ga.attr_id=a.attr_id')
            ->select();
        return $arr;
    }

    public function get_activity_rank($act_id){
        return M('FavourableActivityRank')->where(['act_id'=>$act_id])->select();
    }

    /**
     * 获取产品信息
     * @param $condition
     * @param string $field
     * @return mixed
     */
    public function get_GoodsInfo($condition, $field = 'g.*') {

        if(is_array($condition)){
            $condition['is_on_sale'] = array('eq',1);
            $condition['is_check'] = array('eq',0);
            $condition['is_delete'] = array('eq',0);
        }elseif(is_string($condition)){
            $condition .= ' AND is_delete=0 AND is_on_sale=1 AND is_check=0';
        }
        $res = $this
            ->alias('g')
            ->field($field . ',b.brand_name,b.brand_logo,s.store_name,ss.*,ga.id gid,ga.act_id aid,ga.config,fa.act_name,fa.act_starttime,fa.act_endtime,fa.discount,fa.act_config,fa.is_add')
            ->join('LEFT JOIN __BRAND__ b ON b.brand_id=g.brand_id')
            ->join('LEFT JOIN __STORE__ s ON g.store_id=s.store_id')
            ->join('LEFT JOIN __STORE_SETTING__ ss ON g.store_id=ss.store_id')
            ->join('LEFT JOIN __GOODS_ACTIVITY__ ga on g.goods_id=ga.goods_id')
            ->join('LEFT JOIN __FAVOURABLE_ACTIVITY__ fa on ga.act_id=fa.act_id')
            ->where($condition)
            ->find();

        if ($res) {
            $res['url'] = U('goods/index') . '?id=' . $res['goods_id'];
        }
        return $res;
    }

    /**
     * 按产品ID取产品信息
     * @param $goods_id
     * @return mixed
     */
    public function get_GoodsInfoBYid($goods_id) {
        return $this->get_GoodsInfo('g.goods_id=' . $goods_id);
    }

    /**
     * 根据店铺自主分类读取所有产品
     * @param $store_id int  店铺ID
     * @param $cat int | array 店铺分类
     * @param $pagesize int 每页显示数量
     * @param $rank int 当前用户权限
     * @return array
     */
    public function getStoreGoodsForCat($store_id, $cat, $pagesize = 5, $rank = 0){
        $store_id = intval($store_id);
        if ($store_id <= 0) return [];
        $where = $this->goods_where;
        $where['store_id'] = $store_id;
        $where['user_rank'] = ['elt',$rank];

        if ($cat) {
            if (is_array($cat)) {
                $where['seller_cate_id'] = ['IN',$cat];
            } elseif (is_numeric($cat)) {
                $where['seller_cate_id'] = $cat;
            } else {
                return [];
            }
        }

        $count = M('goods')->where($where)->count();
        $Page = new \Think\Page($count, $pagesize);
        $Page->show();
        $res = M('goods')
            ->where($where)
            ->limit($Page->firstRow,$Page->listRows)
            ->order('sort_order')
            ->select();
        $res = $this->convertGoodsActivityList($res);
//        for($i = 0;$i<200; $i++){
//            $data[] = $res[0];
//        }
//        $Page = new \Think\Page(count($data), 10);
//        $res = array_slice($data, $Page->firstRow, $Page->listRows);
        $curpage = empty($_GET['p']) ? 1 : max(intval($_GET['p']),1);
        $total_page = $Page->totalPages ? $Page->totalPages : 1;

        return array('str'=>$res, 'pagecount'=>$total_page, 'curpage'=>$curpage);
    }


    /**
     * 获取店铺产品
     * @param $id  int 店铺 ID
     * @param int $pagesize   每页显示数量
     * @param int $user_rank   用户权限
     * @return bool|mixed
     */
    public function get_store_goods($id, $pagesize = 5, $user_rank = 0) {
        $map = $this->goods_where;
//        $map['shop_price'] = array('neq',0);//店铺内商品价格为0不显示
//        $map['user_rank'] = ['elt',$user_rank];
        if (is_array($id) and !empty($id)) {
            $map['store_id'] = array('in',$id);
        } elseif (is_numeric($id) and intval($id) > 0) {
            $map['store_id'] = array('eq',$id);
        } else {
            return false;
        }
        $map['is_delete'] = 0;
        $map['is_on_sale'] = 1;
//        $map['shop_price'] = array('neq',0);
//        $where = array();
//        $where =
//        $where = 'is_on_sale=1 and is_check=0 and is_delete=0 and is_dealer='.intval($is_dealer);
//        if (is_array($id) and !empty($id)) {
//            $where .= ' and ' . $this->db_create_in($id,'store_id');
//        } elseif (is_numeric($id) and intval($id) > 0) {
//            $where .= ' and store_id=' . $id;
//        } else {
//            return false;
//        }
        $where = $map;
        $counts = M('goods')->where($where)->count();
        $page = new Page($counts,$pagesize);
        $res = M('goods')       //上线销售和独立销售的产品
            ->where($where)
            ->limit($page->firstRow,$page->listRows)
            ->select();
        $res = $this->convertGoodsActivityList($res,$user_rank);
        return array('goods_list'=>$res,'page'=>$page->show(),'total'=>$page->totalPages);
    }

    /**
     * 获取店铺自定类型商品
     * @param $id
     * @param $type
     * @param $row
     * @return array
     */
    public function get_type_goods($id, $type,$row = 4){
        $map = array();
        if(is_array($id)){
            $map['store_id'] = array('in',$id);
        }elseif (is_numeric($id) and intval($id) > 0) {
            $map['store_id'] = array('eq',$id);
        } else {
            return false;
        }

        $map['shop_price'] = array('neq',0);

        switch ($type){
            case 'new':
                $order = 'store_id DESC';
                break;
            case 'hot':
                $map['is_hot'] = array('eq', 1);
                break;
            default:
                $order = 'store_id ASC';
                break;
        }

        $where = $map;

        $res = M('goods')->where($where)->limit($row)->order($order)->select();
        foreach ($res as $k => $v) {
            $res[$k]['url'] = U('Goods/index') . '?id=' . $v['goods_id'];
            $res[$k]['goods_thumb'] = $this->img_url($res[$k]['goods_thumb']);
            $res[$k]['goods_img'] = $this->img_url($res[$k]['goods_img']);
            $res[$k]['original_img'] = $this->img_url($res[$k]['original_img']);
        }
        return $res;
    }


    /**
     * 根据获取的活动产品ID与分类ID获取相关产品
     * @param $goods_ids
     * @param $cate_ids
     * @param $order
     * @return array
     */
    public function get_goods_list_by_IDCATE($goods_ids, $cate_ids = '',$order='g.goods_id DESC'){
        $map = $this->goods_where;
        $map['g.goods_id'] = array('in',$goods_ids)/* = [
            'is_on_sale'    => array('eq',1),
            'is_alone_sale' => array('eq',1),
            'is_check'      => array('eq',0),
            'is_delete'     => array('eq',0),
            'goods_id'      => array('in',$goods_ids),
        ]*/;
        if(!empty($cate_ids)) $map['cat_id'] = array('in',$cate_ids);
        $res = M('goods')->alias('g')
            ->field('g.*')
            ->join('LEFT JOIN __GOODS_ACTIVITY__ ga on g.goods_id=ga.goods_id')
            ->where($map)->order($order)->select();
        return $this->replace_goods_img('goods', $res, 'goods_id');
    }


    /**
     * 按条件，每个店铺取几个商品
     * @param $id
     * @param int $count
     * @param int $rank
     * @param string $order_column
     * @param string $group_column
     * @return bool|mixed
     */
    public function get_store_goods1($id, $count = 5, $rank = 0, $order_column="goods_id",$group_column="store_id") {
        $map = $this->goods_where;
        $map['shop_price'] = array('neq',0);
        /*$map['is_on_sale'] = array('eq',1);
        $map['is_check'] = array('eq',0);
        $map['is_delete'] = array('eq',0);*/
        if (is_array($id) and !empty($id)) {
            $map['g.store_id'] = array('in',$id);
        } elseif (is_numeric($id) and intval($id) > 0) {
            $map['g.store_id'] = array('eq',$id);
        } else {
            return false;
        }
        $view = $this->alias('g')->field('g.*')
            //->join('left join __STORE_IMAGES__ img on g.store_id=img.store_id')
            ->where($map)->select(false);
        $sql = <<<SQL
                SELECT
                  *
                FROM
                   ($view)  main
                WHERE
                  (SELECT COUNT(1)
                   FROM  ($view)  sub
                 WHERE 
                   main.$group_column = sub.$group_column
                     AND
                main.$order_column < sub.$order_column
                  ) < $count;
SQL;
        $res = $this->query($sql);
        return $this->convertGoodsActivityList($res,$rank);
    }

    /**
     * 根据坐标获取附近的店铺列表
     * @param array $start
     * @param string $fields
     * @param int $pagesize
     * @return array
     */
    public function get_nearby_store($start, $fields = 's.*',$pagesize = 5){
        $m = M("store");
        $map = [
//            'lat' => [
//                ['gt',$start['right-bottom']['lat']],
//                ['lt', $start['left-top']['lat']],
//
//            ],
//            'lng'=>[
//                ['gt',$start['left-top']['lng']],
//                ['lt', $start['right-bottom']['lng']]
//            ],
//            's.store_id'  => ['in', '92,95,97,107,115,116,117,130,131,135,138,140,141,142'],
            'store_state' => ['eq',STORE_OPEN]
        ];

        //取得有效商品sql
        $map_goods = array();
        $map_goods['is_on_sale'] = array('eq',1);
        $map_goods['is_check'] = array('eq',0);
        $map_goods['is_delete'] = array('eq',0);
        $map_goods['shop_price'] = array('neq',0);
        $valid_goods = M('goods')->where($map_goods)->select(false);

        $count = $m->alias('s')
                    ->join(' __STORE_BUSINESS__ sb ON s.store_id=sb.store_id')
                    ->where($map)->count();

        $Page = new \Think\Page($count, $pagesize);
        $show = $Page->show();
        $res = $m->alias('s')
            ->where($map)
            ->join('LEFT JOIN ('.$valid_goods .') g ON s.store_id=g.store_id')
            ->join(' __STORE_BUSINESS__ sb ON s.store_id=sb.store_id')
            ->join('LEFT JOIN __STORE_IMAGES__ i ON s.store_id=i.store_id')
            ->join('LEFT JOIN __STORE_CREDIT__ sc ON s.store_id=sc.store_id')
            ->join('LEFT JOIN __ORDER_INFO__ oi ON s.store_id=oi.store_id')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->group('s.store_id')
            ->field($fields . ',count(g.goods_id) as goods_count ,i.*, lng, lat, sc.store_sales')
            ->order('s.store_sort desc, goods_count desc')
            ->select();
        $ids = array();

        //获取销量查询时间
        $time = time();
        $date = $time - 30*24*60*60;
        $order_map = ['pay_time' => [
                        ['gt',$date],
                        ['elt',$time]
                    ]];
//        $location = session('location');

        foreach ($res as &$v) {
            $city = $district ='';
//            $region_map['region_id'] = array('in',[$v['city'],$v['district']]);
//            $region = M('Region')->where($region_map)->select();
//            foreach ($region as $ard){
//                if($ard['region_type'] == 2){
//                    $city = $ard['region_name'];
//                }elseif($ard['region_type'] == 3){
//                    $district = $ard['region_name'];
//                }
//            }
            $v['address'] = $city ? $city . '&nbsp;|&nbsp;' . $district : '';
            $ids[] = $v['store_id'];
            $v['url'] = U('store/info?id=' . $v['store_id']);
            $v['store_label'] = $this->img_url($v['store_label']);
            $v['store_banner'] = $this->img_url($v['store_banner']);
            $v['store_avatar'] = $this->img_url($v['store_avatar']);
            $order_map['store_id'] = $v['store_id'];
            //$v['order_sales'] = M('OrderInfo')->where($order_map)->count();
            //$v['distance'] = get_distance($v['lng'],$v['lat'],$location['lng'],$location['lat']);
        }
        $curpage = empty($_GET['p']) ? 1 : max(intval($_GET['p']),1);
        return array('list'=>$res,'show'=>$show,'total'=>$Page->totalPages,'ids'=>$ids,'count'=>$count, 'curpage'=>$curpage);
    }

    /**
     * 获取店铺列表
     * @param string $fields
     * @param int $pagesize
     * @return array
     */
    public function get_store($keywords="",$fields = 's.*',$pagesize = 10){
        $m = M("store");

        //取得有效商品sql
        $map_goods = array();
        $map_goods['is_on_sale'] = array('eq',1);
        $map_goods['is_check'] = array('eq',0);
        $map_goods['is_delete'] = array('eq',0);
        $map_goods['shop_price'] = array('neq',0);
        $valid_goods = M('goods')->where($map_goods)->select(false);
        //取得有效店铺条件
        $map = array();
        $map['s.store_state'] = array('eq',STORE_OPEN);
        if($keywords!==""&&trim($keywords)){
            $map['s.store_name'] = array('like','%'.$keywords.'%');
        }
        $Page = new \Think\Page($count = $m->alias('s')->where($map)->count(), $pagesize);
        $show = $Page->show();
        $res = $m->alias('s')
            ->where($map)
            ->join('LEFT JOIN ('.$valid_goods .') g ON s.store_id=g.store_id')
            ->join('LEFT JOIN __STORE_IMAGES__ i ON s.store_id=i.store_id')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->group('s.store_id')
            ->field($fields . ',count(g.goods_id) as goods_count ,i.*')
            ->order('s.store_id desc')
            ->select();
        $ids = array();
        foreach ($res as &$v) {
            $ids[] = $v['store_id'];
            $v['url'] = U('store/info?id=' . $v['store_id']);
            $v['store_label'] = $this->img_url($v['store_label']);
            $v['store_banner'] = $this->img_url($v['store_banner']);
            $v['store_avatar'] = $this->img_url($v['store_avatar']);
        }
        return array('list'=>$res,'show'=>$show,'total'=>$Page->totalPages,'ids'=>$ids,'count'=>$count);
    }

    public function goods_list_on_kp() {

    }

    /**
     * 获取产品列表（分页）
     * @param $id
     * @param int $max
     * @param int $is_dealer
     * @param string $sort
     * @param string $order
     * @param $keywords string
     * @param $type int
     * @param $rank int
     * @return array
     */
    public function goods_list($id, $max = 10, $is_dealer = 0,$sort = 'add_time', $order = 'desc',$keywords="",$type=1,$goods_id=0,$rank=0) {
        $m = M('goods');
        $is_dealer = in_array($is_dealer,[0,1,2]) ? $is_dealer : 0;
        //条件
        $map = array();
        $map['is_delete'] = array('eq',0);
        $map['is_on_sale'] = array('eq',1);
        $map['is_check'] = array('eq',0);
        $map['user_rank'] = ['elt',$rank];
        if($is_dealer==0){//旗舰商品会出现在普通商品列表
            //shop_price==0 则不显示
            $map['shop_price'] = array('neq',0);
            $map['is_dealer'] = array(
                array('eq',1),
                array('eq',0),
                array('OR')
            );
        }else{
            $map['is_dealer'] = array('eq',$is_dealer);
        }

        if($goods_id and is_array($goods_id)){
            $map['goods_id'] = array('in',$goods_id);
        }

        if (is_array($id)) {
            $map['_string'] = $this->db_create_in($id,'g.cat_id');
        }else if(intval($id)>0){
            $map['g.cat_id'] = array('eq',$id);
        }

        if($keywords){//搜索
            $column = $this->get_search_column($type);
            $map[$column['key']] = array("like",'%'.$keywords.'%');
        }
        $count      = $m->alias('g')->where($map)->count();// 查询满足要求的总记录数
        $Page       = new \Think\Page($count, $max);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $Page->show();// 分页显示输出
        if ($sort) $sort = ',' . $sort;
        $list = $m->alias('g')
            ->field('g.*,si.store_label,b.brand_name,b.brand_logo,c.cat_name')
            ->where($map)
            ->join('LEFT JOIN __STORE_IMAGES__ si ON g.store_id=si.store_id')
            ->join('LEFT JOIN __STORE_CREDIT__ sc ON g.store_id=sc.store_id')
            ->join('LEFT JOIN __BRAND__ b ON g.brand_id=b.brand_id')
            ->join('LEFT JOIN __CATEGORY__ c ON g.cat_id=c.cat_id')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('g.sort_order ' . $sort . ' '. $order)
            ->select();


        foreach ($list as &$v) {
            $v['goods_thumb']   = $this->img_url($v['goods_thumb']);
            $v['url']           = U('Goods/index') . '?id=' . $v['goods_id'] . '&store_token='.$this->store_token;
            $v['brand_logo']    = $this->img_url($v['brand_logo']);
        }
        $curpage = empty($_GET['p']) ? 1 : max(intval($_GET['p']),1);
        $data =  array('goods_list'=>$list,'page'=>$show,'curpage'=>$curpage,'total'=>$Page->totalPages);
        return $data;
    }

    /**
     * 获取产品列表（分页）
     * @param $id
     * @param int $max
     * @param int $act_id
     * @param string $order
     * @param $keywords string
     * @param $type int
     * @param $rank int
     * @return array
     */
    public function act_goods_list($id, $act_id, $sort = 'add_time', $order = 'desc', $keywords="", $type=1, $max = 10) {
        $m = M('goods');
        $map = $this->goods_where;

        if (is_array($id)) {
            $map['_string'] = $this->db_create_in($id,'g.cat_id');
        }else if(intval($id)>0){
            $map['g.cat_id'] = array('eq',$id);
        }

        if($keywords){
            $column = $this->get_search_column($type);
            $map[$column['key']] = array("like",'%'.$keywords.'%');
        }
        $map['ga.act_id'] = $act_id;
        $count      = $m->alias('g')
            ->join(' __GOODS_ACTIVITY__ ga ON g.goods_id=ga.goods_id')
            ->where($map)->count();
        $Page       = new \Think\Page($count, $max);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $Page->show();// 分页显示输出
        if ($sort) $sort = ',' . $sort;
        $list = $m->alias('g')
            ->field('g.*,si.store_label,b.brand_name,b.brand_logo,c.cat_name, ga.*, ga.id aid')
            ->where($map)
            ->join(' __GOODS_ACTIVITY__ ga ON g.goods_id=ga.goods_id')
            ->join('LEFT JOIN __STORE_IMAGES__ si ON g.store_id=si.store_id')
            ->join('LEFT JOIN __STORE_CREDIT__ sc ON g.store_id=sc.store_id')
            ->join('LEFT JOIN __BRAND__ b ON g.brand_id=b.brand_id')
            ->join('LEFT JOIN __CATEGORY__ c ON g.cat_id=c.cat_id')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('ga.recommend ' . $sort . ' '. $order)
            ->select();


        foreach ($list as &$v) {
            $v['goods_thumb']   = $this->img_url($v['goods_thumb']);
            $v['url']           = U('Goods/index') . '?id=' . $v['goods_id'];
            $v['brand_logo']    = $this->img_url($v['brand_logo']);
        }
        $curpage = empty($_GET['p']) ? 1 : max(intval($_GET['p']),1);
        $data =  array('goods_list'=>$list,'page'=>$show,'curpage'=>$curpage,'total'=>$Page->totalPages);
        return $data;
    }

    /**
     * 通过type获取搜索字段
     * @param $type
     */
    public function get_search_column($type){
        $keys = array(
            1=>array('key'=>'g.goods_name','name'=>"商品名称")
        );
        return $keys[$type];

    }

    /**
     * 获取顶级分类分组的前几条指定数据，如获取每个顶级分类最新的4个产品
     *
     */
    public function get_each_cate_goods($order='add_time desc',$num=4,$is_dealer=0){
        $topcats = $this->get_sub_cat_ids(0,false);
        $data =array();
        foreach($topcats as $cat){
            $map = array();
            $map['is_delete'] = array('eq',0);
            $map['is_on_sale'] = array('eq',1);
            $map['is_check'] = array('eq',0);
            $map['is_dealer'] = array('eq',$is_dealer);
            $arr = $this->get_sub_cat_ids($cat);
            $arr[] = $cat;
            $map['cat_id'] = array('in',$arr);
            $d = array();
            $d['goods_list']= $this->where($map)->limit($num)->order($order)->select();
            foreach($d['goods_list'] as &$dd){
                $dd['shop_price'] = sprintf('%1.2f',$dd['shop_price']);
                $dd['stock_price'] = sprintf('%1.2f',$dd['stock_price']);
                $tpl = "%s?id=%d%s";
                switch($is_dealer){
                    case 0: $goods_url = sprintf($tpl,  U('Goods/index'),$dd['goods_id'],'&normal');break;
                    case 1: $goods_url = sprintf($tpl, U('Goods/index'),$dd['goods_id'],'&stock');break;
                    case 2: $goods_url = sprintf($tpl, U('Goods/index'),$dd['goods_id'],'&gift');break;
                    default:return false;
                }
                $dd['url'] = $goods_url;
            }
            $d['cat_info'] = M('category')->where('cat_id=%d',$cat)->find();
            $tpl = "%s?id=%d%s";
            switch($is_dealer){
                case 0: $cat_url = sprintf($tpl,  U('Goods/index'),$cat,'&normal');break;
                case 1: $cat_url = sprintf($tpl, U('fenlei'),$cat,'&stock');break;
                case 2: $cat_url = sprintf($tpl, U('Gift/fenlei'),$cat,'&gift');break;
                default:return false;
            }
            $d['cat_info']['cat_logo'] = $this->img_url($d['cat_info']['cat_logo']);
            $d['cat_info']['stock_icon'] = $this->img_url($d['cat_info']['stock_icon']);
            $d['cat_info']['stock_banner'] = $this->img_url($d['cat_info']['stock_banner']);
            $d['cat_info']['url']= $cat_url;

            $data[] = $d;

        }
        return $data;

    }

    /**
     * 递归获取子分类id
     * @param $cat_id
     * @param bool $is_recursion 是否递归获取
     * @param array $cat_ids 容器
     * @return array
     */
//    public function get_sub_cat_id1($cat_id,$is_recursion=true,&$cat_ids=array()){
//        $map = array();
//        $map['parent_id'] = array('eq',$cat_id);
//        switch (session('is_dealer')){
//            case 0:$map['is_index'] = array('eq',1);break;
//            case 1:$map['is_stock'] = array('eq',1);break;
//            case 2:$map['is_gift'] = array('eq',1);break;
//            default:$map['is_index'] = array('eq',1);break;
//        }
//        $subs = M('category')->field('cat_id')->where($map)->select();
//        if($subs){
//            $subs = array_map('array_shift',$subs);
//            sort($subs);
//            if(!$is_recursion){
//                return $subs;
//            }
//            if(is_array($subs)){
//                $cat_ids = array_merge($cat_ids,$subs);
//                foreach($subs as $sub){
//                    $this->get_sub_cat_ids($sub,true,$cat_ids);
//                }
//            }
//        }
//        return $cat_ids;
//    }

    public function get_sub_cat_ids($cat_id,$is_recursion=true,&$cat_ids = array()){
        static $cat_info = array();
        if(!$cat_info){
            $cat_info = M('category')->field('cat_id,parent_id,is_index,is_gift,is_stock')->select();
            if(!$cat_info) return null;
        }
        $subs = array();//装子id的
        foreach($cat_info as $c){
                switch (session('is_dealer')){
                    case 0:$condition = $c['is_index']==1&&$c['parent_id']==$cat_id;break;
                    case 1:$condition = $c['is_stock']==1&&$c['parent_id']==$cat_id;break;
                    case 2:$condition = $c['is_gift']==1&&$c['parent_id']==$cat_id;break;
                    default:$condition = $c['is_index']==1&&$c['parent_id']==$cat_id;
                }
            if($condition) {
                $subs[] = $c['cat_id'];
            }
        }
        if(!$is_recursion) {
            return $subs;
        }
        if(is_array($subs)){
            $cat_ids = array_merge($cat_ids,$subs);
            foreach($subs as $sub){
                $this->get_sub_cat_ids($sub,true,$cat_ids);
            }
        }
        return $cat_ids;
    }

    /**
     * 获取活动商品权限
     * @param $act_id
     * @param $type
     * @return array
     */
    public function get_type_activity_rank($act_id, $type){
        return M('FavourableActivityRank')->where(['act_id'=>$act_id, 'type'=>$type])->find();
    }

    //作废.....
    public function convertGoodsActivityList_back($goods,$user_rank=0){
        /* @var Goods $g */
        $g = Element::createObject('goods');
        foreach($goods as $key => &$each){
            $g->setData($each);
            $act = $g->getActivityList();
            if($act){
                /* @var Activity $act */
                $act = reset($act);
                $each['act_type']   = $act->data['act_type'];
                $each['act_name']   = $act->data['act_name'];
                $each['act_config'] = $act->config;
                $each['act_data']   = $act->data;
                $each['act_price']  = $act->price()->RMB;
                $each['act_format_price'] = (string)$act->price();
                $each['show_price'] = $act->price()->WB;
                $auth_user = $act->getAuthUser();
                $map = array('goods_id'=>$each['goods_id'], 'act_id'=>$act->data['act_id']);
                $aid = M('GoodsActivity')->where($map)->getField('id');
                $each['url'] = U('Goods/index') . '?id=' . $each['goods_id'] . '&aid=' . AesEnCrypt($aid);
                //判断活动权限
                if(!empty($auth_user) && !in_array($user_rank,$auth_user)) unset($goods[$key]);
            }else{
                $each['act_type'] = null;
                $each['act_format_price'] = '￥' . price_format($each['shop_price']);
                if($each['shop_price'] > 9999)
                    $each['act_format_price'] = sprintf('¥%.2f万元',$each['shop_price']/10000);
                $each['url'] = U('Goods/index') . '?id=' . $each['goods_id'];
                //普通商品判断商品权限
                if($each['user_rank'] > $user_rank) unset($goods[$key]);
            }
            $each['goods_thumb'] = img_url($each['goods_thumb']);
            $each['goods_img'] = img_url($each['goods_img']);
            $each['original_img'] = img_url($each['original_img']);
        }
        return $goods;
    }

    public function convertGoodsActivityList($goods,$user_rank=0){
        //从接口获取活动数据
        $result= curlpost('get_goods_list', array_merge(['goods_list'=>json_encode($goods)], C('API_APPINFO')));

        if($result['status'] != 200) return array();
        foreach($goods as $key => &$each){
            //判断商品是否有活动
            if(isset($result['data'][$each['goods_id']])){
                $act = $result['data'][$each['goods_id']];
                $each['act_type']   = $act['act_data']['act_type'];
                $each['act_name']   = $act['act_data']['act_name'];
                $each['act_config'] = $act['act_config'];
                $each['act_data']   = $act['act_data'];
                $each['act_price']  = $act['act_price'];
                $each['act_format_price'] = $act['act_format_price'];
                $each['show_price'] = $act['show_price'];
                $auth_user = $act['auth_user'];
                $map = array('goods_id'=>$each['goods_id'], 'act_id'=>$act['act_data']['act_id']);
                $aid = M('GoodsActivity')->where($map)->getField('id');
                $each['url'] = U('Goods/index') . '?id=' . $each['goods_id'] . '&aid=' . AesEnCrypt($aid) . '&store_token='.$this->store_token;
                //判断活动权限
                if(!empty($auth_user) && !in_array($user_rank,$auth_user)) unset($goods[$key]);
            }else{
                $each['act_type'] = null;
                $each['act_format_price'] = '￥' . price_format($each['shop_price']);
                if($each['shop_price'] > 9999)
                    $each['act_format_price'] = sprintf('¥%.2f万元',$each['shop_price']/10000);
                $each['url'] = U('Goods/index' ) . '?id=' . $each['goods_id'] . '&store_token='.$this->store_token;
                //普通商品判断商品权限
                if($each['user_rank'] > $user_rank) unset($goods[$key]);
            }
            $each['goods_thumb'] = img_url($each['goods_thumb']);
            $each['goods_img'] = img_url($each['goods_img']);
            $each['original_img'] = img_url($each['original_img']);
        }
        return $goods;
    }



    /**
     * 对产品列表进行批量活动读取
     * @param $goods
     * @return mixed
     */
    public function check_active_goods($goods){
        if ($goods and !is_array($goods)) return [];
        /* @var Goods $actData */
        $actData = Element::createObject('goods', ['']);




        foreach ($goods as  &$v){
            $actData->setData($v);

            //$act = $actData->activityList;
            /*$actData = Element::createObject('goods', array($goods_info['goods_id'], $goods_info));
            $act = $actData->activityList[$goods_info['aid']];*/

            //$g->setData($each);

            $x = $actData->getActivityArray();


            $v['goods_thumb']   = img_url($v['goods_thumb']);
            $v['url']           = U('Goods/index') . '?id=' . $v['goods_id'] . '&aid=' . AesEnCrypt($v['aid']);
            $v['brand_logo']    = img_url($v['brand_logo']);

            if ($act) {

                foreach($act as $each){
                    /* @var Activity $each */
                    $x=  $each->data;
                }

                //$v['goods_price_format'] = (string)$act->price();
            }else{
                //$v['goods_price_format'] = $this->price_format($v['goods_price']);
            }
        }
        return $goods;
    }

    public function index1(){

        $goods = M('goods')->where(array('goods_id'=>array('in', array(407,408,409))))->select();
        /* @var Goods $g */
        $g = Element::createObject('goods');
        foreach($goods as $each){

            $g->setData($each);

            $x = $g->getActivityArray();

        }


    }


    /**
     * 随机获取活动商品
     * @param int $num
     * @param int $save_time
     * @param int $store_id
     * @return mixed
     */
    public function get_rand_act_goods($num=4,$save_time=1800, $store_id=0, $rank=0){

        //if($list = S('rand_goods')) return $list;
        $time = time();
        $where = [
            'act_starttime' =>  ['ELT',$time],
            '_string'       =>  'act_endtime=0 or act_endtime>='.$time,
            'act_id'        =>  ['NOT IN',[77]],
        ];
        $map = 'shop_price > 0 AND is_delete=0 AND is_on_sale=1 AND is_check=0 AND user_rank=0 AND ';
        if($store_id) $map .= "store_id={$store_id} AND ";
        $list = M('GoodsActivity')->alias('ga')
            ->field('ga.*,ga.id aid,g.*')
            ->where($map.'act_id IN ' . M('FavourableActivity')
                    ->field('act_id')
                    ->where($where)
                    ->buildSql())
            ->join('LEFT JOIN __GOODS__ g ON g.goods_id=ga.goods_id')
            ->group()->limit($num)->order('rand()')->select();

        $result= curlpost('get_goods_list', array_merge(['goods_list'=>json_encode($list)], C('API_APPINFO')));

        if($result['status'] != 200) return array();

        foreach ($list as  &$v){
            $v['goods_thumb']   = $this->img_url($v['goods_thumb']);
            $v['url']           = U('Goods/index') . '?id=' . $v['goods_id'] . '&aid=' . AesEnCrypt($v['aid']) . '&store_token='.$this->store_token;
            $v['brand_logo']    = $this->img_url($v['brand_logo']);

            if(isset($result['data'][$v['goods_id']])){
                $act = $result['data'][$v['goods_id']];
                $v['act_type']   = $act['act_data']['act_type'];
                $v['act_name']   = $act['act_data']['act_name'];
                $v['act_price']  = $act['act_price'];
                $v['act_config'] = $act['config'];
                $v['act_format_price'] = $act['act_format_price'];
                $v['show_price'] = $act['show_price'];
            }else{
                $v['goods_price_format'] = $this->price_format($v['goods_price']);
            }
        }
  
        //S('rand_goods',$list,$save_time);
        return $list;
    }

    /**
     * 获取店铺的活动商品
     * @param int $store
     *
     */
    public function get_store_act_goods($id,$is_dealer=0,$pagesize = 5, $order_column="goods_id",$group_column="store_id",$rank=1){
        $map = $this->goods_where;
        if(is_array($is_dealer)){
            $map['is_dealer'] = array('in',$is_dealer);
        }else{
            $map['is_dealer'] = array('eq',$is_dealer);
        }
//        $map['shop_price'] = array('gt',0);
//        $map['user_rank'] = array('elt',$rank);

        if (is_array($id) and !empty($id)) {
            $map['g.store_id'] = array('in',$id);
        } elseif (is_numeric($id) and intval($id) > 0) {
            $map['g.store_id'] = array('eq',$id);
        } else {
            return false;
        }
        $counts = $this->alias('g')->field('g.*,ga.act_id act_id, ga.id aid')
            ->join(' __GOODS_ACTIVITY__ ga on g.goods_id=ga.goods_id')
            ->where($map)->count();
        $page = new Page($counts,$pagesize);

        $view = $this->alias('g')->field('g.*,ga.act_id act_id, ga.id aid')
            //->join('left join __STORE_IMAGES__ img on g.store_id=img.store_id')
                ->join(' __GOODS_ACTIVITY__ ga on g.goods_id=ga.goods_id')
                ->limit($page->firstRow,$page->listRows)
                ->where($map)->select(false);


        $sql = <<<SQL
                SELECT
                  *
                FROM
                   ($view)  main
                WHERE
                  (SELECT COUNT(1)
                   FROM  ($view)  sub
                 WHERE 
                   main.$group_column = sub.$group_column
                     AND
                main.$order_column < sub.$order_column
                  ) < $pagesize;
SQL;
        $res = $this->query($sql);

        $res = $this->convertGoodsActivityList($res,$rank);
        return array('goods_list'=>$res,'page'=>$page->show(),'total'=>$page->totalPages);
    }

    /**
     * 获取首页店铺的活动商品
     * @param int $store
     */
    public function get_index_store_goods($id,$is_dealer=0, $order_column="goods_id",$group_column="store_id",$rank=0){
        $map = $this->goods_where;
        if(is_array($is_dealer)){
            $map['is_dealer'] = array('in',$is_dealer);
        }else{
            $map['is_dealer'] = array('eq',$is_dealer);
        }

        if (is_array($id) and !empty($id)) {
            $map['g.store_id'] = array('in',$id);
        } elseif (is_numeric($id) and intval($id) > 0) {
            $map['g.store_id'] = array('eq',$id);
        } else {
            return false;
        }
        $counts = $this->alias('g')->field('g.*,ga.act_id act_id, ga.id aid')
            ->join(' __GOODS_ACTIVITY__ ga on g.goods_id=ga.goods_id')
            ->where($map)->count();

        $view = $this->alias('g')->field('g.*,ga.act_id act_id, ga.id aid')
            //->join('left join __STORE_IMAGES__ img on g.store_id=img.store_id')
            ->join(' __GOODS_ACTIVITY__ ga on g.goods_id=ga.goods_id')
            ->where($map)->select(false);


        $sql = <<<SQL
                SELECT
                  *
                FROM
                   ($view)  main
                WHERE
                  (SELECT COUNT(1)
                   FROM  ($view)  sub
                 WHERE 
                   main.$group_column = sub.$group_column
                     AND
                main.$order_column < sub.$order_column
                  ) < $counts;
SQL;
        $res = $this->query($sql);

        $res = $this->convertGoodsActivityList($res,$rank);
        return $res;
    }

    public function index_recommend_goods(){
        $map['fa.act_type'] = ['IN','kp,discount,panic'];
        $activity = M('FavourableActivity')->alias('fa')
            ->field('fa.*, far.id user_rank')
            ->join('LEFT JOIN __FAVOURABLE_ACTIVITY_RANK__ far on fa.act_id=far.act_id AND far.type='.FAR_USER_RANK)
            ->where($map)
            ->select();

        $act_ids = [];
        $endtime = 0;
        $time = time();
        foreach ($activity as $v){
            if(intval($v['user_rank']) <= 1){
                $activityList[$v['act_id']] = $v;
                $act_endtime[$v['act_id']] = $v['act_endtime'];
                $act_ids[] = $v['act_id'];
            }

            //设置最快过期的活动
            if($v['act_endtime'] > 0){
                $all_endtime[$v['act_endtime']] = $v;
                $act_time[$v['act_id']] = $v['act_endtime'];
                $endtime = min($act_time);
                $soon_act = $all_endtime[$endtime];
            }
        }

        $where = $this->goods_where;
        $where['recommend'] = ['EQ',1];
        $where['act_id']    = ['IN',$act_ids];
        $goods_list = $this->alias('g')
                ->field('g.*, ga.act_id, ga.id aid')
                ->join(' __GOODS_ACTIVITY__ ga on g.goods_id=ga.goods_id')
                ->where($where)
                ->select();//获取活动商品

        $result= curlpost('get_goods_list', array_merge(['goods_list'=>json_encode($goods_list)], C('API_APPINFO')));

        $data = $act_goods_list = [];
        foreach ($goods_list as &$goods) {
            //print_r($result['data'][$goods['goods_id']]);exit;
            if($act = $result['data'][$goods['goods_id']]) {
                $goods['act_type'] = $act['act_data']['act_type'];
                $goods['act_name'] = $act['act_data']['act_name'];
                $goods['act_price'] = $act['act_price'];
                $goods['act_config'] = $act['config'];
                $goods['act_starttime'] = $act['act_data']['act_starttime'];
                $goods['act_endtime'] = $act['act_data']['act_endtime'];
                $goods['act_format_price'] = $act['act_format_price'];
                $goods['url'] = U('goods/index') . '?id=' . $goods['goods_id'] . '&aid=' . AesEnCrypt($goods['aid']);
                $goods['goods_thumb'] = img_url($goods['goods_thumb']);
                $goods['goods_img'] = img_url($goods['goods_img']);

                //设置首页需要展现的活动商品
                switch ($act['act_data']['act_type']){
                    case 'panic':
                        $data['panic'] = $goods;
                        break;
                    case 'kp':
                        $data['kp'] = $goods;
                        break;
                    case 'team':
                        $data['team'] = $goods;
                        break;
                }
            }
        }

        //最快结束的一个活动
        if(isset($soon_act)) {
            $soon_act['goods'] = $act_goods_list[$soon_act['act_id']][0];
            if(empty($soon_act['goods'])) $soon_act['act_endtime'] = 0;
            $data['discount'] = $soon_act;
        }

        return $data;
    }

    /**
     * @param $cat_id
     * @return bool
     **/
    public function is_real_goods($cat_id){
        $goods_cat = M('Category')->where("cat_id={$cat_id}")->getField('is_real');
        return intval($goods_cat) ? true : false;
    }
}