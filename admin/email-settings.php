<?php
require __DIR__.'/shop-auth.php';
require dirname(__DIR__).'/api/commerce-mail.php';
$error='';$notice='';$config=shop_mail_config();
if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_csrf();
    try{
        if(($_POST['action']??'')==='save'){
            $password=(string)($_POST['smtp_password']??'');
            if(strlen($password)>256||preg_match('/[\x00-\x1F]/',$password))throw new InvalidArgumentException('Check the mailbox password.');
            $sameMailbox=($config['username']??'')==='orders@shervinahuniversal.com'&&($config['host']??'')==='smtp.hostinger.com';
            $password=$password!==''?$password:($sameMailbox?(string)($config['password']??''):'');
            if(!$password&&!empty($_POST['enabled']))throw new InvalidArgumentException('Enter the orders mailbox password before enabling email.');
            $config=['enabled'=>!empty($_POST['enabled']),'host'=>'smtp.hostinger.com','port'=>465,'encryption'=>'smtps','username'=>'orders@shervinahuniversal.com','from'=>'orders@shervinahuniversal.com','password'=>$password];
            $path=dirname(__DIR__,2).'/private-mail-config.php';$tmp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';$raw="<?php\nreturn ".var_export($config,true).";\n";
            if(file_put_contents($tmp,$raw,LOCK_EX)!==strlen($raw))throw new RuntimeException('Save failed');chmod($tmp,0600);
            if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Save failed');}
            header('Location: email-settings.php?saved=1');exit;
        }
        if(($_POST['action']??'')==='test'){
            if(empty($config['enabled']))throw new InvalidArgumentException('Save and enable email first.');
            if(($_SESSION['mail_test_at']??0)>time()-60)throw new InvalidArgumentException('Wait a minute before another test.');
            $_SESSION['mail_test_at']=time();$dir=shop_storage().'/outbox';if(!is_dir($dir))mkdir($dir,0700);
            $states=[];
            foreach(['mikidx280@gmail.com','shervinahuniversal@gmail.com'] as $to){$path=$dir.'/test-'.bin2hex(random_bytes(16)).'.json';shop_atomic_json($path,['key'=>'connection-test','to'=>$to,'subject'=>'Shervinah store email connection test','body'=>'This is a connection test requested from your store admin. No order or payment was created.','status'=>'queued','attempts'=>0,'created_at'=>gmdate('c')]);shop_mail_deliver($path);$job=json_decode((string)file_get_contents($path),true);$states[]=$to.': '.$job['status'];}
            $notice=implode(' · ',$states).'. Check both inboxes and spam folders.';
        }
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Could not save or test email. Check the configuration and try again.';}
}
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Email settings</title><link rel="stylesheet" href="admin.css"><header><a href="orders.php">Orders</a><a href="products.php">Products</a><a href="email-settings.php">Email settings</a></header><main><h1>Order email connection</h1><p>Sender: <strong>orders@shervinahuniversal.com</strong>. Merchant alerts: mikidx280@gmail.com and shervinahuniversal@gmail.com.</p><p>Create the orders@shervinahuniversal.com mailbox in Hostinger Email first, complete the domain email/DNS setup, then enter its mailbox password here yourself. This does not create the mailbox. Do not enter your Hostinger account password or send passwords in chat.</p><p>Hostinger Email: smtp.hostinger.com, encrypted SMTP on port 465. If your domain uses a different mail provider, configure that provider privately before enabling sending.</p><p><a href="https://www.hostinger.com/support/1575756-how-to-get-email-account-configuration-details-for-hostinger-email/" target="_blank" rel="noreferrer noopener">Hostinger email setup instructions</a></p><p>Settings are stored privately above the website directory. The saved password is never displayed.</p>
<?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif?><?php if(isset($_GET['saved'])):?><p role="status">Settings saved. Use the test below to verify delivery.</p><?php endif?><?php if($notice):?><p role="status"><?=esc($notice)?></p><?php endif?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label>Orders mailbox password<input type="password" name="smtp_password" autocomplete="new-password" maxlength="256" placeholder="<?=(($config['username']??'')==='orders@shervinahuniversal.com'&&!empty($config['password']))?'Saved. Leave blank to keep it.':'Mailbox password'?>"></label><label><input type="checkbox" name="enabled" <?=!empty($config['enabled'])&&($config['username']??'')==='orders@shervinahuniversal.com'?'checked':''?>> Enable order emails</label><button name="action" value="save">Save connection</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><p>Sends a test to both merchant addresses. SMTP acceptance does not guarantee inbox delivery.</p><button name="action" value="test">Send connection test</button></form></main></html>
