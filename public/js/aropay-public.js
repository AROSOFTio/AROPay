/**
 * AROPay Public JavaScript
 * Handles phone network auto-detection and MoMo payment status polling.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

/* global jQuery, aropay_params */
(function ($) {
    'use strict';

    /* ── Network detection map (Uganda prefixes after country code 256) ── */
    var MTN_PREFIXES    = ['77', '78', '76', '39', '31'];
    var AIRTEL_PREFIXES = ['70', '75', '74', '20'];

    var AROPayPublic = {

        pollTimer:    null,
        pollInterval: 5000,
        pollTimeout:  120000,
        elapsed:      0,

        init: function () {
            this.bindPhoneInput();
            this.initPendingScreen();
        },

        /* ── Phone input & network detection ──────────────────────── */

        bindPhoneInput: function () {
            $(document).on('input change', '#aropay_phone', function () {
                AROPayPublic.detectNetwork($(this).val());
            });
        },

        detectNetwork: function (phone) {
            var digits = phone.replace(/\D/g, '');

            /* Normalise to 9-digit local number */
            if (digits.startsWith('256')) {
                digits = digits.slice(3);
            } else if (digits.startsWith('0')) {
                digits = digits.slice(1);
            }

            var prefix = digits.slice(0, 2);
            var network = null;

            if (MTN_PREFIXES.indexOf(prefix) > -1) {
                network = 'mtn';
            } else if (AIRTEL_PREFIXES.indexOf(prefix) > -1) {
                network = 'airtel';
            }

            this.updateNetworkBadge(network);
            this.updateHiddenNetwork(network);
        },

        updateNetworkBadge: function (network) {
            var $badge = $('#aropay-network-badge');
            $badge.removeClass('mtn airtel').text('');

            if (network === 'mtn') {
                $badge.addClass('mtn').text('MTN MoMo');
            } else if (network === 'airtel') {
                $badge.addClass('airtel').text('Airtel Money');
            }
        },

        updateHiddenNetwork: function (network) {
            /* If there's a hidden network input (auto-detect mode) update it */
            var $hidden = $('input[name="aropay_network_detected"]');
            if (!$hidden.length) {
                $hidden = $('<input type="hidden" name="aropay_network">');
                $('#aropay-yo-fields').append($hidden);
            }
            if (network) {
                $hidden.attr('name', 'aropay_network').val(network);
            }
        },

        /* ── Pending payment screen polling ──────────────────────── */

        initPendingScreen: function () {
            var $wrap = $('#aropay-payment-pending');
            if (!$wrap.length) return;

            var ref = $wrap.data('ref');
            if (!ref) return;

            this.elapsed = 0;
            this.updateProgress(0);
            this.poll(ref);
            this.pollTimer = setInterval(function () {
                AROPayPublic.elapsed += AROPayPublic.pollInterval;
                AROPayPublic.updateProgress(AROPayPublic.elapsed);

                if (AROPayPublic.elapsed >= AROPayPublic.pollTimeout) {
                    clearInterval(AROPayPublic.pollTimer);
                    AROPayPublic.setTimerText(
                        (aropay_params && aropay_params.i18n && aropay_params.i18n.timeout)
                            ? aropay_params.i18n.timeout
                            : 'Payment timed out. Please go back and try again.',
                        true
                    );
                    return;
                }

                AROPayPublic.poll(ref);
            }, this.pollInterval);
        },

        poll: function (ref) {
            var restUrl = (aropay_params && aropay_params.rest_url)
                ? aropay_params.rest_url + 'transaction-status/' + encodeURIComponent(ref)
                : '/wp-json/aropay/v1/transaction-status/' + encodeURIComponent(ref);

            $.ajax({
                url:      restUrl,
                method:   'GET',
                dataType: 'json',
                success:  function (data) {
                    if (!data) return;

                    if (data.status === 'completed' && data.redirect) {
                        clearInterval(AROPayPublic.pollTimer);
                        AROPayPublic.setTimerText('✓ Payment confirmed! Redirecting…');
                        AROPayPublic.updateProgress(AROPayPublic.pollTimeout);
                        window.location.href = data.redirect;

                    } else if (data.status === 'failed') {
                        clearInterval(AROPayPublic.pollTimer);
                        AROPayPublic.setTimerText('✗ Payment failed. Please go back and try again.', true);
                    }
                },
                error: function () {
                    /* Network error — keep polling silently */
                }
            });
        },

        updateProgress: function (elapsed) {
            var pct       = Math.min(100, (elapsed / this.pollTimeout) * 100);
            var remaining = Math.max(0, Math.floor((this.pollTimeout - elapsed) / 1000));

            $('#aropay-progress-fill').css('width', pct + '%');

            if (remaining > 0) {
                var waiting = (aropay_params && aropay_params.i18n && aropay_params.i18n.processing)
                    ? aropay_params.i18n.processing
                    : 'Processing payment…';
                $('#aropay-pending-timer').text(waiting + ' (' + remaining + 's remaining)');
            }
        },

        setTimerText: function (msg, isError) {
            var $el = $('#aropay-pending-timer');
            $el.text(msg);
            if (isError) {
                $el.addClass('aropay-error');
                $('#aropay-progress-fill').css('background', '#e74c3c');
            }
        }
    };

    $(document).ready(function () {
        AROPayPublic.init();
    });

}(jQuery));
