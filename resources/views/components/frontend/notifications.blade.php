<link rel="stylesheet" href="{{ asset('pages-css/notifications.css') }}">
<div class="notify-wrap" aria-live="polite" aria-atomic="true"></div>
<script>
const wrap = document.querySelector('.notify-wrap');

function createNotify({ title = '', desc = '' , DURATION_MS = 5000, autoplay = true } = {}) {
  // العنصر
  const el = document.createElement('div');
  el.className = 'notify';
  el.setAttribute('role','status');
  el.innerHTML = `
    <div class="icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg" style="width:28px;height:28px">
        <path d="M20 6L9 17l-5-5"></path>
      </svg>
    </div>
    <div class="content11">
      <div class="title11">${escapeHtml(title)}</div>
      <div class="desc11">${escapeHtml(desc)}</div>
    </div>
    <button class="close" aria-label="إغلاق الإشعار">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 6L6 18M6 6l12 12"/>
      </svg>
    </button>
    <div class="progress"><i></i></div>
  `;

  wrap.appendChild(el);

  const closeBtn = el.querySelector('.close');
  let closed = false;
  closeBtn.addEventListener('click', () => hide(el));

  let timer = null;
  if (autoplay) {
    const bar = el.querySelector('.progress > i');
    bar.style.animation = 'none';
    void bar.offsetWidth;
    bar.style.animation = `fill ${DURATION_MS}ms linear forwards`;

    timer = setTimeout(() => hide(el), DURATION_MS);
  }

  function hide(target){
    if (closed) return;
    closed = true;
    target.classList.add('closing');
    setTimeout(() => {
      if (wrap.contains(target)) wrap.removeChild(target);
    }, 480);
    if (timer) clearTimeout(timer);
  }

  return { el, hide: () => hide(el) };
}

function escapeHtml(str) {
  if (typeof str !== 'string') return '';
  return str.replace(/[&<>"'`=\/]/g, function(s) {
    return ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
      '/': '&#x2F;',
      '`': '&#x60;',
      '=': '&#x3D;'
    })[s];
  });
}

function shownav(){
    createNotify({ title: 'تنبية', desc: 'يرجي تسجيل الدخول للاستفادة من هذه الميزة' });
}
</script>