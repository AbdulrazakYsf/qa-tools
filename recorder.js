(function () {
    console.log("ðŸŽ¥ Tool Studio Recorder Active - CORS Bypass Mode");

    if (window.__recorderInjected) return;
    window.__recorderInjected = true;

    // Determine Proxy Endpoint (relative to current location)
    // If we are at /QA-TOOLS/proxy.php, the endpoint is same.
    const PROXY_ENDPOINT = 'proxy.php';

    // Helper: Is this a URL we should proxy? (i.e. not local)
    function shouldProxy(url) {
        if (!url) return false;
        if (url.toString().indexOf('proxy.php') !== -1) return false; // Already proxied
        if (url.toString().startsWith('data:')) return false;
        if (url.toString().startsWith('blob:')) return false;
        return true;
        // Ideally we proxy everything external.
    }

    function createProxyUrl(targetUrl) {
        // Resolve relative URLs against the document base (which is effectively the target site due to <base>)
        // But wait, if we are in proxy.php, document.baseURI might be the target.
        // Let's resolve absolute first.
        const resolved = new URL(targetUrl, document.baseURI).href;
        return PROXY_ENDPOINT + '?url=' + encodeURIComponent(resolved) + '&mode=native';
    }

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

        // Normalize
        let url = resource;
        if (resource instanceof Request) {
            url = resource.url;
        }

        // Capture Original Request Data
        const method = (config && config.method) ? config.method.toUpperCase() : 'GET';
        const body = (config && config.body) ? config.body : null;
        const headers = (config && config.headers) ? config.headers : {};

        // REWRITE URL TO PROXY
        if (shouldProxy(url)) {
            // We must rewrite the arguments to point to proxy
            // And we need to pass headers/method to proxy? 
            // My simple proxy.php primarily handles GET. I need to upgrade proxy.php to handle full forwarding.
            // For now, let's assume we update proxy.php.

            // Note: If we proxy, the Origin header sent to Jarir will be our server.
            // Jarir CORS might allow server-to-server.

            const proxyUrl = createProxyUrl(url);

            // We need to send original headers via some mechanism if proxy needs them?
            // Actually, fetch() to proxy.php is Same-Origin, so headers are loose.
            // But proxy.php needs to forward them to Jarir.
            // Simple proxy approach: pass everything as is to proxy.php.

            if (resource instanceof Request) {
                // Recreate request
                args[0] = new Request(proxyUrl, {
                    method: method,
                    headers: headers,
                    body: body,
                    credentials: resource.credentials,
                    mode: 'cors'
                });
            } else {
                args[0] = proxyUrl;
            }
        }

        const startTime = Date.now();

        try {
            const response = await originalFetch.apply(this, args);

            // Clone response to read body
            const clone = response.clone();
            let resBody = null;
            try {
                resBody = await clone.text();
                try { resBody = JSON.parse(resBody); } catch (e) { }
            } catch (e) { }

            // Notify (Log the ORIGINAL URL, not proxy url)
            notifyParent('api-call', {
                type: 'fetch',
                url: url.toString(), // Log the real URL
                method: method,
                requestHeaders: headers,
                requestBody: body,
                status: response.status,
                responseBody: resBody,
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
            originalUrl: url,
            method: method,
            requestHeaders: {}
        };

        // Rewrite URL
        if (shouldProxy(url)) {
            const proxyUrl = createProxyUrl(url);
            // Call open with proxy URL
            return originalOpen.call(this, method, proxyUrl, ...rest);
        }

        return originalOpen.apply(this, arguments);
    };

    // Capture headers
    const originalSetHeader = XMLHttpRequest.prototype.setRequestHeader;
    XMLHttpRequest.prototype.setRequestHeader = function (key, val) {
        if (this._reqData) {
            this._reqData.requestHeaders[key] = val;
        }
        originalSetHeader.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function (body) {
        const self = this;
        const startTime = Date.now();
        if (this._reqData) this._reqData.body = body;

        this.addEventListener('loadend', function () {
            let resBody = self.response;
            try {
                if (self.responseType === '' || self.responseType === 'text') {
                    try { resBody = JSON.parse(self.responseText); } catch (e) { resBody = self.responseText; }
                }
            } catch (e) { }

            notifyParent('api-call', {
                type: 'xhr',
                url: self._reqData ? self._reqData.originalUrl : 'unknown',
                method: self._reqData ? self._reqData.method : 'GET',
                requestHeaders: self._reqData ? self._reqData.requestHeaders : {},
                requestBody: self._reqData ? self._reqData.body : null,
                status: self.status,
                responseBody: resBody,
                duration: Date.now() - startTime
            });
        });

        return originalSend.apply(this, arguments);
    };

})();
