(function () {
    'use strict';

    if (typeof egaAdminNotice === 'undefined') {
        return;
    }

    document.addEventListener('click', function (event) {
        var notice = event.target.closest('.ega-config-notice');
        if (!notice) {
            return;
        }

        var isDismissButton = event.target.closest('.notice-dismiss');
        if (!isDismissButton) {
            return;
        }

        var body = new URLSearchParams();
        body.append('action', egaAdminNotice.action);
        body.append('nonce', egaAdminNotice.nonce);

        fetch(egaAdminNotice.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
    });
})();
