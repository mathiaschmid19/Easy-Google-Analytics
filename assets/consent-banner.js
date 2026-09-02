(function () {
    'use strict';

    var COOKIE_NAME = 'easygoogleanalytics_consent';

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    function setCookie(name, value) {
        var oneYear = 60 * 60 * 24 * 365;
        document.cookie = name + '=' + encodeURIComponent(value) + '; max-age=' + oneYear + '; path=/; SameSite=Lax';
    }

    function updateConsent(granted) {
        if (typeof window.gtag !== 'function') {
            return;
        }
        window.gtag('consent', 'update', {
            ad_storage: granted ? 'granted' : 'denied',
            analytics_storage: granted ? 'granted' : 'denied',
            ad_user_data: granted ? 'granted' : 'denied',
            ad_personalization: granted ? 'granted' : 'denied'
        });
    }

    function detectComplianz() {
        // Only trust the window.complianz global as a signal that Complianz is
        // actually active. cmplz_* cookies can persist long after Complianz has
        // been uninstalled (they carry long expiries), which previously caused
        // this function to keep returning true forever, permanently suppressing
        // the fallback banner with no way left to grant consent. Complianz, when
        // actually active, always defines this global on page load.
        return typeof window.complianz !== 'undefined';
    }

    function detectCookiebot() {
        return typeof window.Cookiebot !== 'undefined';
    }

    function bindComplianz() {
        document.addEventListener('cmplz_status_change', function () {
            var granted = getCookie('cmplz_statistics') === 'allow';
            updateConsent(granted);
        });
    }

    function bindCookiebot() {
        window.addEventListener('CookiebotOnAccept', function () {
            var granted = window.Cookiebot && window.Cookiebot.consent && window.Cookiebot.consent.statistics;
            updateConsent(!!granted);
        });
        window.addEventListener('CookiebotOnDecline', function () {
            updateConsent(false);
        });
    }

    function initFallbackBanner() {
        var banner = document.getElementById('ega-consent-banner');
        var manageBtn = document.getElementById('ega-consent-manage');
        var acceptBtn = document.getElementById('ega-consent-accept');
        var rejectBtn = document.getElementById('ega-consent-reject');

        if (!banner) {
            return;
        }

        function showManageButton() {
            banner.setAttribute('hidden', 'hidden');
            if (manageBtn) {
                manageBtn.removeAttribute('hidden');
            }
        }

        function showBanner() {
            if (manageBtn) {
                manageBtn.setAttribute('hidden', 'hidden');
            }
            banner.removeAttribute('hidden');
        }

        // Always bind manage button event listener
        if (manageBtn) {
            manageBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showBanner();
            });
        }

        // Always bind accept button event listener
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function (e) {
                e.preventDefault();
                setCookie(COOKIE_NAME, 'granted');
                updateConsent(true);
                showManageButton();
            });
        }

        // Always bind reject button event listener
        if (rejectBtn) {
            rejectBtn.addEventListener('click', function (e) {
                e.preventDefault();
                setCookie(COOKIE_NAME, 'denied');
                updateConsent(false);
                showManageButton();
            });
        }

        // Check existing consent cookie
        var existing = getCookie(COOKIE_NAME);
        if (existing === 'granted') {
            updateConsent(true);
            showManageButton();
        } else if (existing === 'denied') {
            updateConsent(false);
            showManageButton();
        } else {
            showBanner();
        }
    }

    function init() {
        if (detectComplianz()) {
            bindComplianz();
            return;
        }
        if (detectCookiebot()) {
            bindCookiebot();
            return;
        }
        initFallbackBanner();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
