<?php
declare(strict_types=1);
function shop_atomic_json(string $path,array $data): void {
    $tmp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
    $raw=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    if(file_put_contents($tmp,$raw,LOCK_EX)!==strlen($raw)){@unlink($tmp);throw new RuntimeException('Save failed');}
    chmod($tmp,0600);
    if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Save failed');}
}
function shop_product_input(array $input,array $old,array $stock): array {
    $p=$old;
    foreach(['name','name_fa','description','description_fa'] as $key){
        $v=trim((string)($input[$key]??''));
        if($v===''||strlen($v)>12000||str_contains($v,'—'))throw new InvalidArgumentException('Complete both languages without the long dash character.');
        $p[$key]=$v;
    }
    if(!in_array($input['category']??'',['oils','jewelry','souvenirs'],true))throw new InvalidArgumentException('Choose a category.');
    $price=(string)($input['price']??'');
    if(!preg_match('/^\d{1,5}(?:\.\d{1,2})?$/',$price)||(float)$price<=0)throw new InvalidArgumentException('Enter a positive USD price.');
    $p['usd_cents']=(int)round((float)$price*100);
    foreach(['packed_grams'=>5000,'available'=>100000] as $key=>$max){
        if(filter_var($input[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>$key==='packed_grams'?1:0,'max_range'=>$max]])===false)throw new InvalidArgumentException('Check weight and available quantity.');
    }
    $p['packed_grams']=(int)$input['packed_grams'];
    // The form edits available units. Paid and reserved units remain in the ledger.
    $p['initial_stock']=(int)$input['available']+($stock['sold']??0)+($stock['reserved']??0);
    $p['category']=$input['category'];$p['active']=!empty($input['active']);
    return $p;
}
function shop_upload_image(array $file): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>8*1024*1024||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Upload a JPG, PNG or WebP image up to 8 MB.');
    $info=getimagesize($file['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!$info||!isset($types[$info['mime']])||$info[0]*$info[1]>40000000)throw new InvalidArgumentException('Unsupported image.');
    $dir=shop_storage().'/images';if(!is_dir($dir)&&!mkdir($dir,0700))throw new RuntimeException('Upload unavailable');
    $name=bin2hex(random_bytes(16)).'.'.$types[$info['mime']];
    if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Upload failed');
    chmod($dir.'/'.$name,0600);return 'api/media.php?file='.$name;
}
