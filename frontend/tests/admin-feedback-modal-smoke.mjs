import fs from 'node:fs';
import assert from 'node:assert/strict';

const shared = fs.readFileSync(new URL('../assets/shared.js', import.meta.url), 'utf8');
const adminJs = fs.readFileSync(new URL('../views/admin/index.js', import.meta.url), 'utf8');
const adminPhp = fs.readFileSync(new URL('../views/admin/index.php', import.meta.url), 'utf8');
const adminCss = fs.readFileSync(new URL('../views/admin/styles.css', import.meta.url), 'utf8');

assert.match(shared, /validationMessage\s*\|\|\s*data\.message/);
assert.match(shared, /humanizeApiMessage/);
assert.match(shared, /validation\.confirmed/);
assert.match(adminJs, /single:\s*true/);
assert.match(adminJs, /errorTimeout:\s*0/);
assert.match(adminPhp, /feedback-modal-backdrop/);
assert.match(adminPhp, /Sikeres művelet/);
assert.match(adminPhp, /Nem sikerült/);
assert.match(adminCss, /\.feedback-modal\s*\{/);
assert.match(adminCss, /\.feedback-modal\.error/);

console.log('Admin feedback modal smoke: PASS');
