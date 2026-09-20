<?php
declare(strict_types=1);
// Israel Post business subscriber tariff, July 2026, pp. 6–8 and 14–29.
// Each price is ILS per parcel in the 1–4 monthly shipments column.
function commerce_catalog(): array {
    return [
        'saffron-oil-20ml' => ['name'=>'Saffron Healing Oil, 20 ml','name_fa'=>'روغن زعفران، ۲۰ میلی‌لیتر','ils_cents'=>12500,'packed_grams'=>200,'initial_stock'=>10],
        'jerusalem-gift-set' => ['name'=>'Jerusalem Gift Set: Treasures of the Holy Land','name_fa'=>'بسته هدیه اورشلیم: یادگارهای سرزمین مقدس','ils_cents'=>4000,'packed_grams'=>200,'initial_stock'=>20],
        'red-string-pack-5' => ['name'=>'Red String Bracelets, Pack of 5','name_fa'=>'بسته ۵ عددی دستبند نخ قرمز','ils_cents'=>3000,'packed_grams'=>70,'initial_stock'=>100],
        'hamsa-home-blessing' => ['name'=>'Hamsa with Hebrew Home Blessing','name_fa'=>'خمسه با دعای برکت خانه به زبان عبری','ils_cents'=>4000,'packed_grams'=>250,'initial_stock'=>10],
    ];
}
function commerce_groups(): array {
    // ISO country/territory codes. No default group for unknown destinations.
    $lists = [
        1 => 'US CA IE UY EC BO VE LU NO SI PF FI PE CU CO CW SR SX',
        2 => 'UZ AT UA AZ AE IT BG BH BE BD BR GB GP GE DE ZA IN NL HK TJ GR JP JO MD MO CN SK ES PL FR CG KZ KG KE CY RO CH',
        3 => 'AO EE AW BA DO DK ZW TZ LV MK MX NA NP SL SC RS LK PA PY CD HR SE TR PT',
        4 => 'AU IS SV AI AD AG BQ ER AR BS BT BZ BB BN BM DJ GI JM GU GY GF GN GW GQ GM GD DM HT HN VA VG WF VU TV TO TM TC TW TL IO TT SH LR LS MR MN MS MC MM FM MW MY MQ MP CF MH NR NF NE NZ NI SZ SO AS VC KN PM ST LC OM FK FJ PN PG CL XK CK KY KI NC SB',
        5 => 'UG ID AL AM BW BI BF BJ BY GH GA ZM CI TG CV LA LT MU ML MG MZ ME MV MT EG MA NG SG SN PH TD KR KH CM RW RU TH TN'
    ];
    $groups = [];
    foreach ($lists as $group => $codes) foreach (explode(' ', $codes) as $code) $groups[$code] = $group;
    // Explicit cross-references in the source. Regions of countries remain separate when needed.
    foreach (['AX'=>'FI','GG'=>'GB','JE'=>'GB','IM'=>'GB','GL'=>'DK','FO'=>'DK','LI'=>'CH','SM'=>'IT','RE'=>'FR','TK'=>'NZ','NU'=>'NZ','PR'=>'US','VI'=>'US','HM'=>'AU'] as $code=>$parent) $groups[$code]=$groups[$parent];
    return $groups;
}
function commerce_rates(): array {
    return [1=>[6100,6400,7400,8700,10100,12200,15100], 2=>[5500,5700,6400,7500,8500,9900,11800], 3=>[5700,5900,6600,7700,8800,10500,12500], 4=>[6400,7200,9000,11400,13900,17400,22100], 5=>[5500,5700,6800,8100,9600,11600,14200]];
}
function commerce_calculate(array $items, string $country, string $region, float $rate): array {
    if (!is_finite($rate) || $rate <= 0) throw new InvalidArgumentException('rate_unavailable');
    $groups=commerce_groups();
    if (!isset($groups[$country])) throw new InvalidArgumentException('destination_unavailable');
    $group=$groups[$country];
    if ($country==='PT') {
        if (!in_array($region,['mainland','azores','madeira'],true)) throw new InvalidArgumentException('region_required');
        if ($region!=='mainland') $group=4;
    }
    $catalog=commerce_catalog(); $weight=0; $subtotal=0; $productUsd=0; $lines=[];
    if (!$items || count($items)>20) throw new InvalidArgumentException('invalid_cart');
    foreach($items as $id=>$qty) {
        if (!isset($catalog[$id]) || !is_int($qty) || $qty<1 || $qty>25) throw new InvalidArgumentException('invalid_cart');
        $p=$catalog[$id];
        if (empty($p['packed_grams'])) throw new InvalidArgumentException('weight_missing');
        $weight+=$p['packed_grams']*$qty; $subtotal+=$p['ils_cents']*$qty;
        $unitUsd=(int)round($p['ils_cents']/$rate,0,PHP_ROUND_HALF_UP);$productUsd+=$unitUsd*$qty;
        $lines[]=['id'=>$id,'quantity'=>$qty,'name'=>$p['name'],'name_fa'=>$p['name_fa'],'unit_ils_cents'=>$p['ils_cents'],'unit_usd_cents'=>$unitUsd];
    }
    $limits=[100,250,500,750,1000,1500,2000]; $band=null;
    foreach($limits as $i=>$limit) if($weight<=$limit){$band=$i;break;}
    if($band===null && !in_array($country,['GB','SG'],true)) throw new InvalidArgumentException('parcel_overweight');
    if($weight>5000) throw new InvalidArgumentException('parcel_overweight');
    $base=commerce_rates()[$group][$band??6];
    if($weight>2000) $base+=(int)ceil(($weight-2000)/1000)*($country==='GB'?3400:4500);
    $discount=min(1000,$base); $shipping=$base-$discount;
    $shippingUsd=(int)round($shipping/$rate,0,PHP_ROUND_HALF_UP);
    return ['items'=>$lines,'country'=>$country,'region'=>$region,'group'=>$group,'weight_grams'=>$weight,'weight_limit_grams'=>$band!==null?$limits[$band]:(int)(ceil($weight/1000)*1000),'product_ils_cents'=>$subtotal,'shipping_base_ils_cents'=>$base,'shipping_discount_ils_cents'=>$discount,'shipping_ils_cents'=>$shipping,'product_usd_cents'=>$productUsd,'shipping_usd_cents'=>$shippingUsd,'total_usd_cents'=>$productUsd+$shippingUsd,'currency'=>'USD','rate'=>$rate,'tariff_version'=>'israel-post-2026-07-1-4'];
}
