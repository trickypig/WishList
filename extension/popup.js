import { readProduct } from './product.js';

const ui = Object.fromEntries([...document.querySelectorAll('[id]')].map(el => [el.id, el]));
const storage = chrome.storage.local;
let session;
let pageUrl;
let imageUrl;
let draftDestination;
let busy = false;

function show(panel, message = '') {
  for (const id of ['login', 'editor', 'create', 'retry']) ui[id].hidden = id !== panel;
  ui.logout.hidden = !session;
  ui.status.textContent = message;
}

async function request(path, body) {
  const headers = { 'Content-Type': 'application/json' };
  if (session) {
    headers.Authorization = 'Bearer ' + session.token;
    headers['X-Auth-Token'] = session.token;
  }
  const response = await fetch('https://wish.trickypig.com/api' + path, {
    method: body ? 'POST' : 'GET', headers, body: JSON.stringify(body), credentials: 'omit',
    redirect: 'error', cache: 'no-store'
  });
  if (response.status === 401 && session) {
    const destination = ui.lists.value && { userId: session.userId, listId: ui.lists.value };
    await signOut();
    draftDestination = destination;
    throw new Error('Sign in again to continue with this item.');
  }
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || data.message || 'Wish returned error ' + response.status);
  return data;
}

async function signOut() {
  await storage.remove('session');
  session = null;
  draftDestination = null;
  ui.password.value = '';
  ui.lists.replaceChildren();
  show('login');
}

function updateSave() {
  ui.save.textContent = 'Save to ' + (ui.lists.selectedOptions[0]?.textContent || 'wishlist');
}

async function loadLists() {
  show('retry', 'Loading wishlists…');
  const { lists } = await request('/lists');
  if (!lists.length) return show('create');
  ui.lists.replaceChildren(...lists.map(list => new Option(list.title, list.id)));
  const key = 'destination-' + session.userId;
  const previous = draftDestination?.userId === session.userId
    ? draftDestination.listId
    : (await storage.get(key))[key];
  draftDestination = null;
  if (lists.some(list => String(list.id) === previous)) ui.lists.value = previous;
  updateSave();
  show('editor');
}

async function initialize() {
  await storage.setAccessLevel({ accessLevel: 'TRUSTED_CONTEXTS' });
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!/^https?:\/\//i.test(tab?.url)) return show(null, 'Open a shopping page, then click Wish.');
  pageUrl = new URL(tab.url);
  const [capture] = await chrome.scripting.executeScript({
    target: { tabId: tab.id }, func: readProduct
  }).catch(() => []);
  const product = capture?.result || {};
  ui.source.textContent = pageUrl.hostname;
  ui.itemName.value = (product.name?.trim() || tab.title || pageUrl.hostname).slice(0, 255);
  ui.price.value = product.price || '';
  imageUrl = product.imageUrl || '';
  ui.photo.hidden = !imageUrl;
  if (imageUrl) ui.preview.src = imageUrl;
  session = (await storage.get('session')).session;
  if (session) await loadLists();
  else show('login');
}

async function run(action) {
  if (busy) return;
  busy = true;
  const buttons = [...document.querySelectorAll('button')];
  buttons.forEach(button => button.disabled = true);
  try {
    await action();
  } catch (error) {
    ui.status.textContent = error.message || 'Could not reach Wish. Please try again.';
  } finally {
    buttons.forEach(button => button.disabled = false);
    busy = false;
  }
}

function onSubmit(form, action) {
  form.onsubmit = event => { event.preventDefault(); void run(action); };
}
onSubmit(ui.login, async () => {
  const { token, user } = await request('/auth/login', { email: ui.email.value.trim(), password: ui.password.value });
  if (!token || !user?.id) throw new Error('Wish could not confirm sign-in.');
  ui.password.value = '';
  const account = { token, userId: user.id };
  await storage.set({ session: account });
  session = account;
  await loadLists();
});
onSubmit(ui.create, async () => {
  const title = ui.listName.value.trim();
  if (!title) throw new Error('Enter a wishlist name.');
  await request('/lists', { title, visibility: 'all_families' });
  await loadLists();
});
onSubmit(ui.editor, async () => {
  const name = ui.itemName.value.trim();
  if (!name) throw new Error('Enter an item name.');
  const listId = ui.lists.value;
  const title = ui.lists.selectedOptions[0].textContent;
  const { item } = await request('/lists/' + encodeURIComponent(listId) + '/items', {
    name, price: ui.price.value === '' ? null : ui.price.valueAsNumber, image_url: imageUrl,
    links: [{ url: pageUrl.href, store_name: pageUrl.hostname }]
  });
  if (!item?.id) throw new Error('Wish could not confirm the save. Check your list before retrying.');
  show(null, 'Saved to ' + title + '.');
  await storage.set({ ['destination-' + session.userId]: listId }).catch(() => {});
});
ui.logout.onclick = () => run(signOut);
ui.retry.onclick = () => run(loadLists);
ui.lists.onchange = updateSave;
ui.remove.onclick = () => {
  imageUrl = '';
  ui.preview.removeAttribute('src');
  ui.photo.hidden = true;
};
void run(initialize);
