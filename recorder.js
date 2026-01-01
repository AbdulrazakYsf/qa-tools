(function () {
    console.log("ðŸŽ¥ Tool Studio Recorder Active - CORS Bypass Mode");

    if (window.__recorderInjected) return;
    window.__recorderInjected = true;

    // Send Heartbeat to Parent
    notifyParent('recorder-ready', { url: window.location.href });

    // Determine Proxy Endpoint (relative to current location)
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
        try {
            // Resolve relative URLs against the document base (which is effectively the target site due to <base>)
            // But wait, if we are in proxy.php, document.baseURI might be the target.
            // Let's resolve absolute first.
            const resolved = new URL(targetUrl, document.baseURI).href;
            return PROXY_ENDPOINT + '?url=' + encodeURIComponent(resolved) + '&mode=native';
        } catch (e) {
            return targetUrl;
        }
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

        // Log Raw Attempt
        notifyParent('debug', { msg: 'Fetch Attempt', url: url.toString() });

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
        notifyParent('debug', { msg: 'Fetch Start', url: url.toString() });

        try {
            const response = await originalFetch.apply(this, args);
            notifyParent('debug', { msg: 'Fetch Response', url: url.toString(), status: response.status });

            // Clone response to read body
            const clone = response.clone();

            // Read body and log ASYNCHRONOUSLY to avoid blocking the app
            (async () => {
                let resBody = null;
                let isReadable = true;
                try {
                    resBody = await clone.text();
                    try { resBody = JSON.parse(resBody); } catch (e) { }
                } catch (e) {
                    notifyParent('debug', { msg: 'Skipping API - Response Not Readable', url: url.toString(), error: e.toString() });
                    isReadable = false;
                }

                // Notify (Log the ORIGINAL URL, not proxy url)
                // Filter: Only Jarir APIs AND Readable Response
                // Resolve to absolute to catch relative paths that are actually Jarir APIs
                let fullUrl = url.toString();
                try {
                    fullUrl = new URL(url.toString(), document.baseURI).href;
                } catch (e) { }

                // Broaden filter: jarir.com, /api/, graphql, or .json (common in Magento/PWA)
                if (isReadable && (
                    fullUrl.includes('jarir.com') ||
                    fullUrl.includes('/api/') ||
                    fullUrl.includes('graphql') ||
                    fullUrl.includes('/rest/')
                )) {
                    notifyParent('api-call', {
                        type: 'fetch',
                        url: fullUrl,
                        method: method,
                        requestHeaders: headers,
                        requestBody: body,
                        status: response.status,
                        responseBody: resBody,
                        duration: Date.now() - startTime
                    });
                } else {
                    notifyParent('debug', { msg: 'Filtered Fetch', url: fullUrl });
                }
            })();

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
            let isReadable = true;
            try {
                if (self.responseType === '' || self.responseType === 'text') {
                    try { resBody = JSON.parse(self.responseText); } catch (e) { resBody = self.responseText; }
                }
            } catch (e) {
                notifyParent('debug', { msg: 'Skipping API - XHR Response Not Readable', url: (self._reqData ? self._reqData.originalUrl : 'unknown').toString(), error: e.toString() });
                isReadable = false;
            }

            // Filter: Only Jarir APIs AND Readable
            // Filter: Only Jarir APIs AND Readable
            const urlStr = (self._reqData ? self._reqData.originalUrl : 'unknown').toString();

            let fullUrl = urlStr;
            try {
                fullUrl = new URL(urlStr, document.baseURI).href;
            } catch (e) { }

            if (isReadable && (
                fullUrl.includes('jarir.com') ||
                fullUrl.includes('/api/') ||
                fullUrl.includes('graphql') ||
                fullUrl.includes('/rest/')
            )) {
                notifyParent('api-call', {
                    type: 'xhr',
                    url: fullUrl,
                    method: self._reqData ? self._reqData.method : 'GET',
                    requestHeaders: self._reqData ? self._reqData.requestHeaders : {},
                    requestBody: self._reqData ? self._reqData.body : null,
                    status: self.status,
                    responseBody: resBody,
                    duration: Date.now() - startTime
                });
            } else {
                notifyParent('debug', { msg: 'Filtered XHR', url: fullUrl });
            }
        });

        return originalSend.apply(this, arguments);
    };

    // 3. PerformanceObserver for Static Resources & Blocked Requests
    // This catches what Fetch/XHR interceptors miss (images, scripts, CSS, 403s on assets)
    try {
        const observer = new PerformanceObserver((list) => {
            list.getEntries().forEach((entry) => {
                // Filter out proxy.php calls itself to avoid noise
                if (entry.name.includes('proxy.php')) return;

                // We only care about things that look like external resources or APIs
                // that might have been missed by interceptors (e.g. Images, Scripts)
                // However, fetch/XHR also trigger performance entries.
                // We need to deduplicate or just log them as "Resource" type.
                // For the "Network Tab" UI, duplicate is okay if we match by time,
                // but let's just send everything and let UI handle it.

                notifyParent('api-call', {
                    type: 'resource',
                    url: entry.name,
                    method: 'GET', // Resources are usually GET
                    status: 0, // PerformanceObserver doesn't give status code (security)
                    duration: entry.duration,
                    initiatorType: entry.initiatorType,
                    transferSize: entry.transferSize || 0
                });
            });
        });
        observer.observe({ entryTypes: ['resource'] });
    } catch (e) {
        console.warn("PerformanceObserver not supported", e);
    }

    // Capture Global Errors
    window.onerror = function (msg, url, line) {
        notifyParent('debug', { msg: 'JS Error', error: msg, location: url + ':' + line });
    };

})();
