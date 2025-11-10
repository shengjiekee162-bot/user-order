// checkout.js: 独立结账页面逻辑

async function safeFetchLocal(url, options){
  try{
    const res = await fetch(url, options);
    const text = await res.text();
    try{ return JSON.parse(text); } catch(e){ return null; }
  }catch(err){ showToast && showToast('网络请求失败','error'); return null; }
}

function getCart(){ try{ return JSON.parse(localStorage.getItem('cart')) || []; }catch(e){ return []; } }
function clearCart(){ localStorage.removeItem('cart'); updateGlobalCount(); }
function updateGlobalCount(){ const cnt = getCart().reduce((a,b)=>a+(b.quantity||0),0); const c = document.getElementById('count'); if(c) c.innerText = cnt; }

function renderOrderSummary(){
  const cart = getCart();
  const container = document.getElementById('orderSummaryBody');
  if(!container) return;
  if(cart.length===0){ container.innerHTML = '<p>购物车为空</p>'; return; }
  let total = 0;
  let html = '<table style="width:100%; border-collapse:collapse;">';
  html += '<thead><tr><th style="text-align:left; padding:6px">商品</th><th style="padding:6px">单价</th><th style="padding:6px">数量</th><th style="padding:6px">小计</th></tr></thead><tbody>';
  cart.forEach(it=>{
    const subtotal = parseFloat(it.price || 0) * (it.quantity||1);
    total += subtotal;
    html += `<tr><td style="padding:6px">${escapeHtml(it.name)}</td><td style="padding:6px">RM ${parseFloat(it.price).toFixed(2)}</td><td style="padding:6px">${it.quantity||1}</td><td style="padding:6px">RM ${subtotal.toFixed(2)}</td></tr>`;
  });
  html += `</tbody></table><div style="text-align:right; margin-top:8px; font-weight:700">总计: RM ${total.toFixed(2)}</div>`;
  container.innerHTML = html;
}

function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>'"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c])); }

async function loadAddresses(){
  const user = JSON.parse(localStorage.getItem('user')) || null;
  if(!user) return;
  const res = await safeFetchLocal(`api.php?action=list_addresses&user_id=${user.id}`);
  const sel = document.getElementById('addressSelect');
  if(!sel) return;
  sel.innerHTML = '<option value="">请选择收货地址</option>';
  if(Array.isArray(res)){
    res.forEach(a=> sel.innerHTML += `<option value="${a.id}" ${a.is_default? 'selected': ''}>${escapeHtml(a.recipient_name)} - ${escapeHtml(a.recipient_address)}${a.is_default? '（默认）':''}</option>`);
  }
}

function showAddAddressForm(){ document.getElementById('addAddressForm').style.display = 'block'; }
function hideAddAddressForm(){ document.getElementById('addAddressForm').style.display = 'none'; }

async function addAddress(){
  const user = JSON.parse(localStorage.getItem('user')) || null;
  if(!user) { showToast('请先登录', 'error'); return; }
  const name = document.getElementById('newRecipientName').value.trim();
  const address = document.getElementById('newRecipientAddress').value.trim();
  if(!name || !address){ showToast('请填写完整地址','error'); return; }
  const res = await safeFetchLocal('api.php',{ method:'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action:'add_address', user_id:user.id, recipient_name:name, recipient_address:address }) });
  if(res && res.status==='ok'){ showToast('地址已保存','success'); hideAddAddressForm(); loadAddresses(); } else showToast('保存失败','error');
}

function renderPaymentMethods(){
  const wrap = document.getElementById('paymentMethods');
  if(!wrap) return;
  wrap.innerHTML = `
    <div class="payment-option" data-method="cash">
      <label><span class="icon">💵</span><span class="text">现金支付</span><span class="desc">货到付款</span></label>
    </div>
    <div class="payment-option" data-method="credit_card">
      <label><span class="icon">💳</span><span class="text">信用卡</span><span class="desc">支持 Visa / Mastercard</span></label>
    </div>
    <div class="payment-option" data-method="online_banking">
      <label><span class="icon">🏦</span><span class="text">网上银行</span><span class="desc">网上转账</span></label>
    </div>
    <div id="paymentDetails" style="margin-top:8px; display:none"></div>
  `;

  wrap.querySelectorAll('.payment-option').forEach(el=>{
    el.addEventListener('click', ()=>{
      wrap.querySelectorAll('.payment-option').forEach(x=>x.classList.remove('selected'));
      el.classList.add('selected');
      showPaymentDetail(el.dataset.method);
    });
  });
}

function showPaymentDetail(method){
  const box = document.getElementById('paymentDetails');
  if(!box) return;
  if(method==='credit_card'){
    box.style.display = 'block';
    box.innerHTML = `<div class="payment-detail-form"><input placeholder="卡号" style="width:100%;padding:8px;margin-bottom:6px;" id="card_no"><div style="display:flex;gap:8px"><input placeholder="MM/YY" id="card_exp" style="flex:1;padding:8px"><input placeholder="CVV" id="card_cvv" style="width:90px;padding:8px"></div></div>`;
  } else if(method==='online_banking'){
    box.style.display = 'block';
    box.innerHTML = `<div class="payment-detail-form"><select id="bankSelect" style="width:100%;padding:8px"><option value="">选择银行</option><option value="maybank">Maybank</option><option value="cimb">CIMB</option><option value="public">Public</option></select></div>`;
  } else {
    box.style.display = 'none';
    box.innerHTML = '';
  }
}

async function submitOrder(){
  const user = JSON.parse(localStorage.getItem('user')) || null;
  if(!user) { showToast('请先登录', 'error'); return; }
  const cart = getCart();
  if(cart.length===0){ showToast('购物车为空','error'); return; }

  const addressSel = document.getElementById('addressSelect');
  const addressId = addressSel ? addressSel.value : '';
  if(!addressId){ showToast('请选择收货地址','error'); return; }

  const paymentEl = document.querySelector('#paymentMethods .payment-option.selected');
  const method = paymentEl ? paymentEl.dataset.method : 'cash';

  // 简单校验
  if(method==='credit_card'){
    const no = document.getElementById('card_no').value || '';
    const exp = document.getElementById('card_exp').value || '';
    const cvv = document.getElementById('card_cvv').value || '';
    if(!no || !exp || !cvv){ showToast('请填写信用卡信息','error'); return; }
  }
  if(method==='online_banking'){
    const bank = document.getElementById('bankSelect').value || '';
    if(!bank){ showToast('请选择银行','error'); return; }
  }

  // 准备数据并提交
  const items = cart.map(i=>({ id:i.id, price:i.price, quantity:i.quantity }));
  showLoading && showLoading();
  const res = await safeFetchLocal('api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'create_order', user_id:user.id, items, recipient_address_id: addressId, payment_method: method }) });
  showLoading && showLoading(); // hide
  if(res && res.status==='ok'){
    showToast('下单成功','success');
    clearCart();
    setTimeout(()=> location.href='orders.html', 800);
  } else {
    showToast('下单失败：' + (res && res.message ? res.message : ''),'error');
  }
}

function initCheckoutPage(){
  renderOrderSummary();
  renderPaymentMethods();
  loadAddresses();
  document.getElementById('submitOrderBtn').addEventListener('click', submitOrder);
  // 显示用户
  try{ const u = JSON.parse(localStorage.getItem('user')) || null; const ud=document.getElementById('userDisplay'); if(ud) ud.innerText = u ? `欢迎, ${u.username}` : '未登录'; }catch(e){}
}

if(typeof showToast !== 'function'){
  function showToast(m){ alert(m); }
}

window.addEventListener('DOMContentLoaded', initCheckoutPage);
