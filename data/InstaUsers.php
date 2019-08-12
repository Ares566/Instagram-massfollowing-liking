<?

require_once 'CoreData.php';
class InstaUsers extends CoreData{
    public $id = null;
    public $pk;
    public $username;
    public $full_name;
    public $profile_pic_url;
    public $external_url;
    public $biography;
    public $iscommercial;
    public $issmm;
    public $city_id;
    public $sex;
    public $isbot;
    public $langid;
    public $phone;
    public $email;



    protected $db_table = 'instausers';

    function __construct($id,$caching=false) {
        parent::__construct($id,$caching);
    }

    public function update(){
        parent::updateItem();
    }

    public function getCities(){
        $dba = iDB::GetAdaptor();
        $aCity = $dba->getColumnArray('SELECT cityid FROM usersities WHERE userpk='.$this->pk);
        $aCity[] = $this->city_id;
        return array_unique($aCity);
    }
    public function addCities($aCityIDs = array()){
        if(count($aCityIDs)){
            $dba = iDB::GetAdaptor();
            $dba->delete('usersities',array('userpk'=>$this->pk));
            foreach ($aCityIDs as $iCityID) {
                $dba->insert('usersities',array('userpk'=>$this->pk,'cityid'=>$iCityID));
            }
        }

    }

    public function addFollower($whom){
        require_once 'Followers.php';
        Followers::addFollower($this->pk,$whom);
    }

    public function isWasSendedDirectTask($tid){
        $dba = iDB::GetAdaptor();
        $id = $dba->getOneValue('SELECT id FROM sendeddirect WHERE pk='.$this->pk.' AND taskid='.$tid);
        return $id ? TRUE : FALSE;
    }

    public static function  registerSendedDirect($tid,$pk){
        $dba = iDB::GetAdaptor();
        $dba->insert('sendeddirect',array('pk'=>$pk,'taskid'=>$tid), 'taskid='.$tid);

    }

    public static function getAllUsers($filter=array()){
        $dba = iDB::GetAdaptor();
        $sql = 'SELECT id FROM instausers ';
        if(count($filter)){
            $where_sql = array();
            if(array_key_exists('iscommercial',$filter) && intval($filter['iscommercial']))
                $where_sql[] = 'iscommercial=1';
            if(array_key_exists('sex',$filter) && intval($filter['sex']))
                $where_sql[] = 'sex='.intval($filter['sex']);
            if(array_key_exists('cityid',$filter) && intval($filter['cityid']))
                $where_sql[] = 'pk in(select userpk from usersities where cityid='.intval($filter['cityid']).')';
            $sql .= ' WHERE ('.implode(') AND (',$where_sql).')';
        }
        $aIDs = $dba->getColumnArray($sql);
        return self::composeObjectList($aIDs);
    }
    public static function isIUserByPK($pk){
        $dba = iDB::GetAdaptor();
        $id = $dba->getOneValue('SELECT id FROM instausers WHERE pk='.$pk);
        return $id ? TRUE : FALSE;
    }

    public static function getUserByPK($pk){
        $dba = iDB::GetAdaptor();
        $id = $dba->getOneValue('SELECT id FROM instausers WHERE pk='.$pk);
        if(!$id)
            return NULL;
        return new InstaUsers($id);
    }
    public static function getUserByLogin($username){
        $dba = iDB::GetAdaptor();
        $id = $dba->getOneValue('SELECT id FROM instausers WHERE username='.$dba->Quote($username) );
        if(!$id)
            return NULL;
        return new InstaUsers($id);
    }

    public static function addUser($pk, $username, $full_name, $profile_pic_url, $external_url, $biography){
        require_once 'Filter.php';
        $issmm = 0;
        $iscomm = 0;
        if(isSMMTxt($full_name.' '.$biography)){
            $issmm = 1;
        }
        if(isCommercialTxt($full_name.' '.$biography)){
            $iscomm = 1;
        }
        $iCity = getCityFromText($full_name.' '.$biography);

        $oIUser = self::newItem('instausers',
            array(
                'pk'=>$pk, 'username'=>$username, 'full_name'=>$full_name,
                'profile_pic_url'=>$profile_pic_url, 'external_url'=>$external_url,
                'biography'=>$biography,'iscommercial'=>$iscomm,'issmm'=>$issmm,'city_id'=>$iCity
            )
        );
        return $oIUser;
    }



}

?>