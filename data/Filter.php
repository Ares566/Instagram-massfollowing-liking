<?

/**
 * Detect location by name
 * @param $name
 * @return array
 */
function getLocationLike($name)
{
    require_once 'DB.php';
    $dba = iDB::GetAdaptor();
    $name = mb_strtolower($name);
    $aMainLocations = $dba->getObjectList("SELECT * FROM locations WHERE name LIKE '$name%' LIMIT 5");
    $aSecLocations = $dba->getObjectList("SELECT * FROM locations WHERE name LIKE '%$name%' ORDER BY RAND() LIMIT 9");
    $aRetVal = [];
    $aTTT = array_merge($aMainLocations, $aSecLocations);
    foreach ($aTTT as $_Loc) {
        $aRetVal[$_Loc->pk] = $_Loc->name;
    }
    return $aRetVal;
}

/**
 * Extract email from bio or Instagram post
 * @param $text
 * @return bool
 */
function getEmailFromText($text)
{
    $iENum = preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $text, $matches);
    if ($iENum)
        return $matches[0];
    return false;
}

/**
 * Extract phone number
 * @param $text
 * @return bool
 */
function getPhoneFromText($text)
{
    $text = strtr($text, array('(' => '', ')' => ''));
    $iPhNum = preg_match_all('/\d{3}\s*-*\s*\d{3}\s*-*\s*\d{4}/', $text, $phone_matches);
    if ($iPhNum)
        return $phone_matches[0];
    return false;
}

/**
 *
 * Get city from Instaram post or bio
 * @param $text
 * @return int
 */
function getCityFromText($text)
{
    require_once 'DB.php';
    $dba = iDB::GetAdaptor();
    $aCities = $dba->getObjectList('SELECT * FROM cities');
    foreach ($aCities as $oCity) {
        $aWords = array();
        $aWords[] = mb_strtolower($oCity->name);
        $aWords[] = textTranslite(mb_strtolower($oCity->name));
        if (contains(mb_strtolower($text), $aWords))
            return $oCity->id;
    }
    return 0;
}

function getCityArrayFromText($text)
{
    require_once 'DB.php';
    $dba = iDB::GetAdaptor();
    $aCities = $dba->getObjectList('SELECT * FROM cities');
    $aRetval = array();
    $haystack = $text;
    foreach ($aCities as $oCity) {
        $aWords = array();
        $oCity->name = mb_substr($oCity->name, 0, -1);
        $aWords[] = $oCity->name;
        $aWords[] = mb_strtolower($oCity->name) . ' ';
        $aWords[] = textTranslite(mb_strtolower($oCity->name));
        $aWords[] = textTranslite($oCity->name);
//            if(trim($oCity->nickname)){
//                $aNNC = explode(',',$oCity->nickname);
//                foreach ($aNNC as $scityn){
//                    $aWords[] = mb_strtolower(trim($scityn));
//                }
//            }
        if (contains($haystack, $aWords))
            $aRetval[] = $oCity->id;
    }
    return $aRetval;
}

function isSMMTxt($text)
{
    $aSMM = array(
        'smm', 'смм', 'таргет', 'монетизация', 'реклама', 'продвижение', 'продвигаю'
    );
    return contains(mb_strtolower($text), $aSMM);
}

/**
 * Check by text if account is commercial
 * @param $text
 * @return bool
 */
function isCommercialTxt($text)
{
    $aCommercial = array(
        'магазин', 'интернет', 'цены', 'цена', 'купить', 'покупка', 'продать', 'продаж', 'звони', 'звоните', 'пиши',
        'пишите', 'доставка', 'заказ', 'заказы', 'заказывайте', 'оригинал', 'работа', 'одежда', 'юбки', 'кольца',
        'белье', 'сарафаны', 'наращивание', 'волосы', 'маникюр', 'педикюр', 'запись', 'лак', 'макияж', 'парфюм',
        'парфюмерия', 'китай', 'отправка', 'массаж', 'игрушки', 'организациЯ', 'прием', 'эпиляция', 'студия', 'салон',
        'тур', 'туризм', 'оператор', 'отдых', 'услуг', 'реклама', 'деньги', 'помощь', 'займ', 'бесплатная', 'оплата',
        'аккаунт', 'бутик', 'шоу-рум', 'оригиналы', 'кафе', 'еда', 'ресторан', 'студиЯкрасоты', 'студиядизайна',
        'салонкрасоты', 'дизайн', 'недвижимость', 'запчасти', 'автозапчасти', 'шоп', 'подарки', 'смс', 'смс ', 'купити',
        'продати', 'телефонуйте', 'замовлення', 'замовляйте', 'одяг', 'спідниці', 'кільця', 'білизна', 'сарафани',
        'студія', 'нарощування', 'волосся', 'манікюр', 'іграшки', 'епіляція', 'послуги', 'гроші', 'допомога', 'ціни',
        'ціна', 'безкоштовна', 'оригінали', 'їжа', 'запис', 'парфуми', 'відправка', 'організація', 'прийом', 'відпочинок',
        'дзвони', 'позику', 'питання', 'бутік', 'шоу рум', 'студіякраси', 'студіядізайна', 'салон краси', 'нерухомість',
        'запчастини', 'автозапчастини', 'производство', 'поставк', 'обслуживание', 'бизнес', 'прибыл', 'барахолка', 'объявлени',
        'грузоперевозки', 'order', 'shipping', 'shop', 'fashion', 'photographer', 'tattoo', 'price', 'sell', 'direct', 'show'
    );
    return contains(mb_strtolower($text), $aCommercial);
}

/**
 *
 * Detect language of account by analizing bio and posts
 * @param $text
 * @return array
 */
function detectLang($text)
{
    require_once __DIR__ . '/../vendor/autoload.php';


    $ld = new LanguageDetection\Language(['de', 'en', 'es', 'ru', 'fr']);

    $aRes = $ld->detect($text)->bestResults()->close();
    return $aRes;
}

/**
 *
 * Detect sex of account owner by analyzing his/her posts
 *
 * @param $text
 * @return array|bool
 */
function stem($text)
{
    $text = mb_strtolower($text);
    $text = str_replace(array("\r\n", "\r", "\n", ".", "_", "-"), ' ', $text);
    $text = removeHashTags($text);
    $text = removeATTags($text);
    $text = nameTranslite($text);

    $cmd = "./mystem2 -id --format=json";

    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w")
    );

    $process = proc_open($cmd, $descriptorspec, $pipes);

    if (is_resource($process)) {

        fwrite($pipes[0], $text);
        fclose($pipes[0]);

        $pdf_content = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $return_value = proc_close($process);

        $aStem = json_decode($pdf_content);
        //print_r($aStem);
        if (!count($aStem))
            return false;
        $aSex = ['f' => 0, 'm' => 0];
        foreach ($aStem as $item) {
            if ($item && $item->analysis) {
                $gr = $item->analysis[0]->gr;
                if (strpos($gr, 'имя')) {
                    //echo $item->analysis[0]->lex.'<br/>';
                    if (strpos($gr, 'жен') !== FALSE)
                        $aSex['f'] += 1;
                    else
                        $aSex['m'] += 1;
                }

            }

        }
        return $aSex;
    }

}

//    function stem($text){
//        $cmd = "./mystem2 -id --format=json";
//
//        $descriptorspec = array(
//            0 => array("pipe", "r"),
//            1 => array("pipe", "w")
//        );
//
//        $process = proc_open($cmd, $descriptorspec, $pipes);
//
//        if (is_resource($process)) {
//
//            fwrite($pipes[0], $text);
//            fclose($pipes[0]);
//
//            $pdf_content = stream_get_contents($pipes[1]);
//            fclose($pipes[1]);
//
//            $return_value = proc_close($process);
//
//            $aStem = json_decode($pdf_content);
//            $aSex = ['m'=>0,'f'=>0];
//            //$sCity = '';
//            if(!count($aStem))
//                return false;
//            foreach ($aStem as $item){
//                if($item && $item->analysis) {
//                    $gr = $item->analysis[0]->gr;
//                    if (strpos($gr, 'V,') !== FALSE) {
//                        // глагол
//                        if (strpos($gr, 'жен') !== FALSE)
//                            $aSex['f'] += 1;
//                        else
//                            $aSex['m'] += 1;
//                    }
//                    if (strpos($gr, 'гео') !== FALSE) {
//                        $sCity = $item->analysis[0]->lex;
//                    }
//                }
//
//            }
//
//            return $aSex;
//            //echo $sCity;
//        }
//        return false;
//    }
function contains($string, Array $search, $caseInsensitive = false)
{
    $exp = '/'
        . implode('|', array_map('preg_quote', $search))
        . ($caseInsensitive ? '/i' : '/');
    return preg_match($exp, $string) ? true : false;
}

function textTranslite($str)
{
    $str = mb_strtolower($str);
    $str = preg_replace('/[^\w\d ]/ui', '', $str);
    static $tbl = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'g', 'з' => 'z',
        'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
        'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ы' => 'i', 'э' => 'e', 'ё' => "yo", 'х' => "h",
        'ц' => "ts", 'ч' => "ch", 'ш' => "sh", 'щ' => "shch", 'ъ' => "", 'ь' => "", 'ю' => "yu", 'я' => "ya",
        "'" => "", ' ' => '-', '_' => '-', '.' => '-', "\"" => "", "?" => "", ":" => ""
    );

    return strtr($str, $tbl);
}


function nameTranslite($str, $fromeng = true)
{
    $str = preg_replace('/[^\w\d ]/ui', ' ', $str);
    static $tbl = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'g', 'з' => 'z',
        'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
        'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ы' => 'i', 'э' => 'e', 'А' => 'A',
        'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ж' => 'G', 'З' => 'Z', 'И' => 'I',
        'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R',
        'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Ы' => 'I', 'Э' => 'E', 'ё' => "yo", 'х' => "h",
        'ц' => "ts", 'ч' => "ch", 'ш' => "sh", 'щ' => "shch", 'ъ' => "", 'ь' => "", 'ю' => "yu", 'я' => "ya",
        'Ё' => "YO", 'Х' => "H", 'Ц' => "TS", 'Ч' => "CH", 'Ш' => "SH", 'Щ' => "SHCH", 'Ъ' => "", 'Ь' => "",
        'Ю' => "YU", 'Я' => "YA", "'" => "", ' ' => '_', '.' => '_', "\"" => "", "?" => "", ":" => ""
    );
    static $tbl2 = array(
        'a' => 'а', 'b' => 'б', 'c' => 'к', 'd' => 'д', 'e' => 'е',
        'f' => 'ф', 'g' => 'г', 'h' => 'х', 'i' => 'и', 'ju' => 'ю', 'j' => 'ж',
        'k' => 'к', 'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о',
        'p' => 'п', 'q' => 'к', 'r' => 'р', 'sh' => 'ш', 's' => 'с', 't' => 'т',
        'u' => 'у', 'v' => 'в', 'w' => 'в', 'x' => 'э', 'ya' => 'я', 'y' => 'й',
        'z' => 'з'
    );

    return $fromeng ? strtr($str, $tbl2) : strtr($str, $tbl);
}

function removeHashTags($text)
{
    $re = '/#\S+\s*/';
    return preg_replace($re, '', $text);
}

function removeATTags($text)
{
    $re = '/@\S+\s*/';
    return preg_replace($re, '', $text);
}

function pluralForm($n, $form1, $form2, $form5)
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $form5;
    if ($n1 > 1 && $n1 < 5) return $form2;
    if ($n1 == 1) return $form1;
    return $form5;
}

/**
 * Форматирует время/дату
 * Example:
 *    20 минут назад
 *   2 часа назад
 *   3 дня назад
 *
 * @param string $datetime
 * @return string
 */
function formatDateTime1($datetime)
{
    $time_str = '';
    $unix_time = strtotime($datetime);
    $interval = time() - $unix_time;
    $hours = round($interval / 3600);
    if (round($interval / 60) < 60) {
        $minutes = round($interval / 60);
        $time_str = $minutes . ' минут';
        if ($minutes < 5 || $minutes > 20) {
            switch ($minutes % 10) {
                case 1:
                    $time_str .= 'у';
                    break;
                case 2:
                case 3:
                case 4:
                    $time_str .= 'ы';
                    break;
            }
        }
        $time_str .= ' назад';
    } elseif ($hours < 24) {
        $time_str = $hours . ' час';
        if ($hours < 5 || $hours > 20) {
            switch ($hours % 10) {
                case 1:
                    $time_str .= '';
                    break;
                case 2:
                case 3:
                case 4:
                    $time_str .= 'а';
                    break;
                default:
                    $time_str .= 'ов';
            }
        } else
            $time_str .= 'ов';
        $time_str .= ' назад';
    } elseif ($hours >= 24 && $hours < 48) {
        $time_str = 'вчера';
    } else {
        $days = round($hours / 24);

        $time_str = formatDays($days) . ' назад';
    }
    return $time_str;
}

/**
 * 4 дня
 * @param integer $days
 * @return string
 */
function formatDays($days)
{
    $time_str = $days . ' ';

    if ($days < 5 || $days > 20) {
        switch ($days % 10) {
            case 1:
                $time_str .= 'день';
                break;
            case 2:
            case 3:
            case 4:
                $time_str .= 'дня';
                break;
            default:
                $time_str .= 'дней';
        }
    } else
        $time_str .= 'дней';
    return $time_str;
}
