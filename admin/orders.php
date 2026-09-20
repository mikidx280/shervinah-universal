<?php
declare(strict_types=1);
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict']);
session_start();
header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
if(empty($_SESSION['su_admin'])){header('Location: index.php');exit;}
require_once dirname(__DIR__).'/api/commerce-lib.php';
function esc($value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$inventoryLock=shop_inventory_lock();$inventory=shop_stock();fclose($inventoryLock);
$orders=[];
foreach(glob(shop_storage().'/*.json')?:[] as $path){
    if(!preg_match('/^[a-f0-9]{48}\.json$/',basename($path)))continue;
    $fp=fopen($path,'r');if(!$fp)continue;flock($fp,LOCK_SH);
    $order=json_decode(stream_get_contents($fp),true);flock($fp,LOCK_UN);fclose($fp);
    if(is_array($order)&&isset($order['quote'],$order['customer']))$orders[]=$order;
}
usort($orders,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Orders | Shervinah</title>
<style>body{font:16px system-ui;background:#f4d67c;color:#111;margin:0}header{background:#111;color:#fff;padding:1rem;display:flex;gap:2rem}a{color:inherit}main{max-width:1000px;margin:auto;padding:1rem}article{background:#fff;padding:1rem;margin:1rem 0;overflow-wrap:anywhere}dl{display:grid;grid-template-columns:150px 1fr;gap:.5rem}dd{margin:0}dt{font-weight:bold}.paid{border-left:6px solid #187641}.pending{border-left:6px solid #bd6100}</style>
<header><strong>Shervinah orders</strong><a href="index.php">Inquiries</a><a href="index.php?logout=1">Sign out</a></header><main>
<h1>Orders</h1><p>Ship only orders marked <strong>paid</strong>. Payment status is confirmed with Cardcom. A pending order or a return from the payment page is not proof of payment. Weigh each packed parcel before posting.</p>
<h2>Inventory</h2><p>Pending payment pages reserve units. They are not automatically released because an old payment link may still accept payment. Reconcile abandoned or uncertain payments with Cardcom before releasing stock.</p>
<table><thead><tr><th>Product</th><th>Available</th><th>Reserved</th><th>Sold</th></tr></thead><tbody><?php foreach(commerce_catalog() as $id=>$p):$stock=$inventory[$id];?><tr><td><?=esc($p['name'])?></td><td><?=esc($stock['available'])?></td><td><?=esc($stock['reserved'])?></td><td><?=esc($stock['sold'])?></td></tr><?php endforeach?></tbody></table>
<?php if(!$orders):?><p>No orders yet.</p><?php endif?>
<?php foreach(array_slice($orders,0,250) as $o):$q=$o['quote'];$c=$o['customer']; ?>
<article class="<?= $o['status']==='paid'?'paid':'pending' ?>"><h2><?=esc(substr($o['id'],0,12))?> · <?=esc($o['status'])?></h2>
<dl><dt>Created (UTC)</dt><dd><?=esc($o['created_at'])?></dd><dt>Customer</dt><dd><?=esc($c['name'])?> · <?=esc($c['email'])?> · <?=esc($c['phone'])?></dd>
<dt>Delivery</dt><dd><?=esc($c['address'])?>, <?=esc($c['city'])?>, <?=esc($c['postal_code'])?>, <?=esc($q['country'])?> <?=esc($q['region'])?></dd>
<dt>Products</dt><dd><?php foreach($q['items'] as $item):?><?=esc($item['quantity'])?> × <?=esc($item['name'])?><br><?php endforeach?></dd>
<dt>Packed estimate</dt><dd><?=esc($q['weight_grams'])?> g</dd><dt>Shipping</dt><dd>ILS <?=number_format($q['shipping_ils_cents']/100,2)?> after ILS <?=number_format($q['shipping_discount_ils_cents']/100,2)?> discount</dd>
<dt>Total</dt><dd>USD <?=number_format($q['total_usd_cents']/100,2)?></dd><dt>Exchange rate</dt><dd><?=esc($q['rate'])?> ILS per USD · <?=esc($q['rate_date'])?></dd><dt>Transaction</dt><dd><?=esc($o['transaction_id']??'Not confirmed')?></dd></dl></article>
<?php endforeach?></main></html>
