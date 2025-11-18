/* Lightweight i18n for this demo app
   Usage: add `data-i18n="key"` to elements for innerText replacement
   or add `data-i18n-attr="placeholder"` to replace placeholder attribute.
*/
(function(){
  const translations = {
    zh: {
      'site.title':'示例商城 🛒',
      'header.login':'登录 / 注册',
      'header.forgot':'忘记密码',
      'search.placeholder':'搜索商品...',
      'search.button':'搜索',
      'camera.button':'📷 拍照搜索',
      'categories.all':'全部',
      'cart.label':'🛒 购物车',
      'orders.link':'📦 我的订单',
      'checkout.title':'结账 & 支付',
      'checkout.place_order':'确认下单',

      'login.title':'登录账号',
      'login.username':'用户名',
      'login.password':'密码',
      'login.submit':'登录',

      'register.title':'注册账号',
      'register.username':'用户名',
      'register.email':'邮箱地址',
      'register.password':'密码',
      'register.submit':'注册',

      'forgot.title':'忘记密码',
      'forgot.send':'发送验证码',
      'forgot.gotocode':'我已收到验证码',

      'change.title':'使用旧密码修改密码',
      'change.submit':'提交修改',

      'seller.title':'卖家中心',
      'seller.add':'添加商品',
      'seller.edit':'编辑',
      'seller.delete':'删除'
    },
    en: {
      'site.title':'Sample Shop 🛒',
      'header.login':'Login / Register',
      'header.forgot':'Forgot password',
      'search.placeholder':'Search products...',
      'search.button':'Search',
      'camera.button':'📷 Photo search',
      'categories.all':'All',
      'cart.label':'🛒 Cart',
      'orders.link':'📦 My Orders',
      'checkout.title':'Checkout & Payment',
      'checkout.place_order':'Place Order',

      'login.title':'Login',
      'login.username':'Username',
      'login.password':'Password',
      'login.submit':'Login',

      'register.title':'Register',
      'register.username':'Username',
      'register.email':'Email address',
      'register.password':'Password',
      'register.submit':'Register',

      'forgot.title':'Forgot password',
      'forgot.send':'Send code',
      'forgot.gotocode':'I got the code',

      'change.title':'Change password (old password)',
      'change.submit':'Submit change',

      'seller.title':'Seller Center',
      'seller.add':'Add Product',
      'seller.edit':'Edit',
      'seller.delete':'Delete'
    }
  };

  function getLang(){
    return localStorage.getItem('lang') || (navigator.language && navigator.language.startsWith('zh') ? 'zh' : 'en');
  }

  function setLang(l){
    localStorage.setItem('lang', l);
    apply();
  }

  function apply(){
    const lang = getLang();
    document.querySelectorAll('[data-i18n]').forEach(el=>{
      const key = el.getAttribute('data-i18n');
      const attr = el.getAttribute('data-i18n-attr');
      const txt = (translations[lang] && translations[lang][key]) || (translations['zh'][key]) || key;
      if(attr === 'placeholder') el.setAttribute('placeholder', txt);
      else el.innerHTML = txt;
    });
    // update lang selector if exists
    const sel = document.getElementById('langSwitcher');
    if(sel) sel.value = getLang();
  }

  // inject language switcher to top-right of the body (small unobtrusive)
  function ensureSwitcher(){
    if(document.getElementById('langSwitcher')) return;
    const d = document.createElement('div');
    d.style.position = 'fixed'; d.style.right = '12px'; d.style.top = '12px'; d.style.zIndex = 9999;
    d.innerHTML = `<select id="langSwitcher" style="padding:6px;border-radius:6px;border:1px solid #ddd;background:#fff">
      <option value="zh">中文</option>
      <option value="en">English</option>
    </select>`;
    document.body.appendChild(d);
    const sel = document.getElementById('langSwitcher');
    sel.value = getLang();
    sel.addEventListener('change', ()=> setLang(sel.value));
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    ensureSwitcher();
    apply();
  });

  // expose for debugging
  window.__i18n = { setLang, getLang, apply };
})();
