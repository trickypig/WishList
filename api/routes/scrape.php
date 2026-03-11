<?php

function registerScrapeRoutes(Router $router, PDO $db): void
{
    // POST /scrape/url - fetch URL and extract product name, price, image
    $router->post('/scrape/url', function (array $params) use ($db) {
        // Suppress PHP warnings/notices so they don't corrupt JSON output
        error_reporting(0);
        ini_set('display_errors', '0');

        $user = authenticate();
        $body = $params['_body'];
        $startTime = microtime(true);

        if (empty($body['url'])) {
            Response::error('URL is required');
        }

        $url = $body['url'];
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            logScrape($db, $user['id'], $url, $host, null, null, 0, null, null, null, null, false, 'Invalid URL format', $startTime);
            Response::error('Invalid URL format');
        }

        // Only allow http/https
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            logScrape($db, $user['id'], $url, $host, null, null, 0, null, null, null, null, false, 'Non-HTTP scheme: ' . $scheme, $startTime);
            Response::error('Only HTTP/HTTPS URLs are allowed');
        }

        $isAmazon = (bool) preg_match('/amazon\.(com|co\.uk|ca|de|fr|it|es|co\.jp|com\.au|in)$/i', $host);

        $fetchResult = fetchUrl($url, $isAmazon);
        $html = $fetchResult['html'];
        $httpCode = $fetchResult['http_code'];

        if ($html === false) {
            logScrape($db, $user['id'], $url, $host, $httpCode, null, 0, null, null, null, null, false, 'Fetch failed: ' . $fetchResult['error'], $startTime);
            Response::error('Could not fetch URL', 422);
        }

        $rawLength = strlen($html);
        // Limit to first 500KB for parsing
        $html = substr($html, 0, 500000);

        $result = [
            'url' => $url,
            'name' => '',
            'price' => null,
            'image_url' => '',
            'store_name' => extractStoreName($url),
        ];

        // Use site-specific extractors first, then generic fallbacks
        if ($isAmazon) {
            $result = array_merge($result, extractAmazon($html, $url));
        } elseif (preg_match('/ebay\.(com|co\.uk|de|fr|it|es|com\.au)$/i', $host)) {
            $result = array_merge($result, extractEbay($html, $url));
        }

        // Fill in any blanks with generic extraction
        if (empty($result['name'])) {
            $result['name'] = extractProductName($html);
        }
        if ($result['price'] === null) {
            $result['price'] = extractPrice($html);
        }
        if (empty($result['image_url'])) {
            $result['image_url'] = extractImage($html, $url);
        }

        // Final cleanup: strip store name prefix/suffix from product name
        if (!empty($result['name'])) {
            $result['name'] = stripStoreName($result['name']);
        }

        // Log successful scrape
        logScrape(
            $db, $user['id'], $url, $host, $httpCode, $html, $rawLength,
            $result['name'], $result['price'], $result['image_url'], $result['store_name'],
            true, null, $startTime
        );

        Response::json($result);
    });
}

function logScrape(
    PDO $db, ?int $userId, string $url, string $host, ?int $httpCode,
    ?string $rawHtml, int $htmlLength, ?string $name, ?float $price,
    ?string $imageUrl, ?string $storeName, bool $success, ?string $error,
    float $startTime
): void {
    $durationMs = (int) round((microtime(true) - $startTime) * 1000);
    $now = date('Y-m-d H:i:s');

    try {
        $stmt = $db->prepare("INSERT INTO scrape_logs
            (user_id, url, host, http_code, raw_html, html_length, extracted_name, extracted_price,
             extracted_image_url, extracted_store_name, success, error_message, duration_ms, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $url,
            $host,
            $httpCode,
            $rawHtml,
            $htmlLength,
            $name ?? '',
            $price,
            $imageUrl ?? '',
            $storeName ?? '',
            $success ? 1 : 0,
            $error ?? '',
            $durationMs,
            $now,
        ]);
    } catch (\Exception $e) {
        // Silently fail — logging should never break the scrape response
    }
}

function stripStoreName(string $name): string
{
    // Strip "Amazon.com: Name" or "Amazon.com : Name" prefix
    $name = preg_replace('/^Amazon\.[a-z.]+\s*:\s*/i', '', $name);

    // Strip "StoreName: Name" for other known stores
    $name = preg_replace('/^(?:eBay|Walmart|Target|Best Buy|Etsy|Newegg)\s*[:|\-]\s*/i', '', $name);

    // Strip " - Amazon.com" or " | eBay" etc. suffix
    $name = preg_replace('/\s*[\-\|:]\s*(?:Amazon\.[a-z.]+|eBay|Walmart|Target|Best Buy|Etsy|Newegg).*$/i', '', $name);

    return trim($name);
}

function fetchUrl(string $url, bool $isAmazon = false): array
{
    $result = ['html' => false, 'http_code' => null, 'error' => ''];

    // Build headers that look more like a real browser
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Accept-Encoding: gzip, deflate, br',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
        'Sec-Ch-Ua-Mobile: ?0',
        'Sec-Ch-Ua-Platform: "Windows"',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1',
        'Upgrade-Insecure-Requests: 1',
    ];

    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    if (function_exists('curl_init')) {
        $ch = curl_init();

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => '',
            CURLOPT_COOKIEFILE => '',
            CURLOPT_REFERER => 'https://www.google.com/',
        ];

        if ($isAmazon) {
            $opts[CURLOPT_COOKIE] = 'session-id=000-0000000-0000000; i18n-prefs=USD';
        }

        curl_setopt_array($ch, $opts);
        $html = curl_exec($ch);
        $result['http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($html === false || $html === '') {
            $result['error'] = curl_error($ch) ?: 'Empty response';
            curl_close($ch);
            return $result;
        }
        curl_close($ch);
        $result['html'] = $html;
        return $result;
    }

    // Fallback to file_get_contents
    $headerStr = "User-Agent: $userAgent\r\nReferer: https://www.google.com/\r\n" . implode("\r\n", array_map(fn($h) => $h, $headers)) . "\r\n";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => $headerStr,
            'timeout' => 15,
            'follow_location' => true,
            'max_redirects' => 5,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $html = file_get_contents($url, false, $context);

    // Try to extract HTTP code from response headers
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hdr) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $hdr, $hm)) {
                $result['http_code'] = (int) $hm[1];
            }
        }
    }

    if ($html !== false && $html !== '') {
        $result['html'] = $html;
    } else {
        $result['error'] = 'file_get_contents returned empty/false';
    }
    return $result;
}

// =====================================================
// Amazon-specific extraction
// =====================================================
function extractAmazon(string $html, string $url): array
{
    $result = ['name' => '', 'price' => null, 'image_url' => ''];

    // --- Name ---
    // 1. productTitle span (standard product pages)
    if (preg_match('/<span[^>]+id=["\']productTitle["\'][^>]*>(.*?)<\/span>/si', $html, $m)) {
        $result['name'] = cleanText($m[1]);
    }
    // 2. Title in #title span
    if (empty($result['name']) && preg_match('/<div[^>]+id=["\']title_feature_div["\'].*?<span[^>]*>(.*?)<\/span>/si', $html, $m)) {
        $result['name'] = cleanText($m[1]);
    }
    // 3. og:title but strip " : Amazon..." suffix
    if (empty($result['name']) && preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $name = cleanText($m[1]);
        // Remove Amazon suffix patterns
        $name = preg_replace('/\s*[:|\-]\s*Amazon\..*$/i', '', $name);
        if (strtolower($name) !== 'amazon' && strtolower($name) !== 'amazon.com') {
            $result['name'] = $name;
        }
    }
    if (empty($result['name']) && preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']/i', $html, $m)) {
        $name = cleanText($m[1]);
        $name = preg_replace('/\s*[:|\-]\s*Amazon\..*$/i', '', $name);
        if (strtolower($name) !== 'amazon' && strtolower($name) !== 'amazon.com') {
            $result['name'] = $name;
        }
    }
    // 4. <title> tag, strip Amazon suffixes
    if (empty($result['name']) && preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
        $name = cleanText($m[1]);
        $name = preg_replace('/\s*[:|\-]\s*Amazon\..*$/i', '', $name);
        if (strtolower($name) !== 'amazon' && strtolower($name) !== 'amazon.com' && strlen($name) > 3) {
            $result['name'] = $name;
        }
    }

    // --- Price ---
    // 1. JSON-LD
    $result['price'] = extractPrice($html);

    // 2. a-price-whole / a-price-fraction (main price display)
    if ($result['price'] === null && preg_match('/<span[^>]+class=["\'][^"\']*a-price[^"\']*["\'][^>]*>.*?<span[^>]+class=["\'][^"\']*a-offscreen[^"\']*["\'][^>]*>\s*\$?\s*([0-9.,]+)/si', $html, $m)) {
        $result['price'] = parsePrice($m[1]);
    }
    if ($result['price'] === null && preg_match('/<span[^>]+class=["\'][^"\']*a-price-whole[^"\']*["\'][^>]*>([0-9.,]+)/i', $html, $mW)) {
        $whole = preg_replace('/[^0-9]/', '', $mW[1]);
        $fraction = '00';
        if (preg_match('/<span[^>]+class=["\'][^"\']*a-price-fraction[^"\']*["\'][^>]*>([0-9]+)/i', $html, $mF)) {
            $fraction = $mF[1];
        }
        $result['price'] = parsePrice($whole . '.' . $fraction);
    }
    // 3. priceblock / corePrice
    if ($result['price'] === null && preg_match('/<span[^>]+id=["\'](?:priceblock_ourprice|priceblock_dealprice|corePrice_feature_div)["\'][^>]*>[^<]*?\$\s*([0-9.,]+)/i', $html, $m)) {
        $result['price'] = parsePrice($m[1]);
    }
    // 4. data attribute with price
    if ($result['price'] === null && preg_match('/data-asin-price=["\']([0-9.,]+)["\']/i', $html, $m)) {
        $result['price'] = parsePrice($m[1]);
    }
    // 5. "price":XX.XX in inline JS/JSON
    if ($result['price'] === null && preg_match('/"(?:price|buyingPrice|priceAmount)":\s*"?([0-9]+\.?[0-9]*)"?/i', $html, $m)) {
        $result['price'] = parsePrice($m[1]);
    }

    // --- Image ---
    // 1. og:image
    $result['image_url'] = extractImage($html, $url);

    // 2. Landing image (Amazon main product image)
    if (empty($result['image_url']) && preg_match('/<img[^>]+id=["\']landingImage["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        $result['image_url'] = $m[1];
    }
    // 3. imgBlkFront
    if (empty($result['image_url']) && preg_match('/<img[^>]+id=["\']imgBlkFront["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        $result['image_url'] = $m[1];
    }
    // 4. data-old-hires attribute (high-res product image)
    if (empty($result['image_url']) && preg_match('/data-old-hires=["\']([^"\']+)["\']/i', $html, $m)) {
        $result['image_url'] = $m[1];
    }
    // 5. Image from "colorImages" JSON data
    if (empty($result['image_url']) && preg_match('/"hiRes"\s*:\s*"(https?:[^"]+)"/i', $html, $m)) {
        $result['image_url'] = str_replace('\\/', '/', $m[1]);
    }
    if (empty($result['image_url']) && preg_match('/"large"\s*:\s*"(https?:[^"]+)"/i', $html, $m)) {
        $result['image_url'] = str_replace('\\/', '/', $m[1]);
    }

    return $result;
}

// =====================================================
// eBay-specific extraction
// =====================================================
function extractEbay(string $html, string $url): array
{
    $result = ['name' => '', 'price' => null, 'image_url' => ''];

    // --- Name ---
    // 1. og:title
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $name = cleanText($m[1]);
        $name = preg_replace('/\s*[\|]\s*eBay.*$/i', '', $name);
        $result['name'] = $name;
    }
    if (empty($result['name']) && preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']/i', $html, $m)) {
        $name = cleanText($m[1]);
        $name = preg_replace('/\s*[\|]\s*eBay.*$/i', '', $name);
        $result['name'] = $name;
    }
    // 2. Item title span
    if (empty($result['name']) && preg_match('/<span[^>]+class=["\'][^"\']*x-item-title__mainTitle[^"\']*["\'][^>]*>.*?<span[^>]*>(.*?)<\/span>/si', $html, $m)) {
        $result['name'] = cleanText($m[1]);
    }
    // 3. h1 with itemprop=name
    if (empty($result['name']) && preg_match('/<h1[^>]+itemprop=["\']name["\'][^>]*>(.*?)<\/h1>/si', $html, $m)) {
        $result['name'] = cleanText(strip_tags($m[1]));
    }
    // 4. <title> fallback
    if (empty($result['name']) && preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
        $name = cleanText($m[1]);
        $name = preg_replace('/\s*[\|]\s*eBay.*$/i', '', $name);
        $result['name'] = $name;
    }

    // --- Price ---
    // 1. JSON-LD
    if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $scripts)) {
        foreach ($scripts[1] as $jsonText) {
            $data = @json_decode($jsonText, true);
            if (!$data) continue;
            $p = findPriceInJsonLd($data);
            if ($p !== null) {
                $result['price'] = $p;
                break;
            }
        }
    }
    // 2. itemprop price
    if ($result['price'] === null && preg_match('/<span[^>]+itemprop=["\']price["\'][^>]+content=["\']([0-9.,]+)["\']/i', $html, $m)) {
        $result['price'] = parsePrice($m[1]);
    }
    // 3. x-price-primary span
    if ($result['price'] === null && preg_match('/<div[^>]+class=["\'][^"\']*x-price-primary[^"\']*["\'][^>]*>.*?<span[^>]*>(.*?)<\/span>/si', $html, $m)) {
        $priceText = cleanText(strip_tags($m[1]));
        if (preg_match('/([0-9.,]+)/', $priceText, $pm)) {
            $result['price'] = parsePrice($pm[1]);
        }
    }
    // 4. prcIsum (older eBay layout)
    if ($result['price'] === null && preg_match('/<span[^>]+id=["\']prcIsum["\'][^>]*>[^<]*?[\$£€]\s*([0-9.,]+)/i', $html, $m)) {
        $result['price'] = parsePrice($m[1]);
    }
    // 5. "price" in inline JSON
    if ($result['price'] === null && preg_match('/"price"\s*:\s*"?([0-9]+\.?[0-9]*)"?/i', $html, $m)) {
        $result['price'] = parsePrice($m[1]);
    }

    // --- Image ---
    $result['image_url'] = extractImage($html, $url);

    // eBay-specific image
    if (empty($result['image_url']) && preg_match('/<img[^>]+class=["\'][^"\']*img-responsive[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        $result['image_url'] = $m[1];
    }
    if (empty($result['image_url']) && preg_match('/<div[^>]+class=["\'][^"\']*image-treatment[^"\']*["\'][^>]*>.*?<img[^>]+src=["\']([^"\']+)["\']/si', $html, $m)) {
        $result['image_url'] = $m[1];
    }

    return $result;
}

// =====================================================
// Generic extractors (fallback for any site)
// =====================================================

function extractStoreName(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return '';

    $host = preg_replace('/^www\./', '', $host);

    $stores = [
        'amazon.com' => 'Amazon', 'amazon.co.uk' => 'Amazon UK', 'amazon.de' => 'Amazon DE', 'amazon.ca' => 'Amazon CA',
        'ebay.com' => 'eBay', 'ebay.co.uk' => 'eBay UK',
        'walmart.com' => 'Walmart', 'target.com' => 'Target', 'bestbuy.com' => 'Best Buy',
        'etsy.com' => 'Etsy', 'ikea.com' => 'IKEA',
        'homedepot.com' => 'Home Depot', 'lowes.com' => "Lowe's",
        'costco.com' => 'Costco', 'kohls.com' => "Kohl's",
        'macys.com' => "Macy's", 'nordstrom.com' => 'Nordstrom',
        'zappos.com' => 'Zappos', 'newegg.com' => 'Newegg',
        'bhphotovideo.com' => 'B&H Photo', 'barnesandnoble.com' => 'Barnes & Noble',
        'bookshop.org' => 'Bookshop', 'gamestop.com' => 'GameStop',
        'nike.com' => 'Nike', 'adidas.com' => 'Adidas',
        'apple.com' => 'Apple', 'samsung.com' => 'Samsung',
    ];

    foreach ($stores as $domain => $name) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            return $name;
        }
    }

    $parts = explode('.', $host);
    if (count($parts) >= 2) {
        return ucfirst($parts[count($parts) - 2]);
    }
    return ucfirst($host);
}

function extractProductName(string $html): string
{
    // 1. og:title
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        return cleanText($m[1]);
    }
    if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']/i', $html, $m)) {
        return cleanText($m[1]);
    }

    // 2. Twitter card title
    if (preg_match('/<meta[^>]+name=["\']twitter:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        return cleanText($m[1]);
    }

    // 3. itemprop name
    if (preg_match('/<(?:span|h1|h2|div)[^>]+itemprop=["\']name["\'][^>]*>(.*?)<\/(?:span|h1|h2|div)>/si', $html, $m)) {
        $name = cleanText(strip_tags($m[1]));
        if (strlen($name) > 2) return $name;
    }

    // 4. <title> tag
    if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
        $title = cleanText($m[1]);
        // Remove common store suffixes
        $title = preg_replace('/\s*[\-\|:]\s*(Amazon|eBay|Walmart|Target|Etsy|Best Buy|Shop|Store|Buy|Official).*$/i', '', $title);
        if (strlen($title) > 2) return $title;
    }

    return '';
}

function extractPrice(string $html): ?float
{
    // 1. Schema.org JSON-LD price
    if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $scripts)) {
        foreach ($scripts[1] as $jsonText) {
            $data = @json_decode($jsonText, true);
            if (!$data) continue;
            $price = findPriceInJsonLd($data);
            if ($price !== null) return $price;
        }
    }

    // 2. Meta tag itemprop price
    if (preg_match('/<meta[^>]+itemprop=["\']price["\'][^>]+content=["\']([0-9.,]+)["\']/i', $html, $m)) {
        return parsePrice($m[1]);
    }
    // Reverse attribute order
    if (preg_match('/<meta[^>]+content=["\']([0-9.,]+)["\'][^>]+itemprop=["\']price["\']/i', $html, $m)) {
        return parsePrice($m[1]);
    }

    // 3. og:price:amount or product:price:amount
    if (preg_match('/<meta[^>]+property=["\'](?:og:price:amount|product:price:amount)["\'][^>]+content=["\']([0-9.,]+)["\']/i', $html, $m)) {
        return parsePrice($m[1]);
    }
    if (preg_match('/<meta[^>]+content=["\']([0-9.,]+)["\'][^>]+property=["\'](?:og:price:amount|product:price:amount)["\']/i', $html, $m)) {
        return parsePrice($m[1]);
    }

    // 4. span/div with itemprop="price" and content attribute
    if (preg_match('/<(?:span|div)[^>]+itemprop=["\']price["\'][^>]+content=["\']([0-9.,]+)["\']/i', $html, $m)) {
        return parsePrice($m[1]);
    }

    // 5. Elements with price-related classes containing currency
    if (preg_match('/class=["\'][^"\']*(?:product-price|sale-price|current-price|final-price|offer-price)[^"\']*["\'][^>]*>[^<]*?[\$£€]\s*([0-9]+[.,]?[0-9]*)/i', $html, $m)) {
        return parsePrice($m[1]);
    }

    // 6. Any element with "price" in class/id containing a dollar amount
    if (preg_match('/(?:class|id)=["\'][^"\']*price[^"\']*["\'][^>]*>[^<]*?[\$£€]\s*([0-9]+[.,]?[0-9]*)/i', $html, $m)) {
        return parsePrice($m[1]);
    }

    return null;
}

function findPriceInJsonLd($data): ?float
{
    if (!is_array($data)) return null;

    // Direct offers.price
    if (isset($data['offers']['price'])) {
        return parsePrice((string) $data['offers']['price']);
    }
    if (isset($data['offers'][0]['price'])) {
        return parsePrice((string) $data['offers'][0]['price']);
    }
    if (isset($data['offers']['lowPrice'])) {
        return parsePrice((string) $data['offers']['lowPrice']);
    }
    // AggregateOffer
    if (isset($data['offers']['@type']) && $data['offers']['@type'] === 'AggregateOffer') {
        if (isset($data['offers']['lowPrice'])) return parsePrice((string) $data['offers']['lowPrice']);
        if (isset($data['offers']['highPrice'])) return parsePrice((string) $data['offers']['highPrice']);
    }
    // Offers array within offers
    if (isset($data['offers']['offers']) && is_array($data['offers']['offers'])) {
        foreach ($data['offers']['offers'] as $offer) {
            if (isset($offer['price'])) return parsePrice((string) $offer['price']);
        }
    }

    // Recurse into @graph
    if (isset($data['@graph']) && is_array($data['@graph'])) {
        foreach ($data['@graph'] as $node) {
            $price = findPriceInJsonLd($node);
            if ($price !== null) return $price;
        }
    }

    // Recurse into arrays (some pages wrap in array at top level)
    if (isset($data[0]) && is_array($data[0])) {
        foreach ($data as $node) {
            $price = findPriceInJsonLd($node);
            if ($price !== null) return $price;
        }
    }

    return null;
}

function extractImage(string $html, string $baseUrl): string
{
    // 1. og:image
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        return resolveUrl($m[1], $baseUrl);
    }
    if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
        return resolveUrl($m[1], $baseUrl);
    }

    // 2. Twitter image
    if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        return resolveUrl($m[1], $baseUrl);
    }
    if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/i', $html, $m)) {
        return resolveUrl($m[1], $baseUrl);
    }

    // 3. itemprop image
    if (preg_match('/<(?:img|meta)[^>]+itemprop=["\']image["\'][^>]+(?:src|content)=["\']([^"\']+)["\']/i', $html, $m)) {
        return resolveUrl($m[1], $baseUrl);
    }

    return '';
}

function resolveUrl(string $url, string $baseUrl): string
{
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }
    if (str_starts_with($url, '//')) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $url;
    }
    $parsed = parse_url($baseUrl);
    $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    if (str_starts_with($url, '/')) {
        return $base . $url;
    }
    return $base . '/' . $url;
}

function parsePrice(string $raw): ?float
{
    $clean = preg_replace('/[^\d.,]/', '', $raw);
    if ($clean === '' || $clean === null) return null;

    // Handle European format (1.234,56)
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{2})?$/', $clean)) {
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
    } else {
        $clean = str_replace(',', '', $clean);
    }

    $val = (float) $clean;
    return $val > 0 ? round($val, 2) : null;
}

function cleanText(string $text): string
{
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}
