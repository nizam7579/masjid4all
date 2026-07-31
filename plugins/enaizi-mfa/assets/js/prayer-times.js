// assets/js/prayer-times.js - Hardened Client-Side Prayer Engine
(function() {
    
    const DEFAULT_LAT = 3.14;
    const DEFAULT_LNG = 101.69;
    const DEFAULT_COUNTRY = 'malaysia';
    const DEFAULT_CITY = 'Kuala Lumpur';

    // 1. Helper to read cookies
    function getGeoCookie(name) {
        const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? decodeURIComponent(v[2]).replace(/-/g, ' ') : null;
    }

    // 2. Map country to Aladhan API Methods
    function getPrayerMethod(countryName) {
        if (!countryName) return 3; 
        const country = countryName.toLowerCase().trim();
        const map = {
            'malaysia': 17, 'indonesia': 20, 'singapore': 11, 'saudi arabia': 4,
            'egypt': 5, 'united arab emirates': 8, 'uae': 8, 'kuwait': 9, 'qatar': 10,
            'turkey': 13, 'russia': 14, 'france': 12, 'tunisia': 18, 'algeria': 19,
            'morocco': 21, 'portugal': 22, 'pakistan': 1, 'india': 1, 'bangladesh': 1,
            'afghanistan': 1, 'united states': 2, 'usa': 2, 'canada': 2, 'united kingdom': 3, 'uk': 3
        };
        for (const key in map) {
            if (country.includes(key)) return map[key];
        }
        return 3;
    }

    // 3. The Core Render Engine
    function startClockEngine(wrapper, prayers) {
        
        // Clear any existing clock intervals so we can safely inject new GPS data
        if (wrapper.clockInterval) {
            clearInterval(wrapper.clockInterval);
        }

        function convertTo12Hour(timeStr) {
            if (!timeStr) return "--:--";
            const cleanTime = timeStr.split(' ')[0]; 
            const parts = cleanTime.split(':');
            let hour = parseInt(parts[0], 10);
            const min = parts[1];
            const ampm = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12 || 12;
            return hour + ':' + min + ' ' + ampm;
        }

        function formatMinutes(totalMinutes) {
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            return hours > 0 ? `${hours} hr ${minutes} min` : `${minutes} min`;
        }

        function updateClock() {
            const now = new Date();
            const currentMinutes = now.getHours() * 60 + now.getMinutes();
            const order = ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];
            let currentActive = 'Isha';

            for (let i = 0; i < order.length; i++) {
                if(!prayers[order[i]]) continue;
                const pTime = prayers[order[i]].split(' ')[0].split(':');
                const pMins = parseInt(pTime[0], 10) * 60 + parseInt(pTime[1], 10);
                if (currentMinutes >= pMins) {
                    currentActive = order[i];
                }
            }

            const items = wrapper.querySelectorAll(".niz-prayer-item");
            items.forEach(item => {
                const name = item.getAttribute("data-prayer");
                const valEl = item.querySelector(".niz-time-val");
                if (valEl && prayers[name]) {
                    valEl.textContent = convertTo12Hour(prayers[name]);
                }
                if (name === currentActive) {
                    item.classList.add("niz-active");
                } else {
                    item.classList.remove("niz-active");
                }
            });

            const trackingList = [
                { name: "Fajr", time: prayers.Fajr },
                { name: "Dhuhr", time: prayers.Dhuhr },
                { name: "Asr", time: prayers.Asr },
                { name: "Maghrib", time: prayers.Maghrib },
                { name: "Isha", time: prayers.Isha }
            ].filter(p => p.time);

            let nextPrayerName = "";
            let minutesRemaining = 0;

            for (let i = 0; i < trackingList.length; i++) {
                const tTime = trackingList[i].time.split(' ')[0].split(':');
                const tMins = parseInt(tTime[0], 10) * 60 + parseInt(tTime[1], 10);
                if (tMins > currentMinutes) {
                    nextPrayerName = trackingList[i].name;
                    minutesRemaining = tMins - currentMinutes;
                    break;
                }
            }

            if (!nextPrayerName && trackingList.length > 0) {
                const fTime = trackingList[0].time.split(' ')[0].split(':');
                const fMins = parseInt(fTime[0], 10) * 60 + parseInt(fTime[1], 10);
                nextPrayerName = trackingList[0].name;
                minutesRemaining = (24 * 60) - currentMinutes + fMins;
            }

            const nameTarget = wrapper.querySelector(".niz-next-name");
            const clockTarget = wrapper.querySelector(".niz-countdown-clock");

            if (nameTarget) nameTarget.textContent = nextPrayerName || "—";
            if (clockTarget) clockTarget.innerHTML = `in <strong>${formatMinutes(minutesRemaining)}</strong>`;
        }

        updateClock();
        // Save the interval to the wrapper so it can be overwritten if location changes
        wrapper.clockInterval = setInterval(updateClock, 30000);
    }

    // 4. DEBOUNCER: Waits for all cookies to set before calling API
    let syncTimeout = null;
    window.nizSyncGeoDisplays = function() {
        if (syncTimeout) clearTimeout(syncTimeout);
        syncTimeout = setTimeout(executeSyncCall, 150); // 150ms buffer prevents 429 API Spam
    };

    function executeSyncCall() {
        console.log("[MFA] Sync Display Triggered");

        let lat = getGeoCookie('latitude');
        let lng = getGeoCookie('longitude');
        let city = getGeoCookie('city');
        let country = getGeoCookie('country');

        if (!lat || !lng) {
            console.log("[MFA] No cookies found. Falling back to default: Kuala Lumpur");
            lat = DEFAULT_LAT;
            lng = DEFAULT_LNG;
            country = DEFAULT_COUNTRY;
            city = DEFAULT_CITY;
        }

        const locationString = (city && country) ? `${city}, ${country}` : (city || country || 'Location Found');
        document.querySelectorAll('.js-niz-prayer-geo, .niz-loc-target').forEach(el => {
            el.textContent = locationString;
        });

        const rLat = parseFloat(lat).toFixed(2);
        const rLng = parseFloat(lng).toFixed(2);
        const method = getPrayerMethod(country);
        
        const d = new Date();
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();

        const cacheKey = `mfa_prayers_${rLat}_${rLng}_${day}_${month}_${year}`;
        let cachedData = null;

        try {
            cachedData = JSON.parse(sessionStorage.getItem(cacheKey));
            if (!cachedData || !cachedData.Fajr) cachedData = null; 
        } catch(e) {
            sessionStorage.removeItem(cacheKey);
        }

        if (cachedData) {
            console.log("[MFA] Loading times from browser cache");
            applyPrayerData(cachedData);
        } else {
            console.log(`[MFA] Fetching Aladhan API for Lat: ${rLat}, Lng: ${rLng}`);
            const apiUrl = `https://api.aladhan.com/v1/timings/${day}-${month}-${year}?latitude=${rLat}&longitude=${rLng}&method=${method}`;
            
            fetch(apiUrl)
                .then(res => res.json())
                .then(payload => {
                    if (payload && payload.data && payload.data.timings) {
                        console.log("[MFA] API Fetch Success!");
                        const t = payload.data.timings;
                        const pData = { Fajr: t.Fajr, Sunrise: t.Sunrise, Dhuhr: t.Dhuhr, Asr: t.Asr, Maghrib: t.Maghrib, Isha: t.Isha };
                        sessionStorage.setItem(cacheKey, JSON.stringify(pData));
                        applyPrayerData(pData);
                    }
                })
                .catch(err => console.error('[MFA] Aladhan fetch error:', err));
        }
    }

    function applyPrayerData(prayerData) {
        document.querySelectorAll('.niz-mfa-prayer-times-wrapper').forEach(wrapper => {
            wrapper.setAttribute('data-prayers', JSON.stringify(prayerData));
            startClockEngine(wrapper, prayerData);
        });
    }

    // Initialize on DOM ready safely
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.nizSyncGeoDisplays);
    } else {
        window.nizSyncGeoDisplays();
    }

})();