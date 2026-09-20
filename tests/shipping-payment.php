<?php
require __DIR__.'/../api/commerce-lib.php';
$checks=0;
function check($ok,$label){global $checks;$checks++;if(!$ok)throw new Exception($label);}
function rejects($fn,$code){try{$fn();throw new Exception('Expected '.$code);}catch(InvalidArgumentException $e){check($e->getMessage()===$code,$code);}}
$one=['saffron-oil-20ml'=>1];
foreach(['US'=>5400,'GB'=>4700,'PT'=>4900,'AR'=>6200,'SG'=>4700] as $country=>$expected){$q=commerce_calculate($one,$country,'mainland',3.028);check($q['shipping_ils_cents']===$expected,$country);check($q['weight_grams']===200,'single weight');check($q['total_usd_cents']===$q['product_usd_cents']+$q['shipping_usd_cents'],'USD sum');}
$q=commerce_calculate(['saffron-oil-20ml'=>2],'AR','',3.028);check($q['weight_grams']===400&&$q['shipping_ils_cents']===8000,'two bottles Argentina');check($q['shipping_discount_ils_cents']===1000,'discount once');
check(commerce_calculate($one,'PT','azores',3)['shipping_ils_cents']===6200,'Azores');
check(commerce_calculate(['saffron-oil-20ml'=>10],'CA','',3)['shipping_ils_cents']===14100,'2kg limit');
check(commerce_calculate(['saffron-oil-20ml'=>11],'GB','',3)['shipping_ils_cents']===14200,'UK extra kg');
check(commerce_calculate(['saffron-oil-20ml'=>25],'SG','',3)['shipping_ils_cents']===26700,'Singapore 5kg');
foreach(['IR','SY','LB','IQ','ZZ','IL'] as $c)rejects(fn()=>commerce_calculate($one,$c,'',3),'destination_unavailable');
foreach([0,-1,1.5,'1',26] as $n)rejects(fn()=>commerce_calculate(['saffron-oil-20ml'=>$n],'GB','',3),'invalid_cart');
rejects(fn()=>commerce_calculate(['unknown'=>1],'GB','',3),'invalid_cart');
rejects(fn()=>commerce_calculate(['saffron-oil-20ml'=>11],'AR','',3),'parcel_overweight');
rejects(fn()=>commerce_calculate($one,'PT','',3),'region_required');
$o=['id'=>str_repeat('a',48),'low_profile_id'=>'some-profile','quote'=>['total_usd_cents'=>6176]];
$r=['ResponseCode'=>0,'Operation'=>'ChargeOnly','TerminalNumber'=>140545,'ReturnValue'=>$o['id'],'LowProfileId'=>'some-profile','TranzactionId'=>123,'TranzactionInfo'=>['ResponseCode'=>0,'TerminalNumber'=>140545,'CoinId'=>2,'Amount'=>61.76,'IsRefund'=>false]];
check(shop_paid_matches($r,$o,140545),'valid verified transaction');
foreach(['Amount'=>61.75,'CoinId'=>1,'TerminalNumber'=>1,'ResponseCode'=>5,'IsRefund'=>true] as $k=>$v){$bad=$r;$bad['TranzactionInfo'][$k]=$v;check(!shop_paid_matches($bad,$o,140545),'reject transaction '.$k);}
foreach(['ReturnValue'=>'other','LowProfileId'=>'other','Operation'=>'CreateTokenOnly','ResponseCode'=>1,'TranzactionId'=>0] as $k=>$v){$bad=$r;$bad[$k]=$v;check(!shop_paid_matches($bad,$o,140545),'reject result '.$k);}
echo "$checks meaningful shipping and payment verification checks passed.\n";
