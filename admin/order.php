<?php
require __DIR__.'/shop-auth.php';
require dirname(__DIR__).'/api/commerce-mail.php';
$id=(string)($_GET['id']??'');
if(!preg_match('/^[a-f0-9]{48}$/',$id)||!is_file(shop_storage().'/'.$id.'.json')){http_response_code(404);exit('Order not found');}
$path=shop_storage().'/'.$id.'.json';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_csrf();$lock=shop_inventory_lock(true);$fp=fopen($path,'r+');flock($fp,LOCK_EX);
    try{
        $order=json_decode(stream_get_contents($fp),true,512,JSON_THROW_ON_ERROR);$action=(string)($_POST['action']??'');
        if($action==='verify')shop_verify($order);
        elseif($action==='ship'){
            if($order['status']!=='paid')throw new InvalidArgumentException('Only a verified paid order can be shipped.');
            $tracking=trim((string)($_POST['tracking']??''));$carrier=trim((string)($_POST['carrier']??''));$url=trim((string)($_POST['tracking_url']??''));
            if(!preg_match('/^[A-Za-z0-9 -]{5,80}$/',$tracking)||$carrier===''||strlen($carrier)>100)throw new InvalidArgumentException('Check the carrier and tracking number.');
            if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_USER)!==null))throw new InvalidArgumentException('Tracking link must be a valid HTTPS URL.');
            $order['shipment']=['carrier'=>$carrier,'tracking'=>$tracking,'url'=>$url,'shipped_at'=>gmdate('c')];
        }elseif($action!=='retry')throw new InvalidArgumentException('Unknown action.');
        $order['access_token']??=bin2hex(random_bytes(32));
        shop_write_order($fp,$order);
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Could not complete the action. Please retry later.';}
    finally{fclose($fp);fclose($lock);}
    if(!$error){
        shop_notify($order);
        if($action==='retry'&&!empty($_POST['retry_acknowledged'])){
            foreach(shop_mail_queue($order) as $jobPath){$job=json_decode((string)file_get_contents($jobPath),true);if($job['status']!=='accepted')shop_mail_deliver($jobPath,true);}
        }
        header('Location: order.php?id='.$id.'&updated=1');exit;
    }
}
$fp=fopen($path,'r');flock($fp,LOCK_SH);$order=json_decode(stream_get_contents($fp),true,512,JSON_THROW_ON_ERROR);fclose($fp);$q=$order['quote'];$c=$order['customer'];
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Order <?=esc(substr($id,0,12))?></title><link rel="stylesheet" href="admin.css"><header><a href="orders.php">Orders</a><a href="products.php">Products</a><a href="index.php">Inquiries</a><a href="email-settings.php">Email settings</a></header><main>
<h1>Order <?=esc(substr($id,0,12))?></h1><p>Payment: <strong><?=esc($order['status'])?></strong></p><?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif?>
<article><h2>Packing slip</h2><p>Internal packing document. Not a postage label or a tax invoice.</p><p><?=esc($c['name'])?><br><?=esc($c['address'])?><br><?=esc($c['city'])?> <?=esc($c['state']??'')?> <?=esc($c['postal_code'])?><br><?=esc($q['country'])?> <?=esc($q['region'])?></p><p><?=esc($c['phone'])?> · <?=esc($c['email'])?></p>
<ul><?php foreach($q['items'] as $item):?><li><?=esc($item['quantity'])?> × <?=esc($item['name'])?></li><?php endforeach?></ul><p>Estimated packed weight: <?=esc($q['weight_grams'])?> g. Weigh the final parcel before posting.</p><p>Total paid/order total: USD <?=number_format($q['total_usd_cents']/100,2)?></p><p>Transaction: <?=esc($order['transaction_id']??'Not confirmed')?></p></article><button type="button" onclick="window.print()">Print packing slip</button>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><button name="action" value="verify">Verify payment with Cardcom</button></form>
<?php if($order['status']==='paid'):?><form method="post"><h2>Shipment</h2><p>Purchase the postage label from Israel Post, then enter the tracking details below. Saving sends a dispatch notification to the customer.</p><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label>Carrier<input name="carrier" value="<?=esc($order['shipment']['carrier']??'Israel Post')?>" required maxlength="100"></label><label>Tracking number<input name="tracking" value="<?=esc($order['shipment']['tracking']??'')?>" required maxlength="80"></label><label>Official tracking link (HTTPS, optional)<input type="url" name="tracking_url" value="<?=esc($order['shipment']['url']??'')?>"></label><button name="action" value="ship">Save shipment & notify customer</button></form><?php else:?><p class="no-print">Stock remains reserved while a payment link may still accept payment. This screen cannot cancel Cardcom links. Do not release it without confirming cancellation with Cardcom.</p><?php endif?>
<section class="no-print"><h2>Email delivery</h2><p>Accepted means the mail server accepted the message; it does not prove inbox delivery. An uncertain send requires checking the provider before retrying.</p><ul><?php foreach(glob(shop_storage().'/outbox/'.$id.'-*.json')?:[] as $jobPath):$job=json_decode((string)file_get_contents($jobPath),true);?><li><?=esc($job['to'])?> · <?=esc($job['key'])?> · <?=esc($job['status'])?> <?=esc($job['error']??'')?></li><?php endforeach?></ul><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label><input type="checkbox" name="retry_acknowledged" required> I checked the sender/provider. Retry unsent or uncertain messages; uncertain messages may already have been delivered.</label><button name="action" value="retry">Retry emails only</button></form></section></main></html>
