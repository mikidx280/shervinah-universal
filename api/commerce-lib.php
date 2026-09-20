<?php
declare(strict_types=1);
require_once __DIR__.'/commerce-data.php';
function shop_config(): array {
    $path=dirname(__DIR__,2).'/private-payment-config.php';
    return is_file($path) ? (array)require $path : [];
}
function shop_storage(): string {
    $path=dirname(__DIR__,2).'/shervinah-orders';
    if(!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) throw new RuntimeException('storage_unavailable');
    return $path;
}
function shop_http(string $url, ?array $data=null): array {
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json']]);
    if($data!==null) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($data,JSON_THROW_ON_ERROR)]);
    $body=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($body===false || $status<200 || $status>=300) throw new RuntimeException('provider_unavailable');
    $result=json_decode($body,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($result)) throw new RuntimeException('provider_unavailable');
    return $result;
}
function shop_rate(): array {
    $path=shop_storage().'/usd-rate.json';
    $cache=is_file($path)?json_decode((string)file_get_contents($path),true):null;
    // Refresh every 15 minutes, including during the daily publication window.
    if(is_array($cache) && ($cache['fetched_at']??0)>time()-900) return $cache;
    $raw=shop_http('https://boi.org.il/PublicApi/GetExchangeRate?key=USD');
    $rate=(float)($raw['currentExchangeRate']??0); $date=strtotime((string)($raw['lastUpdate']??''));
    if(($raw['key']??'')!=='USD' || (int)($raw['unit']??1)!==1 || $rate<0.1 || $rate>100 || !$date || $date>time()+3600 || $date<time()-10*86400) throw new RuntimeException('rate_unavailable');
    $result=['rate'=>$rate,'date'=>gmdate('Y-m-d',$date),'fetched_at'=>time()];
    file_put_contents($path,json_encode($result),LOCK_EX); return $result;
}
function shop_quote(array $items,string $country,string $region): array {
    $rate=shop_rate(); $q=commerce_calculate($items,$country,$region,$rate['rate']);
    $q['rate_date']=$rate['date']; $q['expires_at']=time()+900;
    $config=shop_config();
    $q['payment_available']=!empty($config['enabled']) && !empty($config['api_name']) && !empty($config['terminal_number']);
    $q['unavailable_reason']=$q['payment_available']?'':'payment_setup';
    if(in_array($country,$config['suspended_countries']??[],true)) throw new InvalidArgumentException('destination_unavailable');
    // US DDP charges cannot safely be inferred from postage alone.
    // Keep a base quote visible but prevent charging an incomplete total.
    if(in_array($country,['US','PR','VI'],true)) {
        $q['payment_available']=false; $q['unavailable_reason']='us_customs_quote'; $q['total_is_estimate']=true;
    } else $q['total_is_estimate']=false;
    return $q;
}
function shop_cardcom(string $action,array $body): array {
    $config=shop_config();
    if(empty($config['enabled']) || empty($config['api_name']) || empty($config['terminal_number'])) throw new RuntimeException('payment_setup');
    return shop_http('https://secure.cardcom.solutions/api/v11/LowProfile/'.$action, ['TerminalNumber'=>(int)$config['terminal_number'],'ApiName'=>(string)$config['api_name']]+$body);
}
function shop_paid_matches(array $r,array $order,int $terminal): bool {
    $t=$r['TranzactionInfo']??[];
    return ($r['ResponseCode']??null)===0 && ($t['ResponseCode']??null)===0
        && ($r['Operation']??'')==='ChargeOnly' && (int)($r['TerminalNumber']??0)===$terminal
        && (int)($t['TerminalNumber']??0)===$terminal
        && hash_equals($order['id'],(string)($r['ReturnValue']??''))
        && strtolower((string)($r['LowProfileId']??''))===strtolower($order['low_profile_id']??'missing')
        && in_array((int)($t['CoinId']??0),[2,840],true)
        && (int)round((float)($t['Amount']??-1)*100)===$order['quote']['total_usd_cents']
        && (string)($r['TranzactionId']??'0')!=='0' && empty($t['IsRefund']);
}
function shop_verify(array &$order): void {
    if($order['status']==='paid' || empty($order['low_profile_id'])) return;
    $r=shop_cardcom('GetLpResult',['LowProfileId'=>$order['low_profile_id']]);
    if(shop_paid_matches($r,$order,(int)shop_config()['terminal_number'])) {
        $order['status']='paid'; $order['transaction_id']=(string)$r['TranzactionId']; $order['paid_at']=gmdate('c');
    }
}
