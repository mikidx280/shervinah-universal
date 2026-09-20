<?php
declare(strict_types=1);
require_once __DIR__.'/catalog-management.php';
function shop_mail_config(): array {
    $path=dirname(__DIR__,2).'/private-mail-config.php';
    $config=is_file($path)?(array)require $path:[];
    // A previous Gmail connection must not silently send after the sender changes.
    if(($config['from']??'')!=='orders@shervinahuniversal.com'||($config['username']??'')!=='orders@shervinahuniversal.com')$config['enabled']=false;
    return $config;
}
function shop_order_link(array $order): string {
    return 'https://shervinahuniversal.com/order.php?id='.$order['id'].'&token='.($order['access_token']??'');
}
function shop_mail_messages(array $order): array {
    if(($order['status']??'')!=='paid')return [];
    $ref=substr($order['id'],0,12);$fa=($order['language']??'en')==='fa';$q=$order['quote'];
    $lines=[];foreach($q['items'] as $item)$lines[]=$item['quantity'].' × '.($fa?$item['name_fa']:$item['name']).' · USD '.number_format($item['unit_usd_cents']*$item['quantity']/100,2);
    $summary=implode("\n",$lines)."\n".($fa?'ارسال: ':'Shipping: ').'USD '.number_format($q['shipping_usd_cents']/100,2)."\n".($fa?'جمع: ':'Total: ').'USD '.number_format($q['total_usd_cents']/100,2);
    $c=$order['customer'];$address=$c['name']."\n".$c['address']."\n".$c['city'].' '.($c['state']??'').' '.$c['postal_code']."\n".$q['country'].' '.$q['region'];
    $body=($fa?'پرداخت شما تأیید شد. از سفارش شما سپاسگزاریم.':'Your payment is confirmed. Thank you for your order.')."\n\n".$summary."\n\n".$address."\n\n".shop_order_link($order)."\n\n".($fa?'این پیام تأیید سفارش است و فاکتور مالیاتی نیست.':'This is an order confirmation, not a tax invoice.')."\nshervinahuniversal@gmail.com";
    $messages=[['key'=>'confirmation','to'=>$c['email'],'subject'=>($fa?'تأیید سفارش ':'Order confirmed ').$ref,'body'=>$body]];
    foreach(['mikidx280@gmail.com','shervinahuniversal@gmail.com'] as $recipient)$messages[]=['key'=>'merchant-'.substr(hash('sha256',$recipient),0,12),'to'=>$recipient,'subject'=>'Paid order '.$ref,'body'=>"New verified paid order.\n\n".$summary."\n\n".$address."\n".$c['email'].' · '.$c['phone']."\n\nManage and print packing slip:\nhttps://shervinahuniversal.com/admin/order.php?id=".$order['id']];
    if(!empty($order['shipment']['tracking'])){
        $s=$order['shipment'];$messages[]=['key'=>'shipped-'.substr(hash('sha256',$s['tracking']),0,12),'to'=>$c['email'],'subject'=>($fa?'سفارش شما ارسال شد ':'Your order has shipped ').$ref,'body'=>($fa?'سفارش شما ارسال شد.':'Your order has shipped.')."\n".$s['carrier'].': '.$s['tracking']."\n".$s['shipped_at']."\n".($s['url']??'')."\n\n".shop_order_link($order)];
    }
    return $messages;
}
function shop_mail_queue(array $order): array {
    $dir=shop_storage().'/outbox';if(!is_dir($dir)&&!mkdir($dir,0700)&&!is_dir($dir))throw new RuntimeException('mail_storage');
    $paths=[];
    foreach(shop_mail_messages($order) as $message){
        $path=$dir.'/'.$order['id'].'-'.$message['key'].'.json';$paths[]=$path;
        $lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('mail_storage');
        try{if(!is_file($path))shop_atomic_json($path,$message+['order_id'=>$order['id'],'status'=>'queued','attempts'=>0,'created_at'=>gmdate('c')]);}finally{fclose($lock);}
    }
    return $paths;
}
function shop_mail_deliver(string $path,bool $manualRetry=false): void {
    $lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return;}
    try{
        $job=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if($job['status']==='accepted'||(!$manualRetry&&in_array($job['status'],['sending','uncertain'],true)))return;
        $config=shop_mail_config();
        if(empty($config['enabled'])||empty($config['host'])||empty($config['username'])||empty($config['password'])||!filter_var($config['from']??'',FILTER_VALIDATE_EMAIL)){
            $job['status']='configuration_needed';shop_atomic_json($path,$job);return;
        }
        foreach(['Exception','PHPMailer','SMTP'] as $class)require_once dirname(__DIR__).'/vendor/phpmailer/'.$class.'.php';
        $mail=new PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=$config['host'];$mail->SMTPAuth=true;$mail->Username=$config['username'];$mail->Password=$config['password'];
        $mail->SMTPSecure=($config['encryption']??'tls')==='smtps'?'ssl':'tls';$mail->Port=(int)($config['port']??587);$mail->Timeout=10;$mail->Timelimit=10;$mail->CharSet='UTF-8';
        $mail->setFrom($config['from'],'Shervinah Universal');$mail->addReplyTo($config['from']);$mail->addAddress($job['to']);$mail->Subject=$job['subject'];$mail->Body=$job['body'];
        $mail->MessageID='<'.hash('sha256',basename($path)).'@shervinahuniversal.com>';
        // Persist before sending. An interrupted SMTP exchange is uncertain, never silently retried.
        $job['status']='sending';$job['attempts']++;$job['last_attempt']=gmdate('c');shop_atomic_json($path,$job);
        try{$mail->send();$job['status']='accepted';$job['accepted_at']=gmdate('c');unset($job['error']);}
        catch(Throwable $e){$job['status']='uncertain';$job['error']='SMTP did not confirm completion. Check the sender mailbox/provider before retrying.';}
        shop_atomic_json($path,$job);
    }finally{fclose($lock);}
}
function shop_notify(array $order): void {
    try{foreach(shop_mail_queue($order) as $path)shop_mail_deliver($path);}catch(Throwable $e){error_log('Shervinah mail queue failed; order remains paid.');}
}
