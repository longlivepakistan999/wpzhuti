/**
 * QWE AI Academy - Main JavaScript
 *
 * @package QWE_Developer_Flavor
 */

(function () {
    'use strict';

    /**
     * Mobile menu toggle.
     */
    function initMobileMenu() {
        var toggle = document.querySelector('.menu-toggle');
        var nav = document.querySelector('.main-navigation');
        if (!toggle || !nav) return;

        toggle.addEventListener('click', function () {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            nav.classList.toggle('is-open');
        });

        // Close menu on outside click
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target) && !toggle.contains(e.target) && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /**
     * Generate Table of Contents from h2/h3 headings in tutorial content.
     */
    function initTableOfContents() {
        var tocNav = document.getElementById('toc-nav');
        var content = document.querySelector('.tutorial-content');
        if (!tocNav || !content) return;

        var headings = content.querySelectorAll('h2, h3');
        if (headings.length < 2) {
            var tocContainer = document.getElementById('tutorial-toc');
            if (tocContainer) tocContainer.style.display = 'none';
            return;
        }

        var list = document.createElement('ul');
        list.className = 'toc-list';

        headings.forEach(function (heading, index) {
            var id = heading.id || 'section-' + index;
            heading.id = id;

            var li = document.createElement('li');
            li.className = 'toc-list__item toc-list__item--' + heading.tagName.toLowerCase();

            var a = document.createElement('a');
            a.href = '#' + id;
            a.textContent = heading.textContent;
            a.className = 'toc-list__link';

            li.appendChild(a);
            list.appendChild(li);
        });

        tocNav.appendChild(list);

        // Highlight active section on scroll
        var tocLinks = tocNav.querySelectorAll('.toc-list__link');
        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        tocLinks.forEach(function (link) {
                            link.classList.remove('is-active');
                        });
                        var activeLink = tocNav.querySelector('a[href="#' + entry.target.id + '"]');
                        if (activeLink) activeLink.classList.add('is-active');
                    }
                });
            },
            { rootMargin: '-80px 0px -60% 0px', threshold: 0 }
        );

        headings.forEach(function (heading) {
            observer.observe(heading);
        });
    }

    /**
     * Sticky TOC sidebar behavior.
     */
    function initStickyToc() {
        var toc = document.querySelector('.tutorial-toc__inner');
        if (!toc) return;

        var headerHeight = 72 + 24; // header height + padding

        window.addEventListener('scroll', function () {
            var parent = toc.parentElement;
            var parentRect = parent.getBoundingClientRect();

            if (parentRect.top < headerHeight) {
                toc.style.position = 'fixed';
                toc.style.top = headerHeight + 'px';
                toc.style.width = parent.offsetWidth + 'px';
            } else {
                toc.style.position = 'static';
                toc.style.width = 'auto';
            }
        });
    }

    /**
     * Smooth scroll for anchor links.
     */
    function initSmoothScroll() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href^="#"]');
            if (!link) return;

            var targetId = link.getAttribute('href').slice(1);
            var target = document.getElementById(targetId);
            if (!target) return;

            e.preventDefault();
            var headerOffset = 80;
            var targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerOffset;

            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth',
            });
        });
    }

    /**
     * Header search expand/collapse.
     */
    function initHeaderSearch() {
        var searchInput = document.querySelector('.header-search__input');
        if (!searchInput) return;

        searchInput.addEventListener('focus', function () {
            this.parentElement.classList.add('is-expanded');
        });

        searchInput.addEventListener('blur', function () {
            if (!this.value) {
                this.parentElement.classList.remove('is-expanded');
            }
        });
    }

    /**
     * Initialize all components on DOM ready.
     */
    function init() {
        initMobileMenu();
        initTableOfContents();
        initStickyToc();
        initSmoothScroll();
        initHeaderSearch();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
