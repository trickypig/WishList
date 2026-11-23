// Chrome serializes this function into the active tab. Keep all dependencies local.
export function readProduct() {
  const products = [];
  function visit(node) {
    if (Array.isArray(node)) return node.forEach(visit);
    if (!node || typeof node !== 'object') return;
    if ([node['@type']].flat().includes('Product')) products.push(node);
    if (node['@graph']) visit(node['@graph']);
  }
  for (const script of document.querySelectorAll('script[type="application/ld+json"]')) {
    try { visit(JSON.parse(script.textContent)); } catch { /* Ignore incomplete markup. */ }
  }
  if (products.length > 1) return {};
  const product = products[0] || {};
  const meta = key => document.querySelector('meta[property="' + key + '"]')?.content;
  const offers = [product.offers || {}].flat();
  const rawPrice = offers.length === 1 ? offers[0]?.price ?? meta('product:price:amount') : '';
  const currency = offers[0]?.priceCurrency || meta('product:price:currency');
  const price = (!currency || currency === 'USD') && /^\d+(\.\d{1,2})?$/.test(rawPrice) ? String(rawPrice) : '';
  const image = [product.image].flat()[0];
  let imageUrl = '';
  try {
    const raw = (typeof image === 'string' ? image : image?.url) || meta('og:image');
    if (raw) {
      const url = new URL(raw, document.baseURI);
      if (['http:', 'https:'].includes(url.protocol)) imageUrl = url.href;
    }
  } catch { /* The item can be saved without a photo. */ }
  return { name: typeof product.name === 'string' ? product.name : meta('og:title'), price, imageUrl };
}
