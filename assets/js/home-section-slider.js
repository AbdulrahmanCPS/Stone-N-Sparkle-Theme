/**
 * Homepage section image sliders: prev/next, counter, keyboard.
 */
(function () {
    'use strict';

    var sliders = document.querySelectorAll('.ss-section-slider');
    if (!sliders.length) return;

    function initSlider(container) {
        var track = container.querySelector('.ss-section-slider__track');
        var slidesWrapper = container.querySelector('.ss-section-slider__slides');
        var slides = container.querySelectorAll('.ss-section-slider__slide');
        var prevBtn = container.querySelector('.ss-section-slider__prev');
        var nextBtn = container.querySelector('.ss-section-slider__next');
        var counterEl = container.querySelector('.ss-section-slider__counter');

        var total = slides.length;
        if (total <= 1) return;

        var current = 0;

        function goTo(index) {
            current = (index + total) % total;
            if (slidesWrapper) {
                slidesWrapper.style.transform = 'translateX(-' + (current * 100) + '%)';
            }
            slides.forEach(function (slide, i) {
                if (slide) {
                    if (i === current) {
                        slide.setAttribute('aria-current', 'true');
                    } else {
                        slide.removeAttribute('aria-current');
                    }
                }
            });
            if (counterEl) {
                counterEl.textContent = (current + 1) + ' / ' + total;
            }
        }

        function prev() {
            goTo(current - 1);
        }

        function next() {
            goTo(current + 1);
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                prev();
                nextBtn && nextBtn.focus();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                next();
                prevBtn && prevBtn.focus();
            });
        }

        container.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prev();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                next();
            }
        });

        goTo(0);
    }

    sliders.forEach(initSlider);
})();
