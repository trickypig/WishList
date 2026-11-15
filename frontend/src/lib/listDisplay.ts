import type { Item } from '../types';

export const TILE_COUNT = 5;

/** Stable per-list color, so a list looks the same on its card and its page. */
export function listColorVar(listId: number): string {
  return `var(--tile-${(listId % TILE_COUNT) + 1})`;
}

/** Image tile color class, rotating by position. */
export function tileClass(index: number): string {
  return `tile-${(index % TILE_COUNT) + 1}`;
}

/** Items (not units) that still have quantity left to buy. */
export function countOpen(items: Pick<Item, 'quantity_desired' | 'total_purchased'>[]): { open: number; total: number } {
  const open = items.filter((item) => (item.total_purchased ?? 0) < item.quantity_desired).length;
  return { open, total: items.length };
}

/** Grid drop target: the right half of a card means "drop after it". */
export function dropBeforeId(
  clientX: number,
  rect: { left: number; width: number },
  section: Pick<Item, 'id'>[],
  index: number,
): number | null {
  const after = clientX > rect.left + rect.width / 2;
  return after ? (section[index + 1]?.id ?? null) : section[index].id;
}
