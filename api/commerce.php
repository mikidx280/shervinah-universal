<?php
declare(strict_types=1);
require_once __DIR__.'/commerce-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
function shop_reply(int $status,array $body): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function shop_write($fp,array $data): void {rewind($fp);ftruncate($fp,0);if(fwrite($fp,json_encode($data,JSON_THROW_ON_ERROR))===false)throw new RuntimeException('storage_unavailable');fflush($fp);}
$action=(string)($_GET['action']??'catalog');
try {
    if($action==='webhook') {
        // The random order ID locates the record; only Cardcom verification can mark it paid.
        $id=(string)($_GET['order']??'');
        if(!preg_match('/^[a-f0-9]{48}$/',$id)) shop_reply(400,['error'=>'invalid_order']);
        $path=shop_storage().'/'.$id.'.json';
        if(!is_file($path)) shop_reply(404,['error'=>'invalid_order']);
        $fp=fopen($path,'r+');flock($fp,LOCK_EX);$order=json_decode(stream_get_contents($fp),true,512,JSON_THROW_ON_ERROR);
        shop_verify($order);shop_write($fp,$order);flock($fp,LOCK_UN);fclose($fp);
        shop_reply(200,['received'=>true]);
    }
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/']);session_start();
    $_SESSION['shop_csrf']??=bin2hex(random_bytes(24));
    if($action==='catalog' && $_SERVER['REQUEST_METHOD']==='GET') {
        $rate=shop_rate();$products=commerce_catalog();
        foreach($products as &$p) $p['usd_cents']=(int)round($p['ils_cents']/$rate['rate']);unset($p);
        shop_reply(200,['products'=>$products,'countries'=>commerce_groups(),'rate'=>$rate,'csrf'=>$_SESSION['shop_csrf']]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST') shop_reply(405,['error'=>'method_not_allowed']);
    if((int)($_SERVER['CONTENT_LENGTH']??0)>16000) shop_reply(413,['error'=>'request_too_large']);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if(!in_array($origin,['https://shervinahuniversal.com','https://www.shervinahuniversal.com'],true)) shop_reply(403,['error'=>'invalid_origin']);
    $input=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($input) || !hash_equals($_SESSION['shop_csrf'],(string)($input['csrf']??''))) shop_reply(403,['error'=>'invalid_session']);
    if($action==='quote') {
        $q=shop_quote((array)($input['items']??[]),(string)($input['country']??''),(string)($input['region']??''));
        $q['id']=bin2hex(random_bytes(24));$_SESSION['shop_quote']=$q;
        shop_reply(200,$q);
    }
    if($action==='checkout') {
        $q=$_SESSION['shop_quote']??null;
        if(!$q || !hash_equals($q['id'],(string)($input['quote_id']??'')) || $q['expires_at']<time()) shop_reply(409,['error'=>'quote_expired']);
        if(empty($q['payment_available'])) shop_reply(503,['error'=>$q['unavailable_reason']]);
        if(empty($input['consent'])) shop_reply(422,['error'=>'consent_required']);
        $customer=[];
        foreach(['name'=>180,'email'=>190,'phone'=>60,'address'=>300,'city'=>120,'postal_code'=>30] as $field=>$max) {
            $v=trim((string)($input['customer'][$field]??''));
            if($v==='' || strlen($v)>$max || preg_match('/[\x00-\x1F]/',$v)) shop_reply(422,['error'=>'customer_details']);
            $customer[$field]=$v;
        }
        if(!filter_var($customer['email'],FILTER_VALIDATE_EMAIL)) shop_reply(422,['error'=>'customer_details']);
        $config=shop_config();
        if(in_array($q['country'],$config['suspended_countries']??[],true)) shop_reply(422,['error'=>'destination_unavailable']);
        $id=$q['id'];$path=shop_storage().'/'.$id.'.json';$fp=fopen($path,'c+');flock($fp,LOCK_EX);
        $old=stream_get_contents($fp);$order=$old?json_decode($old,true,512,JSON_THROW_ON_ERROR):null;
        if($order) {
            if(!empty($order['payment_url']) && $order['status']==='pending') shop_reply(200,['url'=>$order['payment_url']]);
            shop_reply(409,['error'=>$order['status']==='paid'?'already_paid':'payment_pending']);
        }
        // Bound session creation, and never repeat an uncertain provider request.
        if(($_SESSION['last_checkout']??0)>time()-30) shop_reply(429,['error'=>'try_later']);
        $_SESSION['last_checkout']=time();
        $order=['id'=>$id,'quote'=>$q,'customer'=>$customer,'status'=>'creating','created_at'=>gmdate('c'),'consent_version'=>'2026-09-19'];
        shop_write($fp,$order);
        $base=$origin;
        $r=shop_cardcom('Create',['Operation'=>'ChargeOnly','ReturnValue'=>$id,'Amount'=>$q['total_usd_cents']/100,'ISOCoinId'=>2,'Language'=>'en','ProductName'=>'Shervinah order '.substr($id,0,12),'SuccessRedirectUrl'=>$base.'/checkout.html?order='.$id,'FailedRedirectUrl'=>$base.'/checkout.html?order='.$id.'&result=failed','CancelRedirectUrl'=>$base.'/checkout.html?order='.$id.'&result=cancelled','WebHookUrl'=>$base.'/api/commerce.php?action=webhook&order='.$id,'UIDefinition'=>['CardOwnerNameValue'=>$customer['name'],'CardOwnerEmailValue'=>$customer['email'],'CardOwnerPhoneValue'=>$customer['phone'],'IsCardOwnerEmailRequired'=>true],'AdvancedDefinition'=>['MinNumOfPayments'=>1,'MaxNumOfPayments'=>1]]);
        $url=(string)($r['Url']??'');
        if(($r['ResponseCode']??null)!==0 || empty($r['LowProfileId']) || parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_HOST)!=='secure.cardcom.solutions') {
            $order['status']='setup_error';shop_write($fp,$order);shop_reply(502,['error'=>'provider_unavailable']);
        }
        $order['status']='pending';$order['low_profile_id']=$r['LowProfileId'];$order['payment_url']=$url;shop_write($fp,$order);flock($fp,LOCK_UN);fclose($fp);
        $_SESSION['shop_orders'][$id]=true;shop_reply(200,['url'=>$url]);
    }
    if($action==='status') {
        $id=(string)($input['order']??'');
        if(!preg_match('/^[a-f0-9]{48}$/',$id)||empty($_SESSION['shop_orders'][$id]))shop_reply(404,['error'=>'invalid_order']);
        $fp=fopen(shop_storage().'/'.$id.'.json','r+');flock($fp,LOCK_EX);$order=json_decode(stream_get_contents($fp),true,512,JSON_THROW_ON_ERROR);
        shop_verify($order);shop_write($fp,$order);flock($fp,LOCK_UN);fclose($fp);
        shop_reply(200,['status'=>$order['status'],'reference'=>substr($id,0,12),'total_usd_cents'=>$order['quote']['total_usd_cents'],'items'=>$order['quote']['items']]);
    }
    shop_reply(404,['error'=>'not_found']);
} catch(InvalidArgumentException $e) { shop_reply(422,['error'=>$e->getMessage()]); }
catch(Throwable $e) { error_log('Shervinah commerce: '.get_class($e));shop_reply(503,['error'=>'service_unavailable']); }
