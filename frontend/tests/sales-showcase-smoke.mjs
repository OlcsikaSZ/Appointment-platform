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
const screenshotsDir = path.join(root, 'frontend', 'assets', 'sales', 'screenshots');

assert.match(router, /'main'\s*=>\s*'\/demo'/);
assert.match(router, /'showcase'\s*=>\s*'\/'/);
assert.match(router, /'\/'\s*=>\s*'bemutato'/);
assert.match(router, /'\/demo'\s*=>\s*'main'/);
assert.match(router, /\$relativePath === '\/bemutato'/);
assert.match(html, /Olcsi Business/);
assert.match(html, /Élő demo kipróbálása/);
assert.match(html, /Írj egy „DEMÓ” üzenetet/);
assert.match(html, /Nem látványterv\. Működő rendszer\./);
assert.match(html, /Referencia partner program/);
assert.match(html, /sales-audience-list/);
assert.match(html, /Szépség &amp; megjelenés/);
assert.doesNotMatch(html, /sales-professions/);
assert.doesNotMatch(html, /\$videoEmbedUrl\s*=\s*''/);
assert.match(
  html,
  /https:\/\/www\.youtube-nocookie\.com\/embed\/N5YuxQ5z-B4/
);
assert.match(html, /sales-video-consent/);
assert.match(html, /Videó betöltése/);
assert.doesNotMatch(html, /<iframe\b/i);
assert.match(js, /querySelector\('\.sales-video-consent'\)/);
assert.match(js, /document\.createElement\('iframe'\)/);
assert.match(js, /replaceChildren\(iframe\)/);
assert.match(html, /assets\/sales\/screenshots/);
assert.match(html, /is_file\(\$screenshotBase\.\$item\['file'\]\)/);
assert.match(css, /sales-device-phone/);
assert.match(css, /sales-partner-card/);
assert.match(css, /@media \(max-width: 720px\)/);
assert.match(css, /Final responsive balance pass/);
assert.match(css, /sales-feature-card-large \{ grid-column: auto; \}/);
assert.match(js, /IntersectionObserver/);
assert.match(js, /is-scrolled/);
assert.match(assetReadme, /01-home\.webp/);

for (const file of [
  '01-home.webp',
  '02-services.webp',
  '03-booking.webp',
  '04-booking-mobile.webp',
  '05-admin-calendar.webp',
  '06-statistics.webp',
]) {
  const filePath = path.join(screenshotsDir, file);
  assert.ok(fs.existsSync(filePath), `Missing showcase screenshot: ${file}`);
  assert.ok(fs.statSync(filePath).size > 1000, `Showcase screenshot appears empty: ${file}`);
}

assert.doesNotMatch(html, /https:\/\/linktr\.ee/i);
assert.doesNotMatch(html, /localhost/i);

console.log('Sales showcase smoke: PASS');
