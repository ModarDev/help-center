(function (global) {
    'use strict';

    function AjaxError(message, status, payload) {
        this.name = 'AjaxError';
        this.message = message || 'Request failed';
        this.status = typeof status === 'number' ? status : 0;
        this.payload = payload || null;
    }
    AjaxError.prototype = Object.create(Error.prototype);
    AjaxError.prototype.constructor = AjaxError;

    function toFormBody(payload) {
        var search = new URLSearchParams();
        Object.keys(payload || {}).forEach(function (key) {
            var value = payload[key];
            if (value === undefined || value === null) {
                return;
            }
            search.append(key, String(value));
        });
        return search.toString();
    }

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    async function request(url, options) {
        var opts = options || {};
        var headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest'
        }, opts.headers || {});

        var response = await fetch(url, {
            method: opts.method || 'GET',
            credentials: opts.credentials || 'same-origin',
            headers: headers,
            body: opts.body
        });

        var raw = await response.text();
        var data = parseJsonSafe(raw);

        if (!data) {
            throw new AjaxError('Server returned invalid JSON', response.status, { raw: raw });
        }

        if (!response.ok) {
            throw new AjaxError(data.message || 'HTTP request failed', response.status, data);
        }

        if (opts.requireSuccess !== false && data.success === false) {
            throw new AjaxError(data.message || 'Request was not successful', response.status, data);
        }

        return data;
    }

    function getJSON(url, options) {
        return request(url, Object.assign({}, options, { method: 'GET' }));
    }

    function postForm(url, payload, options) {
        var opts = Object.assign({}, options, {
            method: 'POST',
            headers: Object.assign({
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            }, options && options.headers ? options.headers : {}),
            body: toFormBody(payload || {})
        });

        return request(url, opts);
    }

    function postMultipart(url, formData, options) {
        var opts = Object.assign({}, options, {
            method: 'POST',
            body: formData
        });
        return request(url, opts);
    }

    global.AppAjax = {
        AjaxError: AjaxError,
        request: request,
        getJSON: getJSON,
        postForm: postForm,
        postMultipart: postMultipart
    };
})(window);
