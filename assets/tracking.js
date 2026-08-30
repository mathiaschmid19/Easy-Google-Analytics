(function () {
    'use strict';

    if (typeof easyGA4TrackingConfig === 'undefined') {
        return;
    }

    var config = easyGA4TrackingConfig;

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    function consentGranted() {
        return getCookie('easygoogleanalytics_consent') === 'granted';
    }

    function fireEvent(name, params) {
        if (typeof window.gtag !== 'function' || !consentGranted()) {
            return;
        }
        window.gtag('event', name, params);
    }

    function getExtension(url) {
        try {
            var pathname = new URL(url, window.location.href).pathname;
            var match = pathname.match(/\.([a-zA-Z0-9]+)$/);
            return match ? match[1].toLowerCase() : null;
        } catch (e) {
            return null;
        }
    }

    if (config.outbound || config.downloads) {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (!link) {
                return;
            }

            var url;
            try {
                url = new URL(link.href, window.location.href);
            } catch (e) {
                return;
            }

            var isOutbound = url.hostname !== window.location.hostname;

            if (config.downloads) {
                var ext = getExtension(link.href);
                if (ext && config.downloadExtensions.indexOf(ext) !== -1) {
                    fireEvent('file_download', {
                        file_extension: ext,
                        link_url: link.href
                    });
                    return;
                }
            }

            if (config.outbound && isOutbound) {
                fireEvent('click', {
                    link_url: link.href,
                    link_domain: url.hostname,
                    outbound: true
                });
            }
        });
    }

    if (config.scroll) {
        var milestones = [25, 50, 75, 90];
        var fired = {};
        var ticking = false;

        function checkScroll() {
            ticking = false;
            var scrollTop = window.scrollY || document.documentElement.scrollTop;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            if (docHeight <= 0) {
                return;
            }
            var percent = (scrollTop / docHeight) * 100;

            milestones.forEach(function (milestone) {
                if (!fired[milestone] && percent >= milestone) {
                    fired[milestone] = true;
                    fireEvent('scroll', { percent_scrolled: milestone });
                }
            });
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(checkScroll);
                ticking = true;
            }
        });
    }

    if (config.forms) {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || form.tagName !== 'FORM') {
                return;
            }
            fireEvent('form_submit', { form_id: form.id || null });
        });
    }
})();
