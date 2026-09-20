<?php
declare(strict_types=1);
$name=(string)($_GET['file']??'');
if(!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/',$name,$m)){http_response_code(404);exit;}
$path=dirname(__DIR__,2).'/shervinah-orders/images/'.$name;
if(!is_file($path)){http_response_code(404);exit;}
header('Content-Type: '.['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$m[1]]);
header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: '.filesize($path));readfile($path);
