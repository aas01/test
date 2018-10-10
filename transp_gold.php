<?PHP
class Transport {
    public function balance() {}

    public function reports() {}

    public function detailReport() {}

    public function send($params = array(), $phones = array()) {
        if (!isset($params["action"])) $params["action"] = "send";
        foreach( $phones as $v ) {
            $params['phone'][] = $v;
        };
        $result = $this->request("send", $params);
        return $result;
    }

    public function get($responce, $key) {
        if (isset($responce[$key], $responce[$key][0], $responce[$key][0][0])) return $responce[$key][0][0];
        return false;
    }

    public function parseXML($xml) {
        if (function_exists("simplexml_load_string"))
            return $this->XMLToArray($xml);
        else
            return $xml;
    }

    public function request($action,$params = array(),$someXML = "") {
        $xml = $this->makeXML($params,$someXML);
        if (HTTPS_METHOD == "curl"){
            return $this->parseXML( $this->request_curl($action,$xml) );
        } else {
            $this->error("В настройках указан неизвестный метод запроса - '".HTTPS_METHOD."'");
        };
    }

    public function request_curl($action = "",$xml) {
        //echo($xml);
        if (USE_HTTPS == 1)
            $address = HTTPS_ADDRESS;
        else
            $address = HTTP_ADDRESS;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/xml; charset=utf-8'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CRLF, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_URL, $address);

        $result = curl_exec($ch);
		var_dump ($result);
        curl_close($ch);

        return $result;
    }

    public function makeXML($params,$someXML = "") {
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
		  <request>";
		if( !empty($params) && is_array($params) ) {
            if( isset($params['text']) && (isset($params['phone']) && is_array($params['phone'])) ) {
                $params['text'] = $this->getConvertedString($params['text']);
                foreach( $params['phone'] as $k => $v ) {
                    $v = $this->getConvertedString($v);
                    $xml .= "<message>
							 <sender>".signature."</sender>
							 <text>{$params['text']}</text>
							 <abonent phone=\"".$v."\" number_sms=\"".($k+1)."\" />
							 </message>";
                };
            };
        };
		$xml .= "<security>
				 <login value=\"".HTTPS_LOGIN."\" />
				 <password value=\"".HTTPS_PASSWORD."\" />
				 </security>
				 </request>";
		//$GLOBALS['sm']->log($xml);
		return $xml;
	}

    public function getConvertedString($value, $from = false) {
        if (HTTPS_CHARSET != "utf-8") {
            if (function_exists("iconv")){
                if (!$from)
                    return iconv(HTTPS_CHARSET,"utf-8",$value);
                else
                    return iconv("utf-8",HTTPS_CHARSET,$value);
            }
            else
                $this->error("Не удается перекодировать переданные параметры в кодировку utf-8 - отсутствует функция iconv");
        }
        return $value;
    }

    public function error( $errorMes = null ) {
        if( !empty($errorMes) ) {
            throw new Exception($errorMes);
        };
    }

    public function XMLToArray($xml) {
        if (!strlen($xml)) {
            $descr = "Не удалось получить ответ от сервера!";
            if (USE_HTTPS == 1){
                $descr .= " Возможно конфигурация вашего сервера не позволяет отправлять HTTPS-запросы. Попробуйте установить значение USE_HTTPS = 0 в файле config.php";
            }
            return array("code" => 0, "descr" => $descr);
        }
        $xml = simplexml_load_string($xml);

        $return = array();
        foreach($xml->children() as $child)
        {
            $return[$child->getName()][] = $this->makeAssoc((array)$child);
        }
        if( HTTPS_CHARSET != 'utf-8' ) {
        	$return = $this->convertArrayCharset($return);
        };
        return $return;
    }

    public function convertArrayCharset($return) {
        foreach ($return as $key => $value){
            if (is_array($value)) $return[$key] = $this->convertArrayCharset($return[$key]);
            else $return[$key] = $this->getConvertedString($value, true);
        }
        return $return;
    }

    public function makeAssoc($array) {
        if (is_array($array))
            foreach ($array as $key => $value){
                if (is_object($value)) {
                    $newValue = array();
                    foreach($value->children() as $child)
                    {
                        $newValue[] = (string)$child;
                    }
                    $array[$key] = $newValue;
                }
            }
        else $array = (string)$array;

        return $array;
    }

    public function __construct() {
        $fileName = realpath(dirname(__FILE__))."/conf_gold.php";
        if( file_exists($fileName) ) {
            include_once($fileName);
            if( !defined("HTTPS_LOGIN") || !defined("HTTPS_PASSWORD") ) {
                $this->error("failed to configure class:\"".__CLASS__."\"");
            };
        } else {
            $this->error("File \"{$fileName}\" not found in this directory");
        };
    }
};

?>