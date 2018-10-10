<?php
// 10.129.12.39/var/www/leadgid/; /usr/bin/php index.php 20160111 20160111
		
class leadgid
{
    public function process()
    {
        $src_path = __DIR__ . "/";
        $serverName = "10.129.12.35";
        if (!$conn = mssql_connect($serverName, "smuser_new", "smpassword_new")) {
            throw new Exception ('Ошибка при подключении к базе данных');
        }
        mssql_select_db('NOVSM2010', $conn);

        $sql = "select rco.ID_CLIENT, c.getparam
			from report_client_online rco
			join Clients c on c.ID = rco.ID_CLIENT
			where rco.dogovor is null
			and rco.partner = 'leadgid'
			and (select top 1 ID from Dogovor where ID_CLIENT = rco.ID_CLIENT and ID_STATUS = 0 order by ID) is not null
			and datediff(DAY, rco.date_request, (select top 1 D_DATEINPUT from Dogovor where ID_CLIENT = rco.ID_CLIENT and ID_STATUS = 0 order by ID)) < 31
		";
        $leadgid_reestr_questions_clients = mssql_query($sql);

		$mas = array();
		$val = array();
        while ($row = mssql_fetch_assoc($leadgid_reestr_questions_clients)) {
			$val['client_id'] = $row['ID_CLIENT'];
			$val['getparam'] = $row['getparam'];
			$mas[] = $val;
        }

		
		if (is_array($mas) && !empty($mas)){
			foreach ($mas as $val){
				$id_client = $val["client_id"];
				$getparam = $val["getparam"];
				//$str = substr(stristr($getparam, '?'), 1); 
				$str = $getparam;
				$str_arr = explode("&", $str);
				
				$id_transaction = '';
				foreach ($str_arr as $str_val){
					if (stripos($str_val, 'tid') !== false){
						$id_transaction = explode("=", $str_val)[1];
					}
				}
				
				//$service_url = 'http://go.leadgid.ru/aff_goal?a=lsr&goal_id=1657&adv_sub='.$id_client.'&transaction_id='.$id_transaction;
				//$service_url = 'http://go.leadgid.ru/aff_goal?a=lsr&goal_id=1657&adv_sub='.$id_client.'&transaction_id=10213ab53fd5287ebd0cbd8a51b78c';
				$service_url = 'http://my.leadgid.ru/stats/universal?offer=3235&subid='.$id_client.'&status=approved';
				//$service_url = 'https://go.leadgid.ru/aff_lsr?offer_id=3235&adv_sub='.$id_client.'&transaction_id='.$id_transaction.'&status=approved';
				
				$res = file_get_contents($service_url);
								
				//if ($res == 'success=true;'){
					$sql = "exec p_add_partner_request 2, '".$service_url."', ".$id_client.", 'leadgid'";
					mssql_query($sql);
				/*}
				else {
					$sql = "insert into partner_request_online(request, type, date) values('".$service_url."',0, getdate())";
					mssql_query($sql);
				}*/	
			}
		}
	}
}

$leadgid = new leadgid();

try {
    $leadgid->process();
} catch (Exception $exception) {
    echo $exception->getMessage();
}
