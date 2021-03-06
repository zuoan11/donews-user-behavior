<?php
namespace wild\UserBehavior\Services;

use wild\UserBehavior\Contracts\GameList;
use Illuminate\Database\Eloquent\Model;
use wild\UserBehavior\Services\GameListImages;
use DB;

class GameListModel extends Model implements GameList
{
	protected $connection = "tgbus_users_behavior";

	protected $table = "gamelist";

    //关联图片
    public function image()
    {
        return $this->hasOne("wild\UserBehavior\Services\GameListImages", 'game_list_id', 'id')->select('img_path')->where("type", 'cover');
    }

    //获取游戏单列表
    public static function getGameList($keyword, $page, $page_size, $type = 1, $app_id, $sortfield = 'created_at', $order = 'desc')
    {
        if ($type == 1) {
            $data = self::select('id', 'uid', 'title', 'content', 'created_at')->where("status", 0)->where("app_name", $app_id)->where('title', 'like', "%".$keyword."%")->offset(($page - 1) * $page_size)->limit($page_size)->get();
        } else {
            $data = self::select('id', 'uid', 'title', 'content', 'created_at')->where("status", 0)->where("is_system",0)->where("app_name", $app_id)->orderBy($sortfield, $order)->offset(($page - 1) * $page_size)->limit($page_size)->get();
        }
        return self::handle($data, $app_id);
    }

    //获取用户游戏单
    public static function getUserList($user_id, $page, $page_size, $app_id, $sort = 'created_at', $sortfield = '')
    {
        $system = self::select('id')->where("app_name", $app_id)->where("status", 0)->where("uid", $user_id)->where("is_system", 1)->first();
        if(!$system){
            $insert['uid'] = $user_id;
            $insert['title'] = '默认游戏单';
            $insert['content'] = '默认游戏单';
            $insert['status'] = 0;
            $insert['app_name'] = $app_id;
            $insert['is_public'] = 0;
            $insert['is_system'] = 1;
            $insert['created_at'] = date('Y-m-d H:i:s');
            $insert['updated_at'] = date('Y-m-d H:i:s');
            self::insert($insert);
        }

        $order = ''; 
        if($sortfield){
            $order = orderBy($sortfield, "DESC");
        }

        $data = self::select('id', 'uid', 'title', 'content', 'created_at', 'is_system')->where("app_name", $app_id)->where("status", 0)->where('uid', $user_id)->orderBy($sort, 'DESC')->offset(($page - 1) * $page_size)->limit($page_size)->get();

        return self::handle($data, $app_id);
    }

    //创建游戏单
    public static function create($app_id, $user_id, $title, $content, $is_public = 0, $path)
    {
        $insert['uid'] = $user_id;
        $insert['title'] = $title;
        $insert['content'] = $content;
        $insert['status'] = 0;
        $insert['app_name'] = $app_id;
        $insert['is_public'] = $is_public;
        $insert['is_system'] = 0;
        $insert['created_at'] = date('Y-m-d H:i:s');
        $insert['updated_at'] = date('Y-m-d H:i:s');
        $id = self::insertGetId($insert);

        // $id = DB::getPdo()->lastInsertId();

        if($path){
            $img['game_list_id'] = $id;
            $img['type'] = 'cover';
            $img['img_type'] = 1;
            $img['img_size'] = 0;
            $img['img_width'] = 0;
            $img['img_height'] = 0;
            $img['is_delete'] = 0;
            $img['img_path'] = $path;
            GameListImages::insert($img);
        }
        $data = self::select('id', 'uid', 'title', 'content', 'created_at')->where("id", $id)->get();

        return self::handle($data, $app_id);
    }

    //游戏单删除
    public static function deleteGameList($app_id, $user_id, $id)
    {
        $res = self::where("app_name", $app_id)->where("id", $id)->where("uid", $user_id)->update(['status'=>1]);
        if(!$res){
            return false;
        }
        return true;
    }

    //游戏单浏览量和收藏数量增加
    public static function increase($app_id, $id, $type)
    {
        if($type == 1){
            $keyword = $app_id.'_collection';
        }else{
            $keyword = $app_id.'_browse';
        }
        self::where("id", $id)->increment($keyword,1);
        return true;
    }

    //判断游戏是否在游戏单中
    public static function isExistence($user_id, $id, $app_name)
    {
        $game_list = self::where("uid",$user_id)->where("app_name",$app_name)->get()->toArray();
        $lists_id = array_column($game_list, 'id');

        $games = GameListGamesModel::where("g_id",$id)->where("app_name",$app_name)->get()->toArray();
        $games_id = array_column($games, 'l_id');

        $data = array_intersect($lists_id,$games_id);

        if($data){
            return true;
        }

        return false;

    }

    //获取游戏单详情
    public static function getDetails($app_id, $ids)
    {
        $data = self::select('id', 'uid', 'title', 'content', 'created_at')->whereIn("id", $ids)->where("status", 0)->get();

        return self::handle($data, $app_id);
    }

    protected static function handle($data, $app_id)
    {
        foreach ($data as $val){
            $val->image;
            $val->games;
            $val->game_total = GameListGamesModel::where('l_id', $val->id)->where("app_name", $app_id)->count();
        }
        return $data;
    }

    public function games()
    {
        return $this->hasMany("wild\UserBehavior\Services\GameListGamesModel", "l_id");
    }
}

?>