<?php
namespace App\Http\Controllers;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\Room;


// クラスを作成する
    /*
    クラス1 ルームクラス
        ・メンバ
            （ID）
            ユーザ１のインスタンス
            ユーザ２のインスタンス
        ・関数
            アクセッサ
            ユーザーの追加（ネーム、CONN）
            CONNからユーザー削除
            DBの内容を変更

    クラス２　ユーザクラス
        ・メンバ
            ユーザCONN
            ユーザネーム
            所属するルームクラスの参照
        ・関数
            アクセッサ
            部屋に所属する
            部屋の所属を外す
    */
class User{
    private ConnectionInterface $conn;
    private String $name;
    private ChatRoom $chatRoom;

    public function __construct(ConnectionInterface $conn,String $name){
        $this->conn = $conn;
        $this->name = $name;
    }

    public function getConn(){
        return $this->conn;
    }
    public function getName(){
        return $this->name;
    }
    public function getChatRoom(){
        if(empty($this->chatRoom)) return null;
        return $this->chatRoom;
    }
    public function enterRoom(ChatRoom $room){
        $this->chatRoom = $room;
    }
    public function exitRoom(){
        unset($this->chatRoom);
    }
}
class ChatRoom{
    private $id;
    private $user1;
    private $user2;
    public function __construct(int $id){
        $this->id = $id;
    }

    public function getId(){
        return $this->id;
    }
    public function getUser1(){
        return $this->user1;
    }
    public function getUser2(){
        return $this->user2;
    }

    public function getElseUser(User $user){
        if($this->user1->getConn() === $user->getConn()){
            return $this->user2;
        } else if($this->user2->getConn() === $user->getConn()){
            return $this->user1;
        } else {
            echo("error : message sent, but this user is not in this room \n");
            return null;
        }
    }

    public function updateDataBase(){
        $roomdb = Room::find($this->id);
        if($this->user1 == null) $roomdb->user1 = null;
        else $roomdb->user1 = $this->user1->getName();
        if($this->user2 == null) $roomdb->user2 = null;
        else $roomdb->user2 = $this->user2->getName();
        $roomdb->save();
    }

    public function addUser(User $user){
        if($this->user1 == null){
            $this->user1 = $user;
            $this->updateDataBase();
            return FALSE;
        }
        if($this->user2 == null){
            $this->user2 = $user;
            $this->updateDataBase();
            return FALSE;
        }
        return TRUE;
    }
    public function removeUser(User $user){
        if($this->user1->getConn() == $user->getConn()){
            $this->user1 = null;
            $this->updateDataBase();
            return FALSE;
        }
        if($this->user2->getConn() == $user->getConn()){
            $this->user2 = null;
            $this->updateDataBase();
            return FALSE;
        }
        return TRUE;
    }    
}


class SocketController extends Controller implements MessageComponentInterface {
    protected $clients;
    protected $rooms;
    protected $users;

    // client情報をmapで管理し、user1とuser2を紐づける（無向辺で結ぶ）
    // 変更　user同士を紐づけるのではなく、ルームIDとuserをつなぐ
    public function __construct() {
        $this->clients = new \SplObjectStorage;

        $this->rooms = array();
        $this->users = new \SplObjectStorage;
        $roomDatas = Room::all();

        foreach( $roomDatas as $roomData ){
            $this->rooms[$roomData->id] = new ChatRoom($roomData->id);
        }

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
        $msgId = substr($msg,0,1);
        echo($msg . "\n");
        if($msgId == '0') {
            // [$msgId, $roomid, $enterFlag, $name ] = explode(" ", $msg);
            [$msgId, $roomid, $enterFlag] = explode(" ", $msg);
            $name = "user";
            $roomid = intval($roomid);
            $enterFlag = intval($enterFlag);

            // ユーザー情報を登録し、入室処理を行う。
            if($enterFlag == 1){
                // ルームが存在しない場合、エラーを返す。
                if(!isset($this->rooms[$roomid]) ) {
                    $from->send("error");
                    echo("room no. " . $roomid . " has not found\n");
                    return ;
                }
                // ユーザーが既にどこかのルームに入っている場合、エラーを返す。
                if( isset($this->users[$from]) && ($this->users[$from]->getChatRoom() != null)){
                    $from->send("critical error : this user is alreadry in a room");
                    echo("critical error : this user is already in a room\n");
                    return ;
                }
                echo('registered user : ' . $name . "\n");
                $user = new User($from,$name);
                $this->users[$from] = $user;

                if( $this->rooms[$roomid]->addUser($this->users[$from]) ){
                    $from->send("0NG");
                    echo("error : room is full\n");
                } else {
                    echo("send OK\n");
                    $from->send("0OK");
                    $this->users[$from]->enterRoom($this->rooms[$roomid]);
                }
            } 
            // $rooms からユーザー情報を削除する。　存在しなければエラーを返す。
            else {
                if($this->rooms[$roomid]->removeUser($this->users[$from])){
                    $from->send("error : can't delete user from room");
                    echo("error : can't delete user from room\n");
                } else {
                    $this->users[$from]->exitRoom();
                }
            }
            return ;
        }

        if($msgId == '1'){
            // ルームが同じ人に対してメッセージを送信する。
            // ユーザーがルームに入っていない場合はエラーを返す
            if( !isset($this->users[$from]) ||  $this->users[$from]->getChatRoom() == null){
                $from->send("error : this user have not entered any room");
                echo("error : this user have not entered any room");
                return ;
            }
            $msg = substr($msg,1);
            $room = $this->users[$from]->getChatRoom();
            $target = $room->getElseUser($this->users[$from]);
            if( $target == null ){
                return ;
            } else {
                echo("send message from user\n");
                $target->getConn()->send("1" . $msg);
            }
            echo("message sent\n");
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";

        //ユーザがルーム内に存在する場合、退出処理を行う。
        if( !isset($this->users[$conn]) || $this->users[$conn]->getChatRoom() == null) return ;
        $room = $this->users[$conn]->getChatRoom();
        $this->users[$conn]->exitRoom();
        $room->removeUser($this->users[$conn]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}

// 連想配列に必要な機能
// id -> userを特定
// user -> idを特定