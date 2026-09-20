<?php
declare(strict_types=1);
// Order records are the inventory ledger, so a duplicate webhook never deducts twice.
function shop_stock_totals(array $products,array $orders): array {
    $stock=[];
    foreach($products as $id=>$p)$stock[$id]=['initial'=>$p['initial_stock'],'sold'=>0,'reserved'=>0,'available'=>$p['initial_stock']];
    foreach($orders as $o){
        if(empty($o['inventory_reserved']))continue;
        $bucket=($o['status']??'')==='paid'?'sold':(in_array($o['status']??'',['creating','pending','setup_error'],true)?'reserved':null);
        if($bucket===null)continue;
        foreach($o['quote']['items'] as $line){
            if(!isset($stock[$line['id']]))continue;
            $stock[$line['id']][$bucket]+=$line['quantity'];
        }
    }
    foreach($stock as &$row)$row['available']=max(0,$row['initial']-$row['sold']-$row['reserved']);unset($row);
    return $stock;
}
function shop_stock_assert(array $lines,array $stock): void {
    foreach($lines as $line)if(!isset($stock[$line['id']])||$line['quantity']>$stock[$line['id']]['available'])throw new InvalidArgumentException('out_of_stock');
}
// All order writers take this lock before taking any per-order lock.
function shop_inventory_lock(bool $exclusive=false){
    $fp=fopen(shop_storage().'/inventory.lock','c+');
    if(!$fp||!flock($fp,$exclusive?LOCK_EX:LOCK_SH))throw new RuntimeException('storage_unavailable');
    return $fp;
}
function shop_stock(): array {
    $orders=[];
    foreach(glob(shop_storage().'/*.json')?:[] as $path){
        if(!preg_match('/^[a-f0-9]{48}\.json$/',basename($path)))continue;
        $raw=file_get_contents($path);
        if($raw===false)throw new RuntimeException('storage_unavailable');
        if($raw==='')continue;
        $orders[]=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    }
    return shop_stock_totals(commerce_catalog(),$orders);
}
