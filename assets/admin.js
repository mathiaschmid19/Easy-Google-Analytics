/**
 * Easy Google Analytics - Modern Admin Script
 */
(function ($) {
  'use strict';

  // Relocate WordPress notices below the header banner (.ega-notices-container)
  function relocateNotices() {
    const $container = $('.ega-notices-container');
    if (!$container.length) return;

    const $notices = $('.ega-admin-wrap .notice, .ega-admin-wrap .updated, .ega-admin-wrap .error, .ega-admin-wrap .settings-error');
    $notices.each(function () {
      if (!$(this).parent().is('.ega-notices-container')) {
        $(this).appendTo($container);
      }
    });
  }

  relocateNotices();

  $(document).ready(function () {
    relocateNotices();
    setTimeout(relocateNotices, 50);
    setTimeout(relocateNotices, 200);

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

    // Tab switching
    const $tabTriggerSettings = $('#ega-tab-trigger-settings');
    const $tabTriggerDesign = $('#ega-tab-trigger-design');
    const $tabPanelSettings = $('#ega-tab-panel-settings');
    const $tabPanelDesign = $('#ega-tab-panel-design');

    function activateTab(name) {
      const isSettings = name === 'settings';
      $tabTriggerSettings.toggleClass('is-active', isSettings).attr('aria-selected', isSettings ? 'true' : 'false');
      $tabTriggerDesign.toggleClass('is-active', !isSettings).attr('aria-selected', !isSettings ? 'true' : 'false');
      $tabPanelSettings.prop('hidden', !isSettings);
      $tabPanelDesign.prop('hidden', isSettings);
    }

    $tabTriggerSettings.on('click', function () {
      activateTab('settings');
    });
    $tabTriggerDesign.on('click', function () {
      activateTab('design');
    });

    // Banner Design live preview
    if (typeof egaBannerDesign !== 'undefined') {
      const $bgColor = $('#ega-banner-bg-color');
      const $textColor = $('#ega-banner-text-color');
      const $acceptColor = $('#ega-banner-accept-color');
      const $rejectColor = $('#ega-banner-reject-color');
      const $paletteInput = $('#ega-banner-palette-input');
      const $layoutRadios = $('input[name="for_you_google_analytics_banner_layout"]');
      const $message = $('#ega-banner-message');
      const $acceptLabel = $('#ega-banner-accept-label');
      const $rejectLabel = $('#ega-banner-reject-label');
      const $previewBanner = $('#ega-design-preview-banner');
      const $previewMessage = $('#ega-design-preview-message');
      const $previewReject = $('#ega-design-preview-reject');
      const $previewAccept = $('#ega-design-preview-accept');

      function currentRejectStyle() {
        const paletteKey = $paletteInput.val();
        const preset = egaBannerDesign.palettes[paletteKey];
        return preset ? preset.reject_style : 'outline';
      }

      function syncPreview() {
        $previewBanner.css({
          background: $bgColor.val(),
          color: $textColor.val()
        });
        $previewAccept.css({ background: $acceptColor.val(), color: '#ffffff' });

        const rejectStyle = currentRejectStyle();
        $previewBanner.attr('data-reject-style', rejectStyle);
        if (rejectStyle === 'filled') {
          $previewReject.css({ background: $rejectColor.val(), color: $textColor.val(), border: 'none' });
        } else {
          $previewReject.css({ background: 'transparent', color: $rejectColor.val(), border: '1px solid ' + $rejectColor.val() });
        }

        $previewBanner.removeClass('ega-layout-bar ega-layout-corner');
        $previewBanner.addClass('ega-layout-' + $layoutRadios.filter(':checked').val());

        $previewMessage.text($message.val().trim() !== '' ? $message.val() : egaBannerDesign.defaults.message);
        $previewAccept.text($acceptLabel.val().trim() !== '' ? $acceptLabel.val() : egaBannerDesign.defaults.acceptLabel);
        $previewReject.text($rejectLabel.val().trim() !== '' ? $rejectLabel.val() : egaBannerDesign.defaults.rejectLabel);
      }

      $bgColor.add($textColor).add($acceptColor).add($rejectColor).add($message).add($acceptLabel).add($rejectLabel).on('input', syncPreview);
      $layoutRadios.on('change', syncPreview);

      // Manually editing a color field after a preset was selected means the
      // colors no longer match that preset — switch the hidden palette input
      // to 'custom' so save doesn't silently re-apply the last-clicked preset
      // and discard the manual edit, and clear the swatch active state so the
      // UI doesn't keep showing a preset as selected.
      $bgColor.add($textColor).add($acceptColor).add($rejectColor).on('input', function () {
        $paletteInput.val('custom');
        $('.ega-palette-swatch').removeClass('is-active');
      });

      // #ega-banner-message is a <textarea>, not an <input>, so the generic
      // `form.ega-settings-form input` dirty-tracking selector never matches it.
      // Bind markFormDirty explicitly so editing this field marks the form dirty too.
      $message.on('input', markFormDirty);

      // Palette swatch selection
      $('.ega-palette-swatch').on('click', function () {
        const key = $(this).data('palette');
        const preset = egaBannerDesign.palettes[key];
        if (!preset) {
          return;
        }

        $('.ega-palette-swatch').removeClass('is-active');
        $(this).addClass('is-active');
        $paletteInput.val(key);

        $bgColor.val(preset.bg);
        $textColor.val(preset.text);
        $acceptColor.val(preset.accept);
        $rejectColor.val(preset.reject);

        syncPreview();
        markFormDirty();
      });

      syncPreview();
    }

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
