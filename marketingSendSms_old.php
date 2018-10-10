<?php
/* 
 *  Created by Lobanov Dmitriy at 02.09.2016
 */

//CRON для отправки смс маркетологов

function getSslPage($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function parsePage($page){
    preg_match_all('/<tr>.*<\/tr>/i', $page, $tableString);
    foreach($tableString[0] as $key => $value){
        preg_match_all('/<td>.*<\/td>/Ui', $value, $subString);
        foreach($subString[0] as $k => $v){
            $subString[0][$k] = trim(str_replace(array("<td>","</td>"), "", iconv("windows-1251", "utf-8", $v)));
        }
        $tableString[0][$key] = $subString[0];
    }
    
    return $tableString[0];
}

function getSmsFromBD(){
    require_once("servicesConf.php");
    $link = mssql_connect(SRV_IP, SRV_LOGIN, SRV_PASSWORD);
    if (!$link) {die('No connect');}
	mssql_select_db(SRV_DB, $link);

    $querysms=' select top(50) id, sms_text, phone from ofo_informer_sms (nolock) where state_sms=0 ';
    $qressms= mssql_query($querysms);
    $rows = mssql_num_rows($qressms);
    
    for($i = 0; $i < $rows; $i++){
        $sms = mssql_fetch_assoc($qressms);
        $ids[] = $sms["id"];
        if (function_exists("iconv")){
            $sms["sms_text"] =  iconv("windows-1251","utf-8", $sms["sms_text"]);
        }else{
            die("Не удается перекодировать переданные параметры в кодировку utf-8 - отсутствует функция iconv");
        }
        $phonesArray[] = $sms;
    }
    $idsStr = implode(",", $ids);
    
    $querystat = ' update ofo_informer_sms set state_sms=1 where id in('.$idsStr.') ';
    $qresstat = mssql_query($querystat);
    
    return $phonesArray;
}

function groupNumbers($sms, $register){
    $groups = array();

    foreach($sms as $key => $smsData){
        $match = false;
        $def = substr($smsData["phone"], 1, 3);
        $range = substr($smsData["phone"], 4);
        foreach($register as $data){
            if($data[0] == $def && $range >= $data[1] && $range <= $data[2]){
                $match = true; 
                break;
            }
        }
        $match ? $groups[$data[4]][$key] = $smsData : $groups["other"][$key] = $smsData; 

        if($key%20 == 0){
            sleep(2);
        }
    }
    
    return $groups;
}

//Отправка смс через кабинеты
function sendAllSms($groups){
    require_once("servicesConf.php");
    require_once("serviceFactory.php"); 
    
    //$service = ServiceFactory::createService("mts");
    //$sms = array("id"=>1,"sms_text"=>'Тестовая "смс"', "phone"=>"89511234568");
    //var_dump($service->isActive());
    //print_r($service->getName());
    //print_r($service->getConf());
    
    foreach($groups as $key => $phones){
        $i = 0;
        
        //Создание сервиса для отправки смс в зависимости от оператора 
        if($key == "ООО \"Т2 Мобайл\""){
            $service = ServiceFactory::createService("tele2");
        }else if($key == "ОАО \"Мобильные ТелеСистемы\"" || $key == "ПАО \"Мобильные ТелеСистемы\""){
            $service = ServiceFactory::createService("mts");
        }else{
            $service = ServiceFactory::createService();
        }
        
        if(!$service->isActive()){
            $service = ServiceFactory::createService();
        }
        
        foreach($phones as $phone){
            $i++;

            //Отправка смс через сервис
            $request = $service->sendSMS($phone);
            if($request === true){
                //Запись статуса отправки и оператора с таблицу стека
                $operator = $service->getName();
                $querystat = ' update ofo_informer_sms set is_send=1, operator=\''.$operator.'\', date_send=GETDATE() where id='.$phone["id"];
                $qresstat = mssql_query($querystat);

                //Запись лога
                $t = iconv("utf-8", "windows-1251", $phone["sms_text"]);
                $p = $phone["phone"];
                $l = $phone["id"];
                $s = '(c) web sms_autoinformer';

                $querylog="insert into tSMS_stats(D_DATETIME, I_TRUE_USER, S_MESSAGE, S_NUMBER,  S_MACHINE, S_QUERY) values(GETDATE(),-1,'$t','$p','$s','$l')";
                $qreslog= mssql_query($querylog);
            }else{ 
                
                $operator = $service->getName();
                $querystat = ' update ofo_informer_sms set is_send=0, operator=\''.$operator.'\', date_send=GETDATE() where id='.$phone["id"];
                $qresstat = mssql_query($querystat);
                
                //Запись лога
                $t = iconv("utf-8", "windows-1251", $request);
                $p = $phone["phone"];
                $l = $phone["id"];
                $s = '(c) web sms_autoinformer';

                $querylog="insert into tSMS_stats(D_DATETIME, I_TRUE_USER, S_MESSAGE, S_NUMBER,  S_MACHINE, S_QUERY) values(GETDATE(),-1,'Error: $t','$p','$s','$l')";
                $qreslog= mssql_query($querylog);
            }
            
            if($i == 10){
                sleep(1);
                $i = 0;
            }
        }
    }
}

//Получение номеров из стека БД2010 и проставление статуса изъятия номеров
$sms = getSmsFromBD();
if(!empty($sms)){
    //Парсинг реестара диапазонов номеров по операторам
    $page = getSslPage("https://www.rossvyaz.ru/docs/articles/DEF-9x.html");
    if(!$page){
        require_once("servicesConf.php");
        $link = mssql_connect(SRV_IP, SRV_LOGIN, SRV_PASSWORD);
        mssql_select_db(SRV_DB, $link);
        $t = iconv("utf-8", "windows-1251", "Не удалось получить реестр выделенных номеров");
        $s = '(c) web sms_autoinformer';
        $querylog="insert into tSMS_stats(D_DATETIME, I_TRUE_USER, S_MESSAGE, S_MACHINE) values(GETDATE(),-1,'ParseError: $t','$s')";
        $qreslog= mssql_query($querylog);
        die("Не удалось получить данные страницы реестра выделенных номеров");

    }
    $register = parsePage($page);
    if(!is_array($register)){
        die("Не удалось получить реестр выделенных номеров");
    }
    //Группировка номеров по операторам       
    $groups = groupNumbers($sms, $register);
    //Отправка смс через кабинеты
    sendAllSms($groups);
}

/*
$page = getSslPage("https://www.rossvyaz.ru/docs/articles/DEF-9x.html");
$register = parsePage($page);
$operators = array();
foreach($register as $v){
    $operators[] = $v[4];
}
$unique = array_unique($operators);
sort($unique, SORT_STRING);
echo '<pre>';
print_r($unique);
echo '</pre>';
*/