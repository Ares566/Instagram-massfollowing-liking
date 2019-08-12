<?
require_once 'Clients.php';
require_once 'CoreData.php';
class Tasks extends CoreData{
    public $id = null;
    public $cid = '';
    public $tasktype = 0;
    public $usinglogin = '';
    public $startafter = '';
    public $settings = '';
    public $isactive = 0;
    public $parenttaskid = 0;
    public $firstactivated = null;

    protected $db_table = 'tasks';

    const TT_GETFOLLOWERS = 1;
    const TT_GETUSERINFO = 2;
    const TT_PROCESSMASSFOLLOWING = 3;
    const TT_ADDFOLLOWER = 4;
    const TT_LIKEITEMS = 5;
    const TT_DELFOLLOWER = 6;
    const TT_PROCESSMASSFOLLOWING_TAGS = 7;
    const TT_PROCESSMASSFOLLOWING_GEO = 8;
    const TT_GETUSERS_GEO = 9;
    const TT_MASSDIRECT = 10;
    const TT_SENDDIRECT = 11;

    function __construct($id,$caching=false) {
        parent::__construct($id,$caching);
        //$this->settings = unserialize($this->settings);
    }
    public function delete(){
        parent::deleteItem();
    }
    public function update(){
        //$this->settings = serialize($this->settings);
        parent::updateItem();
    }
    public static function getTitleByType($tid){
        $aTTT = array(
            self::TT_GETFOLLOWERS => 'Сбор фолловеров',
            self::TT_PROCESSMASSFOLLOWING => 'Массфолловинг по пользователям',
            self::TT_PROCESSMASSFOLLOWING_TAGS => 'Массфолловинг по тегам',
            self::TT_PROCESSMASSFOLLOWING_GEO => 'Массфолловинг по ГЕО'
        );
        return $aTTT[$tid];
    }
    public function getTaskOwner(){
        return new Clients($this->cid);
    }
    public function getUser4MF(){
        $dba = iDB::GetAdaptor();
        return $dba->getObjectList('SELECT * FROM users4mf WHERE isapproved > -1 AND taskid='.$this->id);

    }


    public function getStat(){
        require_once 'Filter.php';
        $dba = iDB::GetAdaptor();
        $aStat = $dba->getObjectList('SELECT * FROM task_stat WHERE taskid='.$this->id);
        $sRetVal = 'Задача запущена '.formatDateTime1($this->firstactivated).".\n";
        $iWasFollowed = 0;
        if(count($aStat)) {
            $sRetVal .= "Было произведено действий: ";
            foreach ($aStat as $oSt) {
                if ($oSt->tasktype == self::TT_ADDFOLLOWER){
                    $sRetVal .= $oSt->stat . ' ' . pluralForm($oSt->stat, 'подписка', 'подписки', 'подписок') . ', ';
                    $iWasFollowed = $oSt->stat;
                }

                if ($oSt->tasktype == self::TT_LIKEITEMS)
                    $sRetVal .= $oSt->stat . ' ' . pluralForm($oSt->stat, 'лайк', 'лайка', 'лайков') . ', ';
                if ($oSt->tasktype == self::TT_DELFOLLOWER)
                    $sRetVal .= $oSt->stat . ' ' . pluralForm($oSt->stat, 'отписка', 'отписки', 'отписок');
            }
        }

        if($this->tasktype == self::TT_PROCESSMASSFOLLOWING || $this->tasktype == self::TT_PROCESSMASSFOLLOWING_TAGS || $this->tasktype == self::TT_PROCESSMASSFOLLOWING_GEO){
            $oUser = self::getTaskOwner();
            $aResultOfFollowing = $dba->getColumnArray('SELECT DISTINCT who FROM followers WHERE fromtask=0 AND whom='.$oUser->instagram_uid.' AND who in (SELECT whom FROM followers WHERE who='.$oUser->instagram_uid.' AND fromtask='.$this->id.')');
            $iNewFollowers = count($aResultOfFollowing);
            $conv = 0;
            if($iWasFollowed )
                $conv = floor($iNewFollowers/$iWasFollowed * 100);

            $sRetVal .= "\nРезультат работы: подписок на продвигаемый аккаунт $iNewFollowers, конверсия $conv%";
        }

        return $sRetVal;
    }
    public function incStat($inum = 1){
        $dba = iDB::GetAdaptor();

        $parenttask = $this->parenttaskid?$this->parenttaskid:$this->id;
        $dba->insert('task_stat', array('taskid'=>$parenttask,'tasktype'=>$this->tasktype,'stat'=>$inum),' stat=stat+'.$inum.', ladate=CURRENT_TIMESTAMP ');
    }

    public function addUser4MF($login){
        $dba = iDB::GetAdaptor();
        $oUser = $this->getTaskOwner();
        if(!$oUser->instagram_uid || !$oUser->isapproved)
            throw new Exception('Ошибка 732: Неверные данные клиента');
        $dba->insert('users4mf', array('forIUID'=>$oUser->instagram_uid,'login'=>trim($login),'taskid'=>$this->id),' taskid=taskid ');
    }

    public function delUser4MF($id){
        $dba = iDB::GetAdaptor();
        $oUser = $this->getTaskOwner();
        if(!$oUser->instagram_uid || !$oUser->isapproved)
            throw new Exception('Ошибка 732: Неверные данные клиента');
        $dba->delete('users4mf', array('id'=>$id,'taskid'=>$this->id));
    }

    public function getMFTask(){
        $dba = iDB::GetAdaptor();
        $oMFT = $dba->getObject('SELECT * FROM mf_task WHERE tid='.$this->id);

        if(!($oMFT && $oMFT->tid))
            return FALSE;
        foreach ($oMFT as $key => $value) {
            if ((substr($key, 0, 2) == 'is')) {
                $oMFT->{$key} = $value==1?TRUE:FALSE;
            }
            if ($key == 'atcities' ) {

                $aRetVal = array();
                if(trim($value)) {
                    require_once 'data/City.php';
                    $aCities = City::getAllCities($value);
                    foreach ($aCities as $oCity) {
                        $oTTT = new stdClass();
                        $oTTT->text = $oCity->name;
                        $oTTT->value = $oCity->id;
                        $aRetVal[] = $oTTT;
                    }
                }
                $oMFT->{$key} = $aRetVal;
            }

            if ($key == 'atlangs' ) {

                $aRetVal = array();
                if(trim($value)) {
                    require_once 'data/Lang.php';
                    $aLangs = Lang::getAllLangs($value);
                    foreach ($aLangs as $oLang) {
                        $oTTT = new stdClass();
                        $oTTT->text = $oLang->lang;
                        $oTTT->value = $oLang->id;
                        $aRetVal[] = $oTTT;
                    }
                }
                $oMFT->{$key} = $aRetVal;
            }

        }

        return $oMFT;

    }
    public function addMFTask($unfollowdays,$isunfollowdays,$islikelastpost,$likelastpost,$isonlylike,$isignoresmm,$isignorecomm,$isignorebot,$islimitlang,$islimitsex,$islimitcity,$atlangs,$atcities,$sex){
        $dba = iDB::GetAdaptor();
        $dba->insert('mf_task',

            array('tid'=>$this->id,'unfollowdays'=>$unfollowdays,'isunfollowdays'=>$isunfollowdays,'islikelastpost'=>$islikelastpost,'likelastpost'=>$likelastpost,'isonlylike'=>$isonlylike,'isignoresmm'=>$isignoresmm,'isignorecomm'=>$isignorecomm,'isignorebot'=>$isignorebot,'islimitlang'=>$islimitlang, 'islimitsex'=>$islimitsex, 'islimitcity'=>$islimitcity,'atlangs'=>$atlangs,'atcities'=>$atcities,'sex'=>$sex),
            " `unfollowdays`=$unfollowdays,`isunfollowdays`=$isunfollowdays,`islikelastpost`=$islikelastpost,`likelastpost`=$likelastpost,`isonlylike`=$isonlylike,`isignoresmm`=$isignoresmm,`isignorecomm`=$isignorecomm,`isignorebot`=$isignorebot, `islimitlang`=$islimitlang, `islimitsex`=$islimitsex, `islimitcity`=$islimitcity,`atlangs`='$atlangs',`atcities`='$atcities',`sex`=$sex "
        );
    }

    /* STATIC */
    public static function markUser4MF($login,$mark){
        $dba = iDB::GetAdaptor();
        $dba->update('users4mf', array('isapproved'=>$mark), array('login'=>$login) );

    }
    public static function getTasks4Client($cid, $onlyactive = TRUE, $aFilter = array()){
        $dba = iDB::GetAdaptor();
        $sql = 'SELECT id FROM tasks WHERE startafter<=NOW() AND cid = '.intval($cid);
        if($onlyactive)
            $sql .= ' AND isactive=1 ';
        if(count($aFilter)){
            $sql .= ' AND tasktype NOT IN ('.implode(', ',array_filter($aFilter)).') ';
        }
        $sql .= ' ORDER BY ordering DESC';

        $aIDs = $dba->getColumnArray($sql);
        return self::composeObjectList($aIDs);
    }
    public static function getTasks($cid,$ttype){
        $dba = iDB::GetAdaptor();
        if(is_array($ttype))
            $aIDs = $dba->getColumnArray('SELECT id FROM tasks WHERE tasktype IN('.implode(',',$ttype).') AND cid = '.intval($cid));
        else
            $aIDs = $dba->getColumnArray('SELECT id FROM tasks WHERE tasktype='.intval($ttype).' AND cid = '.intval($cid));
        return self::composeObjectList($aIDs);
    }
    public static function addTask($forcid, $tasktype, $usinglogin, $startafter = 'NOW',$settings = '',$parenttaskid=0,$order=0){

        // чтоб дважды не добавлялось
        $dba = iDB::GetAdaptor();
        $aIDs = $dba->getColumnArray('SELECT id FROM tasks WHERE usinglogin ='.$dba->Quote($usinglogin).' AND tasktype='.intval($tasktype).' AND cid = '.intval($forcid));
        if(count($aIDs)){
            $aTTT = self::composeObjectList($aIDs);
            $oTask = array_shift($aTTT);
            $oTask->isactive = 1;
            $oTask->ordering = $order;
            $oTask->startafter = date('Y-m-d H:i:s',strtotime($startafter));
            $oTask->update();
            return $oTask;
        }


        $oTask = self::newItem('tasks',
                    array(
                        'cid' => $forcid,
                        'tasktype' => $tasktype,
                        'usinglogin' => $usinglogin,
                        'startafter' => date('Y-m-d H:i:s',strtotime($startafter)),
                        'settings' => $settings,
                        'parenttaskid' => $parenttaskid,
                        'ordering' => $order
                    )
                );
        return $oTask;
    }

    
}

?>