<?php
require __DIR__.'/shop-auth.php';
$lock=shop_inventory_lock();$inventory=shop_stock();$catalog=commerce_catalog();$orders=[];
try{
    foreach(glob(shop_storage().'/*.json')?:[] as $path){
        if(!preg_match('/^[a-f0-9]{48}\.json$/',basename($path)))continue;
        $o=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if(is_array($o)&&isset($o['quote'],$o['customer']))$orders[]=$o;
    }
}finally{fclose($lock);}
usort($orders,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
function order_bucket(array $o): string {return $o['status']==='paid'?(!empty($o['shipment']['tracking'])?'shipped':'ready'):(in_array($o['status'],['creating','pending'],true)?'pending':'attention');}
$counts=['all'=>count($orders),'ready'=>0,'shipped'=>0,'pending'=>0,'attention'=>0];$paidCents=0;
foreach($orders as $o){$counts[order_bucket($o)]++;if($o['status']==='paid')$paidCents+=$o['quote']['total_usd_cents'];}
$labels=['all'=>'כל ההזמנות','ready'=>'תשלום אושר, להכנה','shipped'=>'נשלחו','pending'=>'ממתינות לאישור תשלום','attention'=>'דורשות בדיקה'];
$filter=(string)($_GET['status']??'all');if(!isset($labels[$filter]))$filter='all';$search=trim(substr((string)($_GET['q']??''),0,200));
$filtered=array_values(array_filter($orders,function($o)use($filter,$search){if($filter!=='all'&&order_bucket($o)!==$filter)return false;return $search===''||stripos(json_encode([$o['id'],$o['customer'],$o['quote']['country'],$o['quote']['items'],$o['shipment']??[]],JSON_UNESCAPED_UNICODE),$search)!==false;}));
$page=max(1,min((int)($_GET['page']??1),max(1,(int)ceil(count($filtered)/50))));$pages=max(1,(int)ceil(count($filtered)/50));
?><!doctype html><html lang="he" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ניהול הזמנות | Shervinah</title><link rel="stylesheet" href="admin.css"><header><strong>Shervinah · ניהול החנות</strong><a href="orders.php">הזמנות</a><a href="products.php">מוצרים ומלאי</a><a href="email-settings.php">הגדרות מייל</a><a href="index.php">פניות</a><a href="index.php?logout=1">יציאה</a></header><main class="orders-dashboard">
<h1>דשבורד הזמנות</h1><p>כאן רואים מה הוזמן, למי ולאן לשלוח, ומה אושר לתשלום. שולחים רק הזמנות שמסומנות <strong>תשלום אושר</strong>.</p>
<nav class="dashboard-stats" aria-label="סינון הזמנות"><?php foreach($labels as $key=>$label):?><a href="?status=<?=esc($key)?>" class="stat-card <?=$filter===$key?'selected':''?>" <?=$filter===$key?'aria-current="page"':''?>><strong><?=$counts[$key]?></strong><span><?=esc($label)?></span></a><?php endforeach?></nav>
<p>סכום ההזמנות שאושרו: <strong dir="ltr">$<?=number_format($paidCents/100,2)?> USD</strong></p>
<form method="get" class="order-search"><input type="hidden" name="status" value="<?=esc($filter)?>"><label>חיפוש לפי לקוח, מייל, מספר הזמנה, מוצר או מדינה<input name="q" value="<?=esc($search)?>" maxlength="200" type="search"></label><button>חיפוש</button><a href="orders.php">ניקוי סינון</a></form>
<p role="status"><?=count($filtered)?> הזמנות תואמות · עמוד <?=$page?> מתוך <?=$pages?></p>
<?php if(!$filtered):?><article><h2>אין הזמנות להצגה</h2><p>הזמנות חדשות יופיעו כאן לאחר שהלקוח ימשיך לדף התשלום. מצב האישור יתעדכן רק לאחר אימות מול Cardcom.</p></article><?php endif?>
<?php foreach(array_slice($filtered,($page-1)*50,50) as $o):$q=$o['quote'];$c=$o['customer'];$bucket=order_bucket($o);?>
<article class="order-card <?=$o['status']==='paid'?'paid':'pending'?>"><div class="order-card-heading"><h2><a href="order.php?id=<?=esc($o['id'])?>">הזמנה <bdi><?=esc(substr($o['id'],0,12))?></bdi></a></h2><span class="order-badge <?=esc($bucket)?>"><?=esc($labels[$bucket])?></span></div>
<dl><dt>תאריך (UTC)</dt><dd><bdi><?=esc($o['created_at'])?></bdi></dd><dt>הלקוח</dt><dd><?=esc($c['name'])?><br><bdi><?=esc($c['email'])?></bdi> · <bdi><?=esc($c['phone'])?></bdi></dd>
<dt>כתובת למשלוח</dt><dd dir="auto"><?=esc($c['address'])?><br><?=esc($c['city'])?> <?=esc($c['state']??'')?> <?=esc($c['postal_code'])?><br><strong><?=esc($q['country'])?></strong> <?=esc($q['region'])?></dd>
<dt>מה הוזמן</dt><dd><?php foreach($q['items'] as $item):?><div dir="auto"><?=esc($item['quantity'])?> × <?=esc($item['name'])?> · $<?=number_format($item['unit_usd_cents']*$item['quantity']/100,2)?></div><?php endforeach?></dd>
<dt>סה״כ כולל משלוח</dt><dd><bdi>$<?=number_format($q['total_usd_cents']/100,2)?> USD</bdi></dd><dt>אישור תשלום</dt><dd><?=$o['status']==='paid'?'אושר מול Cardcom':'לא אושר. אין לשלוח עדיין.'?><?php if(!empty($o['transaction_id'])):?><br>עסקה: <bdi><?=esc($o['transaction_id'])?></bdi><?php endif?></dd>
<dt>מצב משלוח</dt><dd><?php if(!empty($o['shipment']['tracking'])):?>נשלח · <bdi><?=esc($o['shipment']['tracking'])?></bdi><br><?=esc($o['shipment']['shipped_at'])?><?php else:?><?=$o['status']==='paid'?'ממתין להכנה ולמשלוח':'ממתין לאישור תשלום'?><?php endif?></dd></dl>
<a class="button" href="order.php?id=<?=esc($o['id'])?>">פתיחת ההזמנה, הדפסה ועדכון משלוח</a></article><?php endforeach?>
<nav class="pagination" aria-label="עמודים"><?php if($page>1):?><a href="?<?=esc(http_build_query(['status'=>$filter,'q'=>$search,'page'=>$page-1]))?>">הקודם</a><?php endif?><?php if($page<$pages):?><a href="?<?=esc(http_build_query(['status'=>$filter,'q'=>$search,'page'=>$page+1]))?>">הבא</a><?php endif?></nav>
<details><summary>תמונת מצב מלאי</summary><p>זמין למכירה אינו כולל יחידות שנמכרו או שמורות לניסיונות תשלום.</p><div class="table-scroll"><table><tr><th>מוצר</th><th>זמין</th><th>שמור</th><th>נמכר</th></tr><?php foreach($catalog as $id=>$p):?><tr><td><?=esc($p['name'])?></td><td><?=$inventory[$id]['available']?></td><td><?=$inventory[$id]['reserved']?></td><td><?=$inventory[$id]['sold']?></td></tr><?php endforeach?></table></div></details></main></html>
