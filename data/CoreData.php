<?php
require 'DB.php';
class CoreData{
    
    protected $db_table = '';
    protected $table_id = 'id';
    
    function  __construct($id=NULL,$caching=FALSE) {
        $wherearray = array();
        if(!is_null($id)){
            $wherearray[$this->table_id] = $id;
        }
        if(count($wherearray))
            $this->initItem($wherearray,$caching);
        
    }

    private function initItem($aWhere,$caching=FALSE){
        $ignore = array();
        $dba = iDB::GetAdaptor();
        
        $sql = $dba->createSafeSELECT('*', $this->db_table, $aWhere,  $this->table_id,FALSE,1);
        $from = $dba->getObject($sql,$caching);
        $reflect = new ReflectionObject($this);
        //print_r($reflect->getProperties());exit; 
        foreach ($reflect->getProperties() as $prop){
            $propname = $prop->getName();
            if (!in_array( $propname, $ignore ) && isset( $from->$propname )){
                $this->$propname = $from->$propname;
                if((substr($propname, 0,2)=='is') ){
                    if($from->$propname == 'N')
                        $this->$propname = FALSE;
                    if($from->$propname == 'Y')
                        $this->$propname = TRUE;
                }
            }
        }
    }


    protected function updateItem($aEcxeptFields = array()){
        $aUPDStr = array();
        $dba = iDB::GetAdaptor();
        $reflect = new ReflectionObject($this);
        foreach ($reflect->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $propname = $prop->getName();
            if($propname==$this->table_id)continue;
            if (in_array($propname,$aEcxeptFields)) {
                continue;
            }
            if((substr($propname, 0,2)=='is') ){
                if($this->$propname === TRUE)
                    $aUPDStr[$propname] = 'Y';
                else
                    if($this->$propname === FALSE)
                        $aUPDStr[$propname] = 'N';
                else
                    $aUPDStr[$propname] = $this->$propname;
            }else
                $aUPDStr[$propname] = $this->$propname;
        }
        //print_r($aUPDStr);exit;
        $dba->update($this->db_table, $aUPDStr, array("{$this->table_id}"=>$this->{$this->table_id}));

    }

    protected static function newItem($db_table,$aColumnsValues){
        $dba = iDB::GetAdaptor();
        $id = $dba->insert($db_table, $aColumnsValues);
        if($id){
            return new static($id);
        }else
            return FALSE;
    }


    protected  function deleteItem(){
        $dba = iDB::GetAdaptor();
        $dba->delete($this->db_table, array("{$this->table_id}"=>$this->{$this->table_id}));
        
    }
    
    

    protected function getXMLItem(){
        $aUPDStr[] = "<class>".get_called_class()."</class>";
        foreach ($this->map as $key => $value) {
            $aUPDStr[] = "<$key>".$this->{$this->map[$key]}."</$key>";
        }
        $sUPDStr = implode('', $aUPDStr);
        return $sUPDStr;
    }

    protected function getJSONItem(){
        $aUPDStr['class'] = get_called_class();
        foreach ($this->map as $key => $value) {
            $aUPDStr[$key] = $this->{$this->map[$key]};
        }
        $sUPDStr = json_encode($aUPDStr);
        return $sUPDStr;
    }
    
    public static function composeObjectList($aIDs = array(),$caching=FALSE) {
        $aRetVal = array();
        foreach ($aIDs as $id) {
            $aRetVal[] = new static($id,$caching);
        }
        return $aRetVal;
    }

}

?>
