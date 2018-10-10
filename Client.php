<?php
class Client {
    public function addClientTeleport($request, $response) {
        $data_all = $request->getParsedBody();
        $data = $data_all["data"];
		
		$all_post_param = (array)json_decode($data);
		
		$phone = $all_post_param["phone"];

        $sql = 'select * from list_phone_clients
            where s_phone_code + s_phone_number = :phone
            and i_type = 0 and i_sms = 1
            and ((is_disable is NULL) or (is_disable = 0))';
		
		$data =array(
            'phone' =>  $phone
        );

        $sth = Database::execQuery($sql, $data);
        $qRes = $sth->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($qRes)){

            $resp = array(
                'result' => '0',
                'description' => 'Клиент с таким номером уже существует в системе'
            );

            Logger::setLog('/client/add/phone_teleport', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));
			//Logger::setLog('/client/add/phone_teleport', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', json_encode($all_post_param, JSON_UNESCAPED_UNICODE));

			
            return $response->withJson($resp);
        }
        else{
            $sql = 'exec p_php_add_new_client_teleport :id, :S_F, :S_I, :S_O, :D_BD, :S_ADR1_REG, :S_ADR1_GOR, :phone, :amount, :period';

            $data =array(
                'id' =>  $all_post_param["id"],
                'S_F' =>  iconv( 'UTF-8','WINDOWS-1251', $all_post_param["last_name"]),
                'S_I' =>  iconv( 'UTF-8','WINDOWS-1251', $all_post_param["first_name"]),
                'S_O' =>  iconv( 'UTF-8','WINDOWS-1251', $all_post_param["middle_name"]),
                'D_BD' =>  $all_post_param["birthday"],
                'S_ADR1_REG' => iconv( 'UTF-8','WINDOWS-1251', $all_post_param["residential_region"]),
                'S_ADR1_GOR' =>  iconv( 'UTF-8','WINDOWS-1251', $all_post_param["residential_city"]),
                'phone' => $all_post_param["phone"],
                'amount' => $all_post_param["amount"],
                'period' => $all_post_param["period"]
            );
            $sth = Database::execQuery($sql, $data);
            $qRes = $sth->fetchAll(PDO::FETCH_ASSOC);
            $id_client = (int)$qRes[0]['id'];

            $resp = array(
                'result' => '1',
                'aid' => $id_client
            );

            Logger::setLog('/client/add/phone_teleport', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));
            return $response->withJson($resp);

        }
    }

    public function addClientFromsite($request, $response) {
        $all_post_param = $request->getParsedBody();
        $client = $all_post_param["client"];
        $passports = $all_post_param["passports"];
        $dop = $all_post_param["dop"];

        $cookies = '';
        foreach ($dop["cookies"] as $key => $value)
            $cookies .= $key . '=' . $value.';';
        $cookies = substr($cookies, 0, -1);

        $sql = 'exec p_php_add_new_client_from_site :S_F, :S_I, :S_O, :S_ADR1_REG, :S_ADR1_GOR, :pas_ser, :pas_num, :amount, :period, :address, :phone, :getparam, :cookies';
        $data =array(
            'S_F' =>  iconv( 'UTF-8','WINDOWS-1251', $client["S_F"]),
            'S_I' =>  iconv( 'UTF-8','WINDOWS-1251', $client["S_I"]),
            'S_O' =>  iconv( 'UTF-8','WINDOWS-1251', $client["S_O"]),
            'S_ADR1_REG' => iconv( 'UTF-8','WINDOWS-1251', $client["S_ADR1_REG"]),
            'S_ADR1_GOR' =>  iconv( 'UTF-8','WINDOWS-1251', $client["S_ADR1_GOR"]),
            'pas_ser' => $passports["series"],
            'pas_num' => $passports["number"],
            'amount' => $dop["sum"],
            'period' => $dop["period"],
            'address' => $dop["address"],
            'phone' => $dop["phone"],
            'getparam' => $dop["get_param"],
            'cookies' => $cookies,
        );

        $sth = Database::execQuery($sql, $data);
        $qRes = $sth->fetchAll(PDO::FETCH_ASSOC);

        $resp = array(
            'resp' => (int)$qRes[0]['resp'],
            'aid' => $qRes[0]['com']
        );
        Logger::setLog('/client/add/pas_fromsite', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

        return $response->withJson($resp);

    }

    public function autorizationPhoneApp($request, $response) {
        $all_post_param = $request->getParsedBody();

        if (isset($all_post_param["phone"]))
        {
            $phone = $all_post_param["phone"];
            $pattern = "/[+][7][0-9]{10}$/i";
            if (preg_match($pattern, $phone))
            {
                
				$phone = substr($phone, 1);
				
				$sql = 'exec p_php_autorization_app_count_phone :phone';
				$data =array(
                    'phone' => $phone
                );

				$sth = Database::execQuery($sql, $data);
				$cnt = $sth->fetchAll(PDO::FETCH_ASSOC)[0]["cnt"];

				if ($cnt < 2)
				{
				
					$sql = 'exec p_php_autorization_app_profile :phone';
					$data =array(
						'phone' => $phone
					);

					$sth = Database::execQuery($sql, $data);
					$profile = $sth->fetchAll(PDO::FETCH_ASSOC);
					
					

					if (!empty($profile))
					{
						$resp = array();
						$profile = $profile[0];

						$resp["id"] = (int)$profile["ID"];
						$resp["profile"]["name"] = iconv('WINDOWS-1251', 'UTF-8', $profile["S_F"].' '.$profile["S_I"].' '.$profile["S_O"]);
						$resp["profile"]["date_reg"] = date(DATE_ISO8601, strtotime($profile["D_DATEREG"]));
						$resp["profile"]["date_bd"] = date(DATE_ISO8601, strtotime($profile["D_BD"]));
						$resp["profile"]["email"] = $profile["S_KONT_EMAIL"];

						$sql = 'exec p_php_autorization_app_loan :id_client';
						$data =array(
							'id_client' =>  $profile["ID"],
						);
						$sth = Database::execQuery($sql, $data);
						$loan = $sth->fetchAll(PDO::FETCH_ASSOC);
						

						if (!empty($loan))
						{
							$loan = $loan[0];
							$resp["loan"]["contract_id"] = (int)$loan["ID"];
							$resp["loan"]["number"] = (int)$loan["ID_DOGOVOR"];
							$resp["loan"]["summ"] = (float)$loan["M_SUMMA"];
							$resp["loan"]["interest"] = (float)$loan["expPercent"];
							$resp["loan"]["interest_rate"] = (float)$loan["I_STAVKA"];
							$resp["loan"]["date"] = date(DATE_ISO8601, strtotime($loan["D_DATEINPUT"]));
							$resp["loan"]["maturity_date"] = date(DATE_ISO8601, strtotime($loan["date_exp"]));
							$resp["loan"]["actual_dept"] = (float)$loan["exp"];
							$resp["loan"]["payment_available_since"] = date(DATE_ISO8601, strtotime($loan["payment_available_since"]));
							$resp["loan"]["loanBalance"] = (float)$loan["loanBalance"];
						}
						else
						{
							$resp["loan"]["contract_id"] = null;
							$resp["loan"]["number"] = null;
							$resp["loan"]["summ"] = null;
							$resp["loan"]["interest"] = null;
							$resp["loan"]["interest_rate"] = null;
							$resp["loan"]["date"] = null;
							$resp["loan"]["maturity_date"] = null;
							$resp["loan"]["actual_dept"] = null;
							$resp["loan"]["payment_available_since"] = null;
							$resp["loan"]["loanBalance"] = null;
							//$resp["loan"] = array();
						}

						$sql = 'p_php_autorization_app_limits :id_client';
						$data =array(
							'id_client' =>  $profile["ID"],
						);
						$sth = Database::execQuery($sql, $data);
						$limits = $sth->fetchAll(PDO::FETCH_ASSOC)[0];
						die('123');

						$resp["limits"]["prolongation"]["min_value"] = (int)$limits["min_days"];
						$resp["limits"]["prolongation"]["max_value"] = (int)$limits["max_days"];

						$resp["limits"]["increase"]["min_value"] = (int)$limits["min_sum"];
						$resp["limits"]["increase"]["max_value"] = (int)$limits["max_sum"];
						$resp["limits"]["interest_rate"] = (float)$limits["procent"];
						
						//die($profile["ID"]);
						$sql = 'exec p_php_autorization_app_client_lk :id_client';
						$data =array(
							'id_client' =>  $profile["ID"]
						);
						Database::execQuery($sql, $data);
						
						Logger::setLog('/client/autorization/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));
						return $response->withJson($resp);

					}
					else
					{
						$resp = array(
							'detail' => 'Пользователь не найден'
						);

						Logger::setLog('/client/autorization/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

						return $response->withJson($resp)
							->withStatus(404);
					}
				}
				else
				{
					$resp = array(
						'detail' => 'Ваш номер телефона указан в нескольких анкетах. Обратитесь в офис'
					);

					Logger::setLog('/client/autorization/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

					return $response->withJson($resp)
							->withStatus(400);
				}
            }
            else
            {
                $resp = array(
                    'detail' => 'Не валидный формат телефона'
                );

                Logger::setLog('/client/autorization/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

                return $response->withJson($resp)
                    ->withStatus(400);
            }
        }
        else
        {
            $resp = array(
                'detail' => 'no required parameters'
            );

            Logger::setLog('/client/autorization/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

            return $response->withJson($resp)
                ->withStatus(404);
        }
    }

    public function limitsApp($request, $response) {
        $all_post_param = $request->getParsedBody();

        if (isset($all_post_param["user_id"]))
        {
            $pattern = "/[0-9]$/i";
            if (preg_match($pattern, $all_post_param["user_id"]))
            {
                $sql = 'select count(*) cnt from Clients where ID = :id_client';
                $data =array(
                    'id_client' =>  $all_post_param["user_id"]
                );

                $sth = Database::execQuery($sql, $data);
                $res = $sth->fetchAll(PDO::FETCH_ASSOC);

                if ($res[0]["cnt"] > 0)
                {
                    $sql = 'p_php_autorization_app_limits :id_client';
                    $data =array(
                        'id_client' =>  $all_post_param["user_id"],
                    );
                    $sth = Database::execQuery($sql, $data);
                    $limits = $sth->fetchAll(PDO::FETCH_ASSOC)[0];

                    $resp["prolongation"]["min_value"] = (int)$limits["min_days"];
                    $resp["prolongation"]["max_value"] = (int)$limits["max_days"];

                    $resp["increase"]["min_value"] = (int)$limits["min_sum"];
                    $resp["increase"]["max_value"] = (int)$limits["max_sum"];
                    $resp["interest_rate"] = (float)$limits["procent"];

                    Logger::setLog('/client/autorization/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));
                    return $response->withJson($resp);
                }
                else
                {
                    $resp = array(
                        'detail' => 'User not found'
                    );

                    Logger::setLog('/client/limits/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

                    return $response->withJson($resp)
                        ->withStatus(404);
                }
            }
            else
            {
                $resp = array(
                    'user_id' => 'Invalid ID'
                );

                Logger::setLog('/client/limits/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

                return $response->withJson($resp)
                    ->withStatus(400);
            }
        }
        else
        {
            $resp = array(
                'detail' => 'no required parameters'
            );

            Logger::setLog('/client/limits/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

            return $response->withJson($resp)
                ->withStatus(404);
        }
    }

    public function cardListApp($request, $response) {
        $all_post_param = $request->getParsedBody();

        if (isset($all_post_param["user_id"]))
        {
            $pattern = "/[0-9]$/i";
            if (preg_match($pattern, $all_post_param["user_id"]))
            {
                $sql = 'select count(*) cnt from Clients where ID = :id_client';
                $data =array(
                    'id_client' =>  $all_post_param["user_id"]
                );

                $sth = Database::execQuery($sql, $data);
                $res = $sth->fetchAll(PDO::FETCH_ASSOC);

                if ($res[0]["cnt"] > 0)
                {
                    $sql = 'exec p_php_autorization_app_cards :id_client';
                    $data =array(
                        'id_client' =>  $all_post_param["user_id"],
                    );
                    $sth = Database::execQuery($sql, $data);
                    $cards = $sth->fetchAll(PDO::FETCH_ASSOC); //заглушка, т.к. нет привязанных карт

                    if (!empty($cards))
                    {
                        $resp = array();
                        foreach ($cards as $v) {
                            $v["deleted"] = ($v["deleted"] === 'True');
                            $v["prime"] = ($v["prime"] === 'True');
                            array_push($resp, $v);
                        }
                    }
                    else
                    {
                        $resp = array();
                    }
                    
                    Logger::setLog('/client/autorization/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));
                    return $response->withJson($resp);
                }
                else
                {
                    $resp = array(
                        'detail' => 'User not found'
                    );

                    Logger::setLog('/client/limits/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

                    return $response->withJson($resp)
                        ->withStatus(404);
                }
            }
            else
            {
                $resp = array(
                    'user_id' => 'Invalid ID'
                );

                Logger::setLog('/client/limits/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

                return $response->withJson($resp)
                    ->withStatus(400);
            }
        }
        else
        {
            $resp = array(
                'detail' => 'no required parameters'
            );

            Logger::setLog('/client/limits/phone_app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

            return $response->withJson($resp)
                ->withStatus(404);
        }

    }
	
	public function changePhoneApp($request, $response) {
        $all_post_param = $request->getParsedBody();
		Logger::setLog('/client/change_phone/app', 'test', 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));
		
        if (isset($all_post_param["user_id"]) && isset($all_post_param["phone"]))
        {
            $pattern = "/[+][7][0-9]{10}$/i";
            if (preg_match($pattern, $all_post_param["phone"]))
            {
                $sql = 'select count(*) cnt from Clients_lk where ID_CLIENT = :id_client';
                $data =array(
                    'id_client' =>  $all_post_param["user_id"],
                );
                $sth = Database::execQuery($sql, $data);
                $res = $sth->fetchAll(PDO::FETCH_ASSOC); //заглушка, т.к. нет привязанных карт

                $resp = array();
                if ($res[0]["cnt"] > 0)
                {
                    $service_url = 'http://api.srochnodengi.id-east.ru/v1/web/auth/update-phone/';
                    $curl = curl_init($service_url);
                    $curl_post_data = array(
                        "user_id" => $all_post_param["user_id"],
                        "phone" => $all_post_param["phone"]
                    );

                    $curl_post_data = array(0 => $curl_post_data);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen(json_encode($curl_post_data))));
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
                    curl_exec($curl);
                    curl_close($curl);

                    $resp["user_id"] = $all_post_param["user_id"];
                    $resp["phone"] = $all_post_param["phone"];
                }
                Logger::setLog('/client/change_phone/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));
                return $response->withJson($resp)
                    ->withStatus(200);
            }
            else
            {
                $resp = array(
                    'detail' => 'Не валидный формат телефона'
                );

                Logger::setLog('/client/change_phone/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

                return $response->withJson($resp)
                    ->withStatus(400);
            }
        }
        else
        {
            $resp = array(
                'detail' => 'no required parameters'
            );

            Logger::setLog('/client/change_phone/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

            return $response->withJson($resp)
                ->withStatus(404);
        }
    }
	
	public function changePhoneTestApp($request, $response) {
        $all_post_param = $request->getParsedBody();
        if (isset($all_post_param["user_id"]) && isset($all_post_param["phone"]))
        {
            $pattern = "/[+][7][0-9]{10}$/i";
            if (preg_match($pattern, $all_post_param["phone"]))
            {
                $phone = substr($all_post_param["phone"], 1);
                $sql = 'exec p_php_update_phone_app_test :id_client, :phone';
                $data =array(
                    'id_client' =>  $all_post_param["user_id"],
                    'phone' =>  $phone
                );

                Database::execQuery($sql, $data);
                $resp = array();

                Logger::setLog('/client/change_phone/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));
                return $response->withJson($resp)
                    ->withStatus(200);
            }
            else
            {
                $resp = array(
                    'detail' => 'Не валидный формат телефона'
                );

                Logger::setLog('/client/change_phone/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

                return $response->withJson($resp)
                    ->withStatus(400);
            }
        }
        else
        {
            $resp = array(
                'detail' => 'no required parameters'
            );

            Logger::setLog('/client/change_phone/app', iconv( 'UTF-8','WINDOWS-1251',json_encode($resp, JSON_UNESCAPED_UNICODE)), 'post', iconv( 'UTF-8','WINDOWS-1251',json_encode($all_post_param, JSON_UNESCAPED_UNICODE)));

            return $response->withJson($resp)
                ->withStatus(404);
        }
    }
}
?>