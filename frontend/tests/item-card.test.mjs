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
  assert.match(html, />Undo Purchase</);
  assert.match(html, /1 of 2 purchased/);
  assert.doesNotMatch(html, /Move up|Move down/);
});

test('other family members get purchase controls without edit/delete controls', () => {
  const html = render({ item: { ...suggestion, purchased_by_me: 0 }, isOwner: false, canEdit: false, canDelete: false });
  assert.match(html, />Mark Purchased</);
  assert.doesNotMatch(html, />Edit<|>Delete<|Move up|Move down/);
});

test('official items retain owner edit and reorder controls without attribution', () => {
  const html = render({ item: { ...suggestion, suggested_by: null, suggested_by_name: null }, isOwner: true });
  assert.match(html, />Edit</);
  assert.match(html, />Delete</);
  assert.match(html, /Move up/);
  assert.doesNotMatch(html, /Suggested by|purchased|Purchase/);
});
