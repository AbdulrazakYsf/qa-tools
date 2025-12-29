(function () {
    console.log("ðŸŽ¥ Tool Studio Recorder Active");

    if (window.__recorderInjected) return;
    window.__recorderInjected = true;

    // Helper to post message to parent
    function notifyParent(type, data) {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                source: 'tool-studio-recorder',
                type: type,
                payload: data
            }, '*');
        }
    }

    // 1. Monkey-Patch fetch
    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
        let [resource, config] = args;

        // Normalize URL
        let url = resource;
        if (resource instanceof Request) {
            url = resource.url;
        }

        // Capture Request
        const method = (config && config.method) ? config.method.toUpperCase() : 'GET';
        const body = (config && config.body) ? config.body : null;
        const headers = (config && config.headers) ? config.headers : {};

        const startTime = Date.now();

        try {
            const response = await originalFetch.apply(this, args);

            // Clone response to read body without consuming it
            const clone = response.clone();
            let resBody = null;
            try {
                resBody = await clone.text();
                // Try parsing JSON
                try { resBody = JSON.parse(resBody); } catch (e) { }
            } catch (e) { }

            notifyParent('api-call', {
                type: 'fetch',
                url: url.toString(),
                method: method,
                requestHeaders: headers,
                requestBody: body,
                status: response.status,
                responseBody: resBody, // Limit size in prod?
                duration: Date.now() - startTime
            });

            return response;
        } catch (err) {
            notifyParent('api-error', {
                type: 'fetch',
                url: url.toString(),
                method: method,
                error: err.toString()
            });
            throw err;
        }
    };

    // 2. Monkey-Patch XMLHttpRequest
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        this._reqData = {
            method: method,
            url: url,
            requestHeaders: {}
        };
        // Hook setRequestHeader to capture headers
        const originalSetHeader = this.setRequestHeader;
        this.setRequestHeader = function (key, val) {
            this._reqData.requestHeaders[key] = val;
            originalSetHeader.apply(this, arguments);
        };

        return originalOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function (body) {
        const self = this;
        const startTime = Date.now();
        this._reqData.body = body;

        this.addEventListener('loadend', function () {
            let resBody = self.response;
            try {
                if (self.responseType === '' || self.responseType === 'text') {
                    try { resBody = JSON.parse(self.responseText); } catch (e) { resBody = self.responseText; }
                }
            } catch (e) { }

            notifyParent('api-call', {
                type: 'xhr',
                url: self._reqData.url,
                method: self._reqData.method,
                requestHeaders: self._reqData.requestHeaders,
                requestBody: self._reqData.body,
                status: self.status,
                responseBody: resBody,
                duration: Date.now() - startTime
            });
        });

        return originalSend.apply(this, arguments);
    };

})();
