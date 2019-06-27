<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/06/24 0024
 * Time: 17:23
 */

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use think\App;
use think\Exception;
use app\common\lib\Limbo;

class Ws
{
    const HOST = "0.0.0.0";
    const PORT = 9501;

    public $ws = null;

    public function __construct()
    {
        $this->ws = new Server(self::HOST, self::PORT);
        $this->ws->set([
//            "document_root"         => "/var/www/SwooleTP5/thinkphp/public/static",
//            "enable_static_handler" => true,
            "worker_num" => 5
        ]);
        $this->ws->on("WorkerStart", [$this, "onWorkerStart"]);
        $this->ws->on("request", [$this, "onRequest"]);
        $this->ws->on("open", [$this, "onOpen"]);
        $this->ws->on("message", [$this, "onMessage"]);
        $this->ws->on("close", [$this, "onClose"]);
        $this->ws->start();
    }

    public function onWorkerStart(Server $server, $workerID)
    {
        define("APP_PATH", __DIR__ . "/../application/");
        require __DIR__ . "/../thinkphp/base.php";
    }

    public function onRequest(Request $request, Response $response)
    {
        $_SERVER = [];
        if (isset($request->server)) {
            foreach ($request->server as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
        if (isset($request->header)) {
            foreach ($request->header as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
        $_GET = [];
        if (isset($request->get)) {
            foreach ($request->get as $k => $v) {
                $_GET[$k] = $v;
            }
        }
        $_POST = [];
        if (isset($request->post)) {
            foreach ($request->post as $k => $v) {
                $_POST[$k] = $v;
            }
        }

        ob_start();//开启缓存
        try {
            App::run()->send();
        } catch (Exception $e) {
            //todo
            $this->ws->stop();
        }
        $res = ob_get_contents();//获取缓存的内容
        ob_end_clean();
        $response->end($res);
    }

    public function onOpen(Server $server, Request $request)
    {
        echo "server: handshake success with fd{$request->fd}" . PHP_EOL;
//        $this->ws->push($request->fd, json_encode($request->get));

        echo "open: " . Limbo::go($request->get, $server, $request->fd, $request) . PHP_EOL;
    }

    public function onMessage(Server $server, Frame $frame)
    {
        echo "receive from {$frame->fd}: {$frame->data}" . PHP_EOL
//            . "opcode: {$frame->opcode}" . PHP_EOL
//            . "fin: {$frame->finish}" . PHP_EOL
        ;
//        $this->ws->push($frame->fd, "server receive: " . $frame->data);

        $data = [
            "method" => "message",
            "data"   => $frame->data
        ];
        echo "message: " . Limbo::go($data, $server, $frame->fd, null, $frame) . PHP_EOL;
    }

    public function onClose(Server $server, $fd, $reactorId)
    {
        echo "client {$fd} closed" . PHP_EOL;

        $data = [
            "method" => "close",
            "fd"     => $fd
        ];
        echo "close: " . Limbo::go($data, $server, $fd) . PHP_EOL;
    }
}