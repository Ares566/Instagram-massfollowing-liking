<?php

require_once 'CoreData.php';
class Followers extends CoreData{
    public $id = null;
    public $who;
    public $whom;
    public $date;
    public $fromtask;

    protected $db_table = 'followers';

    function __construct($id,$caching=false) {
        parent::__construct($id,$caching);
    }


    public static function isFollowed($who,$whom,$fromtask=0){
        $dba = iDB::GetAdaptor();
        $aIDS = $dba->getColumnArray('SELECT id FROM followers WHERE who='.intval($who).' AND whom='.intval($whom).' AND fromtask='.intval($fromtask) );
        return count($aIDS)?TRUE:FALSE;
    }
    public static function getFollowersFor($whom){
        $dba = iDB::GetAdaptor();
        return $dba->getObjectList('SELECT *,MAX(date) as max_date FROM followers WHERE whom='.intval($whom).' GROUP BY who ORDER BY max_date DESC');
    }
    public static function addFollower($who, $whom,$fromtask=0){
        $oIUser = self::newItem('followers',
            array(
                'who'=>$who, 'whom'=>$whom,'fromtask'=>$fromtask
            )
        );
        return $oIUser;
    }


}