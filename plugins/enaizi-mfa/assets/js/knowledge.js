// assets/js/knowledge.js
(function() {
    
    // --- ADD THIS CAROUSEL FUNCTION ---
    function initKnowledgeCarousels() {
        if (typeof Swiper === 'undefined') {
            setTimeout(initKnowledgeCarousels, 200);
            return;
        }
        document.querySelectorAll('.nizKnowledgeSwiper').forEach(function(el) {
            if (el.classList.contains('swiper-initialized') || el.swiper) return;
            var postCount = parseInt(el.getAttribute('data-post-count')) || 0;
            new Swiper(el, {
                slidesPerView: 1, spaceBetween: 20, loop: postCount > 1,
                autoplay: { delay: 4000, disableOnInteraction: false, pauseOnMouseEnter: true },
                pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
                breakpoints: {
                    640: { slidesPerView: 2, loop: postCount > 2 },
                    1024: { slidesPerView: 3, loop: postCount > 3 }
                },
                grabCursor: true
            });
        });
    }
    
    
    function initKnowledgeDirectory() {
        document.querySelectorAll('.niz-knowledge-wrapper').forEach(function(wrapper) {
            if (wrapper.dataset.init === 'true') return;
            wrapper.dataset.init = 'true';

            var ajaxurl = wrapper.getAttribute('data-ajaxurl');
            var currentKnowledgeId = wrapper.getAttribute('data-current-id') || '0'; 
            var offset = 0;
            var limit = 9;
            var searchTimeout = null;

            var listContainer = wrapper.querySelector('#knowledge-list');
            var loadMoreBtn = wrapper.querySelector('.load-more-knowledge-btn');
            var searchInput = wrapper.querySelector('.niz-knowledge-search');
            var categorySelect = wrapper.querySelector('.niz-knowledge-category');

            function fetchKnowledge(forceReplace) {
                if (!listContainer) return;
                if (forceReplace) {
                    offset = 0;
                    listContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
                }

                var formData = new FormData();
                formData.append('action', 'niz_mfa_load_more_knowledge');
                formData.append('offset', offset);
                formData.append('limit', limit);
                formData.append('search', searchInput ? searchInput.value : '');
                formData.append('category', categorySelect ? categorySelect.value : '');
                formData.append('current_knowledge_id', currentKnowledgeId); 
                
                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        if (html.trim() === '') {
                            if (forceReplace) listContainer.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 40px; color: #94a3b8;">No knowledge articles found matching your query.</div>';
                            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                            return;
                        }
                        if (loadMoreBtn) loadMoreBtn.style.display = 'block';
                        if (forceReplace) listContainer.innerHTML = html;
                        else listContainer.insertAdjacentHTML('beforeend', html);
                        offset += limit;
                    }).catch(err => console.error(err));
            }

            if (loadMoreBtn) loadMoreBtn.addEventListener('click', () => fetchKnowledge(false));
            if (categorySelect) categorySelect.addEventListener('change', () => fetchKnowledge(true));
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => fetchKnowledge(true), 400);
                });
            }

            fetchKnowledge(true);
        });
    }

    // --- UPDATE YOUR INITIALIZER AT THE BOTTOM ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initKnowledgeCarousels(); 
            initKnowledgeDirectory();
        });
    } else {
        initKnowledgeCarousels(); 
        initKnowledgeDirectory();
    }


})();