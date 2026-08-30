/**
 * Easy Google Analytics - Modern Admin Script
 */
(function ($) {
  'use strict';

  $(document).ready(function () {
    const $ga4Input = $('input[name="for_you_google_analytics_ga4_code"]');
    const $gtmInput = $('input[name="for_you_google_analytics_gtm_id"]');
    const $consentToggle = $('input[name="for_you_google_analytics_consent_banner_enabled"]');
    const $previewDrawer = $('#ega-consent-preview');
    const $saveBtn = $('#ega-save-btn');
    const $saveIndicator = $('#ega-save-indicator');
    let formDirty = false;

    // Auto-uppercase & real-time GA4 validation
    function validateGA4() {
      const val = $ga4Input.val().trim().toUpperCase();
      $ga4Input.val(val);
      const $msg = $('#ega-ga4-validation-msg');

      if (!val) {
        $ga4Input.removeClass('is-valid is-invalid');
        $msg.empty();
        return;
      }

      const ga4Regex = /^G-[A-Z0-9]+$/;
      if (ga4Regex.test(val)) {
        $ga4Input.removeClass('is-invalid').addClass('is-valid');
        $msg.html('<span class="valid">&#10003; Valid GA4 Measurement ID format</span>');
      } else {
        $ga4Input.removeClass('is-valid').addClass('is-invalid');
        $msg.html('<span class="error">&#9888; Must start with G- followed by letters or numbers</span>');
      }
    }

    // Auto-uppercase & real-time GTM validation
    function validateGTM() {
      const val = $gtmInput.val().trim().toUpperCase();
      $gtmInput.val(val);
      const $msg = $('#ega-gtm-validation-msg');

      if (!val) {
        $gtmInput.removeClass('is-valid is-invalid');
        $msg.empty();
        return;
      }

      const gtmRegex = /^GTM-[A-Z0-9]+$/;
      if (gtmRegex.test(val)) {
        $gtmInput.removeClass('is-invalid').addClass('is-valid');
        $msg.html('<span class="valid">&#10003; Valid GTM Container ID format</span>');
      } else {
        $gtmInput.removeClass('is-valid').addClass('is-invalid');
        $msg.html('<span class="error">&#9888; Must start with GTM- followed by letters or numbers</span>');
      }
    }

    $ga4Input.on('input', validateGA4);
    $gtmInput.on('input', validateGTM);

    // Initial validation check on load
    if ($ga4Input.val()) validateGA4();
    if ($gtmInput.val()) validateGTM();

    // Event tracking cards click handler
    $('.ega-event-item').on('click', function (e) {
      if ($(e.target).is('input[type="checkbox"]')) {
        return; // Allow native checkbox click
      }
      const $checkbox = $(this).find('input[type="checkbox"]');
      const isChecked = !$checkbox.prop('checked');
      $checkbox.prop('checked', isChecked).trigger('change');
    });

    // Update styling when event checkbox changes
    $('.ega-event-item input[type="checkbox"]').on('change', function () {
      const $card = $(this).closest('.ega-event-item');
      if ($(this).is(':checked')) {
        $card.addClass('is-selected');
      } else {
        $card.removeClass('is-selected');
      }
      markFormDirty();
    });

    // Consent Banner switch toggle sync
    $consentToggle.on('change', function () {
      const isChecked = $(this).is(':checked');
      const $wrapper = $(this).closest('.ega-toggle-wrapper');
      if (isChecked) {
        $wrapper.addClass('active');
        $previewDrawer.slideDown(200);
      } else {
        $wrapper.removeClass('active');
        $previewDrawer.slideUp(200);
      }
      markFormDirty();
    });

    // Mark form as dirty when any setting changes
    function markFormDirty() {
      if (!formDirty) {
        formDirty = true;
        $saveIndicator.html('<span style="color:#f59e0b;font-weight:600;">&#9679; You have unsaved changes</span>');
        $saveBtn.css('animation', 'pulse 1.5s infinite');
      }
    }

    $('form.ega-settings-form input').on('input change', function () {
      markFormDirty();
    });

    $('form.ega-settings-form').on('submit', function () {
      formDirty = false;
      $saveBtn.html('<span class="dashicons dashicons-update spin" style="margin-right:6px;"></span> Saving...');
    });

    // Show toast when settings updated
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('settings-updated') === 'true') {
      showToast('Settings saved successfully!');
    }

    function showToast(message) {
      let $toast = $('#ega-toast');
      if (!$toast.length) {
        $toast = $('<div id="ega-toast" class="ega-toast"></div>').appendTo('body');
      }
      $toast.html('<span style="color:#10b981;font-size:16px;">&#10003;</span> ' + message);
      $toast.addClass('show');
      setTimeout(function () {
        $toast.removeClass('show');
      }, 4000);
    }
  });
})(jQuery);
