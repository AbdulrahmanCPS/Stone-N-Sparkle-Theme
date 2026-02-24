(function () {
  'use strict';

  function ready(fn){
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function(){
    var root = document.querySelector('.ss-product-gallery[data-images]');
    if (!root) return;

    var images;
    try {
      images = JSON.parse(root.getAttribute('data-images') || '[]');
    } catch (e) {
      images = [];
    }
    if (!Array.isArray(images) || images.length === 0) return;

    var thumbs   = root.querySelector('.ss-product-thumbs');
    var viewport = root.querySelector('.ss-stage-viewport');
    var imgEl    = root.querySelector('.ss-stage-image');
    var prevBtn  = root.querySelector('.ss-gallery-prev');
    var nextBtn  = root.querySelector('.ss-gallery-next');

    var ZOOM_SCALE = 2.5;
    var state = { index: 0, zoomed: false };

    function setActiveThumb(){
      var btns = thumbs ? thumbs.querySelectorAll('.ss-thumb-btn') : [];
      btns.forEach(function(b, i){
        b.classList.toggle('is-active', i === state.index);
      });
    }

    function show(i){
      state.index = ((i % images.length) + images.length) % images.length;
      var item = images[state.index];
      imgEl.src = item.full;
      imgEl.alt = item.alt || '';
      imgEl.style.transform = 'scale(1) translate(0px, 0px)';
      imgEl.style.transformOrigin = '0 0';
      setActiveThumb();
      if (images.length <= 1) {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
      } else {
        if (prevBtn) prevBtn.style.display = '';
        if (nextBtn) nextBtn.style.display = '';
      }
    }

    function buildThumbs(){
      if (!thumbs) return;
      thumbs.innerHTML = '';
      images.forEach(function(item, i){
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ss-thumb-btn' + (i === 0 ? ' is-active' : '');
        btn.setAttribute('aria-label', 'View image ' + (i + 1));
        var t = document.createElement('img');
        t.src = item.thumb || item.full;
        t.alt = item.alt || '';
        t.loading = 'lazy';
        t.decoding = 'async';
        btn.appendChild(t);
        btn.addEventListener('click', function(){ show(i); });
        thumbs.appendChild(btn);
      });
    }

    if (prevBtn) prevBtn.addEventListener('click', function(){ show(state.index - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function(){ show(state.index + 1); });

    if (viewport) {
      viewport.addEventListener('mouseenter', function(){
        imgEl.style.transition = 'transform 0.2s ease';
        state.zoomed = true;
      });

      viewport.addEventListener('mousemove', function(e){
        if (!state.zoomed) return;
        var rect = viewport.getBoundingClientRect();
        var xPct = (e.clientX - rect.left)  / rect.width;
        var yPct = (e.clientY - rect.top)   / rect.height;
        var tx = -(xPct * (ZOOM_SCALE - 1) * rect.width);
        var ty = -(yPct * (ZOOM_SCALE - 1) * rect.height);
        imgEl.style.transition = 'none';
        imgEl.style.transformOrigin = '0 0';
        imgEl.style.transform = 'scale(' + ZOOM_SCALE + ') translate(' + (tx / ZOOM_SCALE) + 'px, ' + (ty / ZOOM_SCALE) + 'px)';
      });

      viewport.addEventListener('mouseleave', function(){
        state.zoomed = false;
        imgEl.style.transition = 'transform 0.2s ease';
        imgEl.style.transform  = 'scale(1) translate(0px, 0px)';
      });

      viewport.addEventListener('touchstart', function(){}, { passive: true });
    }

    buildThumbs();
    show(0);
  });
})();
