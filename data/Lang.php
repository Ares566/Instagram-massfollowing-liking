<?php

require_once 'CoreData.php';
class Lang extends CoreData{
    public $id = null;
    public $lcode;
    public $lang;


    protected $db_table = 'lang';

    function __construct($id,$caching=false) {
        parent::__construct($id,$caching);
    }
    public static function getAllLangs($ids=''){
        $dba = iDB::GetAdaptor();
        $aLang = $dba->getObjectList('SELECT * FROM lang '.($ids?"WHERE id in($ids)":''));
        return $aLang;

    }
    public static function getLangByText($text){
        $ld = new LanguageDetection\Language();
        $lang = $ld->detect($text)->bestResults();
        $dba = iDB::GetAdaptor();
        $aLang = $dba->getObject('SELECT * FROM lang WHERE lcode='.$dba->Quote($lang));
        return $aLang;

    }



}