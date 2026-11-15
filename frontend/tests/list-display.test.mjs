import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { build } from 'esbuild';

const result = await build({
  entryPoints: [fileURLToPath(new URL('../src/lib/listDisplay.ts', import.meta.url))],
  bundle: true, write: false, platform: 'node', format: 'cjs', packages: 'external',
});
const compiled = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), compiled, compiled.exports);
const { listColorVar, tileClass, countOpen, dropBeforeId, shouldShowImage, itemCountLabel } = compiled.exports;

test('list color is stable per list id and wraps the five-color palette', () => {
  assert.equal(listColorVar(0), 'var(--tile-1)');
  assert.equal(listColorVar(7), 'var(--tile-3)');
});

test('tile class rotates through five tiles by position', () => {
  assert.equal(tileClass(4), 'tile-5');
  assert.equal(tileClass(5), 'tile-1');
});

test('countOpen counts items, not units, that are not fully purchased', () => {
  assert.deepEqual(countOpen([]), { open: 0, total: 0 });
  assert.deepEqual(countOpen([
    { quantity_desired: 3, total_purchased: 1 },
    { quantity_desired: 1, total_purchased: 1 },
    { quantity_desired: 2 },
  ]), { open: 2, total: 3 });
});

test('dropBeforeId uses the horizontal midpoint of the hovered card', () => {
  const section = [{ id: 10 }, { id: 20 }, { id: 30 }];
  const rect = { left: 0, width: 100 };
  assert.equal(dropBeforeId(30, rect, section, 0), 10);
  assert.equal(dropBeforeId(70, rect, section, 0), 20);
  assert.equal(dropBeforeId(70, rect, section, 2), null);
});

test('shouldShowImage hides only the URL that failed, so a corrected URL shows again', () => {
  assert.equal(shouldShowImage('', null), false);
  assert.equal(shouldShowImage('https://a/x.jpg', null), true);
  assert.equal(shouldShowImage('https://a/x.jpg', 'https://a/x.jpg'), false);
  assert.equal(shouldShowImage('https://a/fixed.jpg', 'https://a/x.jpg'), true);
});

test('item count label uses loaded items, else the summary count', () => {
  assert.equal(itemCountLabel({ items: [{}, {}] }), '2 items');
  assert.equal(itemCountLabel({ item_count: 1 }), '1 item');
  assert.equal(itemCountLabel({ item_count: 0 }), '0 items');
  assert.equal(itemCountLabel({}), null);
});
