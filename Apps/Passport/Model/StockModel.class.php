<?php

/**
 * 即牛 - 经销商商城
 * ============================================================================
 * 版权所有 2011-2016 武汉微生活电子商务有限公司，并保留所有权利。
 * 网站地址: http://www.jiniu.cc;
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: keheng(keheng@163.com)
 * $Id: StockModel.class.php 17156 2016-06-29 16:29:47Z keheng $
*/

namespace Passport\Model;
class StockModel extends BaseModel{
    protected $tableName = 'store';
    public function get_goods_list($id = ''){
       if (is_array($id)) {

       }
    }
    /**
     * 读取店铺的分类
     * @return mixed
     */
    public function get_store_category(){
        $res = M('store_category')->order('sort_order desc')->select();
        foreach ($res as $k => $v) {
            $res[$k]['url'] = U('store/index') . '?id=' . $v['cat_id'];
        }
        return $res;
    }

    /**
     * 取店铺分类
     * @param $id
     * @return bool|array
     */
    public function get_store_cat($id) {
        $id = intval($id);
        if ($id <= 0) return false;
        return M('seller_goods_cate')->where(['store_id'=>$id,'hide'=>0])->order('sort desc')->select();
    }

    /**
     * 设置店铺状态
     * @param $store_id
     * @param $status
     * @return bool
     */
    public function set_store_state($store_id, $status) {
        return $this->where(['store_id'=>$store_id])->save(['store_state'=>$status]);
    }

    /**
     * 用户是否在店铺或店铺有没有过期
     * @param $user_id
     * @return mixed
     */
    public function is_store($user_id){
        if ($user_id <= 0) return false;

        $store_info = $this->get_store_info('s.user_id=' . $user_id);
        if ($store_info['store_state'] != STORE_OPEN or $store_info['store_end_time'] < time()) {
            return false;
        } else {
            return true;
        }

    }

    /**
     * 获取店铺信息
     * @param $where
     * @return bool
     */
    public function get_store_info($where){
        if (!$where) return false;// . " and s.store_state=1"
        $s = M('store')->alias('s')->where($where)->field('s.*,sg.grade_name,si.*,sb.*,st.min_sale_money,GROUP_CONCAT(r.region_name SEPARATOR "-") as diqu,sc.store_sales,t.tem_goods,t.tem_name,t.tem_content,sw.*,s.store_id,st.conditional_amount,st.store_qq')
            ->join('LEFT JOIN __STORE_GRADE__ sg ON sg.grade_id=s.grade_id')
            ->join('LEFT JOIN __STORE_IMAGES__ si ON s.store_id = si.store_id')
            ->join('LEFT JOIN __STORE_BUSINESS__ sb on sb.store_id= s.store_id' )
            ->join('LEFT JOIN __STORE_SETTING__ st on st.store_id= s.store_id' )
            ->join('LEFT JOIN __REGION__ r on s.province = r.region_id or s.city = r.region_id or s.district = r.region_id ')
            ->join('LEFT JOIN __STORE_TEMPLATE__ t ON s.store_theme=t.tem_name')
            ->join('LEFT JOIN __STORE_CREDIT__ sc ON s.store_id=sc.store_id')
            ->join('LEFT JOIN __STORE_WX__ sw ON s.store_id=sw.store_id')
            ->group('s.store_id')
            ->find();
        if($s){
            if($s['store_theme'] == '0'){
                $data = M('StoreTemplate')->where(['tem_name'=>'default'])->find();
                $s['tem_goods'] = $data['tem_goods'];
                $s['tem_name'] = $data['tem_name'];
                $s['tem_content'] = $data['tem_content'];
            }
            $s['store_label'] = img_url($s['store_label']);
            $s['store_banner'] = img_url($s['store_banner']);
            $s['store_avatar'] = img_url($s['store_avatar']);
            $s['business'] = img_url($s['business']);
        }
        return $s;
    }

    /**
     * 添加店铺信息
     * @param $data
     * @return bool
     */
    public function add_store($data){
        $store_data = [
            'store_name'    =>  $data['store_name']?:'',
            'grade_id'      =>  intval($data['grade_id']),
            'user_id'       =>  $data['user_id'],
            'sc_id'         =>  0,
            'kp_ratio'      =>  $this->_CFG['kp_ratio'],
            'store_goods_num'=> $this->_CFG['store_goods_num'],
            'store_theme'   =>  0,
            'province'   =>  $data['province'],
            'city'       =>  $data['city'],
            'district'       =>  $data['district'],
            'store_state'   =>  0,
            'add_time'      =>  $data['add_time'],
            'store_sort'    =>  100,
            'store_start_time'  =>  $data['store_start_time'],
            'store_end_time'  =>  $data['store_end_time'],
        ];

        $sdata = [
            'store_company_name' => $data['store_company_name'] ?: '',
            'business' => $data['business'],
            'business_num' => $data['business_num'],
            'contact' => $data['contact'],
            'mobile' => $data['mobile'],
            'store_address' => '',//$data['store_address'],
            'store_zy' => $data['range'],
            'store_zip' => 0,
        ];
        $m = M('store');
        if(!$data['store_id']) {
            $m->add($store_data);
            $store_id = $m->getLastInsID();
            if ($store_id > 0) {
                $sdata['store_id'] = $store_id;
                if (M('StoreBusiness')->data($sdata)->add()) {
                    //echo 'StoreBusiness:1' . "\n";
                } else {
                    //echo 'StoreBusiness:2' . "\n";
                }
                $sdata = [
                    'store_id' => $store_id,
                ];
                M('StoreCredit')->data($sdata)->add();
                M('StoreImages')->data($sdata)->add();
                M('StoreSetting')->data($sdata)->add();
                M('Users')->data(['store_id' => $store_id])->where(['user_id' => $data['user_id']])->save();
                return true;
            } else {
                return false;
            }
        }else{

            unset($store_data['add_time']);
            unset($store_data['store_start_time']);
            unset($store_data['store_end_time']);
            $store_data['store_id'] = $data['store_id'];
            if(!$data['business']) {
                unset($sdata['business']);
            }

            $sdata['store_id'] = $data['store_id'];
            if($m->save($store_data) or M('StoreBusiness')->save($sdata)){
                return true;
            }else{
                return false;
            }
        }
    }

    /**
     * 获取所有店铺类型
     * @param null $grade_id
     * @return array
     */
    public function get_grade_list($grade_id = null){
        $tmp = M('StoreGrade')->select();
        $list = array();
        foreach($tmp as $k => $v){
            $list[$v['grade_id']] = $v['grade_name'];
        }
        if($grade_id) return $list[$grade_id];
        return $list;
    }

    /**
     * 检查值是否存在于指定字段中
     * @param $val
     * @param $filed
     * @return bool
     */
    public function is_exists($val,$filed){
        $fields = $this->getDbFields();
        if( !in_array($filed,$fields) ){
            return false;
        }
        $count = $this->where(array($filed=>$val))->count();
        return $count?true:false;
    }

    /**
     * 获取店铺主管理员
     * @param $store_id
     */
    public function store_user($store_id){
        $store_user = M('Users')->where(['store_id'=>$store_id])->find();
        return $store_user;
    }

}