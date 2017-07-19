<?php
namespace Common\Element\Base\Di;



class Instance {

    public $id;

    protected function __construct($id){
        $this->id = $id;
    }

    public static function of($id){
        return new static($id);
    }










    //public function get(){}
    //public static function ensure(){}
}


