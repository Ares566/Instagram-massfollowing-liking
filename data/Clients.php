<?

require_once 'CoreData.php';
require_once 'InstaUsers.php';

class Clients extends CoreData{
    public $id = null;
    public $ownerid = 0;
    public $instagram_uid = '';
    public $login = '';
    public $password = '';
    public $mail = '';
    public $cell = '';
    public $notes = '';
    public $isapproved = 0;
    public $proxyid = 0;

    protected $db_table = 'clients';

    function __construct($id,$caching=false) {
        parent::__construct($id,$caching);
    }

    public function update(){
        parent::updateItem();
    }
    public function getTasks(){
        require_once 'Tasks.php';
        return Tasks::getTasks4Client($this->id);

    }
    public function getTasksWithFilter($aFilter = array()){
        require_once 'Tasks.php';
        return Tasks::getTasks4Client($this->id,TRUE,$aFilter);

    }

    public static function getClients4Owner($ownerid){
        $dba = iDB::GetAdaptor();
        $aIDs = $dba->getColumnArray('SELECT id FROM clients WHERE ownerid = '.intval($ownerid));
        $aClients = self::composeObjectList($aIDs);
        foreach ($aClients as $key=> $oClient) {
            $aClients[$key]->instauser = null;
            if($oClient->isapproved == 1){
                $oIUser = InstaUsers::getUserByPK($oClient->instagram_uid);
                if($oIUser && $oIUser->id)
                    $aClients[$key]->instauser = $oIUser;
            }
        }
        return $aClients;
    }
    public static function getAllClients(){
        $dba = iDB::GetAdaptor();
        $aIDs = $dba->getColumnArray('SELECT id FROM clients');
        return self::composeObjectList($aIDs);
    }

    public static function addClient($ownerid, $login,$password,$mail,$cell,$notes){
        $oClient = self::newItem('clients',
            array(
                'ownerid' => $ownerid,
                'login' => $login,
                'password' => $password,
                'mail' => $mail,
                'cell' => $cell,
                'notes' => $notes
            )
        );
        return $oClient;
    }

}

?>