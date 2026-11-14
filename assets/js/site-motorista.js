(function () {
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var header = document.querySelector('.sm-header');
  var menuBtn = document.getElementById('sm-menu');
  var mobile = document.getElementById('sm-nav-mobile');

  function onScroll() {
    if (!header) return;
    header.classList.toggle('is-scrolled', window.scrollY > 10);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  if (menuBtn && mobile) {
    menuBtn.addEventListener('click', function () {
      var open = mobile.classList.toggle('open');
      mobile.hidden = !open;
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    mobile.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        mobile.classList.remove('open');
        mobile.hidden = true;
        menuBtn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  var nodes = document.querySelectorAll('[data-reveal]');
  if (reduce || !('IntersectionObserver' in window)) {
    nodes.forEach(function (node) { node.classList.add('is-in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { threshold: 0.16, rootMargin: '0px 0px -6% 0px' });
    nodes.forEach(function (node) { io.observe(node); });
  }

  var hero = document.querySelector('.sm-hero');
  if (hero && !reduce && window.matchMedia('(pointer:fine)').matches) {
    hero.addEventListener('pointermove', function (event) {
      var box = hero.getBoundingClientRect();
      hero.style.setProperty('--mx', ((event.clientX - box.left) / box.width * 100).toFixed(1) + '%');
      hero.style.setProperty('--my', ((event.clientY - box.top) / box.height * 100).toFixed(1) + '%');
    });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-apk]');
    if (!link) return;
    if (link.getAttribute('data-baixando') === '1') {
      event.preventDefault();
      return;
    }
    link.setAttribute('data-baixando', '1');
    window.setTimeout(function () { link.removeAttribute('data-baixando'); }, 8000);
  }, true);

  document.querySelectorAll('.sm-acc-item').forEach(function (item) {
    item.addEventListener('toggle', function () {
      if (!item.open) return;
      item.parentElement.querySelectorAll('.sm-acc-item').forEach(function (outro) {
        if (outro !== item) outro.open = false;
      });
    });
  });
})();
