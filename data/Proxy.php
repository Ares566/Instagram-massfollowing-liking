<?php

require_once 'CoreData.php';
class Proxy extends CoreData{
    public $id = null;
    public $ip;
    public $port;
    public $login;
    public $password;
    public $isactive;

    protected $db_table = 'proxy';

    function __construct($id,$caching=false) {
        parent::__construct($id,$caching);
    }

    public function delete(){
        parent::deleteItem();
    }

    public function update(){
        parent::updateItem();
    }

    public static function getNewProxy(){
        $dba = iDB::GetAdaptor();
        $aUsedProxies = $dba->getObjectList('SELECT
              proxy.id,
              COUNT(clients.id) AS Total
            FROM
              proxy
            LEFT JOIN clients ON clients.proxyid = proxy.id
            WHERE proxy.isactive=1
            GROUP BY proxy.id ORDER BY Total');
        $oMinUsedProxy = array_shift($aUsedProxies);
        return new Proxy($oMinUsedProxy->id);
    }

    function test(){
        $url = 'https://www.instagram.com/';
        $ch = curl_init();
        $timeout = 5; // set to zero for no timeout
        try {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_PROXY, "http://".$this->ip); //your proxy url
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->port); // your proxy port number
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->login.":".$this->password); //username:pass
            $file_contents = curl_exec($ch);
            curl_close($ch);
        }catch (\Error $e){
            return false;
        }
        return $file_contents===FALSE?FALSE:TRUE;
    }

}