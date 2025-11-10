// 添加页面加载动画
document.addEventListener('DOMContentLoaded', function() {
  // 初始化加载动画
  showLoading();
  
  // 预加载完成后隐藏加载动画
  window.addEventListener('load', function() {
    hideLoading();
    document.body.classList.add('fade-in');
  });
  
  // 为所有按钮添加涟漪效果
  document.querySelectorAll('button').forEach(button => {
    if (!button.classList.contains('btn-ripple')) {
      button.classList.add('btn-ripple');
    }
  });
  
  // 为搜索输入框添加防抖
  const searchInput = document.getElementById('searchInput');
  let searchTimeout;
  
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      searchProducts();
    }, 500);
  });
  
  // 添加购物车动画
  window.addToCart = function(p) {
    const cartIcon = document.querySelector('.cart');
    const productElement = event.target.closest('.card');
    
    if (productElement && cartIcon) {
      // 创建一个飞行的商品元素
      const flyingItem = document.createElement('div');
      flyingItem.style.cssText = `
        position: fixed;
        z-index: 9999;
        width: 30px;
        height: 30px;
        background: var(--color-primary);
        border-radius: 50%;
        pointer-events: none;
      `;
      
      // 获取起始位置
      const start = productElement.getBoundingClientRect();
      const end = cartIcon.getBoundingClientRect();
      
      // 设置初始位置
      flyingItem.style.left = start.left + 'px';
      flyingItem.style.top = start.top + 'px';
      
      document.body.appendChild(flyingItem);
      
      // 添加动画
      flyingItem.animate([
        {
          transform: 'scale(1)',
          left: start.left + 'px',
          top: start.top + 'px'
        },
        {
          transform: 'scale(0.5)',
          left: end.left + end.width/2 + 'px',
          top: end.top + end.height/2 + 'px'
        }
      ], {
        duration: 800,
        easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
      }).onfinish = () => {
        flyingItem.remove();
        // 调用原始的添加购物车函数
        addItemToCart(p);
      };
    } else {
      addItemToCart(p);
    }
  };
  
  // 原始的添加购物车函数
  window.addItemToCart = function(p) {
    const found = cart.find(i => i.id === p.id);
    if (found) found.quantity++;
    else cart.push({...p, quantity:1});
    
    document.getElementById('count').innerText = cart.reduce((a,b)=>a+b.quantity,0);
    showToast(`已将 ${p.name} 加入购物车！`, 'success');
    
    // 添加购物车图标动画
    const cartIcon = document.querySelector('.cart');
    cartIcon.classList.add('scale-in');
    setTimeout(() => cartIcon.classList.remove('scale-in'), 300);
  };
  
  // 为支付选项添加动画
  document.querySelectorAll('.payment-option').forEach(option => {
    option.addEventListener('click', function() {
      this.classList.add('scale-in');
      setTimeout(() => this.classList.remove('scale-in'), 300);
    });
  });
  
  // 添加骨架屏
  window.showSkeleton = function() {
    const container = document.getElementById('productContainer');
    const skeletonHTML = Array(8).fill(`
      <div class="card skeleton">
        <div class="skeleton-text" style="width: 70%"></div>
        <div class="skeleton-text" style="width: 40%"></div>
        <div class="skeleton-text" style="width: 100%"></div>
      </div>
    `).join('');
    
    container.innerHTML = skeletonHTML;
  };
  
  // 修改商品加载函数
  const originalLoadProducts = window.loadProducts;
  window.loadProducts = async function() {
    showSkeleton();
    await originalLoadProducts();
  };
  
  // 添加滚动到顶部按钮
  const scrollTopButton = document.createElement('button');
  scrollTopButton.className = 'fab';
  scrollTopButton.innerHTML = '↑';
  scrollTopButton.style.display = 'none';
  document.body.appendChild(scrollTopButton);
  
  scrollTopButton.addEventListener('click', () => {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
  
  window.addEventListener('scroll', () => {
    if (window.scrollY > 200) {
      scrollTopButton.style.display = 'flex';
    } else {
      scrollTopButton.style.display = 'none';
    }
  });
  
  // 添加图片懒加载
  document.querySelectorAll('img[data-src]').forEach(img => {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          img.src = img.dataset.src;
          observer.unobserve(img);
        }
      });
    });
    
    observer.observe(img);
  });
});

// 添加页面切换动画
window.addEventListener('beforeunload', () => {
  document.body.classList.add('page-exit');
});