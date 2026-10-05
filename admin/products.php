<?php
require __DIR__.'/shop-auth.php';
require_once dirname(__DIR__).'/api/catalog-management.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['save_featured'])){
    admin_csrf();$lock=shop_inventory_lock(true);
    try{
        $all=commerce_catalog();
        $ids=array_values((array)($_POST['featured']??[]));
        if(count($ids)!==4||count(array_unique($ids))!==4)throw new InvalidArgumentException('יש לבחור ארבעה מוצרים שונים.');
        foreach($ids as $id)if(!isset($all[$id])||empty($all[$id]['active']))throw new InvalidArgumentException('המוצרים הנבחרים חייבים להיות מוצגים בחנות.');
        shop_atomic_json(shop_storage().'/featured-products.json',$ids);
        header('Location: products.php?featured_saved=1');exit;
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'לא ניתן היה לשמור את המוצרים הנבחרים.';}finally{fclose($lock);}
}elseif($_SERVER['REQUEST_METHOD']==='POST'){
    admin_csrf();$lock=shop_inventory_lock(true);
    try{
        $all=commerce_catalog();$id=(string)($_POST['id']??'');
        if($id==='new')$id='product-'.bin2hex(random_bytes(8));
        elseif(!isset($all[$id]))throw new InvalidArgumentException('המוצר לא נמצא.');
        $stock=shop_stock();$old=$all[$id]??['images'=>[]];
        if(isset($all[$id])&&!hash_equals(hash('sha256',json_encode($old)),(string)($_POST['version']??'')))throw new InvalidArgumentException('המוצר השתנה בלשונית אחרת. יש לרענן לפני השמירה.');
        if(isset($stock[$id])&&(string)$stock[$id]['available']!==(string)($_POST['previous_available']??''))throw new InvalidArgumentException('המלאי השתנה בזמן העריכה. יש לרענן ולבדוק את הכמות הזמינה.');
        $p=shop_product_input($_POST,$old,$stock[$id]??[]);
        $images=[];
        foreach($p['images']??[] as $index=>$current){
            $replacement=$_FILES['image_replace_'.$index]??null;
            if($replacement && ($replacement['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                if(isset($_POST['remove_images'][$index]))throw new InvalidArgumentException('יש לבחור הסרה או החלפה לאותה תמונה, לא את שתיהן.');
                $images[]=shop_upload_image($replacement);
            }elseif(!isset($_POST['remove_images'][$index])){$images[]=$current;}
        }
        $p['images']=$images;
        if(($_FILES['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$img=shop_upload_image($_FILES['image']);$p['images']=!empty($_POST['replace_images'])?[$img]:array_merge($p['images'],[$img]);}
        if(!$p['images'])throw new InvalidArgumentException('יש להוסיף לפחות תמונה אחת למוצר.');
        $all[$id]=$p;shop_atomic_json(shop_storage().'/catalog.json',$all);
        header('Location: products.php?edit='.rawurlencode($id).'&saved=1');exit;
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'לא ניתן היה לשמור. נסה שוב.';}finally{fclose($lock);}
}
$lock=shop_inventory_lock();$all=commerce_catalog();$stock=shop_stock();$featured=shop_featured_products($all);fclose($lock);
$id=(string)($_GET['edit']??'');$p=$all[$id]??null;
?><!doctype html><html lang="he" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>מוצרים ומלאי | Shervinah</title><link rel="stylesheet" href="admin.css"><header><strong>Shervinah · ניהול החנות</strong><a href="orders.php">הזמנות</a><a href="products.php">מוצרים ומלאי</a><a href="index.php">פניות</a><a href="email-settings.php">הגדרות מייל</a><a href="index.php?logout=1">יציאה</a></header><main><h1>מוצרים ומלאי</h1><p>המחירים מוגדרים בדולר ארה״ב. יש להזין משקל ארוז בגרמים ואת הכמות הזמינה כרגע למכירה. הזמנות, מלאי ותמונות שהועלו נשמרים גם לאחר עדכוני אתר.</p><a class="button" href="?edit=new">הוספת מוצר</a>
<?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif?><?php if(isset($_GET['saved'])):?><p role="status">המוצר נשמר בהצלחה.</p><?php endif?><?php if(isset($_GET['featured_saved'])):?><p role="status">המוצרים הנבחרים בעמוד הראשי נשמרו בהצלחה.</p><?php endif?>
<section class="featured-admin"><h2>מוצרים נבחרים בעמוד הראשי</h2><p>בחר ארבעה מוצרים שיוצגו בעמוד הבית. התמונה, השם, המחיר ומצב המלאי נלקחים אוטומטית מנתוני המוצר.</p><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="save_featured" value="1"><?php for($slot=0;$slot<4;$slot++):?><label>מוצר נבחר #<?=esc($slot+1)?><select name="featured[]" required><?php foreach($all as $pid=>$row):if(empty($row['active']))continue;?><option value="<?=esc($pid)?>" <?=$pid===($featured[$slot]??'')?'selected':''?>><?=esc($row['name'])?></option><?php endforeach?></select></label><?php endfor?><button type="submit">שמירת המוצרים בעמוד הראשי</button></form></section>
<?php if($p||$id==='new'):?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=esc($id)?>"><input type="hidden" name="version" value="<?=esc(hash('sha256',json_encode($p)))?>"><input type="hidden" name="previous_available" value="<?=esc($stock[$id]['available']??0)?>">
<?php foreach(['name'=>'שם באנגלית','name_fa'=>'שם בפרסית','description'=>'תיאור באנגלית','description_fa'=>'תיאור בפרסית'] as $key=>$label):?><label><?=esc($label)?><textarea name="<?=esc($key)?>" dir="<?=$key==='name_fa'||$key==='description_fa'?'rtl':'ltr'?>" required maxlength="6000"><?=esc($p[$key]??'')?></textarea></label><?php endforeach?>
<label>קטגוריה<select name="category"><?php $categoryLabels=['oils'=>'שמנים וטקסים','jewelry'=>'תכשיטים וסמלים','souvenirs'=>'מזכרות מישראל'];foreach($categoryLabels as $cat=>$catLabel):?><option value="<?=esc($cat)?>" <?=$cat===($p['category']??'')?'selected':''?>><?=esc($catLabel)?></option><?php endforeach?></select></label>
<label>מחיר (USD)<input name="price" type="number" min="0.01" max="99999" step="0.01" value="<?=esc(isset($p['usd_cents'])?number_format($p['usd_cents']/100,2,'.',''):'')?>" required></label>
<label>משקל ארוז למשלוח (גרם)<input name="packed_grams" type="number" min="1" max="5000" value="<?=esc($p['packed_grams']??'')?>" required></label>
<label>כמות זמינה למכירה<input name="available" type="number" min="0" max="100000" value="<?=esc($stock[$id]['available']??0)?>" required></label>
<p>שמור להזמנות בתהליך: <?=esc($stock[$id]['reserved']??0)?> · נמכר: <?=esc($stock[$id]['sold']??0)?></p>
<label><input name="active" type="checkbox" <?=($p['active']??true)?'checked':''?>> הצגה בחנות</label>
<h2>תמונות המוצר</h2><p>אפשר להסיר או להחליף כל תמונה בנפרד. השינויים יחולו לאחר שמירת המוצר. יש להשאיר לפחות תמונה אחת או להעלות תמונה חדשה.</p>
<div class="product-image-editor"><?php foreach($p['images']??[] as $index=>$img):?><fieldset><legend>תמונה <?=esc($index+1)?></legend><img src="../<?=esc($img)?>" alt="תמונת מוצר נוכחית <?=esc($index+1)?>"><label><input type="checkbox" name="remove_images[<?=esc($index)?>]" value="1"> הסרת התמונה</label><label>החלפת התמונה<input type="file" name="image_replace_<?=esc($index)?>" accept="image/jpeg,image/png,image/webp"></label></fieldset><?php endforeach?></div>
<label>הוספת תמונה (JPG, PNG או WebP, עד 8MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><label><input type="checkbox" name="replace_images"> החלפת כל גלריית התמונות בתמונה החדשה</label><button>שמירת המוצר</button></form><?php endif?>
<div class="table-scroll"><table><tr><th>מוצר</th><th>מחיר USD</th><th>זמין</th><th>שמור</th><th>מוצג בחנות</th></tr><?php foreach($all as $pid=>$row):?><tr><td><a href="?edit=<?=esc($pid)?>"><?=esc($row['name'])?></a></td><td><?=number_format($row['usd_cents']/100,2)?></td><td><?=$stock[$pid]['available']?></td><td><?=$stock[$pid]['reserved']?></td><td><?=$row['active']?'כן':'לא'?></td></tr><?php endforeach?></table></div></main></html>