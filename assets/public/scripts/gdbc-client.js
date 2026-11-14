/**
 * WPBruiser client script.
 *
 * Requests an anti-spam token for every form that carries the plugin's hidden
 * field, and requests again on bfcache restore and for forms injected later by
 * AJAX (MutationObserver). Configuration is provided by wp_localize_script() as
 * window.WPBruiserConfig: { ajaxUrl, action, nonceAction, inputName, nonce }.
 *
 * Page caches keep serving the nonce that was embedded when the page was
 * rendered. Once it expires the token endpoint answers with code
 * "invalid_nonce"; the client then fetches a fresh nonce and retries once, so
 * cached pages keep working without any cache-busting tricks.
 *
 * Each token is good for one submission and for config.tokenMaxAge seconds. The
 * client therefore asks for a new token shortly after every submit (so a visitor
 * can retry after an error without reloading) and when a token gets old while the
 * page stays open. Those requests send the current token along so the server keeps
 * its age and the visitor is not rejected for submitting too soon.
 */
(function (window, document) {
    'use strict';

    if (!Array.isArray) {
        Array.isArray = function (arg) { return Object.prototype.toString.call(arg) === '[object Array]'; };
    }
    if (!String.prototype.trim) {
        String.prototype.trim = function () { return this.replace(/^[\s﻿\xA0]+|[\s﻿\xA0]+$/g, ''); };
    }

    var config = window.WPBruiserConfig || {};
    if (!config.ajaxUrl || !config.action || !config.nonceAction || !config.inputName) {
        return;
    }

    var browserInfo = {};

    // Ask for a new token once a third of its lifetime has passed.
    var refreshAfterMs = Math.max(60, parseInt(config.tokenMaxAge, 10) || 10800) * 1000 / 3;
    // Wait this long after a submit before replacing the token, so a form plugin that
    // reads its fields a moment after the submit event still sends the token it was
    // submitted with.
    var SUBMIT_REFRESH_DELAY_MS = 1500;

    // MailPoet forms carry the token field as data[name].
    function findTokenField(formElement) {
        return formElement.querySelector('input[name="' + config.inputName + '"]') ||
            formElement.querySelector('input[name="data[' + config.inputName + ']"]');
    }

    function collectBrowserInfo() {
        var w = window, d = document, e = 0, f = 0;
        e |= w.ActiveXObject ? 1 : 0;
        e |= w.opera ? 2 : 0;
        e |= w.chrome ? 4 : 0;
        e |= ('getBoxObjectFor' in d || 'mozInnerScreenX' in w) ? 8 : 0;
        e |= ('WebKitCSSMatrix' in w || 'WebKitPoint' in w || 'webkitStorageInfo' in w || 'webkitURL' in w) ? 16 : 0;
        e |= (e & 16 && ({}.toString).toString().indexOf("\n") === -1) ? 32 : 0;
        f |= 'sandbox' in d.createElement('iframe') ? 1 : 0;
        f |= 'WebSocket' in w ? 2 : 0;
        f |= w.Worker ? 4 : 0;
        f |= w.applicationCache ? 8 : 0;
        f |= (w.history && history.pushState) ? 16 : 0;
        f |= d.documentElement.webkitRequestFullScreen ? 32 : 0;
        f |= 'FileReader' in w ? 64 : 0;

        browserInfo = { screenWidth: screen.width, screenHeight: screen.height, engine: e, features: f };

        var ua = navigator.userAgent.toLowerCase();
        var regex = /compatible; ([\w.+]+)[ \/]([\w.+]*)|([\w .+]+)[: \/]([\w.+]+)|([\w.+]+)/g;
        var match = regex.exec(ua);
        while (match !== null) {
            var prop = {};
            if (match[1]) {
                prop.type = match[1];
                prop.version = match[2];
            } else if (match[3]) {
                prop.type = match[3];
                prop.version = match[4];
            } else {
                prop.type = match[5];
            }
            prop.type = (prop.type).trim().replace('.', '').replace(' ', '_');
            var value = prop.version ? prop.version : true;
            if (browserInfo[prop.type]) {
                if (!Array.isArray(browserInfo[prop.type])) {
                    browserInfo[prop.type] = [browserInfo[prop.type]];
                }
                browserInfo[prop.type].push(value);
            } else {
                browserInfo[prop.type] = value;
            }
            match = regex.exec(ua);
        }
    }

    function isObject(a) {
        return (!!a) && (a.constructor === Object);
    }

    function serialize(obj) {
        var str = [];
        for (var p in obj) {
            if (obj.hasOwnProperty(p)) {
                str.push(encodeURIComponent(p) + '=' + encodeURIComponent(obj[p]));
            }
        }
        return str.join('&');
    }

    function post(data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', config.ajaxUrl, true);
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }
            var rs = null;
            try {
                rs = JSON.parse(xhr.responseText);
            } catch (e) {
                rs = null;
            }
            callback(xhr.status, rs);
        };
        xhr.send(serialize(data));
    }

    function refreshNonce(callback) {
        post({ action: config.nonceAction }, function (status, rs) {
            if (status === 200 && rs && rs.success && rs.data && rs.data.nonce) {
                config.nonce = rs.data.nonce;
                callback(true);
            } else {
                callback(false);
            }
        });
    }

    function applyTokens(formElement, tokenField, data) {
        var isMailPoet = (' ' + formElement.className + ' ').indexOf(' mailpoet_form ') > -1;
        var names = [];

        // The browser field that came with the previous token is no longer needed, and
        // left in place it would end up in the form plugin's stored entry or e-mail.
        var stale = formElement.querySelectorAll('input[data-wpbruiser-field]');
        for (var s = 0; s < stale.length; ++s) {
            stale[s].parentNode.removeChild(stale[s]);
        }

        // MailPoet only sends fields named data[...].
        if (isMailPoet && tokenField.name === config.inputName) {
            var plain = formElement.querySelectorAll('input[name="' + config.inputName + '"]');
            for (var k = 0; k < plain.length; ++k) {
                plain[k].name = 'data[' + config.inputName + ']';
            }
        }

        for (var p in data) {
            if (!data.hasOwnProperty(p)) {
                continue;
            }
            if (p === 'token') {
                // Two integrations can print the field into the same form. The server
                // reads the last copy, so every copy gets the token.
                var copies = formElement.querySelectorAll('input[name="' + tokenField.name + '"]');
                for (var c = 0; c < copies.length; ++c) {
                    copies[c].value = data[p];
                }
                names.push(tokenField.name);
                continue;
            }

            var value = '', parts = String(data[p]).split('|');
            for (var i = 0; i < parts.length; ++i) {
                if (browserInfo.hasOwnProperty(parts[i])) {
                    value += browserInfo[parts[i]];
                }
            }

            var elm = document.createElement('input');
            elm.type = 'hidden';
            elm.name = isMailPoet ? 'data[' + p + ']' : p;
            elm.value = value;
            elm.setAttribute('data-wpbruiser-field', '1');
            formElement.appendChild(elm);
            names.push(elm.name);
        }

        formElement._wpbruiserNames = names;
        formElement._wpbruiserFetchedAt = new Date().getTime();

        // Ninja Forms submits over jQuery AJAX with a payload it builds itself; merge the
        // current tokens in. Registered once per form; it reads the fields at send time.
        if (window.jQuery && formElement.querySelector('.nf-element') && !formElement._wpbruiserPrefilter) {
            formElement._wpbruiserPrefilter = true;
            window.jQuery.ajaxPrefilter(function (options, originalOptions) {
                if (!isObject(options) || !isObject(originalOptions) || !('data' in originalOptions) || !isObject(originalOptions.data)) {
                    return;
                }
                if (!('action' in originalOptions.data) || originalOptions.data.action !== 'nf_ajax_submit') {
                    return;
                }
                var extra = {}, current = formElement._wpbruiserNames || [];
                for (var t = 0; t < current.length; ++t) {
                    var field = formElement.querySelector('input[name="' + current[t] + '"]');
                    if (field) {
                        extra[current[t]] = field.value;
                    }
                }
                options.data = window.jQuery.param(window.jQuery.extend(originalOptions.data || {}, extra));
            });
        }

        // Tell add-ons the form now carries a token. Forms that are not submitted as
        // HTML forms (the WooCommerce Checkout block posts JSON) forward these fields.
        // Sent again each time the token is replaced.
        var fields = {};
        for (var n = 0; n < names.length; ++n) {
            var input = formElement.querySelector('input[name="' + names[n] + '"]');
            if (input) {
                fields[names[n]] = input.value;
            }
        }
        var tokenEvent;
        try {
            tokenEvent = new CustomEvent('wpbruiser:token', { bubbles: true, detail: { fields: fields } });
        } catch (e) {
            tokenEvent = document.createEvent('CustomEvent');
            tokenEvent.initCustomEvent('wpbruiser:token', true, false, { fields: fields });
        }
        formElement.dispatchEvent(tokenEvent);

        addAdminNote(formElement);
    }

    // Administrators see a small note under every form that received a token.
    function addAdminNote(formElement) {
        if (!config.adminNote || formElement.hidden || formElement.querySelector('.wpbruiser-admin-note')) {
            return;
        }
        var note = document.createElement('p');
        note.className = 'wpbruiser-admin-note';
        note.textContent = '\u2713 ' + config.adminNote;
        note.style.cssText = 'margin:8px 0 0;font-size:12px;line-height:1.4;color:#50575e;';
        formElement.appendChild(note);
    }

    function retrieveToken(formElement, isRetry) {
        var tokenField = findTokenField(formElement);
        if (!tokenField) {
            return;
        }

        // One request per form at a time; the one in flight brings a fresh token anyway.
        if (formElement._wpbruiserPending && !isRetry) {
            return;
        }
        formElement._wpbruiserPending = true;

        var data = {};
        data[config.inputName] = config.nonce;
        data.action = config.action;
        data.requestTime = (new Date()).getTime();
        data.browserInfo = JSON.stringify(browserInfo);
        if (formElement._wpbruiserFetchedAt && tokenField.value) {
            data.previousToken = tokenField.value;
        }

        post(data, function (status, rs) {
            if (status === 200 && rs && rs.success === false && !isRetry && rs.data && rs.data.code === 'invalid_nonce') {
                refreshNonce(function (ok) {
                    if (ok) {
                        retrieveToken(formElement, true);
                    } else {
                        formElement._wpbruiserPending = false;
                    }
                });
                return;
            }

            formElement._wpbruiserPending = false;

            if (status !== 200 || !rs || rs.success === false) {
                return;
            }
            if (rs.data && typeof rs.data === 'object' && rs.data.token) {
                applyTokens(formElement, tokenField, rs.data);
            }
        });
    }

    // Tokens that have been around for a while are replaced before they expire.
    function refreshStaleTokens(formElement) {
        var forms = formElement ? [formElement] : document.forms;
        var now = new Date().getTime();
        for (var i = 0; i < forms.length; ++i) {
            if (forms[i]._wpbruiserFetchedAt && now - forms[i]._wpbruiserFetchedAt > refreshAfterMs) {
                retrieveToken(forms[i], false);
            }
        }
    }

    function watchTokenLifetime() {
        // A token is spent once the form is submitted. Fetch a new one so the visitor
        // can submit again (after a validation error, a failed payment, or a second
        // message) without reloading the page.
        document.addEventListener('submit', function (event) {
            var formElement = event.target;
            if (!formElement || formElement.tagName !== 'FORM' || !findTokenField(formElement)) {
                return;
            }
            window.setTimeout(function () {
                retrieveToken(formElement, false);
            }, SUBMIT_REFRESH_DELAY_MS);
        }, true);

        document.addEventListener('focusin', function (event) {
            if (event.target && event.target.form) {
                refreshStaleTokens(event.target.form);
            }
        }, true);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshStaleTokens(null);
            }
        }, false);

        // Also covers a laptop waking from sleep: the overdue timer fires on wake.
        window.setInterval(function () {
            refreshStaleTokens(null);
        }, 60000);
    }

    function requestTokens(root) {
        var forms;
        if (root && root.nodeType === 1 && root.tagName === 'FORM') {
            forms = [root];
        } else if (root && root.querySelectorAll) {
            forms = root.querySelectorAll('form');
        } else {
            forms = document.forms;
        }
        for (var i = 0; i < forms.length; ++i) {
            retrieveToken(forms[i], false);
        }
    }

    // Forms rendered later by AJAX (Ultimate Member, ZM Ajax Login, popups) get their token too.
    function observeInjectedForms() {
        if (typeof window.MutationObserver === 'undefined' || !document.body) {
            return;
        }
        var observer = new MutationObserver(function (mutations) {
            for (var m = 0; m < mutations.length; ++m) {
                var added = mutations[m].addedNodes;
                for (var n = 0; n < added.length; ++n) {
                    var node = added[n];
                    if (node.nodeType !== 1) {
                        continue;
                    }
                    if (node.tagName === 'FORM' || (node.querySelector && node.querySelector('form'))) {
                        requestTokens(node);
                    }
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    function start() {
        collectBrowserInfo();
        requestTokens(document);
        observeInjectedForms();
        watchTokenLifetime();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    // Pages restored from the back/forward cache carry tokens that may have expired.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            requestTokens(document);
        }
    }, false);

    // requestTokens() also replaces a form's spent token: add-ons call it after a
    // submission that does not fire a submit event (Ninja Forms, the WooCommerce
    // Checkout block, EDD's checkout login).
    window.WPBruiserClient = {
        requestTokens: function (root) { requestTokens(root || document); }
    };

})(window, document);
