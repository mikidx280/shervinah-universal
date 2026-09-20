// Shared accessible shop views. All catalogue text is rendered as text, not HTML.
window.ShopViews = function (ctx) {
  const {text,money,api,getCart,getCatalog,saveCart,addProduct,getLang}=ctx;
  const node=(tag,value,cls)=>{const e=document.createElement(tag);if(value)e.textContent=value;if(cls)e.className=cls;return e;};
  const link=(label,href)=>{const a=node('a',label);a.href=href;return a;};
  const title=p=>getLang()==='fa'?p.name_fa:p.name;
  let cartDialog,notice,estimateSeq=0;
  function closeButton(dialog){const b=node('button',text('Close','بستن'),'shop-close');b.type='button';b.onclick=()=>dialog.close();return b;}
  function image(src,alt){const img=node('img');img.src=src;img.alt=alt;img.loading='lazy';return img;}
  function card(id,p){
    const box=node('article',null,'store-card');const a=link('',`product.html?id=${encodeURIComponent(id)}`);a.append(image(p.images[0],title(p)));box.append(a,link(title(p),a.href),node('strong',money(p.usd_cents)));
    const b=node('button',p.stock_available?text('Add to cart','افزودن به سبد'):text('Out of stock','ناموجود'),'button button-primary');b.type='button';b.disabled=!p.stock_available;b.onclick=()=>addProduct(id);box.append(b,link(text('View details','مشاهده جزئیات'),a.href));return box;
  }
  const categories=[['oils','Oils & rituals','روغن‌ها و آیین‌ها','saffron-oil-20ml'],['souvenirs','Treasures from Israel','یادگارهای اسرائیل','jerusalem-gift-set'],['jewelry','Jewelry & symbols','زیورآلات و نمادها','red-string-pack-5']];
  function renderShop(){
    const grid=document.querySelector('.shop-page-grid');if(!grid)return;grid.replaceChildren();
    const requested=new URLSearchParams(location.search).get('category')||location.hash.slice(1);const selected=categories.find(c=>c[0]===requested);
    grid.classList.toggle('category-overview',!selected);
    if(selected){
      const section=node('section',null,'store-category');section.append(link(text('All collections','همه مجموعه‌ها'),'shop.html'),node('h2',text(selected[1],selected[2])));
      const cards=node('div',null,'store-grid');Object.entries(getCatalog().products).filter(([,p])=>p.category===selected[0]).forEach(([id,p])=>cards.append(card(id,p)));section.append(cards);grid.append(section);return;
    }
    for(const [category,en,fa,featured] of categories){
      const entries=Object.entries(getCatalog().products).filter(([,p])=>p.category===category);if(!entries.length)continue;
      const [id,p]=entries.find(([pid])=>pid===featured)||entries[0];const box=node('article',null,'collection-card');const href='shop.html?category='+category;
      const photo=link('',href);photo.append(image(p.images[0],title(p)));box.append(photo,node('h2',text(en,fa)),node('p',text('Featuring: ','محصول منتخب: ')+title(p)));
      const browse=link(text('Explore collection','مشاهده مجموعه'),href);browse.className='shop-primary';box.append(browse);grid.append(box);
    }
  }
  function renderProduct(){
    const root=document.getElementById('product-content');if(!root)return;
    const id=new URLSearchParams(location.search).get('id'),p=getCatalog().products[id];root.replaceChildren();
    if(!p){root.append(node('h1',text('Product unavailable','محصول در دسترس نیست')),link(text('Browse the shop','مشاهده فروشگاه'),'shop.html'));return;}
    document.title=title(p)+' | Shervinah Universal';
    const area=node('div',null,'product-detail-grid'),gallery=node('div',null,'detail-gallery'),info=node('section');
    for(const src of p.images){const a=link('',src);a.target='_blank';a.rel='noopener';a.setAttribute('aria-label',text('Enlarge product image','بزرگ‌نمایی تصویر محصول'));a.append(image(src,title(p)));gallery.append(a);if(src.includes('shervinah-preview'))gallery.append(node('small',text('Digital styling preview','پیش‌نمایش دیجیتالی')));}
    const desc=node('p',getLang()==='fa'?p.description_fa:p.description,'product-description');
    info.append(node('h1',title(p)),node('p',money(p.usd_cents),'detail-price'),desc,node('p',text(`${p.stock_available} available`,`${p.stock_available} عدد موجود`)),node('p',text(`Packed shipping weight: ${p.packed_grams} g`,`وزن بسته برای ارسال: ${p.packed_grams} گرم`)));
    const buy=node('button',p.stock_available?text('Add to cart','افزودن به سبد'):text('Out of stock','ناموجود'),'button button-primary');buy.disabled=!p.stock_available;buy.onclick=()=>addProduct(id);info.append(buy);
    const help=node('p');help.append(link(text('Shipping','ارسال'),'shipping.html'),document.createTextNode(' · '),link(text('Returns','مرجوعی'),'returns.html'),document.createTextNode(' · '),link(text('Ask about this product','پرسش درباره محصول'),'mailto:shervinahuniversal@gmail.com?subject='+encodeURIComponent(title(p))));info.append(help);area.append(gallery,info);root.append(area);
    const related=Object.entries(getCatalog().products).filter(([pid,item])=>pid!==id&&item.stock_available>0).sort((a,b)=>Number(b[1].category===p.category)-Number(a[1].category===p.category)).slice(0,3);
    if(related.length){root.append(node('h2',text('You may also like','پیشنهادهای دیگر')));const cards=node('div',null,'store-grid');related.forEach(([pid,item])=>cards.append(card(pid,item)));root.append(cards);}
  }
  function badge(){const count=Object.values(getCart()).reduce((a,b)=>a+b,0);document.querySelectorAll('[data-store-cart]').forEach(b=>b.textContent=text(`Cart (${count})`,`سبد خرید (${count})`));const existing=document.getElementById('cartCount');if(existing)existing.textContent=count;}
  function notify(added,id){
    notice.replaceChildren(closeButton(notice));notice.append(node('h2',added?text('Added to your cart','به سبد خرید اضافه شد'):text('No more units available','تعداد بیشتری موجود نیست')));
    const p=getCatalog().products[id];if(p){const row=node('div',null,'added-product');row.append(image(p.images[0],title(p)),node('strong',title(p)),node('span',money(p.usd_cents)));notice.append(row);}
    const related=Object.entries(getCatalog().products).filter(([pid,item])=>pid!==id&&!getCart()[pid]&&item.stock_available>0).slice(0,3);
    if(related.length){notice.append(node('h3',text('Complete your selection','انتخاب خود را کامل کنید')));const cards=node('div',null,'drawer-recommendations');related.forEach(([pid,item])=>cards.append(card(pid,item)));notice.append(cards);}
    const footer=node('div',null,'drawer-actions');const checkout=link(text('Checkout','تکمیل خرید'),'checkout.html');checkout.className='shop-primary';const keep=node('button',text('Continue shopping','ادامه خرید'),'shop-secondary');keep.onclick=()=>notice.close();const view=node('button',text('View or edit cart','مشاهده یا ویرایش سبد'),'shop-secondary');view.onclick=()=>{notice.close();openCart();};footer.append(checkout,view,keep);notice.append(footer);if(cartDialog.open)cartDialog.close();if(!notice.open)notice.showModal();badge();
  }
  function renderCart(){
    estimateSeq++;cartDialog.replaceChildren(closeButton(cartDialog));cartDialog.append(node('h2',text('Your cart','سبد خرید شما')));const cart=getCart();
    if(!Object.keys(cart).length){cartDialog.append(node('p',text('Your cart is empty. Find something meaningful in the shop.','سبد خرید خالی است. محصولات فروشگاه را ببینید.')),link(text('Browse products','مشاهده محصولات'),'shop.html'));return;}
    let subtotal=0;
    for(const [id,n] of Object.entries(cart)){
      const p=getCatalog().products[id];if(!p)continue;subtotal+=p.usd_cents*n;
      const row=node('div',null,'store-cart-row');row.append(image(p.images[0],title(p)),link(title(p),`product.html?id=${encodeURIComponent(id)}`));
      const input=node('input');input.type='number';input.min=1;input.max=Math.max(1,Math.min(25,p.stock_available));input.value=n;input.setAttribute('aria-label',text('Quantity of ','تعداد ')+title(p));input.onchange=()=>{const v=Number(input.value);if(!Number.isInteger(v)||v<1||v>Math.min(25,p.stock_available)){input.value=n;return;}cart[id]=v;saveCart();renderCart();badge();};
      const remove=node('button',text('Remove','حذف'));remove.onclick=()=>{delete cart[id];saveCart();renderCart();badge();};row.append(input,node('span',money(p.usd_cents*n)),remove);if(n>p.stock_available)row.append(node('p',text('Please reduce the quantity; availability has changed.','موجودی تغییر کرده است؛ تعداد را کاهش دهید.')));cartDialog.append(row);
    }
    cartDialog.append(node('p',text('Products: ','محصولات: ')+money(subtotal)));
    const label=node('label',text('Estimate shipping to','برآورد ارسال به')),select=node('select');select.append(new Option(text('Choose a country','کشور را انتخاب کنید'),''));
    const names=new Intl.DisplayNames([getLang()],{type:'region'});Object.keys(getCatalog().countries).map(c=>[c,names.of(c)]).sort((a,b)=>a[1].localeCompare(b[1])).forEach(([c,n])=>select.append(new Option(n,c)));select.value=sessionStorage.getItem('su-country')||'';label.append(select);
    const region=node('select');region.setAttribute('aria-label',text('Portugal region','منطقه پرتغال'));[['mainland','Mainland / سرزمین اصلی'],['azores','Azores / آزور'],['madeira','Madeira / مادیرا']].forEach(([v,l])=>region.append(new Option(l,v)));region.value=sessionStorage.getItem('su-region')||'mainland';
    const estimate=node('p');estimate.setAttribute('role','status');
    async function calculate(){const seq=++estimateSeq;region.hidden=select.value!=='PT';sessionStorage.setItem('su-country',select.value);sessionStorage.setItem('su-region',region.value);if(!select.value){estimate.textContent='';return;}estimate.textContent=text('Calculating…','در حال محاسبه…');try{const q=await api('quote',{items:getCart(),country:select.value,region:select.value==='PT'?region.value:''});if(seq!==estimateSeq)return;estimate.textContent=text('Shipping after discount: ','ارسال پس از تخفیف: ')+money(q.shipping_usd_cents)+text(' · Total: ',' · جمع: ')+money(q.total_usd_cents);}catch(e){if(seq===estimateSeq)estimate.textContent=ctx.message(e.message);}}
    select.onchange=calculate;region.onchange=calculate;const checkout=link(text('Continue to checkout','ادامه به تکمیل خرید'),'checkout.html');checkout.className='shop-primary';cartDialog.append(label,region,estimate,checkout);const keep=node('button',text('Continue shopping','ادامه خرید'),'shop-secondary');keep.onclick=()=>cartDialog.close();cartDialog.append(keep);calculate();
  }
  function openCart(){if(!getCatalog())return;renderCart();if(!cartDialog.open)cartDialog.showModal();}
  function mount(){
    cartDialog=node('dialog',null,'store-cart-dialog');cartDialog.setAttribute('aria-label',text('Shopping cart','سبد خرید'));document.body.append(cartDialog);
    notice=node('dialog',null,'added-drawer');notice.setAttribute('aria-label',text('Added to cart and recommendations','سبد خرید و پیشنهادها'));document.body.append(notice);window.addEventListener('hashchange',()=>{if(getCatalog())renderShop();});
    const header=document.querySelector('header');if(header&&!document.getElementById('cartButton')){const b=node('button');b.type='button';b.dataset.storeCart='';b.onclick=openCart;header.append(b);}window.addEventListener('store:cart',openCart);badge();
  }
  return {mount,notify,badge,render:()=>{renderShop();renderProduct();badge();},openCart};
};
