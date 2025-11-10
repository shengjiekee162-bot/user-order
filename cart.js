// cart.js: 管理购物车页面交互

function getCart(){
  try{ return JSON.parse(localStorage.getItem('cart')) || []; } catch(e){ return []; }
}

function saveCart(cart){
  localStorage.setItem('cart', JSON.stringify(cart));
  updateGlobalCount();
}

function updateGlobalCount(){
  const cnt = getCart().reduce((a,b)=>a+(b.quantity||0),0);
  // 如果有首页的 count 元素，更新它（多页面也能使用）
  const c = document.getElementById('count');
  if(c) c.innerText = cnt;
}

function renderCart(){
  const cart = getCart();
  const container = document.getElementById('cartContainer');
  if(!container) return;
  if(cart.length===0){
    container.innerHTML = '<p>购物车为空。<a href="index.html">去逛逛</a></p>';
    return;
  }
  let total = 0;
  let html = '<table style="width:100%; border-collapse:collapse;">';
  html += '<thead><tr><th style="text-align:left; padding:8px">商品</th><th style="padding:8px">单价</th><th style="padding:8px">数量</th><th style="padding:8px">小计</th><th style="padding:8px">操作</th></tr></thead><tbody>';
  cart.forEach((it, idx)=>{
    const subtotal = parseFloat(it.price || 0) * (it.quantity||1);
    total += subtotal;
    html += `<tr data-idx="${idx}"><td style="padding:8px">${escapeHtml(it.name)}</td><td style="padding:8px">RM ${parseFloat(it.price).toFixed(2)}</td><td style="padding:8px"><input type="number" min="1" value="${it.quantity||1}" style="width:72px;padding:6px;" onchange="updateQty(${idx}, this.value)"></td><td style="padding:8px">RM ${subtotal.toFixed(2)}</td><td style="padding:8px"><button onclick="removeItem(${idx})" class="btn-ripple">删除</button></td></tr>`;
  });
  html += `</tbody></table><div style="text-align:right; margin-top:12px; font-weight:600">总计: RM ${total.toFixed(2)}</div>`;
  container.innerHTML = html;
}

function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>'"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c])); }

function updateQty(idx, val){
  const cart = getCart();
  const q = parseInt(val)||1;
  cart[idx].quantity = q;
  saveCart(cart);
  renderCart();
  showToast('已更新数量', 'success');
}

function removeItem(idx){
  const cart = getCart();
  const name = cart[idx] ? cart[idx].name : '商品';
  cart.splice(idx,1);
  saveCart(cart);
  renderCart();
  showToast(`${name} 已从购物车移除`, 'info');
}

function initCartPage(){
  renderCart();
  updateGlobalCount();
  const checkoutBtn = document.getElementById('checkoutBtn');
  if(checkoutBtn){
    checkoutBtn.addEventListener('click', ()=>{
      const cart = getCart();
      if(cart.length===0){ showToast('购物车为空', 'error'); return; }
      // 跳转到结账页面
      location.href = 'checkout.html';
    });
  }
  // 显示用户信息（如果存在）
  try{
    const user = JSON.parse(localStorage.getItem('user')) || null;
    const udiv = document.getElementById('userDisplay');
    if(udiv) udiv.innerText = user ? `欢迎, ${user.username}` : '未登录';
  }catch(e){}
}

// 尝试使用全局 showToast，如果没有则定义一个简单版本
if(typeof showToast !== 'function'){
  function showToast(msg){ alert(msg); }
}

window.addEventListener('DOMContentLoaded', initCartPage);
