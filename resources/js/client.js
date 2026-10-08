/**
 * WPToolkit HTTP client — a classic script with no build step (works on WordPress 6.4+).
 *
 * Calls routes declared with the PHP Router over REST or admin-ajax, with the right nonce, and
 * turns error responses into ToolkitError carrying the server's status, code, message and details.
 *
 * Coexistence (ADR-0005, ADR-0006): this file may be loaded by several plugins, at several
 * versions. It never replaces anything: it registers a factory under its own version in
 * window.wptoolkit.__clients, and each plugin creates its own client from its own config:
 *
 *     window.wptoolkit["my-plugin"].api.call("books_search", { q: "dune" })
 *
 * @license GPL-2.0-or-later
 */
(function (root) {
    'use strict';

    var VERSION = '1.0.0-dev';

    // The shared namespace is locked: no script can replace it; entries stay writable.
    if (!Object.prototype.hasOwnProperty.call(root, 'wptoolkit')) {
        Object.defineProperty(root, 'wptoolkit', { value: {}, writable: false, configurable: false, enumerable: true });
    }
    var namespace = root.wptoolkit;
    namespace.__clients = namespace.__clients || {};
    if (namespace.__clients[VERSION]) {
        return;
    }

    function ToolkitError(status, code, message, details) {
        var error = new Error(message);
        error.name = 'ToolkitError';
        error.status = status;
        error.code = code;
        error.details = details || {};
        return error;
    }

    function encodeForm(data) {
        var parts = [];
        Object.keys(data).forEach(function (key) {
            var value = data[key];
            if (Array.isArray(value)) {
                value.forEach(function (item) {
                    parts.push(encodeURIComponent(key + '[]') + '=' + encodeURIComponent(item));
                });
            } else if (value !== undefined && value !== null) {
                parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
            }
        });
        return parts.join('&');
    }

    function createClient(config, fetchImpl) {
        var doFetch = fetchImpl || root.fetch.bind(root);
        var routes = config.routes || {};

        function parse(response) {
            if (response.status === 204) {
                return Promise.resolve(null);
            }
            return response.text().then(function (text) {
                var body = null;
                try {
                    body = text === '' ? null : JSON.parse(text);
                } catch (ignore) {
                    body = null;
                }
                if (!response.ok) {
                    var code = body && body.code ? body.code : 'http_' + response.status;
                    var message = body && body.message ? body.message : 'Request failed with status ' + response.status;
                    throw new ToolkitError(response.status, code, message, body && body.details);
                }
                return body;
            });
        }

        function viaRest(route, data, method) {
            var path = route.path.replace(/\{([a-z_][a-z0-9_]*)\}/gi, function (match, name) {
                var value = data[name];
                delete data[name];
                return encodeURIComponent(value === undefined ? '' : value);
            });
            var url = config.restUrl.replace(/\/$/, '') + '/' + route.namespace + '/' + path;
            var init = { method: method, credentials: 'same-origin', headers: { 'X-WP-Nonce': config.restNonce, Accept: 'application/json' } };

            if (method === 'GET' || method === 'HEAD') {
                var query = encodeForm(data);
                url += query ? (url.indexOf('?') === -1 ? '?' : '&') + query : '';
            } else {
                init.headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(data);
            }
            return doFetch(url, init).then(parse);
        }

        function viaAjax(route, data, method) {
            var payload = Object.assign({ action: route.action }, data);
            if (route.nonce) {
                payload._wpnonce = route.nonce;
            }
            var init = { method: method === 'GET' ? 'GET' : 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' } };
            var url = config.ajaxUrl;

            if (init.method === 'GET') {
                url += (url.indexOf('?') === -1 ? '?' : '&') + encodeForm(payload);
            } else {
                init.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
                init.body = encodeForm(payload);
            }
            return doFetch(url, init).then(parse);
        }

        return {
            version: VERSION,

            /**
             * Call a named route. Resolves with the response data, rejects with ToolkitError.
             */
            call: function (name, data, options) {
                var route = routes[name];
                if (!route) {
                    return Promise.reject(new ToolkitError(0, 'unknown_route', 'Unknown route "' + name + '"'));
                }
                var method = (options && options.method) || route.methods[0] || 'GET';
                var payload = Object.assign({}, data || {});

                return route.transport === 'ajax' ? viaAjax(route, payload, method) : viaRest(route, payload, method);
            },

            ToolkitError: ToolkitError
        };
    }

    namespace.__clients[VERSION] = createClient;
})(typeof window !== 'undefined' ? window : globalThis);
