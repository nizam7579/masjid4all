// assets/js/business.js - Cache Safe Business Engine
(function() {
    
    function getGeoCookie(name) {
        const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? decodeURIComponent(v[2]).replace(/-/g, ' ') : null;
    }

    // ============================================
    // CAROUSEL INIT (SWIPER)
    // ============================================
    function initBizCarousels() {
        if (typeof Swiper === 'undefined') {
            setTimeout(initBizCarousels, 200);
            return;
        }
        document.querySelectorAll('.nizBusinessSwiper').forEach(function(el) {
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
    // GLOBAL DIRECTORY (Nearest Business)
    // ============================================
    function initGlobalBusinessDirectory() {
        // Query selector loop protects multi-widget rendering instances
        document.querySelectorAll('.niz-business-wrapper').forEach(function(wrapper) {
            if (wrapper.dataset.init === 'true') return;
            wrapper.dataset.init = 'true';

            var ajaxurl = wrapper.getAttribute('data-ajaxurl');
            var currentBusinessId = wrapper.getAttribute('data-current-id') || '0'; 
            var offset = 0;
            var limit = 9;
            var searchTimeout = null;

            var listContainer = wrapper.querySelector('#business-list');
            var loadMoreBtn = wrapper.querySelector('#load-more-business-btn');
            var searchInput = wrapper.querySelector('#niz-business-search-input');
            var countrySelect = wrapper.querySelector('#niz-business-country-select');

            var userCountry = getGeoCookie('country');
            if (userCountry && countrySelect) {
                for (var i = 0; i < countrySelect.options.length; i++) {
                    if (countrySelect.options[i].value.toLowerCase() === userCountry.toLowerCase()) {
                        countrySelect.selectedIndex = i;
                        break;
                    }
                }
            }

            function fetchBusinesses(forceReplace) {
                if (!listContainer) return;
                if (forceReplace) {
                    offset = 0;
                    listContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
                }
                
                var lat = getGeoCookie('latitude') || '3.14';
                var lng = getGeoCookie('longitude') || '101.69';
                var country = countrySelect ? countrySelect.value : (userCountry || 'Malaysia');
                var search = searchInput ? searchInput.value : '';

                var formData = new FormData();
                formData.append('action', 'niz_mfa_load_more_businesses');
                formData.append('offset', offset);
                formData.append('limit', limit);
                formData.append('lat', lat);
                formData.append('lng', lng);
                formData.append('country', country);
                formData.append('search', search);
                formData.append('current_business_id', currentBusinessId); 
                
                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        if (html.trim() === '') {
                            if (forceReplace) listContainer.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 40px; color: #94a3b8;">No businesses found in this area.</div>';
                            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                            return;
                        }
                        if (loadMoreBtn) loadMoreBtn.style.display = 'block';
                        if (forceReplace) listContainer.innerHTML = html;
                        else listContainer.insertAdjacentHTML('beforeend', html);
                        offset += limit;
                    }).catch(err => console.error(err));
            }

            if (loadMoreBtn) loadMoreBtn.addEventListener('click', () => fetchBusinesses(false));
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => fetchBusinesses(true), 500);
                });
            }
            if (countrySelect) countrySelect.addEventListener('change', () => fetchBusinesses(true));

            var safetyFallbackTimeout = setTimeout(() => { 
                clearInterval(checkLocationInterval); 
                fetchBusinesses(true); 
            }, 3000);

            var checkLocationInterval = setInterval(() => {
                if (getGeoCookie('latitude')) {
                    clearInterval(checkLocationInterval);
                    clearTimeout(safetyFallbackTimeout);
                    fetchBusinesses(true);
                }
            }, 300);
        });
    }

    // ============================================
    // LOCAL BUSINESSES (Near Mosque or City CPT)
    // ============================================
    function initLocalBusinesses() {
        document.querySelectorAll('.niz-local-wrapper').forEach(wrapper => {
            if (wrapper.dataset.init === 'true') return;
            wrapper.dataset.init = 'true';

            var ajaxurl = wrapper.getAttribute('data-ajaxurl');
            var targetLat = wrapper.getAttribute('data-biz-lat');
            var targetLng = wrapper.getAttribute('data-biz-lng');
            var targetName = wrapper.getAttribute('data-biz-name');

            var offset = 0;
            var limit = 6;

            var listContainer = wrapper.querySelector('.local-business-list-canvas');
            var loadMoreBtn = wrapper.querySelector('.load-more-local-btn');

            function fetchLocal(forceReplace) {
                if (forceReplace) { offset = 0; }

                var formData = new FormData();
                formData.append('action', 'niz_mfa_load_local_businesses');
                formData.append('offset', offset);
                formData.append('limit', limit);
                formData.append('lat', targetLat);
                formData.append('lng', targetLng);

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        if(forceReplace) listContainer.innerHTML = '';
                        
                        if (html.trim() === '') {
                            if (forceReplace) listContainer.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px; color:#94a3b8;">No businesses found near ${targetName}.</div>`;
                            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                            return;
                        }
                        if (loadMoreBtn) loadMoreBtn.style.display = 'block';
                        listContainer.insertAdjacentHTML('beforeend', html);
                        offset += limit;
                    }).catch(err => console.error(err));
            }

            fetchLocal(true);
            if (loadMoreBtn) loadMoreBtn.addEventListener('click', () => fetchLocal(false));
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initBizCarousels(); initGlobalBusinessDirectory(); initLocalBusinesses();
        });
    } else {
        initBizCarousels(); initGlobalBusinessDirectory(); initLocalBusinesses();
    }
})();