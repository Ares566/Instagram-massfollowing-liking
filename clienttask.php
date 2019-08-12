<?php

    // Processing all task for current user $argv[1]
    date_default_timezone_set('Europe/Moscow');

    list($c,$cid) = explode('_',$argv[1]);
    define( 'LOCK_FILE', "run/".basename( $argv[1], ".php" ).".lock" );
    if( isLocked() ) die( date('Y-m-d H:i:s')." CID:$cid Already running.\n" );

    require __DIR__.'/vendor/autoload.php';
    require_once 'data/Clients.php';
    require_once 'data/Tasks.php';
    require_once 'data/InstaUsers.php';
    require_once 'data/Followers.php';
    require_once 'data/Proxy.php';
    require_once 'data/Lang.php';
    require_once 'data/Likes.php';

    $oClient = new Clients($cid);
    if(!$oClient->id)
        die( date('Y-m-d H:i:s')." CID:$cid Wrong CID.\n" );

    /////// CONFIG ///////
    $username = $oClient->login;
    $password = $oClient->password;

    $debug = false;
    $truncatedDebug = false;
    //////////////////////
///
    // Инициализация
    $aTimers = array(
        Tasks::TT_GETFOLLOWERS => time(),
        Tasks::TT_GETUSERINFO => time(),
        Tasks::TT_PROCESSMASSFOLLOWING => time(),
        Tasks::TT_ADDFOLLOWER => time(),
        Tasks::TT_LIKEITEMS => time(),
        Tasks::TT_DELFOLLOWER => time(),
        Tasks::TT_PROCESSMASSFOLLOWING_TAGS => time(),
        Tasks::TT_PROCESSMASSFOLLOWING_GEO => time()
    );

    // sec
    $aTimouts = array(
        Tasks::TT_GETFOLLOWERS => 20,
        Tasks::TT_GETUSERINFO => 1,
        Tasks::TT_PROCESSMASSFOLLOWING => 20,
        Tasks::TT_ADDFOLLOWER => 25,
        Tasks::TT_LIKEITEMS => 25,
        Tasks::TT_DELFOLLOWER => 3,
        Tasks::TT_PROCESSMASSFOLLOWING_TAGS => 20,
        Tasks::TT_PROCESSMASSFOLLOWING_GEO => 20
    );
    $icntFollowsPerTime = 100;

    $icntLikesPerTime = 100;

    $ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);

    
    if (!$oClient->proxyid) {
        // клиенту еще ны выделялся прокси
        $oProxy = Proxy::getNewProxy();
        $oClient->proxyid = $oProxy->id;
        $oClient->update();
    }
    $oCProxy = new Proxy($oClient->proxyid);
    if ($oCProxy->test()) {
        $ig->setProxy('http://' . $oCProxy->login . ':' . $oCProxy->password . '@' . $oCProxy->ip . ':' . $oCProxy->port);
    } else {
        $oCProxy->isactive = 0;
        $oCProxy->update();
    }


    try {
        $ig->login($username, $password);
        $mainuserpk = $ig->people->getUserIdForName($username);
    } catch (\Exception $e) {
        echo date('Y-m-d H:i:s')." CID:$cid Something went wrong: ".$e->getMessage()."\n";
        exit(0);
    }

    if(!$oClient->isapproved){
        // нужно проверить нового клиента и добавить его в табдицу пользователей
        $pk = $ig->people->getUserIdForName($username);
        $response = $ig->people->getInfoById($pk);
        $aFollower = $response->getUser();

        $biography = $aFollower->getBiography();
        $username = $aFollower->getUsername();
        $profile_pic_url = $aFollower->getProfilePicUrl();
        $full_name = $aFollower->getFullName();
        $external_url = $aFollower->getExternalUrl();

        InstaUsers::addUser($pk, $username, $full_name, $profile_pic_url, $external_url, $biography);
        $oClient->isapproved = 1;
        $oClient->instagram_uid = $pk;
        $oClient->update();
        Tasks::addTask($oClient->id,Tasks::TT_GETUSERINFO,$oClient->instagram_uid,'+1 day','daily');
    }
    // главный цикл
    while (TRUE) {
        //TODO: лимит заданий, чтоб чаще обновлялось
        $aFilter = array();
        if(time() - $aTimers[Tasks::TT_ADDFOLLOWER] < $aTimouts[Tasks::TT_ADDFOLLOWER]) {
            $aFilter[] = Tasks::TT_ADDFOLLOWER;
            echo date('Y-m-d H:i:s')." CID:$cid GetTasksWithFilter without ADDFOLLOWER: "."\n";
        }
        if(time() - $aTimers[Tasks::TT_LIKEITEMS] < $aTimouts[Tasks::TT_LIKEITEMS]) {
            $aFilter[] = Tasks::TT_LIKEITEMS;
            echo date('Y-m-d H:i:s')." CID:$cid GetTasksWithFilter without LIKEITEMS: "."\n";
        }
        if(count($aFilter) == 0){
            echo date('Y-m-d H:i:s')." CID:$cid GetTasksWithFilter ALL: "."\n";
            $aTasks = $oClient->getTasks();
        }else
            $aTasks = $oClient->getTasksWithFilter($aFilter);

        if(!count($aTasks)){
            sleep(60);
            continue;
        }
        echo date('Y-m-d H:i:s') . " CID:$cid Tasks in queue: " . count($aTasks). "\n";
        //shuffle($aTasks);
        $aTasks = array_slice($aTasks,0,20);
        foreach ($aTasks as $oTask){
            //echo date('Y-m-d H:i:s') . " CID:$cid Tasks processing start: " . $oTask->id. "\n";
            switch ($oTask->tasktype) {
                case Tasks::TT_GETFOLLOWERS:
                    if(time() - $aTimers[Tasks::TT_GETFOLLOWERS] > $aTimouts[Tasks::TT_GETFOLLOWERS]){
                        THGetFollowers($oTask);
                        $aTimers[Tasks::TT_GETFOLLOWERS] = time();
                    }

                    break;
                case Tasks::TT_GETUSERINFO:
                    if(time() - $aTimers[Tasks::TT_GETUSERINFO] > $aTimouts[Tasks::TT_GETUSERINFO]){
                        THGetUserInfo($oTask);
                        $aTimers[Tasks::TT_GETUSERINFO] = time();
                    }
                    break;
                case Tasks::TT_PROCESSMASSFOLLOWING:
                    if(time() - $aTimers[Tasks::TT_PROCESSMASSFOLLOWING] > $aTimouts[Tasks::TT_PROCESSMASSFOLLOWING]){
                        THMassFollowing($oTask);
                        $aTimers[Tasks::TT_PROCESSMASSFOLLOWING] = time();
                    }
                    break;
                case Tasks::TT_PROCESSMASSFOLLOWING_TAGS:
                    if(time() - $aTimers[Tasks::TT_PROCESSMASSFOLLOWING_TAGS] > $aTimouts[Tasks::TT_PROCESSMASSFOLLOWING_TAGS]){
                        THTagsMassFollowing($oTask);
                        $aTimers[Tasks::TT_PROCESSMASSFOLLOWING_TAGS] = time();
                    }
                    break;
                case Tasks::TT_PROCESSMASSFOLLOWING_GEO:
                    if(time() - $aTimers[Tasks::TT_PROCESSMASSFOLLOWING_GEO] > $aTimouts[Tasks::TT_PROCESSMASSFOLLOWING_GEO]){
                        THGeoMassFollowing($oTask);
                        $aTimers[Tasks::TT_PROCESSMASSFOLLOWING_GEO] = time();
                    }
                    break;
                case Tasks::TT_GETUSERS_GEO:
                    //if(time() - $aTimers[Tasks::TT_GETUSERS_GEO] > $aTimouts[Tasks::TT_GETUSERS_GEO]){
                        THGetUsersByGeo($oTask);
                    //    $aTimers[Tasks::TT_PROCESSMASSFOLLOWING_GEO] = time();
                    //}
                    break;
                case Tasks::TT_MASSDIRECT:
                    THMassDirect($oTask);
                    break;
                case Tasks::TT_SENDDIRECT:
                    THSendDirect($oTask);
                    break;
                case Tasks::TT_ADDFOLLOWER:
                    if(time() - $aTimers[Tasks::TT_ADDFOLLOWER] > $aTimouts[Tasks::TT_ADDFOLLOWER]){
                        if(THAddFollower($oTask)) {
                            $aTimers[Tasks::TT_ADDFOLLOWER] = time();
                            $icntFollowsPerTime--;
                            if ($icntFollowsPerTime <= 0) {
                                $aTimers[Tasks::TT_ADDFOLLOWER] = time() + 24 * 60 * 60;
                                $icntFollowsPerTime = 100;
                            }
                        }else
                            $aTimers[Tasks::TT_ADDFOLLOWER] = time() + 24 * 60 * 60;

                    }
                    break;
                case Tasks::TT_LIKEITEMS:
                    if(time() - $aTimers[Tasks::TT_LIKEITEMS] > $aTimouts[Tasks::TT_LIKEITEMS]){
                        $nextstart = THLikeItemsOfIUser($oTask);
                        $aTimers[Tasks::TT_LIKEITEMS] = time()+$nextstart;
                        $icntLikesPerTime--;
                        if($icntLikesPerTime<=0){
                            $aTimers[Tasks::TT_LIKEITEMS] = time() + 24 * 60 *60;
                            $icntLikesPerTime = rand(500,600);
                        }

                    }
                    break;
                case Tasks::TT_DELFOLLOWER:
                    if(time() - $aTimers[Tasks::TT_DELFOLLOWER] > $aTimouts[Tasks::TT_DELFOLLOWER]){
                        THUnfollow($oTask);
                        $aTimers[Tasks::TT_DELFOLLOWER] = time()+rand(2,8);
//                        $icntLikesPerTime--;
//                        if($icntLikesPerTime<=0){
//                            $aTimers[Tasks::TT_LIKEITEMS] = time() + 60;
//                            $icntLikesPerTime = rand(8,15);
//                        }

                    }
                    break;
            }
            //echo date('Y-m-d H:i:s') . " CID:$cid Tasks processing finished: " . $oTask->id. "\n";
            $slp = rand(3,6);
            sleep($slp);

       }


    }
    unlink( LOCK_FILE );
    exit(0);


    function THSendDirect($oTask)
    {
        global $ig;
        $aSettings = unserialize($oTask->settings);
        $message = $aSettings['message'];

        $titles = array(
            'Реклама в Питере.',
            'Рассылка в Питере.',
            'Вы работаете в Питере?'
        );
        $title = $titles[mt_rand(0, count($titles) - 1)];
        $message = $title."\nРассылка в инстаграме только по жителям Питера.\n\nВ нашей базе 547 000 живых пользователей Санкт-Петербурга. \n\nСегментация всех жителей по полу, возрасту, увлечениям, уровню дохода, наличию детей, семейному положению.\n\nСтоимость рассылки готовы выслать после вашего запроса в WhatsApp +79282719164.\n\nУже через 30 минут можем запустить вашу рассылку в инстаграме по жителям Санкт-Петербурга.";
        $userIds = array($oTask->usinglogin);
        $recipients =
        [
         'users' => $userIds // must be an [array] of valid UserPK IDs 1351974770
        ];
        $ig->direct->sendText($recipients, $message);
        InstaUsers::registerSendedDirect($oTask->parenttaskid,$oTask->usinglogin);
        $oTask->delete();
    }

    function THMassDirect($oTask)
    {
        //global $ig;

        //берем фильтр
        $aSettings = unserialize($oTask->settings);
//        $aSettings =
//array(
//'cityid'=>2,
//'iscommercial'=>1,
//'limitperday'=>50,
//'botsid'=>array(9,10,11,12,13,15,16,17),
//'message'=>'Покажите себя всем клиентам Санкт Петербурга!'
//);
//a:5:{s:6:"cityid";i:2;s:12:"iscommercial";i:1;s:11:"limitperday";i:20;s:6:"botsid";a:2:{i:0;i:8;i:1;i:9;}s:7:"message";s:84:"Покажите себя всем клиентам Санкт Петербурга!";}
        //выбираем пользователей согласно настроек
        $filter = array('cityid'=>$aSettings['cityid'],'iscommercial'=>$aSettings['iscommercial']);
        $aUsers4Directing = InstaUsers::getAllUsers($filter);

        //ставим задачу для конкретной учетки по отправке сообщений
        $iLimitsPerDay = intval($aSettings['limitperday'])?intval($aSettings['limitperday']):50;
        $aSMSetting = array('message'=>$aSettings['message']);
        foreach ($aSettings['botsid'] as $iBotID) {
            $iDMTCnt = 1;
            foreach ($aUsers4Directing as $key => $_iUser) {
                if($_iUser->isWasSendedDirectTask($oTask->id)){
                    unset($aUsers4Directing[$key]);
                    continue;
                }
                Tasks::addTask($iBotID, Tasks::TT_SENDDIRECT, $_iUser->pk, '+' . $iDMTCnt*8 . ' minutes', serialize($aSMSetting), $oTask->id);
                unset($aUsers4Directing[$key]);
                $iDMTCnt++;
                if($iDMTCnt > $iLimitsPerDay)
                    break;
            }

        }

        $oTask->startafter = date('Y-m-d H:i:s', strtotime('+1 day'));
        $oTask->update();
    }

    function THGetUsersByGeo($oTask){
        global $ig;

        // getTags from task
        $aTags = $oTask->getUser4MF();
//        $oSettings = $oTask->getMFTask();
//        $settings = serialize($oSettings);

        $rankToken = \InstagramAPI\Signatures::generateUUID();
        foreach ($aTags as $oTag){
            $response = $ig->location->findPlaces($oTag->login);
            $pk = null;
            foreach ($response->getItems() as $item) {
                //echo $item->getId()."\n";
                $oLocation = $item->getLocation();
                $pk = $oLocation->getPk();
                break;
            }
            echo date('Y-m-d H:i:s')." THGetUsersByGeo: ".$pk."\n";


            $maxId = null;
            $iUserCounter = 0;
            do {
                $response = $ig->location->getFeed($pk, $rankToken,'recent', null, null, $maxId);


                foreach ($response->getSections() as $items) {
                    foreach($items->getLayoutContent()->getMedias() as $item){

                        // request instausers owned tags
                        $_oIUser = $item->getMedia()->getUser();

                        // if we haven't info -> addTask 2 getInfo about they
                        $oInstaUser = InstaUsers::getUserByPK($_oIUser->getPk());
                        if (!($oInstaUser && $oInstaUser->pk)) {
                            Tasks::addTask($oTask->cid, Tasks::TT_GETUSERINFO, $_oIUser->getPk(), 'NOW', $oTask->settings, $oTask->id,4);
                            $iUserCounter++;
                            $oTask->incStat();
                        }

                    }
                }

                $maxId = $response->getNextMaxId();

                if($iUserCounter>10000)
                    $maxId = null;

                sleep(rand(8, 12));
            } while ($maxId !== null);

        }


        $oTask->startafter = date('Y-m-d H:i:s', strtotime('+1 day'));
        $oTask->update();
    }

    function THGeoMassFollowing($oTask){
        global $ig;

        // getTags from task
        $aTags = $oTask->getUser4MF();
        $oSettings = $oTask->getMFTask();
        $settings = serialize($oSettings);

        $rankToken = \InstagramAPI\Signatures::generateUUID();
        foreach ($aTags as $oTag){
            $response = $ig->location->findPlaces($oTag->login);
            $pk = null;
            foreach ($response->getItems() as $item) {
                //echo $item->getId()."\n";
                $oLocation = $item->getLocation();
                $pk = $oLocation->getPk();
                break;
            }

            $response = $ig->location->getFeed($pk, $rankToken,'recent');


            foreach ($response->getSections() as $items) {
                foreach($items->getLayoutContent()->getMedias() as $item) {

                    // request instausers owned tags
                    $_oIUser = $item->getMedia()->getUser();

//            $response = $ig->location->getFeed($pk, $rankToken);
//            $aItems = $response->getItems();
//
//            foreach ($aItems as $item) {

                    // request instausers owned tags
                    //$_oIUser = $item->getUser();

                    // if we haven't info -> addTask 2 getInfo about they
                    $oInstaUser = InstaUsers::getUserByPK($_oIUser->getPk());
                    if (!($oInstaUser && $oInstaUser->pk))
                        Tasks::addTask($oTask->cid, Tasks::TT_GETUSERINFO, $_oIUser->getPk(), 'NOW', '', $oTask->id);

                    // addTask 2 follow that users
                    Tasks::addTask($oTask->cid, Tasks::TT_ADDFOLLOWER, $_oIUser->getPk(), 'NOW', $settings, $oTask->id);
                }
            }

            sleep(rand(3,6));
        }

        $oTask->startafter = date('Y-m-d H:i:s', strtotime('+1 day'));
        $oTask->update();
    }
    function THTagsMassFollowing($oTask){
        global $ig;

        // getTags from task
        $aTags = $oTask->getUser4MF();
        $oSettings = $oTask->getMFTask();
        $settings = serialize($oSettings);

        $rankToken = \InstagramAPI\Signatures::generateUUID();

        foreach ($aTags as $oTag){
            $response = $ig->hashtag->getFeed($oTag->login,$rankToken);
            foreach ($response->getItems() as $item) {
                // request instausers owned tags
                $_oIUser = $item->getUser();

                // if we haven't info -> addTask 2 getInfo about they
                $oInstaUser = InstaUsers::getUserByPK($_oIUser->getPk());
                if(!($oInstaUser && $oInstaUser->pk))
                    Tasks::addTask($oTask->cid, Tasks::TT_GETUSERINFO, $_oIUser->getPk(),'NOW','',$oTask->id);

                // addTask 2 follow that users
                Tasks::addTask($oTask->cid,Tasks::TT_ADDFOLLOWER, $_oIUser->getPk(),'NOW',$settings, $oTask->id);

            }
            sleep(rand(2,8));
        }

        $oTask->startafter = date('Y-m-d H:i:s', strtotime('+1 day'));
        $oTask->update();

    }
    function THMassFollowing($oTask){
        global $ig;
        $aСompetitors = $oTask->getUser4MF();
        $oSettings = $oTask->getMFTask();
        $settings = serialize($oSettings);
        $isLastStep = FALSE;
        foreach ($aСompetitors as $_oCompetitor){
            $oCompetitor = InstaUsers::getUserByLogin($_oCompetitor->login);
            if($oCompetitor && $oCompetitor->pk){//такой чел у нас есть
                // обновить его фолловеров
                Tasks::addTask($oTask->cid,Tasks::TT_GETFOLLOWERS,$_oCompetitor->login,'NOW',serialize(array('limit'=>200)),$oTask->id);
                $aFollowers = Followers::getFollowersFor($oCompetitor->pk);
                if(count($aFollowers)){
                    // если есть фолловеры подписываться согласно настройкам
                    foreach ($aFollowers as $oFollower){
                        Tasks::addTask($oTask->cid,Tasks::TT_ADDFOLLOWER,$oFollower->who,'NOW',$settings,$oTask->id);
                    }
                    $isLastStep = TRUE;
                }
            }else{
                // его у нас нет, взять информацию о конкуренте
                try {
                    $pk = $ig->people->getUserIdForName($_oCompetitor->login);
                    if ($pk)
                        Tasks::addTask($oTask->cid, Tasks::TT_GETUSERINFO, $pk,'NOW','',$oTask->id);
                    else
                        Tasks::markUser4MF($_oCompetitor->login, -1);
                }catch (\Exception $e) {
                    echo date('Y-m-d H:i:s') . " CID:$oTask->id THMassFollowing Something went wrong: " . $e->getMessage() . "\n";
                }
                sleep(rand(2,7));
            }

        }
        //TODO: время рестарта по умному настроить
        if($isLastStep) {
            $oTask->startafter = date('Y-m-d H:i:s', strtotime('+1 day'));
            $oTask->update();
        }
    }
    function THAddFollower($oTask)
    {
        global $ig,$mainuserpk;
        // TODO: если пока нет такого пользователя у нас?
        $oIUser = InstaUsers::getUserByPK($oTask->usinglogin);
        if(!($oIUser && $oIUser->pk)){
            $oTask->startafter = date('Y-m-d H:i:s',strtotime('+1 day'));
            $oTask->update();

            Tasks::addTask($oTask->cid, Tasks::TT_GETUSERINFO, $oTask->usinglogin,'NOW','',$oTask->id,4);
            return false;
        }


        $oSettings = unserialize($oTask->settings);
        /*O:8:"stdClass":9:{s:3:"tid";s:1:"8";s:14:"isunfollowdays";b:1;s:12:"unfollowdays";s:1:"5";s:14:"islikelastpost";b:1;s:12:"likelastpost";s:1:"3";
        s:10:"isonlylike";b:1;s:11:"isignoresmm";b:1;s:12:"isignorecomm";b:0;s:11:"isignorebot";b:1;}*/
        $parenttask = $oTask->parenttaskid ? $oTask->parenttaskid : $oTask->id;

            // подписываемся с настройками
            if ($oSettings->isignoresmm == 1 || $oSettings->isignorecomm == 1) {

                if ($oSettings->isignoresmm == 1 && $oIUser->issmm == 1) {
                    $oTask->delete();
                    return false;
                }
                if ($oSettings->isignorecomm == 1 && $oIUser->iscommercial == 1) {
                    $oTask->delete();
                    return false;
                }
            }

            if($oSettings->islimitlang == 1){
                if(is_array($oSettings->atlangs)){
                    foreach ($oSettings->atlangs as $_lang){
                        $aCTLangs[] = $_lang->value;
                    }
                }else
                    $aCTLangs = explode(',',$oSettings->atlangs);
                if(count($aCTLangs)){
                    $aCTLangs = array_merge($aCTLangs,array(0,101));
                    if(!in_array($oIUser->langid,$aCTLangs)){
                        $oTask->delete();
                        return false;
                    }
                }
            }

            if($oSettings->islimitcity == 1){
                if(is_array($oSettings->atcities)){
                    foreach ($oSettings->atcities as $_city){
                        $aCTCity[] = $_city->value;
                    }
                }else
                    $aCTCity = explode(',',$oSettings->atcities);
                if(count($aCTCity)){
                    $aUserCities = $oIUser->getCities();
                    $aCTCity = array_merge($aCTCity,array(0));
                    $intersection = array_intersect($aUserCities,$aCTCity);
                    if(!($intersection && is_array($intersection) && count($intersection))){
                        $oTask->delete();
                        return false;
                    }
                }
            }

            if($oSettings->islimitsex == 1){
                if($oIUser->sex != -1){
                    if($oIUser->sex != $oSettings->sex){
                        $oTask->delete();
                        return false;
                    }
                }
            }




        // если только лайкать и не подписываться
        if ($oSettings->isonlylike == 1) {
            Tasks::addTask($oTask->cid, Tasks::TT_LIKEITEMS, $oTask->usinglogin, 'NOW', $oTask->settings, $parenttask);
        } else{
            $isFollowed = FALSE;
            try {
                if(!Followers::isFollowed($mainuserpk,$oTask->usinglogin,$oTask->parenttaskid)) {
                    $ig->people->follow($oTask->usinglogin);
                    $oTask->incStat();
                }else
                    $isFollowed = TRUE;
            } catch (\Exception $e) {
                echo date('Y-m-d H:i:s') . " CID:$oTask->cid THAddFollower Something went wrong: " . $e->getMessage() . "\n";
                $oTask->delete();
                return false;
            }

            // запоминаем на кого подписывались если из задания
            if($oTask->parenttaskid && !$isFollowed){
                Followers::addFollower($mainuserpk,$oTask->usinglogin,$oTask->parenttaskid);

            }

            if($oSettings->islikelastpost==1)
                Tasks::addTask($oTask->cid,Tasks::TT_LIKEITEMS,$oTask->usinglogin,'NOW',$oTask->settings,$parenttask);
            if($oSettings->isunfollowdays==1){
                $days = '+ '.intval($oSettings->unfollowdays).' days';
                Tasks::addTask($oTask->cid,Tasks::TT_DELFOLLOWER,$oTask->usinglogin,$days,$oTask->settings,$parenttask);
            }
        }



        $oTask->delete();
        return true;

//        $response = $ig->timeline->getUserFeed($userId, $maxId);
//
//        foreach ($response->getItems() as $item) {
//            $ig->media->like($item->getId());
//        }
    }

    function THLikeItemsOfIUser($oTask){

        global $ig,$mainuserpk,$icntLikesPerTime;
        $parenttask = $oTask->parenttaskid?$oTask->parenttaskid:$oTask->id;
        $oSettings = unserialize($oTask->settings);
        $iNumPosts = intval($oSettings->likelastpost);
        if($iNumPosts>5)
            $iNumPosts = 5;
        if($iNumPosts<=0)
            $iNumPosts = 1;
        $iLikedPosts = 0;
        //TODO:????
//        if(Likes::isLiked($mainuserpk,$oTask->usinglogin,$parenttask))
//            return;
        try {
            $response = $ig->timeline->getUserFeed($oTask->usinglogin, null);
            foreach ($response->getItems() as $item) {

                $ig->media->like($item->getId());
                Likes::addLiker($mainuserpk,$oTask->usinglogin,$parenttask);
                $iNumPosts--;
                $iLikedPosts++;
                $icntLikesPerTime--;
                if ($iNumPosts <= 0)
                    break;
                $slp = rand(3,5);
                sleep($slp);
            }
        }catch (\Exception $e) {

            echo date('Y-m-d H:i:s') . " CID:$oTask->cid THLikeItemsOfIUser $oTask->usinglogin Something went wrong: " . $e->getMessage() . "\n";
            if(strpos($e->getMessage(),'Feedback required')!==FALSE){
                //$aTimers[Tasks::TT_LIKEITEMS] = time() + 24 * 60 *60;
                echo date('Y-m-d H:i:s') . " CID:$oTask->cid Feedback required for LIKE next start:+".(24 * 60 *60)."sec\n";
                return (24 * 60 *60);
            }
        }

        $oTask->incStat($iLikedPosts);
        $oTask->delete();
        return rand(2,8);
    }

    function THUnfollow($oTask){
        //TODO: удалять все невыполненные такси, что с ним связаны
        global $ig;
        try {
            $ig->people->unfollow($oTask->usinglogin);
            $oTask->incStat();
        }catch (\Exception $e) {
            echo date('Y-m-d H:i:s') . " CID:$oTask->cid THUnfollow Something went wrong: " . $e->getMessage() . "\n";
        }
        $oTask->delete();
    }


    function THGetUserInfo($oTask){
        global $ig;
        $oInstaUser = null;
        $oSettings = unserialize($oTask->settings);
        if(filter_var($oTask->usinglogin, FILTER_VALIDATE_INT) === false ){
            $pk = $ig->people->getUserIdForName($oTask->usinglogin);
            $oTask->usinglogin = $pk;
        }

        try {
            $response = $ig->people->getInfoById($oTask->usinglogin);
            if ($response) {
                $aFollower = $response->getUser();

                $biography = $aFollower->getBiography();
                $username = $aFollower->getUsername();
                $profile_pic_url = $aFollower->getProfilePicUrl();
                $full_name = $aFollower->getFullName();
                $external_url = $aFollower->getExternalUrl();
            }else
                throw new Exception('No response from Instagram for PK: '.$oTask->usinglogin);

        } catch (\Exception $e) {
            echo date('Y-m-d H:i:s') . " CID:$oTask->cid THGetUserInfo Something went wrong: " . $e->getMessage() . "\n";
            $oTask->delete();
            return;
        }
        $oInstaUser = InstaUsers::getUserByPK($oTask->usinglogin);
        if($oInstaUser == null) {
            $oInstaUser = InstaUsers::addUser($oTask->usinglogin, $username, $full_name, $profile_pic_url, $external_url, $biography);
        }
        if($oInstaUser->pk) {
            try {
                $oInstaUser->username = $username;
                $oInstaUser->full_name = $full_name;
                $oInstaUser->profile_pic_url = $profile_pic_url;
                $oInstaUser->external_url = $external_url;
                $oInstaUser->biography = $biography;
                require_once 'data/Filter.php';
                //$iNumPosts = 20;
                $response = $ig->timeline->getUserFeed($oTask->usinglogin, null);
                $sText = '';
                $iSex = -1;
                $aItems = $response->getItems();
                foreach ($aItems as $item) {
                    $oCaption = $item->getCaption();
                    if($oCaption != null)
                        $sText .= ($oCaption->getText() . ' ');
//                    else
//                        continue;
//                    $iNumPosts--;
//                    if ($iNumPosts <= 0)
//                        break;
                }
                $maxId = $response->getNextMaxId();


                $aSex = stem($oInstaUser->full_name);
                if ($aSex !== false && is_array($aSex)) {
                    $iSex = $aSex['f'] > $aSex['m'] ? 0 : 1;
                    if ($aSex['f'] + $aSex['m'] == 0)
                        $iSex = -1;

                }
                if($iSex == -1){
                    $aSex = stem($oInstaUser->biography);
                    if ($aSex !== false && is_array($aSex)) {
                        $iSex = $aSex['f'] > $aSex['m'] ? 0 : 1;
                        if ($aSex['f'] + $aSex['m'] == 0)
                            $iSex = -1;

                    }
                }
                $ares = Lang::getLangByText($oInstaUser->full_name.' '.$oInstaUser->biography);
                if($ares && $ares->id){
                    $oInstaUser->langid = $ares->id;
                }
                    //echo count($aItems).'->'.$sText."\n";
                if($sText) {
                    $aCityIDs = getCityArrayFromText($sText);

                    if(intval($oSettings['cityid']))
                        $aCityIDs[] = intval($oSettings['cityid']);

                    if($aCityIDs && is_array($aCityIDs) && count($aCityIDs))
                        $oInstaUser->addCities($aCityIDs);

                }

                $aEmails = getEmailFromText($oInstaUser->biography);
                if(is_array($aEmails)){
                    $oInstaUser->email = array_shift($aEmails);
                }

                $aPhones = getPhoneFromText($oInstaUser->biography);
                if(is_array($aPhones)){
                    $oInstaUser->phone = array_shift($aPhones);
                }

                $oInstaUser->sex = $iSex;
                $sText = removeATTags($sText);
                $sText = removeHashTags($sText);
                if(trim($sText)=='' || $maxId === null)
                    $oInstaUser->isbot = 1;
                else
                    $oInstaUser->isbot = 0;
            } catch (\Exception $e) {
                //echo date('Y-m-d H:i:s') . " CID:$oTask->cid THGetUserSex Something went wrong: " . $e->getMessage() . "\n";
                $oInstaUser->sex = -1;

            }
            $oInstaUser->update();
        }
        if(trim($oTask->settings) == 'daily'){
            $oTask->startafter = date('Y-m-d H:i:s',strtotime('+1 day'));
            $oTask->update();
        }else
            $oTask->delete();
    }
    function THGetFollowers($oTask){
        global $ig;
        // взять фоловеров только утром
        $h = date('H');
//        if ($h > 14 || $h < 7)
//            return;

        $parenttask = $oTask->parenttaskid?$oTask->parenttaskid:$oTask->id;
        try {
            $pk = $ig->people->getUserIdForName($oTask->usinglogin);
            $rankToken = \InstagramAPI\Signatures::generateUUID();
            $oSettings = unserialize($oTask->settings);
            $maxId = null;
            do {

                $response = $ig->people->getFollowers($pk, $rankToken,null,$maxId);
                $_aF = $response->getUsers();
                foreach ($_aF as $_aFollower) {
                    $_userid = $_aFollower->getPk();
                    $oNewIUser = InstaUsers::getUserByPK($_userid);
                    if(!$oNewIUser || !$oNewIUser->pk) {

                        Tasks::addTask($oTask->cid,Tasks::TT_GETUSERINFO,$_userid,'+2 minutes','',$parenttask);

                    }

                    Followers::addFollower($_userid, $pk);

                }
                if($oTask->parenttaskid == 0)
                    $maxId = $response->getNextMaxId();

                $slp = rand(3,8);
                sleep($slp);
            } while ($maxId !== null); // Must use "!==" for comparison instead of "!=".


            if($oTask->parenttaskid) // если это пришло из массфоловинга, то нужно удалить
                $oTask->delete(); // в следующий раз добавится вновь
            $oTask->startafter = date('Y-m-d H:i:s',strtotime('+1 day'));
            $oTask->update();
        } catch (\Exception $e) {
            echo date('Y-m-d H:i:s') . " CID:$oTask->cid THGetFollowers Something went wrong: " . $e->getMessage() . "\n";
        }
    }

    function isLocked(){
        global $cid;
        # If lock file exists, check if stale.  If exists and is not stale, return TRUE
        # Else, create lock file and return FALSE.

        if( file_exists( LOCK_FILE ) )
        {
            # check if it's stale
            $lockingPID = trim( file_get_contents( LOCK_FILE ) );

            # Get all active PIDs.
            $pids = explode( "\n", trim( `ps -e | awk '{print $1}'` ) );

            # If PID is still active, return true
            if( in_array( $lockingPID, $pids ) )  return true;

            # Lock-file is stale, so kill it.  Then move on to re-creating it.
            echo date('Y-m-d H:i:s')." CID:$cid Removing stale lock file.\n";
            unlink( LOCK_FILE );
        }

        file_put_contents( LOCK_FILE, getmypid() . "\n" );
        return false;

    }

    
?>