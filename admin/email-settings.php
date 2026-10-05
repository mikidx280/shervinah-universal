<?php
require __DIR__.'/shop-auth.php';
require dirname(__DIR__).'/api/commerce-mail.php';
$error='';$notice='';$config=shop_mail_config();
if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_csrf();
    try{
        if(($_POST['action']??'')==='save'){
            $password=(string)($_POST['smtp_password']??'');
            if(strlen($password)>256||preg_match('/[\x00-\x1F]/',$password))throw new InvalidArgumentException('יש לבדוק את סיסמת תיבת המייל.');
            $sameMailbox=($config['username']??'')==='orders@shervinahuniversal.com'&&($config['host']??'')==='smtp.hostinger.com';
            $password=$password!==''?$password:($sameMailbox?(string)($config['password']??''):'');
            if(!$password&&!empty($_POST['enabled']))throw new InvalidArgumentException('יש להזין את סיסמת תיבת orders לפני הפעלת שליחת המיילים.');
            $config=['enabled'=>!empty($_POST['enabled']),'host'=>'smtp.hostinger.com','port'=>465,'encryption'=>'smtps','username'=>'orders@shervinahuniversal.com','from'=>'orders@shervinahuniversal.com','password'=>$password];
            $path=dirname(__DIR__,2).'/private-mail-config.php';$tmp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';$raw="<?php\nreturn ".var_export($config,true).";\n";
            if(file_put_contents($tmp,$raw,LOCK_EX)!==strlen($raw))throw new RuntimeException('שמירת ההגדרות נכשלה.');chmod($tmp,0600);
            if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('שמירת ההגדרות נכשלה.');}
            header('Location: email-settings.php?saved=1');exit;
        }
        if(($_POST['action']??'')==='test'){
            if(empty($config['enabled']))throw new InvalidArgumentException('יש לשמור ולהפעיל תחילה את שליחת המיילים.');
            if(($_SESSION['mail_test_at']??0)>time()-60)throw new InvalidArgumentException('יש להמתין דקה לפני שליחת בדיקה נוספת.');
            $_SESSION['mail_test_at']=time();$dir=shop_storage().'/outbox';if(!is_dir($dir))mkdir($dir,0700);
            $states=[];$statusLabels=['accepted'=>'התקבל בשרת הדואר','configuration_needed'=>'נדרשת הגדרה','uncertain'=>'שליחה לא ודאית','queued'=>'ממתין לשליחה','sending'=>'בתהליך שליחה'];
            foreach(['mikidx280@gmail.com','shervinahuniversal@gmail.com'] as $to){$path=$dir.'/test-'.bin2hex(random_bytes(16)).'.json';shop_atomic_json($path,['key'=>'connection-test','to'=>$to,'subject'=>'Shervinah store email connection test','body'=>'This is a connection test requested from your store admin. No order or payment was created.','status'=>'queued','attempts'=>0,'created_at'=>gmdate('c')]);shop_mail_deliver($path);$job=json_decode((string)file_get_contents($path),true);$states[]=$to.': '.($statusLabels[$job['status']]??$job['status']);}
            $notice=implode(' · ',$states).'. יש לבדוק גם את תיקיית הספאם.';
        }
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'לא ניתן היה לשמור או לבדוק את הגדרות המייל. יש לבדוק את ההגדרה ולנסות שוב.';}
}
?><!doctype html><html lang="he" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>הגדרות מייל | Shervinah</title><link rel="stylesheet" href="admin.css"><header><strong>Shervinah · ניהול החנות</strong><a href="orders.php">הזמנות</a><a href="products.php">מוצרים ומלאי</a><a href="index.php">פניות</a><a href="email-settings.php">הגדרות מייל</a><a href="index.php?logout=1">יציאה</a></header><main><h1>חיבור מייל להזמנות</h1><p>כתובת השולח: <strong dir="ltr">orders@shervinahuniversal.com</strong></p><p>התראות על הזמנות משולמות נשלחות ל־<bdi>mikidx280@gmail.com</bdi> ול־<bdi>shervinahuniversal@gmail.com</bdi>.</p><p>תיבת <bdi>orders@shervinahuniversal.com</bdi> צריכה להיות קיימת ב־Hostinger Email ולהיות מוגדרת בדומיין. כאן מזינים רק את הסיסמה של תיבת המייל עצמה. אין להזין את סיסמת חשבון Hostinger ואין לשלוח סיסמאות בצ׳אט.</p><p>החיבור משתמש ב־<bdi>smtp.hostinger.com</bdi>, חיבור SMTP מוצפן בפורט <bdi>465</bdi>.</p><p><a href="https://www.hostinger.com/support/1575756-how-to-get-email-account-configuration-details-for-hostinger-email/" target="_blank" rel="noreferrer noopener">הוראות Hostinger להגדרת תיבת מייל</a></p><p>הסיסמה נשמרת בקובץ פרטי מחוץ לתיקיית האתר ולעולם אינה מוצגת במסך.</p>
<?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif?><?php if(isset($_GET['saved'])):?><p role="status">ההגדרות נשמרו. מומלץ לבצע בדיקת שליחה באמצעות הכפתור למטה.</p><?php endif?><?php if($notice):?><p role="status"><?=esc($notice)?></p><?php endif?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label>סיסמת תיבת orders<input type="password" name="smtp_password" autocomplete="new-password" maxlength="256" placeholder="<?=(($config['username']??'')==='orders@shervinahuniversal.com'&&!empty($config['password']))?'הסיסמה שמורה. השאר ריק כדי לא לשנות אותה.':'סיסמת תיבת המייל'?>"></label><label><input type="checkbox" name="enabled" <?=!empty($config['enabled'])&&($config['username']??'')==='orders@shervinahuniversal.com'?'checked':''?>> הפעלת מיילים אוטומטיים להזמנות</label><button name="action" value="save">שמירת החיבור</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><h2>בדיקת שליחה</h2><p>הבדיקה שולחת מייל לשתי כתובות הניהול. אישור מצד שרת SMTP אומר שהשרת קיבל את ההודעה, אך אינו מבטיח שהיא הגיעה לתיבת הדואר.</p><button name="action" value="test">שליחת מייל בדיקה</button></form></main></html>