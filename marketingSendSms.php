<?php 
require_once("conf_gold.php");
require_once("transp_gold.php");
$link = mssql_connect(SRV_IP, SRV_LOGIN, SRV_PASSWORD);

if (!$link) {die('No connect');}
mssql_select_db(SRV_DB, $link);

 $ph = '79873334980';
 $querysms=' select id, sms_text, phone from ofo_informer_sms (nolock) where state_sms=0 and phone = '.$ph;
 //$querysms=' select top 1 id, sms_text, phone from ofo_informer_sms (nolock) where state_sms=0  and phone = '79616380023';
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

	
if(!empty($phonesArray[])){

		for ($ii = 0; $ii <= $rows-1; $ii++)
			{
				/*$api = new Transport();
				$params = array(
				//"text" => "".mssql_result($qressms, $ii, 8)."",
				"text" => "".iconv ( 'Windows-1251' , 'UTF8' , mssql_result($qressms, $ii, 8) )."",
				"action" => "send"
				);
				$phones = array("".mssql_result($qressms, $ii, 3)."");
				$send = $api->send($params,$phones);*/	
				$phone = mssql_result($qressms, $ii, 2);
				$id = mssql_result($qressms, $ii, 0);
				$text = iconv ('Windows-1251' , 'UTF8' , mssql_result($qressms, $ii, 1));
				$service_url = 'mognet.sdlan.ru:8080/api/send';
                $curl = curl_init($service_url);
                $curl_post_data = array(
                        "script_id" => 6,
                        "category_id" => 4,
                        "operator_id" => 1,
                        "phone" => $phone,
                        // "phone" => "$sms["phone"]",
                        "source" => "SRO4NODENGI",
                        "text" => $text,
                        // "text" => "".iconv ( 'Windows-1251' , 'UTF8' , $sms["sms_text"] )."",
                        "priority" => 0
                );
                
                $curl_post_data = array(0 => $curl_post_data);   
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen(json_encode($curl_post_data)))); 
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
                
                $curl_response = curl_exec($curl);
                
                //var_dump($curl_response);
                if ($curl_response === false) {
                    $info = curl_getinfo($curl);
                    curl_close($curl);
					die('error occured during curl exec. Additioanl info: ' . var_dump($info));
                }
				
				$t = $text;
				$p = $phone;
				//$s = '(c) web sms_autoinformer';
				$s='(c) web new mognet';
				$provider = 'SmsGold';
				
                curl_close($curl);
                $decoded = json_decode($curl_response);
                if (isset($decoded->response->status) && $decoded->response->status == 'ERROR') {
					
					//Запись статуса отправки и оператора с таблицу стека
					$querystat = ' update ofo_informer_sms set is_send=0, operator=\''.$operator.'\', date_send=GETDATE() where id='.$id;
					$qresstat = mssql_query($querystat);
                
					//Запись лога
					$querylog="insert into tSMS_stats(D_DATETIME, I_TRUE_USER, S_MESSAGE, S_NUMBER,  S_MACHINE, S_QUERY, S_PROVIDER) values(GETDATE(),-1,'Error: $t','$p','$s','.mssql_result($qressms, $ii, 0).'), $provider";
					$qreslog= mssql_query($querylog);
                    die('error occured: ' . $decoded->response->errormessage);
                }

				//Существующие операторы через которые отправляем СМС: MyItSms, smsgold, zagruzka
				//Запись статуса отправки и оператора с таблицу стека
				$querystat = ' update ofo_informer_sms set is_send=1, operator=\''.$operator.'\', date_send=GETDATE() where id='.$id;
				$qresstat = mssql_query($querystat);
					
				//Запись лога
				$querylog="insert into tSMS_stats(D_DATETIME, I_TRUE_USER, S_MESSAGE, S_NUMBER,  S_MACHINE, S_QUERY, S_PROVIDER) values(GETDATE(),-1,'$t','$p','$s','.mssql_result($qressms, $ii, 0).'), $provider";
				$qreslog= mssql_query($querylog);
			}

    }else{
        die("Не удалось получить смс из базы даных");
	}

}