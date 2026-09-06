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
        var tokens = {};

        for (var p in data) {
            if (!data.hasOwnProperty(p)) {
                continue;
            }
            if (p === 'token') {
                tokenField.value = data[p];
                tokens[tokenField.name] = null;
                continue;
            }

            var value = '', parts = String(data[p]).split('|');
            for (var i = 0; i < parts.length; ++i) {
                if (browserInfo.hasOwnProperty(parts[i])) {
                    value += browserInfo[parts[i]];
                }
            }

            var elm = document.createElement('input');
            elm.name = p;
            elm.value = value;
            elm.type = 'hidden';
            formElement.appendChild(elm);
            tokens[elm.name] = null;

            if ((' ' + formElement.className + ' ').indexOf(' mailpoet_form ') > -1) {
                elm.name = 'data[' + p + ']';
                tokenField.name = 'data[' + tokenField.name + ']';
            }
        }

        // Ninja Forms submits over jQuery AJAX with a payload it builds itself; merge the tokens in.
        if (window.jQuery && formElement.querySelector('.nf-element')) {
            window.jQuery.ajaxPrefilter(function (options, originalOptions) {
                if (!isObject(options) || !isObject(originalOptions) || !('data' in originalOptions) || !isObject(originalOptions.data)) {
                    return;
                }
                if (!('action' in originalOptions.data) || originalOptions.data.action !== 'nf_ajax_submit') {
                    return;
                }
                for (var token in tokens) {
                    if (!tokens.hasOwnProperty(token)) {
                        continue;
                    }
                    var field = formElement.querySelector('input[name="' + token + '"]');
                    tokens[token] = field ? field.value : null;
                }
                options.data = window.jQuery.param(window.jQuery.extend(originalOptions.data || {}, tokens));
            });
        }
    }

    function retrieveToken(formElement, isRetry) {
        var tokenField = formElement.querySelector('input[name="' + config.inputName + '"]');
        if (!tokenField) {
            return;
        }

        var data = {};
        data[config.inputName] = config.nonce;
        data.action = config.action;
        data.requestTime = (new Date()).getTime();
        data.browserInfo = JSON.stringify(browserInfo);

        post(data, function (status, rs) {
            if (status !== 200 || !rs) {
                return;
            }
            if (rs.success === false) {
                if (!isRetry && rs.data && rs.data.code === 'invalid_nonce') {
                    refreshNonce(function (ok) {
                        if (ok) {
                            retrieveToken(formElement, true);
                        }
                    });
                }
                return;
            }
            if (rs.data && typeof rs.data === 'object') {
                applyTokens(formElement, tokenField, rs.data);
            }
        });
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

    window.WPBruiserClient = {
        requestTokens: function () { requestTokens(document); }
    };

})(window, document);
