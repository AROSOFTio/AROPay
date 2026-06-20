/**
 * AROPay Admin JavaScript
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

/* global jQuery, aropay_admin */
(function ($) {
    'use strict';

    var AROPayAdmin = {

        init: function () {
            this.bindConfirmActions();
            this.bindCopyButtons();
            this.initLogPreviews();
            this.bindMerchantSearch();
            this.bindSettlementRefInput();
        },

        /**
         * Confirm destructive actions.
         */
        bindConfirmActions: function () {
            $(document).on('submit', '.aropay-confirm-form', function (e) {
                var msg = $(this).data('confirm') || 'Are you sure?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
            });

            $(document).on('click', '.aropay-confirm-btn', function (e) {
                var msg = $(this).data('confirm') || 'Are you sure?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
            });
        },

        /**
         * Copy-to-clipboard for callback/IPN URLs.
         */
        bindCopyButtons: function () {
            $(document).on('click', '.aropay-copy-btn', function (e) {
                e.preventDefault();
                var text = $(this).data('copy') || $(this).prev('code').text();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function () {
                        AROPayAdmin.flashMessage('Copied!', 'success');
                    });
                } else {
                    var el = document.createElement('textarea');
                    el.value = text;
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                    AROPayAdmin.flashMessage('Copied!', 'success');
                }
            });
        },

        /**
         * Expand log response body on row click.
         */
        initLogPreviews: function () {
            $(document).on('click', '.aropay-log-preview', function () {
                var $td = $(this).closest('td');
                if ($td.hasClass('expanded')) {
                    $td.removeClass('expanded');
                    $td.find('.aropay-log-full').remove();
                } else {
                    $td.addClass('expanded');
                    var full = $td.data('full') || $(this).text();
                    $td.append('<div class="aropay-log-full"><pre>' + $('<div>').text(full).html() + '</pre></div>');
                }
            });
        },

        /**
         * Live merchant search in merchants table.
         */
        bindMerchantSearch: function () {
            var $input = $('#aropay-merchant-search');
            if (!$input.length) return;

            $input.on('input', function () {
                var q = $(this).val().toLowerCase();
                $('table.aropay-table tbody tr').each(function () {
                    var text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(q) > -1);
                });
            });
        },

        /**
         * Show settlement ref input only when "Mark Settled" is clicked.
         */
        bindSettlementRefInput: function () {
            $(document).on('click', '.aropay-mark-settled-btn', function () {
                $(this).closest('form').find('.aropay-ref-input').toggle();
            });
        },

        /**
         * Show a transient flash message.
         */
        flashMessage: function (msg, type) {
            var cls = 'notice-' + (type || 'info');
            var $el = $('<div class="notice ' + cls + ' is-dismissible aropay-flash"><p>' + msg + '</p></div>');
            $('.wrap.aropay-admin-wrap h1').after($el);
            setTimeout(function () { $el.fadeOut(400, function () { $(this).remove(); }); }, 2500);
        }
    };

    $(document).ready(function () {
        AROPayAdmin.init();
    });

}(jQuery));
