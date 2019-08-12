<?
	// Main script
	// Launch all clients and route to clienttask.php

    require_once 'data/Clients.php';

    $aAllClients = Clients::getAllClients();
    foreach ($aAllClients as $oClient){
        if(!$oClient->isapproved)
            continue;
        $command = 'nohup php ./clienttask.php client_'.$oClient->id.' >> run/robots.log 2>&1 &';
        exec($command ,$op);
    }

