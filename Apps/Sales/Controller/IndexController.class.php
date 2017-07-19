<?php

namespace Sales\Controller;
class IndexController extends WechatController {
    public function index(){
        $this->redirect('active/index');
    }

    public function test(){
        $str = 'WNVbiI7/xVjZyGFbTQeDH660nfiqcgAZUKQgBMTWENhOYXcw4mXx~zC46tRdZBiqAZg7fqx51oZg5XoY~MLA2sFFDapCKLUNgv5T/fM0Q2Y=';
        $str = 'aStlYWW2OpJalgSAmZjG4Vh~N8QGQwLw2WQafK5SWP/MJ4YY8NO~dynIFCtgmtvbjo5pZZ2wvW7Wso2vPUWpLbBL2xGmyytQu69uUsFlghU=';
        echo AesDeCrypt($str);
    }


    public function access_token(){
        $back_act = AesDeCrypt(I('get.back_act'));
        if($back_act != ''){
            $back_act = check_storeid($back_act, I('get.' . C('store_token_name'), '0', 'intval'));
            header("Location:$back_act");
        }else{
            echo "<script>alert('URL错误！！！')</script>";
            //$this->redirect("index/index");
        }
    }

    Public function wx_verify(){
        exit(I('get.key'));
    }
}
