<?php
    set_time_limit(60*60*1);//1h
    date_default_timezone_set('Europe/Moscow');

    list($query,$cid) = explode('_',$argv[1]);
    require __DIR__.'/vendor/autoload.php';
    require_once 'data/Clients.php';
    $oClient = new Clients($cid);
    if(!$oClient->id)
        die( date('Y-m-d H:i:s')." CID:$cid Wrong CID.\n" );

    /////// CONFIG ///////
    $username = $oClient->login;
    $password = $oClient->password;

    $debug = false;
    $truncatedDebug = false;
    //////////////////////

    $ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);
    try {
        $ig->login($username, $password);
        $response = $ig->location->findPlaces($query);
        $dba = iDB::GetAdaptor();
        foreach ($response->getItems() as $items){
            $location = $items->getLocation();
            $name = $location->getName();
            $address = $location->getAddress();
            $address = trim($address)?trim($address):'';
            $lat = $location->getLat();
            $lng = $location->getLng();
            $fbplaceid = $location->getFacebookPlacesId();
            $pk = $location->getPk();
            $dba->insert('locations',
                array(
                    'pk'=>$pk,
                    'name'=>$name,
                    'address'=>$address,
                    'lat'=>$lat,
                    'lng'=>$lng,
                    'fbplaceid'=>$fbplaceid
                ),
                ' pk=pk '
            );
        }
    } catch (\Exception $e) {
        echo 'Something went wrong: '.$e->getMessage()."\n";
        exit(0);
    }