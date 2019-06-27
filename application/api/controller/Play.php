<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/06/25 0025
 * Time: 10:41
 */

namespace app\api\controller;

use think\Controller;

class Play extends Controller
{
    public function getRoads()
    {
        return $this->returnAjax("hello", 1, ["data" => 123]);
    }

    public function returnAjax($msg = '',$code = 1,$data = [],$type = 0)
    {
        if(!empty($data))
        {
            $json = $data;
        }
        $json['msg'] = $msg;
        $json['code'] = $code;
        if ($type)
        {
            return json_encode($json,JSON_UNESCAPED_UNICODE);
        }else{
            return json($json);
        }
    }
}