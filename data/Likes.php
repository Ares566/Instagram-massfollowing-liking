<?php

require_once 'CoreData.php';
class Likes extends CoreData{
    public $id = null;
    public $who;
    public $whom;
    public $date;
    public $fromtask;

    protected $db_table = 'likes';

    function __construct($id,$caching=false) {
        parent::__construct($id,$caching);
    }


    public static function isLiked($who,$whom,$fromtask=0){
        $dba = iDB::GetAdaptor();
        $aIDS = $dba->getColumnArray('SELECT id FROM likes WHERE who='.intval($who).' AND whom='.intval($whom).' AND fromtask='.intval($fromtask) );
        return count($aIDS)?TRUE:FALSE;
    }
    public static function getLikersFor($whom){
        $dba = iDB::GetAdaptor();
        return $dba->getObjectList('SELECT *,MAX(date) as max_date FROM likes WHERE whom='.intval($whom).' GROUP BY who ORDER BY max_date DESC');
    }
    public static function addLiker($who, $whom,$fromtask=0){
        $dba = iDB::GetAdaptor();
        $id = $dba->insert(
            'likes',
            array('who'=>$who, 'whom'=>$whom,'fromtask'=>$fromtask),
            ' fromtask=fromtask '
        );

        //return $oIUser;
    }


}