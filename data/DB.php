<?php
class iDB {
    private $dbh;
    public $cacherefresh;
    function __construct() {

        $this->dbh = new PDO('mysql:host=localhost;dbname=instago;charset=utf8', '', '');
        $this->dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        
        $this->cacherefresh = FALSE;
    }


    
    public function createSafeSELECT($column = '*',$table,$aWhere = array(),$orderby='',$asc = TRUE, $limit=0,$offset=0){
        
        if(!$table)
            return FALSE;

        $sql = 'SELECT ';
        if($column == '*'||!is_array($column)||!count($column))
            $sql .= $column;
        else{
            $sql .= implode(',', $column);
        }
        $sql .= ' FROM `'.$table.'` ';
        
        if(count($aWhere)){
            $aTVals = array();
            foreach ($aWhere as $key => $value) {
                $aTVals[] = "`$key`=".$this->dbh->quote($value);
            }
            $sql .= ' WHERE '.implode(' AND ', $aTVals);
        }
        if($orderby)
            $sql .= ' ORDER BY `'.$orderby.'` '.($asc?'ASC':'DESC');
        
        if($limit)
            $sql .= ' LIMIT '.intval($limit);
        if($offset)
            $sql .= ' OFFSET '.intval($offset);

        return $sql;
    }
    
    public function getObjectList($sql,$caching = FALSE) {
        $query = $this->execute($sql);
        $retdata = $query->fetchAll(PDO::FETCH_CLASS);
        
        return $retdata;
    }
    
    public function getObject($sql,$caching = FALSE) {

        $query = $this->execute($sql);
        $retdata = $query->fetchObject();

        return $retdata;
    }
    
    public function getOneValue($sql,$caching = FALSE) {

        
        $query = $this->execute($sql);
        $retdata = $query->fetchColumn();

        
        return $retdata;
    }
    
    public function getColumnArray($sql,$caching = FALSE) {

        $query = $this->execute($sql);
        $retdata = $query->fetchAll(PDO::FETCH_COLUMN, 0);

        return $retdata;
    }

    public function insert($table,$aColumnsValues = array(),$onDuplicateKey = '') {
        if(!count($aColumnsValues) || !$table)
            return FALSE;
        
        $sql = 'INSERT INTO `'.$table.'`';
        $aTKeys = array();
        $aTVals = array();
        foreach ($aColumnsValues as $key => $value) {
            $aTKeys[] = "`$key`";
            $aTVals[] = $this->dbh->quote($value);
        }
        $sql .= '('.implode(',', $aTKeys).') VALUES ('.implode(',', $aTVals).')';
        if($onDuplicateKey != ''){
            $sql .= ' ON DUPLICATE KEY UPDATE '.$onDuplicateKey.' ';
        }
        $sql .= ';';

        $query = $this->execute($sql);
        
        return $query?$this->dbh->lastInsertId():FALSE;
    }
    
    public function update($table,$aColumnsValues = array() ,$aWhere = array()) {
        if(!count($aColumnsValues) || !count($aWhere)  || !$table)
            return FALSE;
        
        $sql = 'UPDATE `'.$table.'` SET ';
        
        $aTVals = array();
        foreach ($aColumnsValues as $key => $value) {
            $aTVals[] = "`$key`=".$this->dbh->quote($value);
        }
        $sql .= implode(',', $aTVals);
        
        $aTVals = array();
        foreach ($aWhere as $key => $value) {
            $aTVals[] = "`$key`=".$this->dbh->quote($value);
        }
        $sql .= ' WHERE '.implode(' AND ', $aTVals);

        $query = $this->execute($sql);
        
        
    }
    
    public function delete($table,$aWhere = array()){
        if(!count($aWhere) || !$table)
            return FALSE;
        
        $sql = 'DELETE FROM `'.$table.'` WHERE ';
        
        $aTVals = array();
        foreach ($aWhere as $key => $value) {
            $aTVals[] = "`$key`=".$this->dbh->quote($value);
        }
        
        $sql .= implode(' AND ', $aTVals);
        $this->execute($sql);
    }


    public function execute($sql){
        
        $query = NULL;

        $query = $this->dbh->prepare($sql);

        $query->execute();
        return $query;
    }
    public function Quote($value){
        return $this->dbh->quote($value);
    }
    
    public function getEscaped( $text, $extra = false ){
        $result =  str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $text);//$this->dbh->quote( $text );//mysql_real_escape_string 
        if ($extra) {
                $result = addcslashes( $result, '%_' );
        }
        return $result;
    }

    static public function GetAdaptor(){

        static $dbconnector = null;
        if (!isset($dbconnector)) {
                $dbconnector = new iDB();
        }
        return $dbconnector;
    }
    
    

}

