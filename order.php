<?php
declare(strict_types=1);
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
require __DIR__.'/api/commerce-lib.php';
$id=(string)($_GET['id']??'');$token=(string)($_GET['token']??'');$order=null;
if(preg_match('/^[a-f0-9]{48}$/',$id)&&preg_match('/^[a-f0-9]{64}$/',$token)){
    $path=shop_storage().'/'.$id.'.json';
    if(is_file($path)){$fp=fopen($path,'r');flock($fp,LOCK_SH);$row=json_decode(stream_get_contents($fp),true);fclose($fp);if(is_array($row)&&hash_equals((string)($row['access_token']??''),$token))$order=$row;}
}
function esc($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
if(!$order){http_response_code(404);exit('Order link unavailable. Please use the private link in your confirmation email or contact shervinahuniversal@gmail.com.');}
$fa=($order['language']??'en')==='fa';$t=fn($en,$persian)=>$fa?$persian:$en;$q=$order['quote'];
?><!doctype html><html lang="<?=$fa?'fa':'en'?>" dir="<?=$fa?'rtl':'ltr'?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Order | Shervinah Universal</title><link rel="stylesheet" href="commerce.css"><body><header class="checkout-header"><a href="shop.html">SHERVINAH UNIVERSAL</a></header><main class="checkout-shell"><h1><?=esc($t('Your order','سفارش شما'))?> <?=esc(substr($id,0,12))?></h1><p><?=esc($order['status']==='paid'?$t('Payment confirmed','پرداخت تأیید شد'):$t('Payment not confirmed','پرداخت تأیید نشده است'))?></p>
<?php foreach($q['items'] as $item):?><p><?=esc($item['quantity'])?> × <?=esc($fa?$item['name_fa']:$item['name'])?></p><?php endforeach?><p><?=esc($t('Total','جمع'))?>: USD <?=number_format($q['total_usd_cents']/100,2)?></p>
<?php if(!empty($order['shipment'])):$s=$order['shipment'];?><h2><?=esc($t('Shipped','ارسال شد'))?></h2><p><?=esc($s['carrier'])?> · <?=esc($s['tracking'])?></p><p><?=esc($s['shipped_at'])?></p><?php if(!empty($s['url'])):?><a href="<?=esc($s['url'])?>" rel="noreferrer noopener" target="_blank"><?=esc($t('Track shipment','پیگیری مرسوله'))?></a><?php endif?><?php elseif($order['status']==='paid'):?><p><?=esc($t('Preparing your order. Tracking will appear here once dispatched.','سفارش شما در حال آماده‌سازی است. پس از ارسال، اطلاعات پیگیری اینجا نمایش داده می‌شود.'))?></p><?php endif?>
<p><a href="mailto:shervinahuniversal@gmail.com"><?=esc($t('Contact us','تماس با ما'))?></a></p></main></body></html>
