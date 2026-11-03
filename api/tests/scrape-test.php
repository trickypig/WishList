<?php

/**
 * Tests for product extraction and blocked-page detection.
 * Run from CLI: php api/tests/scrape-test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../routes/scrape.php';

$failures = 0;

function check(string $name, bool $passed): void
{
    global $failures;
    echo ($passed ? '  [OK]   ' : '  [FAIL] ') . $name . "\n";
    if (!$passed) {
        $failures++;
    }
}

echo "Blocked pages\n";
$homeDepotError = '<html><head><title>Error Page</title></head><body>Oops!! Something went wrong.</body></html>';
check('a 403 is blocked', detectBlockedPage('<title>Blue Widget</title>', 403) !== null);
check('a 500 is blocked', detectBlockedPage('<title>Blue Widget</title>', 500) !== null);
check('the Home Depot error page is blocked when the status is unknown', detectBlockedPage($homeDepotError, null) !== null);
check('a challenge page is blocked', detectBlockedPage('<title>Just a moment...</title>', 200) !== null);
check('Amazon robot check is blocked', detectBlockedPage('<title>Robot Check</title>', null) !== null);
check('a normal product page is not blocked', detectBlockedPage('<title>Blue Widget</title>', 200) === null);
check('a product with "error" in its name is not blocked', detectBlockedPage('<title>Error Correcting Memory Kit</title>', 200) === null);
check('text in the page body does not count as a block', detectBlockedPage('<title>Blue Widget</title><p>Access Denied</p>', 200) === null);

echo "Prices\n";
check('keeps thousands in a displayed price',
    extractPrice('<span class="price">$1,299.99</span>') === 1299.99);
check('skips a struck-through list price',
    extractPrice('<span class="list-price">$199.99</span><span class="price">$149.99</span>') === 149.99);
check('skips a camelCase was price',
    extractPrice('<div class="wasPrice">$50.00</div><div class="priceNow">$40.00</div>') === 40.00);
check('skips a shipping price',
    extractPrice('<span class="shipping-price">$9.99</span><span id="price">$25.00</span>') === 25.00);
check('structured data beats displayed prices',
    extractPrice('<span class="price">$1.00</span><script type="application/ld+json">{"@type":"Product","offers":{"price":"99.00"}}</script>') === 99.00);
check('returns nothing when no price exists', extractPrice('<p>Hello</p>') === null);

echo "Whole page extraction\n";
$url = 'https://www.homedepot.com/p/widget/123';
$html = '<html><head>
    <meta property="og:title" content="RYOBI Jet Fan Blower - The Home Depot">
    <meta property="og:image" content="/images/blower.jpg">
    <script type="application/ld+json">{"@type":"Product","offers":{"price":"129.00"}}</script>
</head></html>';
$info = extractProductInfo($html, $url);
check('name has the store suffix removed', $info['name'] === 'RYOBI Jet Fan Blower');
check('price is found', $info['price'] === 129.00);
check('image is made absolute', $info['image_url'] === 'https://www.homedepot.com/images/blower.jpg');
check('store name is found', $info['store_name'] === 'Home Depot');
check('url is kept', $info['url'] === $url);

$amazon = extractProductInfo('<span id="productTitle"> Blue Widget </span><span class="a-price"><span class="a-offscreen">$12.50</span></span>', 'https://www.amazon.com/dp/B000');
check('Amazon extractor is still used', $amazon['name'] === 'Blue Widget' && $amazon['price'] === 12.50);

echo "Images in structured data\n";
$ld = fn(string $json) => '<script type="application/ld+json">' . $json . '</script>';
$base = 'https://www.bestbuy.com/product/x';
check('reads a plain image', extractImage($ld('{"@type":"Product","image":"https://img.test/a.jpg"}'), $base) === 'https://img.test/a.jpg');
check('reads the first of several images', extractImage($ld('{"@type":"Product","image":["https://img.test/a.jpg","https://img.test/b.jpg"]}'), $base) === 'https://img.test/a.jpg');
check('reads an image object', extractImage($ld('{"@type":"Product","image":{"@type":"ImageObject","url":"https://img.test/a.jpg"}}'), $base) === 'https://img.test/a.jpg');
check('finds a product inside @graph', extractImage($ld('{"@graph":[{"@type":"WebPage","image":"https://img.test/page.jpg"},{"@type":"Product","image":"https://img.test/a.jpg"}]}'), $base) === 'https://img.test/a.jpg');
check('ignores images on other types', extractImage($ld('{"@type":"Organization","image":"https://img.test/logo.jpg"}'), $base) === '');
check('og:image still wins', extractImage('<meta property="og:image" content="https://img.test/og.jpg">' . $ld('{"@type":"Product","image":"https://img.test/a.jpg"}'), $base) === 'https://img.test/og.jpg');

echo $failures === 0 ? "\nAll tests passed\n" : "\n{$failures} test(s) failed\n";
exit($failures === 0 ? 0 : 1);
