import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { build } from 'esbuild';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

// Compile the real TSX component using Vite's existing compiler dependency.
const result = await build({
  entryPoints: [fileURLToPath(new URL('../src/components/items/ItemCard.tsx', import.meta.url))],
  bundle: true, write: false, platform: 'node', format: 'cjs', packages: 'external', jsx: 'automatic',
});
const compiled = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), compiled, compiled.exports);
const ItemCard = compiled.exports.default;
const suggestion = {
  id: 2, list_id: 1, name: 'Book', description: 'A good read', price: 12.5,
  quantity_desired: 2, image_url: '', sort_order: 0, group_id: null,
  suggested_by: 2, suggested_by_name: 'Alex', links: [{ url: 'https://example.com/book', store_name: 'Book shop' }],
  total_purchased: 1, purchased_by_me: 1,
};
const render = props => renderToStaticMarkup(createElement(ItemCard, { item: suggestion, ...props }));

test('revealed owner card identifies author without exposing purchase state or edit/reorder controls', () => {
  const html = render({ isOwner: true, canEdit: false, canDelete: true });
  assert.match(html, /Suggested by Alex/);
  assert.match(html, /Book shop/);
  assert.match(html, /\$12\.50/);
  assert.match(html, />Delete</);
  assert.doesNotMatch(html, /purchased|Purchase|>Edit<|Move up|Move down/);
});

test('suggestion author can edit, delete and undo their purchase', () => {
  const html = render({ isOwner: false, canEdit: true, canDelete: true });
  assert.match(html, />Edit</);
  assert.match(html, />Delete</);
  assert.match(html, />Undo</);
  assert.match(html, /You&#x27;re getting this ✓/);
  assert.match(html, /1 of 2 taken/);
  assert.doesNotMatch(html, /Move up|Move down/);
});

test('other family members get purchase controls without edit/delete controls', () => {
  const html = render({ item: { ...suggestion, purchased_by_me: 0 }, isOwner: false, canEdit: false, canDelete: false });
  assert.match(html, />I&#x27;ll get this</);
  assert.doesNotMatch(html, />Edit<|>Delete<|Move up|Move down/);
});

test('official items retain owner edit and reorder controls without attribution', () => {
  const html = render({ item: { ...suggestion, suggested_by: null, suggested_by_name: null }, isOwner: true });
  assert.match(html, />Edit</);
  assert.match(html, />Delete</);
  assert.match(html, /Move up/);
  assert.doesNotMatch(html, /Suggested by|purchased|Purchase/);
});

const other = { ...suggestion, suggested_by: null, suggested_by_name: null };

test('fully purchased by others shows Claimed and stripes', () => {
  const html = render({ item: { ...other, total_purchased: 2, purchased_by_me: 0 }, isOwner: false, canEdit: false, canDelete: false });
  assert.match(html, /Claimed ✓/);
  assert.match(html, /item-card-purchased/);
  assert.doesNotMatch(html, /ll get this/);
});

test('fully claimed items identify the people getting them', () => {
  for (const names of [['Bob'], ['Bob', 'Sam']]) {
    const html = render({ item: { ...other, total_purchased: 2, purchased_by_me: 0, purchaser_names: names }, isOwner: false });
    assert.ok(html.includes(`Claimed by ${names.join(', ')}`));
    assert.doesNotMatch(html, /ll get this/);
  }
});

test('claimant names stay hidden from the list owner', () => {
  const html = render({ item: { ...other, total_purchased: 2, purchased_by_me: 0, purchaser_names: ['Bob'] }, isOwner: true });
  assert.doesNotMatch(html, /Bob|Claimed|taken|item-card-purchased/);
});

test('partly purchased by others is still available', () => {
  const html = render({ item: { ...other, quantity_desired: 3, total_purchased: 1, purchased_by_me: 0 }, isOwner: false, canEdit: false, canDelete: false });
  assert.match(html, /ll get this/);
  assert.match(html, /1 of 3 taken/);
  assert.doesNotMatch(html, /item-card-purchased|Claimed/);
});

test('owner never sees purchase state', () => {
  const html = render({ item: { ...other, total_purchased: 2, purchased_by_me: 0 }, isOwner: true });
  assert.doesNotMatch(html, /Claimed|taken|item-card-purchased|getting this/);
});

test('tile class follows tileIndex', () => {
  const html = render({ item: other, isOwner: false, tileIndex: 6 });
  assert.match(html, /item-image tile-2/);
});
