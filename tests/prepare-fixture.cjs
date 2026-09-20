// Isolated local copy. Payment calls are mocked ONLY in this copy, never in production code.
const fs=require('fs'),path=require('path'),os=require('os');
const preview=process.argv.includes('--preview');
const source=path.resolve(__dirname,'..');const dest=fs.mkdtempSync(path.join(os.tmpdir(),preview?'shervinah-store-preview-':'shervinah-store-test-'));
fs.cpSync(source,path.join(dest,'public_html'),{recursive:true,filter:p=>!p.includes(path.sep+'.git')&&!p.includes(path.sep+'tests')});
for(const file of fs.readdirSync(path.join(dest,'public_html')).filter(x=>x.endsWith('.html'))){
 const p=path.join(dest,'public_html',file);let html=fs.readFileSync(p,'utf8');
 html=html.replace(/<script async src="https:\/\/www.googletagmanager.com[^>]*><\/script>/g,'').replace(/<script>window.dataLayer[\s\S]*?<\/script>/g,'');
 html=html.replace('<body>','<body><div style="padding:8px;text-align:center;background:#111;color:#ffdf79;font:14px system-ui" role="note">LOCAL '+(preview?'PREVIEW':'TEST FIXTURE')+' · No real payments or emails</div>');fs.writeFileSync(p,html);
}
const api=path.join(dest,'public_html/api');let lib=fs.readFileSync(path.join(api,'commerce-lib.php'),'utf8');
lib=lib.replace(/function shop_http\([\s\S]*?(?=function shop_rate)/,`function shop_http(string $url,?array $data=null): array {
    if(str_contains($url,'boi.org.il'))return ['key'=>'USD','unit'=>1,'currentExchangeRate'=>3,'lastUpdate'=>gmdate('c')];
    if(str_ends_with($url,'/Create')){file_put_contents(shop_storage().'/create-count.txt','1',FILE_APPEND);file_put_contents(shop_storage().'/mock-provider.json',json_encode($data));return ['ResponseCode'=>0,'LowProfileId'=>'fixture-profile','Url'=>'https://secure.cardcom.solutions/fixture-not-a-real-payment'];}
    if(str_ends_with($url,'/GetLpResult')){
        if(!is_file(shop_storage().'/simulate-paid'))return ['ResponseCode'=>1];
        $o=json_decode(file_get_contents(shop_storage().'/mock-provider.json'),true);
        return ['ResponseCode'=>0,'Operation'=>'ChargeOnly','TerminalNumber'=>123,'ReturnValue'=>$o['ReturnValue'],'LowProfileId'=>'fixture-profile','TranzactionId'=>'fixture-transaction','TranzactionInfo'=>['ResponseCode'=>0,'TerminalNumber'=>123,'CoinId'=>2,'Amount'=>$o['Amount'],'IsRefund'=>false]];
    }
    throw new RuntimeException('Unexpected external request in test');
}
`);fs.writeFileSync(path.join(api,'commerce-lib.php'),lib);
// Only the disposable fixture accepts the loopback origin and non-secure local test cookie.
for(const rel of ['api/commerce.php','admin/index.php','admin/orders.php','admin/shop-auth.php']){
 const f=path.join(dest,'public_html',rel);let s=fs.readFileSync(f,'utf8').replaceAll("'secure'=>true","'secure'=>false");
 if(rel==='api/commerce.php')s=s.replace("['https://shervinahuniversal.com','https://www.shervinahuniversal.com']","['http://localhost:8765','https://shervinahuniversal.com','https://www.shervinahuniversal.com']");
 if(rel==='admin/index.php')s=s.replace("header('Location: index.php');exit;}\n $error","header('Location: products.php');exit;}\n $error");
 fs.writeFileSync(f,s);
}
fs.writeFileSync(path.join(dest,'private-payment-config.php'),"<?php return ['enabled'=>true,'api_name'=>'fixture','terminal_number'=>123];");
fs.writeFileSync(path.join(dest,'private-config.php'),"<?php return ['admin_password_hash'=>password_hash('local-fixture-password', PASSWORD_DEFAULT)];");
fs.writeFileSync(path.join(dest,'fixture-root.txt'),dest);
console.log(dest);
