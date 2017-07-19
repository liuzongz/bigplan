<?php
namespace Sales\Model;

use Sales\Element\Activity;
use Sales\Element\Element;

class FavourableActivityModel extends BaseModel{
    protected $tableName = 'favourable_activity';
    /**
     *  获取活动列表
     * @param $map array 查询条件
     * @param $pagesize int 每页显示条数
     * @return array
     */
    public function get_act_list($store_id, $map, $pagesize=20){
        //$map['act_endtime'] = ['egt', time()];
        $where = ['fa.act_show'=>1];
        $count = $this->alias('fa')->where($where)->count();
        $pages = new \Think\Page($count, $pagesize);
        //$where['fua.test'] = 1;
        $data = $this->alias('fa')
            ->where($where)
            ->field('fa.*,fua.id aid')
            ->join('LEFT JOIN __FAVOURABLE_USER_ACTIVITY__ fua on fua.act_id=fa.act_id and fua.test = 1')
            ->order('sort_order DESC')
            ->limit($pages->firstRow.','.$pages->listRows)
            ->select();
        /**@var Activity $active*/
        $activie = Element::createObject('activity', []);
        foreach($data as $key => &$val){
            $activie->setData($val);
            if(!$activie->isExist() || !$activie->isExpire()) unset($data[$key]);
            $val['act_image'] = img_url($val['act_image']);
            //$val['url'] = U('active/detail'). '?aid=' . AesEnCrypt($val['act_id']).'&status='.AesEnCrypt('edit');
            $val['url']         = get_server('SALES_SERVER', '/active/detail',
            [
                'aid'           => AesEnCrypt($val['act_id']),
                'status'        => 1,
                'store_token'   =>$store_id
            ], 1);
        }
        return $data;
    }

    /**
     * 获取活动信息
     * @param $id int 活动ID
     * @return mixed 返回活动信息
     */
    public function get_act_info($id) {
        return $this->table('__FAVOURABLE_ACTIVITY__')->where(['act_id'=>$id])->find();
    }

}