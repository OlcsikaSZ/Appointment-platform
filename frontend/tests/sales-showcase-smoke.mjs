import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');
const router = fs.readFileSync(path.join(root, 'frontend', 'index.php'), 'utf8');
const html = fs.readFileSync(path.join(root, 'frontend', 'views', 'bemutato', 'index.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'frontend', 'views', 'bemutato', 'styles.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'frontend', 'views', 'bemutato', 'index.js'), 'utf8');
const assetReadme = fs.readFileSync(path.join(root, 'frontend', 'assets', 'sales', 'screenshots', 'README.md'), 'utf8');

assert.match(router, /'showcase'\s*=>\s*'\/bemutato'/);
assert.match(router, /'\/bemutato'\s*=>\s*'bemutato'/);
assert.match(html, /Olcsi Business/);
assert.match(html, /Élő demo kipróbálása/);
assert.match(html, /Írj egy „DEMÓ” üzenetet/);
assert.match(html, /assets\/sales\/screenshots/);
assert.match(html, /is_file\(__DIR__\.\'\/\.\.\/\.\.\/assets\/sales\/screenshots\//);
assert.match(css, /@media \(max-width: 720px\)/);
assert.match(css, /sales-product-scene/);
assert.match(js, /is-scrolled/);
assert.match(assetReadme, /01-home\.webp/);
assert.doesNotMatch(html, /https:\/\/linktr\.ee/i);
assert.doesNotMatch(html, /localhost/i);

console.log('Sales showcase smoke: PASS');
