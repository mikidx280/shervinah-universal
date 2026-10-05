<?php
require __DIR__.'/shop-auth.php';
require dirname(__DIR__).'/api/commerce-mail.php';
$id=(string)($_GET['id']??'');
if(!preg_match('/^[a-f0-9]{48}$/',$id)||!is_file(shop_storage().'/'.$id.'.json')){http_response_code(404);exit('ההזמנה לא נמצאה');}
$path=shop_storage().'/'.$id.'.json';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_csrf();$lock=shop_inventory_lock(true);$fp=fopen($path,'r+');flock($fp,LOCK_EX);
    try{
        $order=json_decode(stream_get_contents($fp),true,512,JSON_THROW_ON_ERROR);$action=(string)($_POST['action']??'');
        if($action==='verify')shop_verify($order);
        elseif($action==='ship'){
            if($order['status']!=='paid')throw new InvalidArgumentException('ניתן לסמן משלוח רק להזמנה שתשלומה אומת.');
            $tracking=trim((string)($_POST['tracking']??''));$carrier=trim((string)($_POST['carrier']??''));$url=trim((string)($_POST['tracking_url']??''));
            if(!preg_match('/^[A-Za-z0-9 -]{5,80}$/',$tracking)||$carrier===''||strlen($carrier)>100)throw new InvalidArgumentException('יש לבדוק את חברת המשלוח ואת מספר המעקב.');
            if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_USER)!==null))throw new InvalidArgumentException('קישור המעקב חייב להיות כתובת HTTPS תקינה.');
            $order['shipment']=['carrier'=>$carrier,'tracking'=>$tracking,'url'=>$url,'shipped_at'=>gmdate('c')];
        }elseif($action!=='retry')throw new InvalidArgumentException('פעולה לא מוכרת.');
        $order['access_token']??=bin2hex(random_bytes(32));
        shop_write_order($fp,$order);
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'לא ניתן היה להשלים את הפעולה. נסה שוב מאוחר יותר.';}
    finally{fclose($fp);fclose($lock);}
    if(!$error){
        shop_notify($order);
        if($action==='retry'&&!empty($_POST['retry_acknowledged'])){
            foreach(shop_mail_queue($order) as $jobPath){$job=json_decode((string)file_get_contents($jobPath),true);if($job['status']!=='accepted')shop_mail_deliver($jobPath,true);}
        }
        header('Location: order.php?id='.$id.'&updated=1');exit;
    }
}
$fp=fopen($path,'r');flock($fp,LOCK_SH);$order=json_decode(stream_get_contents($fp),true,512,JSON_THROW_ON_ERROR);fclose($fp);$q=$order['quote'];$customer=$order['customer'];
$statusLabels=['paid'=>'שולם ואומת','pending'=>'ממתין לאישור תשלום','creating'=>'יצירת תשלום','setup_error'=>'שגיאת הגדרת תשלום'];
$mailStatusLabels=['accepted'=>'התקבל בשרת הדואר','configuration_needed'=>'נדרשת הגדרת מייל','uncertain'=>'שליחה לא ודאית','queued'=>'ממתין לשליחה','sending'=>'בתהליך שליחה'];
function admin_mail_type(array $job): string {
    $key=(string)($job['key']??'');
    if($key==='confirmation')return 'אישור הזמנה ללקוח';
    if(str_starts_with($key,'shipped-'))return 'עדכון משלוח ללקוח';
    if(str_starts_with($key,'merchant-'))return 'התראת הזמנה לבעל החנות';
    return $key;
}
?><!doctype html><html lang="he" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>הזמנה <?=esc(substr($id,0,12))?> | Shervinah</title><link rel="stylesheet" href="admin.css"><header><strong>Shervinah · ניהול החנות</strong><a href="orders.php">הזמנות</a><a href="products.php">מוצרים ומלאי</a><a href="index.php">פניות</a><a href="email-settings.php">הגדרות מייל</a><a href="index.php?logout=1">יציאה</a></header><main>
<h1>הזמנה <bdi><?=esc(substr($id,0,12))?></bdi></h1><p>מצב תשלום: <strong><?=esc($statusLabels[$order['status']]??$order['status'])?></strong></p><?php if(isset($_GET['updated'])):?><p role="status">הפעולה בוצעה.</p><?php endif?><?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif?>
<article><h2>דף אריזה</h2><p>מסמך פנימי להכנת ההזמנה. זה אינו תווית משלוח ואינו חשבונית מס.</p><p dir="auto"><?=esc($customer['name'])?><br><?=esc($customer['address'])?><br><?=esc($customer['city'])?> <?=esc($customer['state']??'')?> <?=esc($customer['postal_code'])?><br><?=esc($q['country'])?> <?=esc($q['region'])?></p><p><bdi><?=esc($customer['phone'])?></bdi> · <bdi><?=esc($customer['email'])?></bdi></p>
<ul><?php foreach($q['items'] as $item):?><li dir="auto"><?=esc($item['quantity'])?> × <?=esc($item['name'])?></li><?php endforeach?></ul><p>משקל ארוז משוער: <?=esc($q['weight_grams'])?> גרם. יש לשקול את החבילה הסופית לפני המשלוח.</p><p>סה״כ ששולם / סה״כ הזמנה: <bdi>USD <?=number_format($q['total_usd_cents']/100,2)?></bdi></p><p>מספר עסקה: <bdi><?=esc($order['transaction_id']??'טרם אושר')?></bdi></p></article><button type="button" onclick="window.print()">הדפסת דף אריזה</button>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><p>אפשר לבצע אימות נוסף של מצב התשלום מול Cardcom.</p><button name="action" value="verify">אימות תשלום מול Cardcom</button></form>
<?php if($order['status']==='paid'):?><form method="post"><h2>משלוח</h2><p>לאחר רכישת המשלוח בדואר ישראל או אצל חברת המשלוחים, הזן כאן את פרטי המעקב. השמירה תשלח ללקוח אוטומטית הודעת משלוח.</p><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label>חברת משלוחים<input name="carrier" value="<?=esc($order['shipment']['carrier']??'Israel Post')?>" required maxlength="100"></label><label>מספר מעקב<input name="tracking" value="<?=esc($order['shipment']['tracking']??'')?>" required maxlength="80"></label><label>קישור רשמי למעקב (HTTPS, לא חובה)<input type="url" name="tracking_url" value="<?=esc($order['shipment']['url']??'')?>"></label><button name="action" value="ship">שמירת המשלוח ושליחת הודעה ללקוח</button></form><?php else:?><p class="no-print">המלאי נשאר שמור כל עוד קישור התשלום עשוי עדיין להיות פעיל. אין לשחרר את המלאי לפני שמוודאים את מצב התשלום ב־Cardcom.</p><?php endif?>
<section class="no-print"><h2>שליחת מיילים</h2><p><strong>התקבל בשרת הדואר</strong> פירושו ששרת המייל קיבל את ההודעה לשליחה; אין בכך הוכחה שההודעה הגיעה לתיבת הדואר של הנמען. במקרה של שליחה לא ודאית יש לבדוק קודם את ספק המייל.</p><ul><?php foreach(glob(shop_storage().'/outbox/'.$id.'-*.json')?:[] as $jobPath):$job=json_decode((string)file_get_contents($jobPath),true);?><li><bdi><?=esc($job['to'])?></bdi> · <?=esc(admin_mail_type($job))?> · <strong><?=esc($mailStatusLabels[$job['status']]??$job['status'])?></strong><?php if(!empty($job['error'])):?> · יש לבדוק את ספק המייל לפני ניסיון חוזר<?php endif?></li><?php endforeach?></ul><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label><input type="checkbox" name="retry_acknowledged" required> בדקתי את תיבת השולח / ספק המייל. יש לנסות שוב הודעות שלא נשלחו או ששליחתן לא ודאית.</label><button name="action" value="retry">ניסיון חוזר לשליחת המיילים בלבד</button></form></section></main></html>