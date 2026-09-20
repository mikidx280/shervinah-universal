<?php
require __DIR__.'/../api/commerce-lib.php';
$checks=0;
function ok($v,$label){global $checks;$checks++;if(!$v)throw new Exception($label);}
$catalog=commerce_catalog();$stock=shop_stock_totals($catalog,[]);
foreach(['saffron-oil-20ml'=>10,'jerusalem-gift-set'=>20,'red-string-pack-5'=>100,'hamsa-home-blessing'=>10] as $id=>$n)ok($stock[$id]['available']===$n,$id.' opening stock');
$basket=['jerusalem-gift-set'=>1,'red-string-pack-5'=>1,'hamsa-home-blessing'=>1];
$q=commerce_calculate($basket,'US','',3.028);
ok($q['weight_grams']===520,'mixed packed weight');ok($q['product_usd_cents']===3000,'mixed price');ok($q['shipping_ils_cents']===7700,'US mixed weight postage');ok($q['shipping_discount_ils_cents']===1000,'single discount');
$one=commerce_calculate(['red-string-pack-5'=>1],'US','',3);ok($one['weight_grams']===70&&$one['shipping_ils_cents']===5100,'one pack not five shipping units');
$h=commerce_calculate(['hamsa-home-blessing'=>1],'US','',3);ok($h['weight_limit_grams']===250&&$h['shipping_ils_cents']===5400,'hamsa boundary');
$order=['inventory_reserved'=>true,'status'=>'pending','quote'=>['items'=>[['id'=>'hamsa-home-blessing','quantity'=>10]]]];
$reserved=shop_stock_totals($catalog,[$order]);ok($reserved['hamsa-home-blessing']['available']===0,'pending reserves stock');ok($reserved['hamsa-home-blessing']['sold']===0,'pending is not sold');
try{shop_stock_assert([['id'=>'hamsa-home-blessing','quantity'=>1]],$reserved);throw new Exception('Oversell allowed');}catch(InvalidArgumentException $e){ok($e->getMessage()==='out_of_stock','oversell blocked');}
$order['status']='paid';$paid=shop_stock_totals($catalog,[$order]);ok($paid['hamsa-home-blessing']['sold']===10&&$paid['hamsa-home-blessing']['reserved']===0,'paid converts reservation to sale');
ok(shop_stock_totals($catalog,[$order])===$paid,'repeated reconciliation does not deduct twice');
unset($order['inventory_reserved']);ok(shop_stock_totals($catalog,[$order])===$stock,'pre-snapshot orders do not reduce current inventory');
foreach($catalog as $p)ok(!str_contains($p['name'],'—')&&!str_contains($p['name_fa'],'—'),'no forbidden punctuation');
foreach([2.5,3.028,4.5] as $rate){$fixed=commerce_calculate(['saffron-oil-20ml'=>1,'jerusalem-gift-set'=>1,'red-string-pack-5'=>1,'hamsa-home-blessing'=>1],'US','',$rate);ok($fixed['product_usd_cents']===7000,'fixed USD prices independent of FX');}
echo "$checks product, mixed-basket and inventory checks passed.\n";
