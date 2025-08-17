// highlight the active nav tab
(function(){
  const current = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav a').forEach(a=>{
    const href = a.getAttribute('href');
    if ((current === '' && href === 'index.html') || current === href) {
      a.classList.add('active');
    }
  });
})();