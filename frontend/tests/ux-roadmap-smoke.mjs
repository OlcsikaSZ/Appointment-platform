import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const read = (path) => readFileSync(resolve(here, path), 'utf8');

const mainPhp = read('../views/main/index.php');
const mainJs = read('../views/main/index.js');
const mainCss = read('../views/main/styles.css');
const legalPhp = read('../views/legal/index.php');
const legalJs = read('../views/legal/index.js');
const managePhp = read('../views/manage/index.php');
const manageJs = read('../views/manage/index.js');
const manageCss = read('../views/manage/styles.css');
const adminPhp = read('../views/admin/index.php');
const adminCss = read('../views/admin/styles.css');

assert.match(mainPhp, /id="foglalas"/);
assert.match(mainPhp, /ref="bookingSection"/);
assert.match(mainJs, /scrollToBooking\(\)/);
assert.doesNotMatch(mainJs, /querySelector\('#foglalas'\)\?\.offsetTop \|\| 0/);

assert.match(mainPhp, /openLegalModal\('Adatkezelési tájékoztató'/);
assert.match(mainPhp, /role="dialog"/);
assert.match(mainPhp, /aria-modal="true"/);
assert.match(mainJs, /trapLegalModalFocus/);
assert.match(mainJs, /event\.key === 'Escape'/);
assert.match(mainCss, /\.legal-acceptance\s*\{[\s\S]*?align-items:\s*center;/);
assert.match(mainCss, /\.legal-modal-dialog/);

assert.match(legalPhp, /data-main-url=/);
assert.match(legalPhp, /@click\.prevent="goBack"/);
assert.match(legalJs, /window\.history\.back\(\)/);
assert.match(legalJs, /window\.location\.assign\(this\.mainUrl\)/);

assert.match(managePhp, /openLegalModal\('Adatkezelési tájékoztató'/);
assert.match(managePhp, /Vissza a foglalásomhoz/);
assert.match(managePhp, /ref="legalModalDialog"/);
assert.doesNotMatch(managePhp, /manage-legal-footer[\s\S]*?target="_blank"/);
assert.match(manageJs, /response\.legal/);
assert.match(manageJs, /trapLegalModalFocus/);
assert.match(manageCss, /\.manage-legal-modal-dialog/);

assert.match(mainPhp, /submitPublicReview/);
assert.match(mainPhp, /Vélemény elküldése/);
assert.match(mainJs, /reviewFormValid/);
assert.match(mainJs, /\/reviews/);
assert.match(mainPhp, /@click="togglePublicReviewForm"/);
assert.match(mainPhp, /v-if="reviewFormOpen \|\| reviewSubmitted"/);
assert.match(mainJs, /togglePublicReviewForm\(\)/);
assert.match(mainCss, /\.review-disclosure-button/);
assert.match(mainCss, /grid-template-rows:0fr/);
assert.ok(
  mainPhp.indexOf('id="public-review-form-section"') < mainPhp.indexOf('class="review-grid"'),
  'A véleményíró lenyíló panelnek a megjelenített vélemények előtt kell lennie.'
);
assert.match(mainPhp, /name="public-review-rating"/);
assert.doesNotMatch(mainPhp, /v-model\.number="reviewForm\.rating" required>[\s\S]*?<option/);
assert.match(mainCss, /\.star-rating input:focus-visible/);
assert.match(mainCss, /\.star-rating \{[^}]*border:0;/);
assert.match(adminPhp, /Jóváhagyás és megjelenítés/);
assert.match(adminPhp, /review-moderation-badge/);
assert.match(adminPhp, /name="admin-review-rating"/);
assert.match(adminCss, /\.admin-star-rating input:focus-visible/);
assert.match(adminCss, /\.admin-star-rating \{[^}]*border:0;/);

assert.match(adminCss, /@media \(max-width:1100px\)[\s\S]*?\.email-log-table thead \{ display:none; \}/);
assert.match(adminCss, /content:attr\(data-label\)/);
assert.match(adminCss, /@media \(max-width:760px\)[\s\S]*?\.settings-panel \.inline-actions/);
assert.match(adminCss, /\.settings-save-bar \.button \{ width:100%; \}/);

console.log('UX roadmap smoke: PASS');
