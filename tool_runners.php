<?php
require_once 'auth_session.php';

/* 
  Tool Runners Library
  Centralized logic for executing QA tools server-side.
*/

class ToolRunner
{

    // Helper: Make HTTP Request
    private static function request($method, $url, $headers = [], $body = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 mins per request max
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For simplicity, though usually bad practice

        // Default Headers
        $defaultHeaders = [
            'User-Agent: QA-Dashboard/2.0',
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $mergedHeaders);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
            }
        } elseif ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        return [
            'ok' => ($httpCode >= 200 && $httpCode < 300),
            'status' => $httpCode,
            'data' => $json ?? $response,
            'error' => $error
        ];
    }

    /* ---------- 1. Add to Cart ---------- */
    public static function run_add_to_cart($input)
    {
        $results = [];
        $mode = $input['mode'] ?? 'guest';
        $countries = $input['countries'] ?? []; // ['SA', 'AE'...]
        $skus = $input['skus'] ?? []; // [{sku, qty}...]
        $loginJson = $input['loginJson'] ?? null; // {username, password}

        $COUNTRY_MAP = [
            'SA' => ['store' => 'sa_en', 'code' => 'SA'],
            'AE' => ['store' => 'ae_en', 'code' => 'AE'],
            'KW' => ['store' => 'kw_en', 'code' => 'KW'],
            'QA' => ['store' => 'qa_en', 'code' => 'QA'],
            'BH' => ['store' => 'bh_en', 'code' => 'BH']
        ];

        foreach ($countries as $country) {
            $map = $COUNTRY_MAP[$country] ?? null;
            if (!$map) {
                $results[] = ['country' => $country, 'status' => 'ERROR', 'message' => 'Unknown Country'];
                continue;
            }

            $store = $map['store'];
            $code = $map['code'];
            $token = null;
            $quoteId = null;

            // 1. Auth / Guest Quote
            if ($mode === 'loggedin') {
                $url = "https://www.jarir.com/api/v2/$store/user/login-v2";
                $res = self::request('POST', $url, [], $loginJson);
                if ($res['ok'] && ($res['data']['success'] ?? false)) {
                    $token = $res['data']['data']['token'] ?? null;
                    $quoteId = $res['data']['data']['quote_id'] ?? null;
                    // $results[] = ['country' => $country, 'status' => 'INFO', 'message' => 'Logged in'];
                } else {
                    $results[] = ['country' => $country, 'store' => $store, 'status' => 'ERROR', 'message' => 'Login Failed', 'details' => $res['data']];
                    continue;
                }
            } else {
                // Guest
                $url = "https://www.jarir.com/api/v2/$store/cart/createv2";
                $res = self::request('POST', $url);
                if ($res['ok']) {
                    $quoteId = $res['data']['data']['result'] ?? null;
                    // $results[] = ['country' => $country, 'status' => 'INFO', 'message' => 'Guest Quote Created'];
                } else {
                    $results[] = ['country' => $country, 'store' => $store, 'status' => 'ERROR', 'message' => 'Guest Init Failed', 'details' => $res['data']];
                    continue;
                }
            }

            // 2. Add to Cart
            if (!$quoteId) {
                $results[] = ['country' => $country, 'store' => $store, 'status' => 'ERROR', 'message' => 'No Quote ID'];
                continue;
            }

            $url = "https://www.jarir.com/api/v2/$store/cart/updateMultiple";
            $headers = [];
            if ($token)
                $headers[] = "currenttoken: $token";

            $body = [
                'cartItem' => [
                    'skus' => $skus, // Ensure proper format [{sku:.., qty:..}]
                    'quoteId' => $quoteId,
                    'extension_attributes' => ['country_code' => $code]
                ]
            ];

            $cartRes = self::request('POST', $url, $headers, $body);

            $status = ($cartRes['ok'] && ($cartRes['data']['success'] ?? false)) ? 'OK' : 'ERROR';
            $msg = $cartRes['data']['message'] ?? ($status == 'OK' ? 'Added to Cart' : 'Failed');

            $results[] = [
                'country' => $country,
                'store' => $store,
                'status' => $status,
                'message' => $msg,
                'details' => $cartRes['data'],
                'url' => "Quote: $quoteId"
            ];
        }

        return $results;
    }

    /* ---------- 2. Brand Link Checker ---------- */
    public static function run_brand($input)
    {
        // Input: 'parents' (array of strings)
        $parents = $input['parents'] ?? [];
        $results = [];

        foreach ($parents as $parentUrl) {
            // Fetch Parent
            $res = self::request('GET', $parentUrl);
            if (!$res['ok']) {
                $results[] = ['parent' => $parentUrl, 'status' => 'ERROR', 'message' => 'Failed to fetch parent'];
                continue;
            }

            // Extract Brands (CMS Items logic)
            $brands = self::extract_brands_from_cms($res['data']);
            $uniqueBrands = array_unique($brands);

            // Construct Links
            // Logic: split /api/, get store from 5th part...
            // Ex: https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/123
            $parts = explode('/api/', $parentUrl);
            $baseUrl1 = $parts[0] . '/api/';
            $pathParts = explode('/', $parentUrl);
            $store = str_replace('_', '-', $pathParts[5] ?? 'sa-en'); // heuristic

            foreach ($uniqueBrands as $brand) {
                $link = "{$baseUrl1}catalogv1/product/store/{$store}/brand/{$brand}/size/20/sort-priority/asc/visibilityAll/true";

                // Verify Link
                $check = self::request('GET', $link);
                $ok = false;

                // Check if hits > 0
                if (isset($check['data']['hits']) && count($check['data']['hits']) > 0) {
                    $ok = true;
                }

                $results[] = [
                    'parent' => $parentUrl,
                    'url' => $link,
                    'status' => $ok ? 'OK' : 'WARN',
                    'message' => $ok ? 'Hits found' : 'No hits',
                    'payload' => ['hits' => count($check['data']['hits'] ?? [])]
                ];
            }
        }
        return $results;
    }

    private static function extract_brands_from_cms($data)
    {
        $items = $data['cms_items']['items'] ?? ($data['data']['cms_items']['items'] ?? []);
        $found = [];

        foreach ($items as $obj) {
            if (!isset($obj['item']) || !is_string($obj['item'])) continue;
            
            $entries = explode('||', $obj['item']);
            foreach ($entries as $entry) {
                // entry format: type,key,value... e.g. "type,brand,Samsung"
                $parts = explode(',', $entry);
                // Check for 'brand' at index 1
                if (isset($parts[1]) && $parts[1] === 'brand' && !empty($parts[2])) {
                    $found[] = $parts[2];
                }
            }
        }
        return $found;
    }
    /* ---------- 3. CMS Block Checker ---------- */
    public static function run_cms($input)
    {
        $parents = $input['parents'] ?? [];
        $results = [];

        foreach ($parents as $parentUrl) {
            $res = self::request('GET', $parentUrl);
            if (!$res['ok']) {
                $results[] = ['parent' => $parentUrl, 'link' => $parentUrl, 'status' => 'ERROR', 'message' => 'Fetch failed'];
                continue;
            }

            $cmsCodes = self::parse_cms_tokens($res['data'], 'cms');

            // Base URL: up to last slash
            // e.g. .../page-v2/123 -> .../page-v2/
            $base = substr($parentUrl, 0, strrpos($parentUrl, '/') + 1);

            if (empty($cmsCodes)) {
                $results[] = ['parent' => $parentUrl, 'link' => $parentUrl, 'status' => 'WARN', 'message' => 'No CMS blocks found'];
            }

            foreach ($cmsCodes as $code) {
                $link = $base . $code;

                // Validate Child
                $check = self::request('GET', $link);
                $isOk = false;
                if ($check['ok']) {
                    $d = $check['data'];
                    $hasItems = isset($d['data']['cms_items']['items']) && count($d['data']['cms_items']['items']) > 0;
                    // JS: ok = (data===null) || hasItems
                    $isNull = ($d['data'] ?? 'notnull') === null;
                    if ($isNull || $hasItems)
                        $isOk = true;
                }

                $results[] = [
                    'parent' => $parentUrl,
                    'link' => $link,
                    'url' => $link,
                    'status' => $isOk ? 'OK' : 'WARN',
                    'message' => $isOk ? 'Valid' : 'Invalid/Empty'
                ];
            }
        }
        return $results;
    }

    /* ---------- 4. Products / SKU Checker ---------- */
    public static function run_products($input)
    {
        $parents = $input['parents'] ?? [];
        $results = [];

        foreach ($parents as $parentUrl) {
            $res = self::request('GET', $parentUrl);
            if (!$res['ok']) {
                $results[] = ['parent' => $parentUrl, 'link' => '-', 'status' => 'ERROR', 'message' => 'Fetch failed'];
                continue;
            }

            $items = self::parse_product_tokens($res['data']);

            // Derive Base & Store
            // JS: url.split('/api')[0]+'/api/'
            $parts = explode('/api', $parentUrl);
            $base = $parts[0] . '/api'; // no trailing slash here, added later? JS code adds it: +'/api/'
            // JS: base=...+'/api/'
            // PHP: $base = $parts[0] . '/api/'

            // Store: 5th segment
            $pathParts = explode('/', $parentUrl);
            // https://.../v2/sa_en/... -> 0:https, 1, 2:www.., 3:api, 4:v2, 5:sa_en
            $store = str_replace('_', '-', $pathParts[5] ?? 'sa-en');

            if (!$base || !$store) {
                $results[] = ['parent' => $parentUrl, 'link' => '-', 'status' => 'ERROR', 'message' => 'URL parse error'];
                continue;
            }

            foreach ($items as $item) {
                $list = $item['list'];
                if (empty($list))
                    continue;

                $uniqueList = array_unique($list);
                $uniqueCount = count($uniqueList);
                $totalCount = count($list);

                // Construct Link
                if ($item['type'] === 'multi') {
                    $skuSegment = implode(',', $list);
                    $link = "{$base}/catalogv2/product/store/{$store}/sku/{$skuSegment}";
                } else {
                    $link = "{$base}/catalogv1/product/store/{$store}/sku/" . $list[0];
                }

                // Verify
                $check = self::request('GET', $link);
                $hitsCount = 0;
                $status = 'Error';

                if ($check['ok']) {
                    $d = $check['data'];
                    $hits = $d['hits']['hits'] ?? ($d['data']['hits']['hits'] ?? []);
                    $hitsCount = count($hits);

                    if ($hitsCount === 0)
                        $status = 'Warning Plus';
                    elseif ($hitsCount === $uniqueCount)
                        $status = 'OK';
                    else
                        $status = 'Warning';
                }

                $results[] = [
                    'parent' => $parentUrl,
                    'link' => $link,
                    'url' => $link,
                    'totalCount' => $totalCount,
                    'uniqueCount' => $uniqueCount,
                    'hitsCount' => $hitsCount,
                    'status' => $status
                ];
            }
        }
        return $results;
    }

    /* Helpers */
    private static function parse_cms_tokens($json, $targetType)
    {
        $items = $json['data']['cms_items']['items'] ?? [];
        $found = [];
        foreach ($items as $obj) {
            $raw = $obj['item'] ?? '';
            // item format: "type,val||type2,val2"
            $parts = explode('||', $raw);
            foreach ($parts as $p) {
                $tokens = explode(',', $p);
                // tokens: [0]=>id?, [1]=>type, [2]=>value
                if (($tokens[1] ?? '') === $targetType && !empty($tokens[2])) {
                    $found[] = $tokens[2];
                }
            }
        }
        return $found;
    }

    private static function parse_product_tokens($json)
    {
        $items = $json['data']['cms_items']['items'] ?? [];
        $result = [];
        foreach ($items as $obj) {
            $raw = $obj['item'] ?? '';
            $tokens = explode('||', $raw);
            // iterate tokens
            for ($i = 0; $i < count($tokens); $i++) {
                $t = $tokens[$i];
                if ($t === 'product' && isset($tokens[$i + 1])) {
                    $result[] = ['type' => 'single', 'list' => [$tokens[$i + 1]]];
                    $i++;
                } elseif ($t === 'products' && isset($tokens[$i + 1])) {
                    $skus = array_filter(array_map('trim', explode(',', $tokens[$i + 1])));
                    if ($skus)
                        $result[] = ['type' => 'multi', 'list' => $skus];
                    $i++;
                }
            }
        }
        return $result;
    }

    /* ---------- 5. Category Link Checker ---------- */
    public static function run_category($input)
    {
        $parents = $input['parents'] ?? [];
        $results = [];

        foreach ($parents as $parentUrl) {
            $res = self::request('GET', $parentUrl);
            if (!$res['ok']) {
                $results[] = ['parent' => $parentUrl, 'link' => '-', 'status' => 'ERROR', 'message' => 'Fetch failed'];
                continue;
            }

            $catIds = self::extract_category_ids($res['data']);

            // Base/Store
            $parts = explode('/api', $parentUrl);
            $base = $parts[0] . '/api';
            $pathParts = explode('/', $parentUrl);
            $store = str_replace('_', '-', $pathParts[5] ?? 'sa-en');

            foreach ($catIds as $id) {
                $link = "{$base}/catalogv1/category/store/{$store}/category_ids/{$id}";
                $isNumeric = is_numeric($id);

                // Verify
                $check = self::request('GET', $link);
                $status = 'Error';
                if ($check['ok']) {
                    $d = $check['data'];
                    $hits = $d['hits']['hits'] ?? ($d['data']['hits']['hits'] ?? []);
                    $status = (count($hits) > 0) ? 'OK' : 'Warning';
                }

                $results[] = [
                    'parent' => $parentUrl,
                    'link' => $link,
                    'status' => $status,
                    'isNumeric' => $isNumeric
                ];
            }
        }
        return $results;
    }

    private static function extract_category_ids($json)
    {
        $items = $json['data']['cms_items']['items'] ?? [];
        $found = [];
        foreach ($items as $obj) {
            $raw = $obj['item'] ?? '';
            $tokens = explode('||', $raw);

            // New Style
            // JS: Loop tokens. If tok=='category', parts[i+1].split('|')
            for ($i = 0; $i < count($tokens); $i++) {
                if (strtolower(trim($tokens[$i])) === 'category' && isset($tokens[$i + 1])) {
                    $ids = explode('|', $tokens[$i + 1]);
                    foreach ($ids as $id)
                        if (trim($id))
                            $found[] = trim($id);
                }
            }
            // Legacy segments: "foo,category,ids"
            foreach ($tokens as $seg) {
                $parts = explode(',', $seg);
                $idx = array_search('category', $parts);
                if ($idx !== false && isset($parts[$idx + 1])) {
                    $ids = explode('|', $parts[$idx + 1]);
                    foreach ($ids as $id)
                        if (trim($id))
                            $found[] = trim($id);
                }
            }
        }
        return array_unique($found);
    }

    /* ---------- 6. Single SKU Checker ---------- */
    public static function run_sku($input)
    {
        // Similar to products but filtering only single type
        $parents = $input['parents'] ?? [];
        $results = [];

        foreach ($parents as $parentUrl) {
            $res = self::request('GET', $parentUrl);
            if (!$res['ok']) {
                $results[] = ['parent' => $parentUrl, 'link' => $parentUrl, 'status' => 'ERROR', 'message' => 'Fetch failed'];
                continue;
            }

            $skus = self::extract_single_skus($res['data']);

            if (empty($skus)) {
                $results[] = ['link' => 'No SKU found', 'parent' => $parentUrl, 'status' => 'Warning'];
                continue;
            }

            // Base/Store
            $parts = explode('/api', $parentUrl);
            $base = $parts[0] . '/api';
            $pathParts = explode('/', $parentUrl);
            $store = str_replace('_', '-', $pathParts[5] ?? 'sa-en');

            foreach ($skus as $sku) {
                $link = "{$base}/catalogv1/product/store/{$store}/sku/{$sku}";

                // Verify
                $check = self::request('GET', $link);
                $status = 'Error';
                if ($check['ok']) {
                    $d = $check['data'];
                    $hits = $d['hits']['hits'] ?? ($d['data']['hits']['hits'] ?? []);
                    $status = (count($hits) > 0) ? 'OK' : 'Warning';
                }

                $results[] = [
                    'link' => $link,
                    'parent' => $parentUrl,
                    'status' => $status
                ];
            }
        }
        return $results;
    }

    private static function extract_single_skus($json)
    {
        $items = $json['data']['cms_items']['items'] ?? [];
        $out = [];
        foreach ($items as $obj) {
            $raw = $obj['item'] ?? '';
            $entries = explode('||', $raw);
            foreach ($entries as $entry) {
                $parts = explode(',', $entry);
                if (($parts[1] ?? '') === 'product' && !empty($parts[2])) {
                    $out[] = trim($parts[2]);
                }
            }
        }
        return array_unique($out);
    }

    /* ---------- 7. Stock / Availability Checker ---------- */
    public static function run_stock($input)
    {
        $parents = $input['parents'] ?? [];
        $results = [];

        foreach ($parents as $parentUrl) {
            $res = self::request('GET', $parentUrl);
            if (!$res['ok']) {
                $results[] = ['parent' => $parentUrl, 'sku' => '-', 'link' => '-', 'status' => 'Error', 'availRaw' => 'Fetch Error'];
                continue;
            }

            // Extract SKUs (extended logic)
            $skus = self::extract_skus_extended($res['data']);

            if (empty($skus)) {
                // No products found
                continue;
            }

            // Base/Store
            $parts = explode('/api', $parentUrl);
            $root = $parts[0];
            $base = $root . '/api/';
            $baseV2 = $root . '/api/v2/';

            $pathParts = explode('/', $parentUrl);
            $storeHyp = str_replace('_', '-', $pathParts[5] ?? 'sa-en');
            $storeUnd = str_replace('-', '_', $storeHyp);

            foreach ($skus as $sku) {
                $productLink = "{$base}catalogv1/product/store/{$storeHyp}/sku/{$sku}";

                // Stock URL
                // JS: ${baseV2}${store}_/stock/getavailability?skuData=${sku}|1|0&customer_group=
                $stockUrl = "{$baseV2}{$storeUnd}/stock/getavailability?skuData=" . urlencode($sku) . "|1|0&customer_group=";

                $sRes = self::request('GET', $stockUrl);
                $statusLabel = 'Error';
                $availRaw = '';

                if ($sRes['ok']) {
                    $d = $sRes['data'];
                    $rec = ($d['data']['result'][0] ?? null);
                    $availRaw = strtoupper(trim($rec['stock_availablity'] ?? ''));

                    switch ($availRaw) {
                        case 'AVAILABLE':
                        case 'IN_STOCK':
                        case 'IN STOCK':
                        case 'PREORDER':
                        case 'BACKORDER':
                            $statusLabel = 'In Stock';
                            break;
                        case 'OUTOFSTOCK':
                        case 'OUT_OF_STOCK':
                        case 'OUT OF STOCK':
                            $statusLabel = 'Out of Stock';
                            break;
                        default:
                            $statusLabel = 'Error';
                    }
                }

                $results[] = [
                    'sku' => $sku,
                    'link' => $productLink,
                    'parent' => $parentUrl,
                    'status' => $statusLabel,
                    'availRaw' => $availRaw
                ];
            }
        }
        return $results;
    }

    private static function extract_skus_extended($json)
    {
        $items = $json['data']['cms_items']['items'] ?? [];
        $out = [];
        foreach ($items as $obj) {
            $raw = $obj['item'] ?? '';
            $tokens = explode('||', $raw);

            for ($i = 0; $i < count($tokens); $i++) {
                $t = strtolower(trim($tokens[$i]));
                // Product/Products
                if ($t === 'product' && isset($tokens[$i + 1])) {
                    $s = explode(',', $tokens[$i + 1])[0];
                    if (trim($s))
                        $out[] = trim($s);
                    $i++;
                } elseif ($t === 'products' && isset($tokens[$i + 1])) {
                    $parts = explode(',', $tokens[$i + 1]);
                    foreach ($parts as $p)
                        if (trim($p))
                            $out[] = trim($p);
                    $i++;
                }
                // Collection/Category hack
                elseif ($t === 'category') {
                    // Check +2
                    $csv = $tokens[$i + 2] ?? '';
                    if ($csv && preg_match('/[0-9,]/', $csv)) {
                        $parts = explode(',', $csv);
                        foreach ($parts as $p)
                            if (trim($p))
                                $out[] = trim($p);
                    }
                }
            }
        }
        return array_unique($out);
    }
}
?>