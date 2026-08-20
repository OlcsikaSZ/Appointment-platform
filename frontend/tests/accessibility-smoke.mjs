import assert from 'node:assert/strict';
import fs from 'node:fs';

const read = (path) => fs.readFileSync(new URL(path, import.meta.url), 'utf8');
const pages = [
  read('../views/main/index.php'),
  read('../views/manage/index.php'),
  read('../views/admin/index.php'),
  read('../views/legal/index.php'),
  read('../views/account/index.php')
];

for (const page of pages) {
  assert.match(page, /class="skip-link"/);
  assert.match(page, /id="main-content"/);
  assert.match(page, /aria-live="polite"/);
}

const manage = pages[1];
assert.match(manage, /meta name="referrer" content="no-referrer"/);
assert.match(manage, /loadState === 'invalid'/);
assert.match(manage, /loadState === 'expired'/);

const adminJs = read('../views/admin/index.js');
assert.match(adminJs, /trapModalFocus\(event\)/);
assert.match(adminJs, /calendarFilters/);

const sharedCss = read('../assets/styles.css');
assert.match(sharedCss, /@media \(max-width: 390px\)/);
assert.match(sharedCss, /prefers-reduced-motion/);
assert.match(sharedCss, /forced-colors: active/);

console.log('Accessibility/responsive static smoke test: OK');
