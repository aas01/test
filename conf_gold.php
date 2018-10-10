<?php
	define("SRV_IP","10.129.12.35");
	define("SRV_LOGIN","smuser_sms");
	define("SRV_PASSWORD","smuser_sms_password");
	define("SRV_DB","NOVSM2010");
	define("HTTPS_LOGIN", "32608"); //Ваш логин для HTTPS-протокола
	define("HTTPS_PASSWORD", "12345"); //Ваш пароль для HTTPS-протокола
	define("HTTPS_ADDRESS", "https://gt.smsgold.ru/xml/"); //HTTPS-Адрес, к которому будут обращаться скрипты. Со слэшем на конце.
    define("HTTP_ADDRESS", "https://gt.smsgold.ru/xml/"); //HTTP-Адрес, к которому будут обращаться скрипты. Со слэшем на конце.
	define("HTTPS_CHARSET", "utf-8"); //кодировка ваших скриптов. cp1251 - для Windows-1251, либо же utf-8 для, сообственно - utf-8 :)
	define("HTTPS_METHOD", "curl"); //метод, которым отправляется запрос (curl)
    define("USE_HTTPS", 0); //1 - использовать HTTPS-адрес, 0 - HTTP
	define("signature","SRO4NODENGI");
?>