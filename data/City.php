<?php

require_once 'CoreData.php';
class City extends CoreData{
    public $id = null;
    public $name;
    public $nickname;



    protected $db_table = 'cities';

    function __construct($id,$caching=false) {
        parent::__construct($id,$caching);
    }
    public static function getAllCities($ids = ''){
        $dba = iDB::GetAdaptor();
        return $dba->getObjectList('SELECT * FROM cities '.($ids?"WHERE id in($ids)":''));
    }


}