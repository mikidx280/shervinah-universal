<?php
// Optional hosting cron: php /absolute/path/public_html/admin/mail-worker.php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/commerce-lib.php';
require dirname(__DIR__).'/api/commerce-mail.php';
$done=0;
foreach(glob(shop_storage().'/outbox/*.json')?:[] as $path){
    $job=json_decode((string)file_get_contents($path),true);
    if(in_array($job['status']??'',['queued','configuration_needed'],true)){shop_mail_deliver($path);if(++$done>=5)break;}
}
echo "Processed $done queued messages.\n";
