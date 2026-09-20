// Run against the disposable PHP fixture at localhost:8765. Never target the live shop.
const assert=require('node:assert/strict'),fs=require('fs'),path=require('path');
const root=process.argv[2];if(!root||!path.basename(root).startsWith('shervinah-store-test-'))throw Error('A disposable fixture root is required');
const base='http://localhost:8765';let cookie='',csrf='',checks=0;
const check=(v,m)=>{assert.ok(v,m);checks++;};
async function api(action,body){const r=await fetch(base+'/api/commerce.php?action='+action,{method:body?'POST':'GET',headers:{Cookie:cookie,Origin:base,'Content-Type':'application/json'},...(body?{body:JSON.stringify({...body,csrf})}:{})});const c=r.headers.get('set-cookie');if(c)cookie=c.split(';')[0];const raw=await r.text();try{return {status:r.status,data:JSON.parse(raw)};}catch{throw Error(action+': '+raw.slice(0,1500));}}
(async()=>{
 let r=await api('catalog');check(r.status===200,'catalog');csrf=r.data.csrf;check(Object.keys(r.data.products).length===5,'five products');check(!r.data.products['saffron-oil-20ml'].initial_stock,'opening stock is private');
 check(fs.existsSync(path.join(root,'shervinah-orders/catalog.json')),'catalog persists outside webroot');
 r=await api('quote',{items:{'red-string-pack-5':1},country:'US',region:''});check(r.data.total_usd_cents===2700,'fixed USD product + converted discounted shipping');const q=r.data;
 const customer={name:'Local Test',email:'customer@example.invalid',phone:'5550000000',address:'Test Street 1',city:'Test City',state:'CA',postal_code:'90210'};
 const payload={quote_id:q.id,consent:true,customer,language:'fa'};
 const savedCsrf=csrf;csrf='bad';r=await api('checkout',payload);check(r.status===403,'reject forged CSRF');csrf=savedCsrf;
 r=await api('checkout',{...payload,customer:{...customer,state:''}});check(r.status===422,'state required for US');
 r=await api('checkout',payload);check(r.status===200,'create mocked payment');
 r=await api('checkout',payload);check(r.status===200,'same attempt idempotent');check(fs.readFileSync(path.join(root,'shervinah-orders/create-count.txt'),'utf8')==='1','only one provider creation');
 r=await api('catalog');check(r.data.products['red-string-pack-5'].stock_available===99,'stock reserved');check(r.data.pending_order.id===q.id,'pending payment recoverable');
 const fresh=await api('quote',{items:{'red-string-pack-5':1},country:'US',region:''});r=await api('checkout',{...payload,quote_id:fresh.data.id});check(r.status===409&&r.data.error==='payment_pending','fresh quote cannot duplicate active payment');
 r=await api('status',{order:q.id});check(r.data.status==='pending','unconfirmed does not become paid');
 check(!fs.existsSync(path.join(root,'shervinah-orders/outbox'))||fs.readdirSync(path.join(root,'shervinah-orders/outbox')).filter(x=>x.endsWith('.json')).length===0,'no premature confirmation emails');
 fs.writeFileSync(path.join(root,'shervinah-orders/simulate-paid'),'1');
 r=await api('status',{order:q.id});check(r.data.status==='paid','verified result marks paid');
 const privateUrl=new URL(r.data.order_url);let statusPage=await fetch(base+privateUrl.pathname+privateUrl.search);check(statusPage.status===200,'private order link works without session');
 privateUrl.searchParams.set('token','0'.repeat(64));statusPage=await fetch(base+privateUrl.pathname+privateUrl.search);check(statusPage.status===404,'wrong order token denied');
 r=await api('status',{order:q.id});check(r.data.status==='paid','repeat verification safe');r=await api('catalog');check(r.data.products['red-string-pack-5'].stock_available===99,'no double stock deduction');
 const jobs=fs.readdirSync(path.join(root,'shervinah-orders/outbox')).filter(x=>x.endsWith('.json'));check(jobs.length===3,'one customer plus two merchant emails exactly once');
 check(jobs.every(f=>JSON.parse(fs.readFileSync(path.join(root,'shervinah-orders/outbox',f),'utf8')).status==='configuration_needed'),'SMTP not configured is visible, never falsely sent');
 const customerJob=JSON.parse(fs.readFileSync(path.join(root,'shervinah-orders/outbox',jobs.find(f=>f.endsWith('-confirmation.json'))),'utf8'));check(customerJob.subject.includes('تأیید'),'customer confirmation follows checkout language');
 // Catalogue edits preserve historical prices and sold quantity.
 const catalogPath=path.join(root,'shervinah-orders/catalog.json');const cat=JSON.parse(fs.readFileSync(catalogPath));cat['red-string-pack-5'].usd_cents=1200;fs.writeFileSync(catalogPath,JSON.stringify(cat));
 r=await api('status',{order:q.id});check(r.data.total_usd_cents===2700,'historical amount unchanged after product edit');
 r=await api('quote',{items:{'red-string-pack-5':1},country:'US',region:''});check(r.data.product_usd_cents===1200,'new orders use new product price');
 cat['red-string-pack-5'].active=false;fs.writeFileSync(catalogPath,JSON.stringify(cat));r=await api('catalog');check(!r.data.products['red-string-pack-5'],'hidden product absent from storefront');r=await api('quote',{items:{'red-string-pack-5':1},country:'US',region:''});check(r.status===422,'hidden product cannot be bought');
 console.log(`${checks} isolated checkout, stock, email, token and catalogue integration checks passed.`);
})().catch(e=>{console.error(e);process.exit(1);});
