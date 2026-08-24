let pageLang=localStorage.getItem('su-lang')||'en';
function applyPageLanguage(next){pageLang=next;localStorage.setItem('su-lang',next);document.documentElement.lang=next;document.documentElement.dir=next==='fa'?'rtl':'ltr';document.querySelectorAll('[data-en]').forEach(el=>{if(el.dataset[next])el.textContent=el.dataset[next]});document.querySelectorAll('.lang-btn').forEach(btn=>btn.classList.toggle('active',btn.dataset.lang===next))}
document.querySelectorAll('.lang-btn').forEach(btn=>btn.addEventListener('click',()=>applyPageLanguage(btn.dataset.lang)));
const a11yButton=document.getElementById('accessibilityToggle');if(a11yButton)a11yButton.addEventListener('click',()=>document.body.classList.toggle('font-large'));
applyPageLanguage(pageLang);