// assets/js/web.js
(function() {

    // ============================================
    // 1. CAROUSEL INIT
    // ============================================
    function initWebCarousels() {
        if (typeof Swiper === 'undefined') {
            setTimeout(initWebCarousels, 200);
            return;
        }
        document.querySelectorAll('.nizWebSwiper').forEach(function(el) {
            if (el.classList.contains('swiper-initialized') || el.swiper) return;
            var postCount = parseInt(el.getAttribute('data-post-count')) || 0;
            new Swiper(el, {
                slidesPerView: 1, spaceBetween: 20, loop: postCount > 1,
                autoplay: { delay: 3500, disableOnInteraction: false, pauseOnMouseEnter: true },
                pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
                breakpoints: {
                    640: { slidesPerView: 2, loop: postCount > 2 },
                    1024: { slidesPerView: 3, loop: postCount > 3 }
                },
                grabCursor: true
            });
        });
    }

    // ============================================
    // 2. MAIN DIRECTORY INIT
    // ============================================
    function initWebDirectory() {
        document.querySelectorAll('.niz-web-wrapper').forEach(function(wrapper) {
            if (wrapper.dataset.init === 'true') return;
            wrapper.dataset.init = 'true';

            var ajaxurl = wrapper.getAttribute('data-ajaxurl');
            var currentWebId = wrapper.getAttribute('data-current-id') || '0'; 
            var offset = 0;
            var limit = 9;
            var searchTimeout = null;

            var listContainer = wrapper.querySelector('#web-list');
            var loadMoreBtn = wrapper.querySelector('.load-more-web-btn');
            var searchInput = wrapper.querySelector('.niz-web-search');
            var categorySelect = wrapper.querySelector('.niz-web-category');
            var countrySelect = wrapper.querySelector('.niz-web-country');

            // --- DEBUG & LOAD SESSION DATA ---
            console.log("--- Checking Session Storage ---");
            
            if (searchInput && sessionStorage.getItem('niz_web_search') !== null) {
                searchInput.value = sessionStorage.getItem('niz_web_search');
                console.log("Restored Search:", searchInput.value);
            }
            
            if (categorySelect) {
                var savedCategory = sessionStorage.getItem('niz_web_category');
                console.log("Restored Category:", savedCategory);
                if (savedCategory) {
                    categorySelect.value = savedCategory;
                    // Force UI update for WordPress plugins like Select2
                    if (window.jQuery) window.jQuery(categorySelect).trigger('change'); 
                }
            }
            
            if (countrySelect) {
                var savedCountry = sessionStorage.getItem('niz_web_country');
                console.log("Restored Country:", savedCountry);
                if (savedCountry) {
                    countrySelect.value = savedCountry;
                    // Force UI update for WordPress plugins like Select2
                    if (window.jQuery) window.jQuery(countrySelect).trigger('change');
                }
            }

            // --- FETCH FUNCTION ---
            function fetchWebsites(forceReplace) {
                if (!listContainer) return;
                if (forceReplace) {
                    offset = 0;
                    listContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
                }

                var formData = new FormData();
                formData.append('action', 'niz_mfa_load_more_web');
                formData.append('offset', offset);
                formData.append('limit', limit);
                formData.append('search', searchInput ? searchInput.value : '');
                formData.append('category', categorySelect ? categorySelect.value : '');
                formData.append('country', countrySelect ? countrySelect.value : '');
                formData.append('current_web_id', currentWebId); 
                
                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        if (html.trim() === '') {
                            if (forceReplace) listContainer.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 40px; color: #94a3b8;">No websites found matching your criteria.</div>';
                            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                            return;
                        }
                        if (loadMoreBtn) loadMoreBtn.style.display = 'block';
                        if (forceReplace) listContainer.innerHTML = html;
                        else listContainer.insertAdjacentHTML('beforeend', html);
                        offset += limit;
                    }).catch(err => console.error(err));
            }

            // --- BIND EVENTS & SAVE TO SESSION ---
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', () => fetchWebsites(false));
            }
            
            // Using jQuery to listen for changes (catches Select2 changes safely)
            if (categorySelect) {
                if (window.jQuery) {
                    window.jQuery(categorySelect).on('change', function() {
                        console.log("Saving Category:", this.value);
                        sessionStorage.setItem('niz_web_category', this.value);
                        fetchWebsites(true);
                    });
                } else {
                    categorySelect.addEventListener('change', function() {
                        console.log("Saving Category:", this.value);
                        sessionStorage.setItem('niz_web_category', this.value);
                        fetchWebsites(true);
                    });
                }
            }
            
            if (countrySelect) {
                if (window.jQuery) {
                    window.jQuery(countrySelect).on('change', function() {
                        console.log("Saving Country:", this.value);
                        sessionStorage.setItem('niz_web_country', this.value);
                        fetchWebsites(true);
                    });
                } else {
                    countrySelect.addEventListener('change', function() {
                        console.log("Saving Country:", this.value);
                        sessionStorage.setItem('niz_web_country', this.value);
                        fetchWebsites(true);
                    });
                }
            }
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    console.log("Saving Search:", this.value);
                    sessionStorage.setItem('niz_web_search', this.value);
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => fetchWebsites(true), 400);
                });
            }

            // --- INITIAL LOAD ---
            fetchWebsites(true);
        });
    }
 
    // ============================================
    // 3. LAUNCH ON READY
    // ============================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initWebCarousels(); 
            initWebDirectory();
        });
    } else {
        initWebCarousels(); 
        initWebDirectory();
    }
})();