<?php
require(__DIR__.'/../vendor/autoload.php');
use Ksyun\Service\Sms;

function getResponse($response)
{
    return json_decode((string)$response->getBody(), true);
}

//----------------------------------

//send sms
$ak = 'AKLTot5yJ5u6TL2Fv-i2hEC7VQ';
$sk = 'OIrekNxjL4f7YBIfZxr8S+G76iT8m/XknLdWse1GiZofvzIsiDI6S9jN7AZkdbGSUQ==';
$param = array(
    'Action'    => 'SendSms',
    'Version'   => '2019-05-01',
    'Mobile'    => '16601104706',
    'SignName'  => '得乐游戏',
    'TplId'     => '181',
    'TplParams' => json_encode(['code' => '1234']),
);
$SmsObj = Sms::getInstance();
$SmsObj->setCredentials($ak, $sk);
try {
    $response = $SmsObj->request('SendSms', array('form_params'=>$param));
    $associateInfo = getResponse($response);
    var_dump($associateInfo);
} catch (\Exception $e) {
    //var_dump($e);
    var_dump($e->getMessage());
}
