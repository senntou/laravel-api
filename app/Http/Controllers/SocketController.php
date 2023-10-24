<?php
namespace App\Http\Controllers;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\Room;

class SocketController extends Controller implements MessageComponentInterface {
    protected $clients;
    //ルームID　ー＞　ユーザーｓ
    protected $rooms;
    //ユーザー　ー＞　ルーム
    protected $user;

    // client情報をmapで管理し、user1とuser2を紐づける（無向辺で結ぶ）
    // 変更　user同士を紐づけるのではなく、ルームIDとuserをつなぐ
    public function __construct() {
        $this->clients = new \SplObjectStorage;

        $this->rooms = array();
        $this->user = new \SplObjectStorage;
        $roomDatas = Room::all();

        foreach( $roomDatas as $roomData ){
            $this->rooms[$roomData->id] = array();
            // echo($roomData->id . "\n");
        }

        // print_r($this->rooms);

        echo "constructed!!!\n";
    }

    // 接続を確率
    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})\n";
    }

    //　メッセージ１・・・入退室を行う
    //      ここでデータベースにユーザーネームを追加する処理も行い、user1,2を無向辺で結ぶ
    //　メッセージ２・・・メッセージのやり取りを行う
    public function onMessage(ConnectionInterface $from, $msg) {

        // echo("received a message\n");
        // print_r($this->rooms);

        $msgId = substr($msg,0,1);

        // データベース処理をまだ書いてないーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
        if($msgId == '0') {
            [$msgId, $roomid, $enterFlag, $name ] = explode(" ", $msg);
            $roomid = intval($roomid);
            $enterFlag = intval($enterFlag);

            if($enterFlag == 1){
                // $roomsにユーザー情報を登録する。　ルームが存在しない場合、エラーを返す。
                if(!isset($this->rooms[$roomid]) ) {
                    $from->send("error");
                    echo("room no. " . $roomid . " has not found");
                    print_r($this->rooms);
                    return ;
                }

                echo('registered user : ' . $name . "\n");
                $this->rooms[$roomid][] = $from;
                $this->user[$from] = $roomid;
            } else {
                // $roomsからユーザー情報を削除する。　存在しなければエラーを返す。
                // 削除は正確には、新しい配列を作成することで対応している。

                $newRoom = array();
                foreach($this->rooms[$roomid] as $user) {
                    if($user != $from) $newRoom[] = $user;
                }
                if(count($newRoom) - 1 != count($this->rooms[$roomid])){
                    $from->send("error");
                    return ;
                }
                $this->rooms[$roomid] = $newRoom;
                unset($this->user[$from]);
            }
        }

        if($msgId == '1'){
            // ルームが同じ人に対してメッセージを送信する。
            if(!isset($this->user[$from])){
                $from->send("error");
                echo("ok");
                return ;
            }
            $roomid = $this->user[$from];
            foreach( $this->rooms[$roomid] as $client ){
                if($client == $from) continue;

                $client->send($msg);
            }
            echo('ok');
        }
        // foreach ($this->clients as $client) {
        //     $client->send($msg);
        // }
    }

    //　えっとね、ここでデータベースからの削除もいると思います
    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}

// 連想配列に必要な機能
// id -> userを特定
// user -> idを特定