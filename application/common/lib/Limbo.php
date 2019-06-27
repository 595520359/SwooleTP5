<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/06/25 0025
 * Time: 10:41
 */

namespace app\common\lib;

use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use think\Db;
use think\Exception;

class Limbo
{
    public static function go($data, Server $server, $fd, Request $request = null, Frame $frame = null)
    {
        $code = 1000;
        if (!empty($server) && !empty($fd)) {
            if (!empty($data)) {
                if (isset($data["method"])) {
                    switch ($data["method"]) {
                        case "open":
                            if (isset($data["uid"]))
                                $code = self::oOpen($data["uid"], $server, $fd);
                            break;
                        case "message":
                            if (isset($data["data"]) && !empty($server))
                                $code = self::oMessage($data["data"], $server, $fd);
                            break;
                        case "close":
                            if (isset($data["fd"]))
                                $code = self::oClose($data["fd"]);
                            break;
                        default:
                    }
                } else {
                    $server->close($fd);
                }
            } else {
                $server->close($fd);
            }
        }
        return $code;
    }

    public static function oOpen($uid, Server $server, $fd)
    {
        if (!empty($uid) && !empty($fd)) {
            try {
                $oldFd = Db::connect([
                    'type'     => 'mysql',
                    // 服务器地址
                    'hostname' => '127.0.0.1',
                    // 数据库名
                    'database' => 'swoole',
                    // 用户名
                    'username' => 'root',
                    // 密码
                    'password' => 'root',
                ])
                    ->name("member")
                    ->where([
                        "id" => $uid
                    ])
                    ->value("fd");
                if (!empty($oldFd)) {
                    if ($server->isEstablished($oldFd)) {
                        $server->push($oldFd, "在其他位置登陆!");
                        $server->close($oldFd);
                    }
                }

                Db::connect([
                    'type'     => 'mysql',
                    // 服务器地址
                    'hostname' => '127.0.0.1',
                    // 数据库名
                    'database' => 'swoole',
                    // 用户名
                    'username' => 'root',
                    // 密码
                    'password' => 'root',
                ])
                    ->name("member")
                    ->where([
                        "id" => $uid
                    ])
                    ->update([
                        "fd" => $fd
                    ]);
                return 0;
            } catch (Exception $e) {
                return 1102;
            }
        } else {
            return 1101;
        }
    }

    public static function oMessage($data, Server $server, $fd)
    {
        $dataArr = json_decode($data, true);
        if ($dataArr) {
            if (isset($dataArr["spec"])) {
                //{"spec":"xxx", "..."}
                switch ($dataArr["spec"]) {
                    case "fleet_comment":
                        //{"data": "from_uid,to_uid"}
                        if (isset($dataArr["data"])) {
                            try {
                                $tempUid = explode(",", $dataArr["data"]);
                                $fromUid = $tempUid[0];
                                $toUid   = $tempUid[1];
                                $toFd    = Db::connect([
                                    'type'     => 'mysql',
                                    // 服务器地址
                                    'hostname' => '127.0.0.1',
                                    // 数据库名
                                    'database' => 'swoole',
                                    // 用户名
                                    'username' => 'root',
                                    // 密码
                                    'password' => 'root',
                                ])
                                    ->name("member")
                                    ->field("fd")
                                    ->where([
                                        "id" => $toUid
                                    ])
                                    ->value("fd");
                            } catch (Exception $e) {
                                return 1212;
                            }

                            if (!empty($toFd) && $server->isEstablished($toFd)) {
                                $server->push($toFd, $fromUid);
                            } else {
                                return 1211;
                            }
                        }
//                        self::oPush($server, $fd, $dataArr);
                        break;
                    default:
                }
                return 0;
            }
        }

        //发送消息，无特殊事件
        //todo
        try {
            $fds = Db::connect([
                'type'     => 'mysql',
                // 服务器地址
                'hostname' => '127.0.0.1',
                // 数据库名
                'database' => 'swoole',
                // 用户名
                'username' => 'root',
                // 密码
                'password' => 'root',
            ])
                ->name("member")
                ->where([
                    "fd" => [
                        "<>", ""
                    ]
                ])
                ->column("fd");
            foreach ($fds as $k => $v) {
                if ($server->isEstablished($v))
                    $server->push($v, json_encode($data));
            }
        } catch (Exception $e) {
            return 1202;
        }
        return 1201;
    }

    public static function oClose($fd)
    {
        if (!empty($fd)) {
            try {
                Db::connect([
                    'type'     => 'mysql',
                    // 服务器地址
                    'hostname' => '127.0.0.1',
                    // 数据库名
                    'database' => 'swoole',
                    // 用户名
                    'username' => 'root',
                    // 密码
                    'password' => 'root',
                ])
                    ->name("member")
                    ->where([
                        "fd" => $fd
                    ])
                    ->update([
                        "fd" => ""
                    ]);
                return 0;
            } catch (Exception $e) {
                return 1302;
            }
        } else {
            return 1301;
        }
    }

    public static function oPush(Server $server, $fd, $data)
    {
        if ($server->isEstablished($fd))
            $server->push($fd, $data);
    }
}