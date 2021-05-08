<?php

    //Задайте параметры здесь
    $email = 'test@test.ru';                   //Ваш e-mail
    $INN = '12345678';                         //Ваш ИНН в налоговой
    $LK_PASS = '12345678';                     //Ваш пароль к личного кабинету налоговой
    $path_save_check = '/var/www/moy-nalog';   //Куда сохранить картинку пробитого чека
    //Конец блока параметров

    $DEVICE_ID = sha1($email);
    $API_PROVIDER = 'https://lknpd.nalog.ru/api/v1/';
    $AUTH_URL = 'auth/lkfl';
    $SALE_URL = 'income';
    $CHECK_DOWNLOAD = 'receipt';
    $APP_VERSION = '1.0.0';
    $SOURCE_TYPE = 'WEB';
    $USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36';
    
    $token = '';
    $refresh_token = '';
    $sale_amount = '1';
    $sale_name = 'Test';

    //Authorization
    $reqparam = json_encode(array(
        'username' => $INN,
        'password' => $LK_PASS,
        'deviceInfo' => array(
            'sourceDeviceId' => $DEVICE_ID,
            'sourceType' => $SOURCE_TYPE,
            'appVersion' => $APP_VERSION,
            'metaDetails' => array(
                'userAgent' => $USER_AGENT
            )
        )
    ));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT , $USER_AGENT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_URL, $API_PROVIDER.$AUTH_URL );
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_POST, 1 );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $reqparam);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 

    $postResult = curl_exec($ch);
    if (!$postResult)
        die('Can\'t get response from FNS');

    $obj_result=json_decode($postResult);

    $token = isset($obj_result->token) ? $obj_result->token : '';
    $refresh_token = isset($obj_result->refreshToken) ? $obj_result->refreshToken : '';
    if (!strlen($token) || !strlen($refresh_token))
        die('Can\'t get tokens from FNS (auth)');

    echo 'token: '.$token."\n\n";
    echo 'refreshToken: '.$refresh_token."\n\n";
    curl_close($ch);

    //Register sale
    $reqparam = json_encode(array(
        'operationTime' => date('c'),
        'requestTime' => date('c'),
        'services' => array(
            array(
                'name' => $sale_name,
                'amount' => $sale_amount,
                'quantity' => '1'
            )
        ),
        'totalAmount' => $sale_amount,
        'client' => array(
            'contactPhone' => null,
            'displayName' => null,
            'inn' => null,
            'incomeType' => 'FROM_INDIVIDUAL'
        ),
        'paymentType' => 'CASH',
        'ignoreMaxTotalIncomeRestriction' => 'false'
    ));
    //echo $reqparam."\n";
    //return;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT , $USER_AGENT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer '.$token
    ));
    curl_setopt($ch, CURLOPT_URL, $API_PROVIDER.$SALE_URL );
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_POST, 1 );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $reqparam);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 

    $postResult = curl_exec($ch);
    if (!$postResult)
        die('Can\'t get response from FNS (sale)');

    $obj_result = json_decode($postResult);
    $approvedReceiptUuid = isset($obj_result->approvedReceiptUuid) ? $obj_result->approvedReceiptUuid : '';

    echo 'approvedReceiptUuid: '.$approvedReceiptUuid."\n\n";
    curl_close($ch);

    //Download check
    $path = $path_save_check.$approvedReceiptUuid.'.jpg';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT , $USER_AGENT);
    curl_setopt($ch, CURLOPT_URL, $API_PROVIDER.$CHECK_DOWNLOAD.'/'.$INN.'/'.$approvedReceiptUuid.'/print' );
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $postResult = curl_exec($ch);
    if (!$postResult)
        die('Can\'t get response from FNS (download check)');
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code == 200)
        file_put_contents($path, $postResult);
    else
        die('Can\'t get file from FNS (download check)');
    
    curl_close($ch);

    echo 'ok';

?>
