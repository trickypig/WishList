<?php

/**
 * Friendly store name for a product URL, e.g. "Best Buy" for bestbuy.com, else the capitalised domain.
 */
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
