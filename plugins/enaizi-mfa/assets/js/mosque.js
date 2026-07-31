// The BFCache Killer: Forces a fresh reload if the user uses the Back/Forward button
window.addEventListener('pageshow', function(event) {
    // event.persisted is TRUE if the browser loaded the page from frozen memory
    if (event.persisted) {
        window.location.reload(); 
    }
});



// assets/js/mosque.js - Cache Safe Directory Engine
(function() {
    
    function getGeoCookie(name) {
        const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? decodeURIComponent(v[2]).replace(/-/g, ' ') : null;
    }

    // ============================================
    // CAROUSEL INIT (SWIPER)
    // ============================================
    function initCarousels() {
        if (typeof Swiper === 'undefined') {
            setTimeout(initCarousels, 200);
            return;
        }
        document.querySelectorAll('.nizMosqueSwiper').forEach(function(el) {
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
    // GLOBAL DIRECTORY (Nearest Mosque to User)
    // ============================================
    function initGlobalDirectory() {
        // Use querySelectorAll to find all directory instances on the page safely
        document.querySelectorAll('.niz-directory-wrapper').forEach(function(wrapper) {
            if (wrapper.dataset.init === 'true') return;
            wrapper.dataset.init = 'true';

            var ajaxurl = wrapper.getAttribute('data-ajaxurl');
            var currentMosqueId = wrapper.getAttribute('data-current-id') || '0'; 
            var offset = 0;
            var limit = 9;
            var searchTimeout = null;

            var listContainer = wrapper.querySelector('#mosque-list');
            var loadMoreBtn = wrapper.querySelector('#load-more-btn');
            var searchInput = wrapper.querySelector('#niz-search-input');
            var countrySelect = wrapper.querySelector('#niz-country-select');

            // Auto-select dropdown based on cookie
            var userCountry = getGeoCookie('country');
            if (userCountry && countrySelect) {
                for (var i = 0; i < countrySelect.options.length; i++) {
                    if (countrySelect.options[i].value.toLowerCase() === userCountry.toLowerCase()) {
                        countrySelect.selectedIndex = i;
                        break;
                    }
                }
            }

            function fetchMosques(forceReplace) {
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
                formData.append('action', 'niz_mfa_load_more_mosques');
                formData.append('offset', offset);
                formData.append('limit', limit);
                formData.append('lat', lat);
                formData.append('lng', lng);
                formData.append('country', country);
                formData.append('search', search);
                formData.append('current_mosque_id', currentMosqueId);
                
                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        if (html.trim() === '') {
                            if (forceReplace) listContainer.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 40px; color: #94a3b8;">No mosques found in this area.</div>';
                            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                            return;
                        }
                        if (loadMoreBtn) loadMoreBtn.style.display = 'block';
                        if (forceReplace) listContainer.innerHTML = html;
                        else listContainer.insertAdjacentHTML('beforeend', html);
                        offset += limit;
                    }).catch(err => console.error(err));
            }

            if (loadMoreBtn) loadMoreBtn.addEventListener('click', () => fetchMosques(false));
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => fetchMosques(true), 500);
                });
            }
            if (countrySelect) countrySelect.addEventListener('change', () => fetchMosques(true));

            // Scoped fallback timers per widget instance
            var safetyFallbackTimeout = setTimeout(() => { 
                clearInterval(checkLocationInterval); 
                fetchMosques(true); 
            }, 3000);

            var checkLocationInterval = setInterval(() => {
                if (getGeoCookie('latitude')) {
                    clearInterval(checkLocationInterval);
                    clearTimeout(safetyFallbackTimeout);
                    fetchMosques(true);
                }
            }, 300);
        });
    }

    // ============================================
    // LOCAL MOSQUES (Near Business/City CPT)
    // ============================================
    function initLocalMosques() {
        document.querySelectorAll('.niz-mosque-nearby-wrapper').forEach(wrapper => {
            if (wrapper.dataset.init === 'true') return;
            wrapper.dataset.init = 'true';

            var ajaxurl = wrapper.getAttribute('data-ajaxurl');
            var targetLat = wrapper.getAttribute('data-biz-lat');
            var targetLng = wrapper.getAttribute('data-biz-lng');
            var targetName = wrapper.getAttribute('data-biz-name');

            var offset = 0;
            var limit = 6;
            var searchTimeout = null;

            var listContainer = wrapper.querySelector('.niz-local-mosque-list');
            var loadMoreBtn = wrapper.querySelector('.niz-local-load-more-btn');
            var searchInput = wrapper.querySelector('.niz-local-search-input');

            function fetchLocal(forceReplace) {
                if (forceReplace) {
                    offset = 0;
                    listContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
                }

                var formData = new FormData();
                formData.append('action', 'niz_mfa_load_local_mosques'); // MUST match your backend AJAX hook
                formData.append('offset', offset);
                formData.append('limit', limit);
                formData.append('lat', targetLat);
                formData.append('lng', targetLng);
                formData.append('search', searchInput ? searchInput.value : '');

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        if (html.trim() === '') {
                            if (forceReplace) listContainer.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px; color:#94a3b8;">No mosques found near ${targetName}.</div>`;
                            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                            return;
                        }
                        if (loadMoreBtn) loadMoreBtn.style.display = 'block';
                        if (forceReplace) listContainer.innerHTML = html;
                        else listContainer.insertAdjacentHTML('beforeend', html);
                        offset += limit;
                    }).catch(err => console.error(err));
            }

            // Local directories have the exact coordinates already, so fetch immediately
            fetchLocal(true);

            if (loadMoreBtn) loadMoreBtn.addEventListener('click', () => fetchLocal(false));
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => fetchLocal(true), 500);
                });
            }
        });
    }

    // Initialize all
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initCarousels(); initGlobalDirectory(); initLocalMosques();
        });
    } else {
        initCarousels(); initGlobalDirectory(); initLocalMosques();
    }
    
})();