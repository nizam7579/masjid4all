// assets/js/qibla.js - High-Performance Qibla Finder
(function() {
    const KAABA_LAT = 21.422487;
    const KAABA_LNG = 39.826206;

    function getGeoCookie(name) {
        const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? decodeURIComponent(v[2]).replace(/-/g, ' ') : null;
    }

    class QiblaFinder {
        constructor(scope) {
            this.scope = scope;
            this.userLat = null;
            this.userLng = null;
            this.buffer = [];
            this.BUFFER_SIZE = 4;
            this.lastRenderedDiff = 0;
            this.animationFrameId = null;
            this.hasAbsoluteSupport = false;
            this.boundProcessOrientation = null;
            this.useManualMode = false;
            this.manualAngle = 0;
            this.isDragging = false;
            this.startX = 0;
            this.startAngle = 0;
            this._lastRenderedDeviation = null;

            this.statusBox = scope.querySelector(".niz-status-box");
            this.actionBtn = scope.querySelector(".niz-btn");
            this.wrapperBtn = scope.querySelector(".niz-button-wrap");
            this.arrowNode = scope.querySelector(".niz-arrow-container");
            this.circleNode = scope.querySelector(".niz-circle");

            this.init();
        }

        init() {
            if (this.actionBtn) {
                this.actionBtn.addEventListener("click", () => this.startCompassPipeline());
            }
            if (this.circleNode) {
                this.circleNode.addEventListener('touchstart', (e) => this.handleTouchStart(e), {passive: false});
                this.circleNode.addEventListener('touchmove', (e) => this.handleTouchMove(e), {passive: false});
                this.circleNode.addEventListener('touchend', () => this.handleTouchEnd());

                this.circleNode.addEventListener('mousedown', (e) => this.handleMouseDown(e));
                this.circleNode.addEventListener('mousemove', (e) => this.handleMouseMove(e));
                this.circleNode.addEventListener('mouseup', () => this.handleMouseUp());
                this.circleNode.addEventListener('mouseleave', () => this.handleMouseUp());
            }
        }

        handleTouchStart(e) {
            if (!this.useManualMode) return;
            e.preventDefault();
            this.isDragging = true;
            this.startX = e.touches[0].clientX;
            this.startAngle = this.manualAngle;
        }

        handleTouchMove(e) {
            if (!this.isDragging || !this.useManualMode) return;
            e.preventDefault();
            const dx = e.touches[0].clientX - this.startX;
            this.manualAngle = this.startAngle + (dx * 0.5);
            this.updateArrow();
        }

        handleTouchEnd() { this.isDragging = false; }

        handleMouseDown(e) {
            if (!this.useManualMode) return;
            e.preventDefault();
            this.isDragging = true;
            this.startX = e.clientX;
            this.startAngle = this.manualAngle;
        }

        handleMouseMove(e) {
            if (!this.isDragging || !this.useManualMode) return;
            e.preventDefault();
            const dx = e.clientX - this.startX;
            this.manualAngle = this.startAngle + (dx * 0.5);
            this.updateArrow();
        }

        handleMouseUp() { this.isDragging = false; }

        calculateQiblaBearing(lat, lng) {
            const radians = d => d * Math.PI / 180;
            const degrees = r => r * 180 / Math.PI;

            const lat1 = radians(lat);
            const lon1 = radians(lng);
            const lat2 = radians(KAABA_LAT);
            const lon2 = radians(KAABA_LNG);

            const dLon = lon2 - lon1;
            const y = Math.sin(dLon) * Math.cos(lat2);
            const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);
            return (degrees(Math.atan2(y, x)) + 360) % 360;
        }

        normalizeDegree(deg) {
            deg = deg % 360;
            return deg < 0 ? deg + 360 : deg;
        }

        getSmoothedHeading(rawHeading) {
            this.buffer.push(rawHeading);
            if (this.buffer.length > this.BUFFER_SIZE) this.buffer.shift();

            let sin = 0, cos = 0;
            this.buffer.forEach(angle => {
                let rad = angle * Math.PI / 180;
                sin += Math.sin(rad);
                cos += Math.cos(rad);
            });
            return this.normalizeDegree(Math.atan2(sin, cos) * 180 / Math.PI);
        }

        updateArrow() {
            if (!this.arrowNode) return;
            let rotation = this.useManualMode ? this.manualAngle : this.lastRenderedDiff;
            this.arrowNode.style.transform = `translate3d(0,0,0) rotate(${Math.round(rotation)}deg)`;
        }

        renderLoop() {
            this.updateArrow();
            this.animationFrameId = requestAnimationFrame(() => this.renderLoop());
        }

        processOrientation(e) {
            if (this.useManualMode) return;

            let heading = null;

            if (e.webkitCompassHeading !== undefined && e.webkitCompassHeading !== null) {
                heading = e.webkitCompassHeading;
                this.hasAbsoluteSupport = true;
            } else if (e.type === 'deviceorientationabsolute' && e.alpha !== null) {
                heading = this.normalizeDegree(360 - e.alpha);
                this.hasAbsoluteSupport = true;
            } else if (e.type === 'deviceorientation' && !this.hasAbsoluteSupport && e.alpha !== null) {
                heading = this.normalizeDegree(360 - e.alpha);
            }

            if (heading === null || isNaN(heading)) return;

            const smoothHeading = this.getSmoothedHeading(heading);

            if (this.userLat !== null && this.userLng !== null) {
                let rotationAngle = 0;
                if (typeof window.orientation !== "undefined") {
                    rotationAngle = window.orientation || 0;
                } else if (window.screen?.orientation?.angle !== undefined) {
                    rotationAngle = window.screen.orientation.angle || 0;
                }

                const adjustedHeading = this.normalizeDegree(smoothHeading + rotationAngle);
                const qiblaTargetBearing = this.calculateQiblaBearing(this.userLat, this.userLng);

                this.lastRenderedDiff = this.normalizeDegree(qiblaTargetBearing - adjustedHeading);
                const deviation = Math.min(this.lastRenderedDiff, 360 - this.lastRenderedDiff);

                if (this.statusBox) {
                    if (deviation < 4) {
                        this.statusBox.textContent = "✅ Locked on Qibla";
                        this.statusBox.style.background = "#006B3E";
                        this.statusBox.style.color = "#ffffff";
                    } else {
                        const currentRoundedDev = Math.round(deviation);
                        if (currentRoundedDev !== this._lastRenderedDeviation) {
                            this.statusBox.textContent = `🧭 ${deviation.toFixed(1)}° away - Rotate device`;
                            this.statusBox.style.background = "#e8f5e9";
                            this.statusBox.style.color = "#333333";
                            this._lastRenderedDeviation = currentRoundedDev;
                        }
                    }
                }
            }
        }

        async startCompassPipeline() {
            if (this.statusBox) this.statusBox.textContent = "🔒 Requesting sensors...";
            if (this.wrapperBtn) this.wrapperBtn.style.display = "none";

            await this.fetchDeviceCoordinates();

            this.boundProcessOrientation = (e) => this.processOrientation(e);
            let sensorsStarted = false;

            const sensorPromise = new Promise((resolve) => {
                const testHandler = (e) => {
                    window.removeEventListener('deviceorientation', testHandler);
                    window.removeEventListener('deviceorientationabsolute', testHandler);
                    sensorsStarted = true;
                    resolve(true);
                };

                window.addEventListener('deviceorientation', testHandler, { once: false });
                window.addEventListener('deviceorientationabsolute', testHandler, { once: false });

                setTimeout(() => {
                    window.removeEventListener('deviceorientation', testHandler);
                    window.removeEventListener('deviceorientationabsolute', testHandler);
                    if (!sensorsStarted) resolve(false);
                }, 2000);
            });

            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                try {
                    const permission = await DeviceOrientationEvent.requestPermission();
                    if (permission !== 'granted') {
                        if (this.statusBox) {
                            this.statusBox.textContent = "❌ Permission denied";
                            this.statusBox.style.background = "#ffebee";
                        }
                        if (this.wrapperBtn) this.wrapperBtn.style.display = "block";
                        return;
                    }
                } catch (err) {
                    console.error('iOS permission error:', err);
                }
            }

            window.addEventListener("deviceorientationabsolute", this.boundProcessOrientation, true);
            window.addEventListener("deviceorientation", this.boundProcessOrientation, true);

            const result = await sensorPromise;

            if (!result) {
                this.useManualMode = true;
                if (this.userLat && this.userLng) {
                    const qiblaBearing = this.calculateQiblaBearing(this.userLat, this.userLng);
                    this.manualAngle = qiblaBearing;

                    if (this.statusBox) {
                        this.statusBox.innerHTML = `⚠️ Auto-rotate unavailable<br>↔️ <strong>Drag the circle</strong> to align arrow<br>Qibla is at <strong>${qiblaBearing.toFixed(1)}°</strong>`;
                        this.statusBox.style.background = "#fff3e0";
                        this.statusBox.style.color = "#e65100";
                    }
                    if (this.circleNode) this.circleNode.style.cursor = 'grab';
                }
            } else {
                if (this.statusBox) {
                    this.statusBox.textContent = "🧭 Sensors active - rotate device";
                    this.statusBox.style.background = "#e8f5e9";
                    this.statusBox.style.color = "#333333";
                }
            }

            if (!this.animationFrameId) {
                this.renderLoop();
            }
        }

        fetchDeviceCoordinates() {
            return new Promise((resolve) => {
                let cLat = getGeoCookie('latitude');
                let cLng = getGeoCookie('longitude');

                if (cLat && cLng) {
                    this.userLat = parseFloat(cLat);
                    this.userLng = parseFloat(cLng);
                    return resolve();
                }

                this.userLat = 3.14;
                this.userLng = 101.69;
                resolve();
            });
        }
    }

    function initQiblaFinders() {
        const qiblaApps = document.querySelectorAll('.niz-qibla-app');
        qiblaApps.forEach(scope => {
            if (!scope.classList.contains('niz-initialized')) {
                scope.classList.add('niz-initialized');
                new QiblaFinder(scope);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQiblaFinders);
    } else {
        initQiblaFinders();
    }
})();
