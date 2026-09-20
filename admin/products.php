<?php
require __DIR__.'/shop-auth.php';
require_once dirname(__DIR__).'/api/catalog-management.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_csrf();$lock=shop_inventory_lock(true);
    try{
        $all=commerce_catalog();$id=(string)($_POST['id']??'');
        if($id==='new')$id='product-'.bin2hex(random_bytes(8));
        elseif(!isset($all[$id]))throw new InvalidArgumentException('Product not found.');
        $stock=shop_stock();$old=$all[$id]??['images'=>[]];
        if(isset($all[$id])&&!hash_equals(hash('sha256',json_encode($old)),(string)($_POST['version']??'')))throw new InvalidArgumentException('This product changed in another tab. Reload before saving.');
        if(isset($stock[$id])&&(string)$stock[$id]['available']!==(string)($_POST['previous_available']??''))throw new InvalidArgumentException('Inventory changed while you were editing. Reload and review the current available quantity.');
        $p=shop_product_input($_POST,$old,$stock[$id]??[]);
        if(($_FILES['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$img=shop_upload_image($_FILES['image']);$p['images']=!empty($_POST['replace_images'])?[$img]:array_merge($p['images'],[$img]);}
        if(!$p['images'])throw new InvalidArgumentException('Add at least one product image.');
        $all[$id]=$p;shop_atomic_json(shop_storage().'/catalog.json',$all);
        header('Location: products.php?edit='.rawurlencode($id).'&saved=1');exit;
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Could not save. Please try again.';}finally{fclose($lock);}
}
$lock=shop_inventory_lock();$all=commerce_catalog();$stock=shop_stock();fclose($lock);
$id=(string)($_GET['edit']??'');$p=$all[$id]??null;
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Manage products</title><link rel="stylesheet" href="admin.css"><header><a href="orders.php">Orders</a><a href="products.php">Products</a><a href="index.php">Inquiries</a><a href="email-settings.php">Email settings</a></header><main><h1>Products & inventory</h1><p>Prices are USD. Enter packed weight in grams and the quantity currently available for sale. Orders, stock and uploaded images survive website deployments.</p><a class="button" href="?edit=new">Add product</a>
<?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif?><?php if(isset($_GET['saved'])):?><p role="status">Saved successfully.</p><?php endif?>
<?php if($p||$id==='new'):?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=esc($id)?>"><input type="hidden" name="version" value="<?=esc(hash('sha256',json_encode($p)))?>"><input type="hidden" name="previous_available" value="<?=esc($stock[$id]['available']??0)?>">
<?php foreach(['name'=>'English name','name_fa'=>'Persian name','description'=>'English description','description_fa'=>'Persian description'] as $key=>$label):?><label><?=esc($label)?><textarea name="<?=esc($key)?>" dir="<?=$key==='name_fa'||$key==='description_fa'?'rtl':'ltr'?>" required maxlength="6000"><?=esc($p[$key]??'')?></textarea></label><?php endforeach?>
<label>Category<select name="category"><?php foreach(['oils','jewelry','souvenirs'] as $cat):?><option <?=$cat===($p['category']??'')?'selected':''?>><?=esc($cat)?></option><?php endforeach?></select></label>
<label>Price (USD)<input name="price" type="number" min="0.01" max="99999" step="0.01" value="<?=esc(isset($p['usd_cents'])?number_format($p['usd_cents']/100,2,'.',''):'')?>" required></label>
<label>Packed weight (grams)<input name="packed_grams" type="number" min="1" max="5000" value="<?=esc($p['packed_grams']??'')?>" required></label>
<label>Available to sell now<input name="available" type="number" min="0" max="100000" value="<?=esc($stock[$id]['available']??0)?>" required></label>
<p>Reserved: <?=esc($stock[$id]['reserved']??0)?>. Sold: <?=esc($stock[$id]['sold']??0)?>.</p>
<label><input name="active" type="checkbox" <?=($p['active']??true)?'checked':''?>> Visible in shop</label>
<div class="images"><?php foreach($p['images']??[] as $img):?><img src="../<?=esc($img)?>" alt="Current product image"><?php endforeach?></div>
<label>Add image (JPG, PNG, WebP; up to 8 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><label><input type="checkbox" name="replace_images"> Replace gallery with this image</label><button>Save product</button></form><?php endif?>
<table><tr><th>Product</th><th>USD</th><th>Available</th><th>Reserved</th><th>Visible</th></tr><?php foreach($all as $pid=>$row):?><tr><td><a href="?edit=<?=esc($pid)?>"><?=esc($row['name'])?></a></td><td><?=number_format($row['usd_cents']/100,2)?></td><td><?=$stock[$pid]['available']?></td><td><?=$stock[$pid]['reserved']?></td><td><?=$row['active']?'Yes':'No'?></td></tr><?php endforeach?></table></main></html>
