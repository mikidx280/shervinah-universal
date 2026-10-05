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
    $ref=substr($order['id'],0,12);$fa=($order['language']??'en')==='fa';$q=$order['quote'];$customer=$order['customer'];
    $name=trim((string)($customer['name']??''));
    $parts=preg_split('/\s+/u',$name,-1,PREG_SPLIT_NO_EMPTY);$firstName=$parts[0]??$name;
    $lines=[];foreach($q['items'] as $item)$lines[]=$item['quantity'].' × '.($fa?$item['name_fa']:$item['name']).' · USD '.number_format($item['unit_usd_cents']*$item['quantity']/100,2);
    $items=implode("\n",$lines);
    $address=$customer['name']."\n".$customer['address']."\n".$customer['city'].' '.($customer['state']??'').' '.$customer['postal_code']."\n".$q['country'].(!empty($q['region'])?' '.$q['region']:'');
    $orderLink=shop_order_link($order);

    if($fa){
        $subject='از سفارش شما متشکریم ✨ | Shervinah Universal #'.$ref;
        $body="سلام {$firstName} عزیز،\n\n"
            ."از خرید شما از Shervinah Universal سپاسگزاریم ✨\n\n"
            ."پرداخت شما با موفقیت تأیید شده و اکنون سفارش شما را با دقت و توجه آماده می‌کنیم.\n\n"
            ."سفارش شما\n\n".$items."\n\n"
            ."هزینه ارسال: USD ".number_format($q['shipping_usd_cents']/100,2)."\n"
            ."مبلغ کل: USD ".number_format($q['total_usd_cents']/100,2)."\n\n"
            ."آدرس ارسال\n".$address."\n\n"
            ."مرحله بعد چیست؟\n\n"
            ."سفارش شما با دقت آماده و به آدرس بالا ارسال خواهد شد.\n\n"
            ."زمان تحویل ممکن است تا ۳۰ روز کاری طول بکشد. تعطیلات رسمی و روزهای استراحت رسمی در اسرائیل جزو روزهای کاری محسوب نمی‌شوند.\n\n"
            ."پس از ارسال سفارش، ایمیل دیگری شامل اطلاعات و شماره پیگیری مرسوله برای شما ارسال خواهد شد.\n\n"
            ."در هر زمان می‌توانید وضعیت سفارش خود را از طریق لینک زیر مشاهده کنید:\n".$orderLink."\n\n"
            ."اگر درباره سفارش خود سؤالی دارید، می‌توانید به همین ایمیل پاسخ دهید یا با ما از طریق آدرس زیر در تماس باشید:\n"
            ."orders@shervinahuniversal.com\n\n"
            ."از اینکه محصولی معنادار از اسرائیل را انتخاب کردید، سپاسگزاریم. ✨\n\n"
            ."با مهر،\nShervinah Universal\n\n"
            ."این ایمیل تأیید سفارش و پرداخت شماست و فاکتور مالیاتی محسوب نمی‌شود.";
    }else{
        $subject='Thank you for your order ✨ | Shervinah Universal #'.$ref;
        $body="Hi {$firstName},\n\n"
            ."Thank you for your order from Shervinah Universal ✨\n\n"
            ."Your payment has been successfully confirmed, and we’re now preparing your order with care.\n\n"
            ."Your order\n\n".$items."\n\n"
            ."Shipping: USD ".number_format($q['shipping_usd_cents']/100,2)."\n"
            ."Total: USD ".number_format($q['total_usd_cents']/100,2)."\n\n"
            ."Shipping address\n".$address."\n\n"
            ."What happens next?\n\n"
            ."Your order will be carefully prepared and shipped to the address above.\n\n"
            ."Delivery may take up to 30 business days, excluding public holidays and official days of rest in Israel.\n\n"
            ."Once your order has been shipped, we’ll send you another email with your tracking information.\n\n"
            ."You can view your order status anytime here:\n".$orderLink."\n\n"
            ."If you have any questions about your order, simply reply to this email or contact us at:\n"
            ."orders@shervinahuniversal.com\n\n"
            ."Thank you for choosing something meaningful from Israel. ✨\n\n"
            ."Warmly,\nShervinah Universal\n\n"
            ."This email confirms your order and payment. It is not a tax invoice.";
    }

    $messages=[['key'=>'confirmation','to'=>$customer['email'],'subject'=>$subject,'body'=>$body]];
    $summary=$items."\n".($fa?'ارسال: ':'Shipping: ').'USD '.number_format($q['shipping_usd_cents']/100,2)."\n".($fa?'جمع: ':'Total: ').'USD '.number_format($q['total_usd_cents']/100,2);
    foreach(['mikidx280@gmail.com','shervinahuniversal@gmail.com'] as $recipient)$messages[]=['key'=>'merchant-'.substr(hash('sha256',$recipient),0,12),'to'=>$recipient,'subject'=>'Paid order '.$ref,'body'=>"New verified paid order.\n\n".$summary."\n\n".$address."\n".$customer['email'].' · '.$customer['phone']."\n\nManage and print packing slip:\nhttps://shervinahuniversal.com/admin/order.php?id=".$order['id']];

    if(!empty($order['shipment']['tracking'])){
        $s=$order['shipment'];$trackingUrl=trim((string)($s['url']??''));
        if($fa){
            $shipSubject='سفارش شما ارسال شد ✨ | Shervinah Universal #'.$ref;
            $shipBody="سلام {$firstName} عزیز،\n\n"
                ."خبر خوب ✨\nسفارش شما از Shervinah Universal ارسال شد.\n\n"
                ."اطلاعات ارسال\n\n"
                ."شرکت حمل‌ونقل: ".$s['carrier']."\n"
                ."شماره پیگیری: ".$s['tracking']."\n"
                .($trackingUrl!==''?"\nمی‌توانید مرسوله خود را از طریق لینک زیر پیگیری کنید:\n".$trackingUrl."\n":'')
                ."\nزمان تحویل ممکن است تا ۳۰ روز کاری طول بکشد. تعطیلات رسمی و روزهای استراحت رسمی در اسرائیل جزو روزهای کاری محسوب نمی‌شوند.\n\n"
                ."زمان تحویل بین‌المللی ممکن است تحت تأثیر خدمات پستی کشور مقصد، مراحل گمرکی یا تأخیرهای محلی نیز قرار بگیرد.\n\n"
                ."در هر زمان می‌توانید وضعیت سفارش خود را از طریق لینک زیر مشاهده کنید:\n".$orderLink."\n\n"
                ."اگر سؤالی دارید، می‌توانید به همین ایمیل پاسخ دهید یا با ما از طریق آدرس زیر در تماس باشید:\n"
                ."orders@shervinahuniversal.com\n\n"
                ."از انتخاب Shervinah Universal سپاسگزاریم ✨\n\n"
                ."با مهر،\nShervinah Universal";
        }else{
            $shipSubject='Your order is on the way ✨ | Shervinah Universal #'.$ref;
            $shipBody="Hi {$firstName},\n\n"
                ."Good news — your order from Shervinah Universal has been shipped ✨\n\n"
                ."Shipping details\n\n"
                ."Carrier: ".$s['carrier']."\n"
                ."Tracking number: ".$s['tracking']."\n"
                .($trackingUrl!==''?"\nYou can track your shipment here:\n".$trackingUrl."\n":'')
                ."\nDelivery may take up to 30 business days, excluding public holidays and official days of rest in Israel.\n\n"
                ."Please note that international delivery times may also be affected by the destination country’s postal service, customs procedures or local delays.\n\n"
                ."You can view your order status anytime here:\n".$orderLink."\n\n"
                ."If you have any questions, simply reply to this email or contact us at:\n"
                ."orders@shervinahuniversal.com\n\n"
                ."Thank you for choosing Shervinah Universal ✨\n\n"
                ."Warmly,\nShervinah Universal";
        }
        $messages[]=['key'=>'shipped-'.substr(hash('sha256',$s['tracking']),0,12),'to'=>$customer['email'],'subject'=>$shipSubject,'body'=>$shipBody];
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
