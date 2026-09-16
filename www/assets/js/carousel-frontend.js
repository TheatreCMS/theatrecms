/**
 * Frontend runtime for the "carousel" EditorJS block.
 *
 * Dependency-free: no jQuery, no Slick. Finds every server-rendered
 * `.editorjs-carousel` element and turns its `.editorjs-carousel__slide`
 * children into a sliding carousel with optional arrows, dots, and
 * autoplay (pauses on hover/focus), plus keyboard and touch/swipe support.
 */
(function () {
    function initCarousel(root) {
        var slides = Array.prototype.slice.call(root.querySelectorAll(':scope > .editorjs-carousel__slide'));
        if (slides.length < 2) {
            return;
        }

        var autoplay      = root.dataset.autoplay === 'true';
        var autoplaySpeed = parseInt(root.dataset.autoplaySpeed, 10);
        if (!Number.isFinite(autoplaySpeed) || autoplaySpeed < 1000) {
            autoplaySpeed = 3000;
        }
        var showArrows = root.dataset.arrows !== 'false';
        var showDots   = root.dataset.dots !== 'false';

        var current = 0;
        var timer   = null;
        var isHovered = false;
        var hasFocusWithin = false;

        var viewport = document.createElement('div');
        viewport.className = 'editorjs-carousel__viewport';

        var track = document.createElement('div');
        track.className = 'editorjs-carousel__track';
        slides.forEach(function (slide) {
            track.appendChild(slide);
        });
        viewport.appendChild(track);
        root.appendChild(viewport);

        function update() {
            track.style.transform = 'translateX(-' + (current * 100) + '%)';
            if (dots) {
                Array.prototype.forEach.call(dots.children, function (dot, index) {
                    dot.classList.toggle('is-active', index === current);
                    dot.setAttribute('aria-current', index === current ? 'true' : 'false');
                });
            }
        }

        function goTo(index) {
            current = (index + slides.length) % slides.length;
            update();
        }

        function next() {
            goTo(current + 1);
        }

        function prev() {
            goTo(current - 1);
        }

        function startAutoplay() {
            if (!autoplay || isHovered || hasFocusWithin) {
                return;
            }
            stopAutoplay();
            timer = window.setInterval(next, autoplaySpeed);
        }

        function stopAutoplay() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        var dots = null;

        if (showArrows) {
            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'editorjs-carousel__arrow editorjs-carousel__arrow--prev';
            prevBtn.setAttribute('aria-label', 'Previous slide');
            prevBtn.innerHTML = '&#10094;';
            prevBtn.addEventListener('click', function () {
                prev();
                startAutoplay();
            });

            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'editorjs-carousel__arrow editorjs-carousel__arrow--next';
            nextBtn.setAttribute('aria-label', 'Next slide');
            nextBtn.innerHTML = '&#10095;';
            nextBtn.addEventListener('click', function () {
                next();
                startAutoplay();
            });

            viewport.appendChild(prevBtn);
            viewport.appendChild(nextBtn);
        }

        if (showDots) {
            dots = document.createElement('div');
            dots.className = 'editorjs-carousel__dots';
            slides.forEach(function (_, index) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'editorjs-carousel__dot';
                dot.setAttribute('aria-label', 'Go to slide ' + (index + 1));
                dot.addEventListener('click', function () {
                    goTo(index);
                    startAutoplay();
                });
                dots.appendChild(dot);
            });
            root.appendChild(dots);
        }

        root.setAttribute('tabindex', '0');
        root.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') {
                prev();
                startAutoplay();
            } else if (event.key === 'ArrowRight') {
                next();
                startAutoplay();
            }
        });

        root.addEventListener('mouseenter', function () {
            isHovered = true;
            stopAutoplay();
        });
        root.addEventListener('mouseleave', function () {
            isHovered = false;
            startAutoplay();
        });
        root.addEventListener('focusin', function () {
            hasFocusWithin = true;
            stopAutoplay();
        });
        root.addEventListener('focusout', function (event) {
            if (!event.relatedTarget || !root.contains(event.relatedTarget)) {
                hasFocusWithin = false;
                startAutoplay();
            }
        });

        var pointerStartX = null;

        track.addEventListener('pointerdown', function (event) {
            pointerStartX = event.clientX;
            stopAutoplay();
        });

        track.addEventListener('pointerup', function (event) {
            if (pointerStartX === null) {
                return;
            }
            var delta = event.clientX - pointerStartX;
            pointerStartX = null;
            var threshold = 40;
            if (delta > threshold) {
                prev();
            } else if (delta < -threshold) {
                next();
            }
            startAutoplay();
        });

        track.addEventListener('pointercancel', function () {
            pointerStartX = null;
            startAutoplay();
        });

        update();
        startAutoplay();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.editorjs-carousel').forEach(initCarousel);
    });
})();
