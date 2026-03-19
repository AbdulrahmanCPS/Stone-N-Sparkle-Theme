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

    var initialImages = JSON.parse(JSON.stringify(images));
    var thumbs   = root.querySelector('.ss-product-thumbs');
    var viewport = root.querySelector('.ss-stage-viewport');
    var prevBtn  = root.querySelector('.ss-gallery-prev');
    var nextBtn  = root.querySelector('.ss-gallery-next');

    var ZOOM_SCALE = 2.5;
    var SNAP_TRANSITION_MS = 300;
    var state = { index: 0, zoomed: false };
    var track = null;
    var lastVariationId = null;

    function getViewportWidth(){
      return viewport ? viewport.getBoundingClientRect().width : 0;
    }

    function getActiveSlideImg(){
      if (!track || !track.children[state.index]) return null;
      return track.children[state.index].querySelector('img');
    }

    function setActiveThumb(){
      if (!thumbs) return;
      var btns = thumbs.querySelectorAll('.ss-thumb-btn');
      for (var b = 0; b < btns.length; b++) {
        btns[b].classList.toggle('is-active', b === state.index);
      }
    }

    function applyTrackPosition(offsetPx, useTransition){
      if (!track) return;
      if (useTransition) {
        track.classList.add('is-snapping');
        track.style.transitionDuration = (SNAP_TRANSITION_MS / 1000) + 's';
      } else {
        track.classList.remove('is-snapping');
        track.style.transitionDuration = '0s';
      }
      track.style.transform = 'translate3d(' + offsetPx + 'px, 0, 0)';
    }

    function show(i){
      var newIndex = ((i % images.length) + images.length) % images.length;
      state.index = newIndex;
      var w = getViewportWidth();
      applyTrackPosition(-state.index * w, true);
      setActiveThumb();
      if (prevBtn && nextBtn) {
        if (images.length <= 1) {
          prevBtn.style.display = 'none';
          nextBtn.style.display = 'none';
        } else {
          prevBtn.style.display = '';
          nextBtn.style.display = '';
        }
      }
    }

    function buildTrack(){
      var w = getViewportWidth();
      if (w <= 0) return;

      var imgEl = root.querySelector('.ss-stage-image');
      var existingTrack = root.querySelector('.ss-stage-track');

      if (existingTrack) {
        track = existingTrack;
        track.classList.remove('is-snapping');
        track.style.transitionDuration = '';
        track.style.transform = '';
        track.innerHTML = '';
      } else {
        track = document.createElement('div');
        track.className = 'ss-stage-track';
        track.setAttribute('aria-label', 'Product image gallery');
        if (imgEl && imgEl.parentNode) {
          imgEl.parentNode.replaceChild(track, imgEl);
        } else if (viewport && viewport.firstChild && !viewport.querySelector('.ss-stage-track')) {
          viewport.insertBefore(track, viewport.firstChild);
        }
      }

      track.style.width = (images.length * w) + 'px';

      for (var i = 0; i < images.length; i++) {
        var slide = document.createElement('div');
        slide.className = 'ss-stage-slide';
        slide.style.width = w + 'px';
        slide.style.minWidth = w + 'px';
        slide.style.flexShrink = '0';
        var img = document.createElement('img');
        img.src = images[i].full;
        img.alt = images[i].alt || '';
        img.loading = i === 0 ? 'eager' : 'lazy';
        img.decoding = 'async';
        img.setAttribute('draggable', 'false');
        slide.appendChild(img);
        track.appendChild(slide);
      }

      applyTrackPosition(-state.index * w, false);
    }

    function updateTrackWidths(){
      if (!track) return;
      var w = getViewportWidth();
      if (w <= 0) return;
      track.style.width = (images.length * w) + 'px';
      for (var s = 0; s < track.children.length; s++) {
        track.children[s].style.width = w + 'px';
        track.children[s].style.minWidth = w + 'px';
      }
      applyTrackPosition(-state.index * w, false);
    }

    function buildThumbs(){
      if (!thumbs) return;
      thumbs.innerHTML = '';
      for (var i = 0; i < images.length; i++) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ss-thumb-btn' + (i === 0 ? ' is-active' : '');
        btn.setAttribute('aria-label', 'View image ' + (i + 1));
        var t = document.createElement('img');
        t.src = images[i].thumb || images[i].full;
        t.alt = images[i].alt || '';
        t.loading = 'lazy';
        t.decoding = 'async';
        btn.appendChild(t);
        (function(idx){ btn.addEventListener('click', function(){ show(idx); }); })(i);
        thumbs.appendChild(btn);
      }
    }

    function isMobileViewport(){
      return typeof window !== 'undefined' && window.innerWidth < 768;
    }

    function resetZoom(){
      if (isMobileViewport()) return;
      state.zoomed = false;
      var img = getActiveSlideImg();
      if (img) {
        img.style.transition = 'transform 0.2s ease';
        img.style.transform = 'scale(1) translate(0px, 0px)';
      }
    }

    if (prevBtn) prevBtn.addEventListener('click', function(){ show(state.index - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function(){ show(state.index + 1); });

    if (prevBtn) prevBtn.addEventListener('mouseenter', resetZoom);
    if (nextBtn) nextBtn.addEventListener('mouseenter', resetZoom);

    if (viewport) {
      /* Desktop zoom on current slide image */
      viewport.addEventListener('mouseenter', function(){
        if (isMobileViewport()) return;
        var img = getActiveSlideImg();
        if (img) {
          img.style.transition = 'transform 0.2s ease';
          state.zoomed = true;
        }
      });

      viewport.addEventListener('mousemove', function(e){
        if (isMobileViewport() || !state.zoomed) return;
        var img = getActiveSlideImg();
        if (!img) return;
        var rect = viewport.getBoundingClientRect();
        var xPct = (e.clientX - rect.left) / rect.width;
        var yPct = (e.clientY - rect.top) / rect.height;
        var tx = -(xPct * (ZOOM_SCALE - 1) * rect.width);
        var ty = -(yPct * (ZOOM_SCALE - 1) * rect.height);
        img.style.transition = 'none';
        img.style.transformOrigin = '0 0';
        img.style.transform = 'scale(' + ZOOM_SCALE + ') translate(' + (tx / ZOOM_SCALE) + 'px, ' + (ty / ZOOM_SCALE) + 'px)';
      });

      viewport.addEventListener('mouseleave', resetZoom);

      /* Mobile: slider that follows finger, then snaps. Higher = less physical swipe needed. */
      var SWIPE_SENSITIVITY = 1.8;
      var touchStartX = 0;
      var touchStartY = 0;
      var touchStartTranslate = 0;
      var isDragging = false;

      viewport.addEventListener('touchstart', function(e){
        if (e.touches.length !== 1 || images.length <= 1) return;
        isDragging = true;
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        touchStartTranslate = -state.index * getViewportWidth();
        if (track) track.classList.remove('is-snapping');
      }, { passive: true });

      viewport.addEventListener('touchmove', function(e){
        if (e.touches.length !== 1 || !isDragging || images.length <= 1 || !track) return;
        var dx = e.touches[0].clientX - touchStartX;
        var dy = e.touches[0].clientY - touchStartY;
        var w = getViewportWidth();
        var minTx = -((images.length - 1) * w);
        var maxTx = 0;
        var tx = touchStartTranslate + (dx * SWIPE_SENSITIVITY);
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 8) {
          e.preventDefault();
        }
        tx = Math.max(minTx, Math.min(maxTx, tx));
        applyTrackPosition(tx, false);
      }, { passive: false });

      viewport.addEventListener('touchend', function(e){
        if (e.changedTouches.length !== 1 || !isDragging || images.length <= 1) return;
        isDragging = false;
        var w = getViewportWidth();
        if (w <= 0) return;
        var currentTx = touchStartTranslate + ((e.changedTouches[0].clientX - touchStartX) * SWIPE_SENSITIVITY);
        var newIndex = Math.round(-currentTx / w);
        newIndex = Math.max(0, Math.min(images.length - 1, newIndex));
        state.index = newIndex;
        applyTrackPosition(-state.index * w, true);
        setActiveThumb();
      }, { passive: true });

      viewport.addEventListener('touchcancel', function(){
        isDragging = false;
        applyTrackPosition(-state.index * getViewportWidth(), true);
      }, { passive: true });
    }

    function initGallery(){
      var w = getViewportWidth();
      if (w <= 0) return false;
      buildTrack();
      buildThumbs();
      show(0);
      return true;
    }

    if (!initGallery()) {
      var tryInit = function(){
        if (initGallery()) return;
        requestAnimationFrame(function(){
          if (initGallery()) return;
          window.addEventListener('load', function once(){
            window.removeEventListener('load', once);
            initGallery();
          });
        });
      };
      requestAnimationFrame(tryInit);
    }

    window.addEventListener('resize', function(){
      if (!track) return;
      updateTrackWidths();
    });

    if (document.querySelector('form.variations_form') && typeof jQuery !== 'undefined') {
      jQuery(document.body).on('found_variation', 'form.variations_form', function (evt, variation) {
        if (!variation) return;
        var vid = variation.variation_id || variation.id;
        if (lastVariationId === vid) return;
        lastVariationId = vid;

        if (variation.image && variation.image.full_src) {
          var variationItem = {
            full: variation.image.full_src,
            thumb: variation.image.thumb_src || variation.image.full_src,
            alt: variation.image.title || ''
          };
          images = [variationItem].concat(initialImages.slice(1));
          state.index = 0;
          buildTrack();
          buildThumbs();
          show(0);
        } else {
          images = JSON.parse(JSON.stringify(initialImages));
          state.index = 0;
          buildTrack();
          buildThumbs();
          show(0);
        }
      });

      jQuery(document.body).on('reset_data', 'form.variations_form', function () {
        lastVariationId = null;
        images = JSON.parse(JSON.stringify(initialImages));
        state.index = 0;
        buildTrack();
        buildThumbs();
        show(0);
      });
    }
  });
})();
