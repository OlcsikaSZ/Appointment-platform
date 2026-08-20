import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const adminHtml = read('../views/admin/index.php');
const adminJs = read('../views/admin/index.js');
const adminCss = read('../views/admin/styles.css');
const routes = read('../../backend/routes/api.php');
const authController = read('../../backend/app/Http/Controllers/Api/AuthController.php');
const seeder = read('../../backend/database/seeders/DatabaseSeeder.php');
const bootstrap = read('../../backend/bootstrap/app.php');

assert.match(adminHtml, /Ownerfiók aktiválása/);
assert.match(adminHtml, /Elfelejtetted a jelszavad/);
assert.match(adminHtml, /Adminprofil és biztonság/);
assert.match(adminHtml, /Kijelentkezés minden eszközről/);
assert.match(adminHtml, /activeTab === 'profile'/);
assert.match(adminHtml, /@click="openProfileTab"/);
assert.match(adminJs, /requestAdminEmailChange/);
assert.match(adminJs, /verifyAdminEmailChange/);
assert.match(adminJs, /revokeAdminSession/);
assert.match(adminJs, /async openProfileTab\(\)/);
assert.match(adminCss, /\.admin-code-inputs/);
assert.match(adminCss, /\.admin-security-grid/);
assert.doesNotMatch(adminCss, /\.admin-user-chip\s*\{\s*display\s*:\s*none/);

const profileView = adminHtml.indexOf("<section v-if=\"activeTab === 'profile'\"");
const settingsView = adminHtml.indexOf("<section v-if=\"activeTab === 'settings'\"");
assert.ok(profileView !== -1 && settingsView !== -1 && profileView < settingsView, 'A profilnézetnek külön, a Beállítások előtt kell megjelennie.');

for (const endpoint of [
  '/auth/owner/activate',
  '/auth/password/forgot',
  '/auth/password/reset',
  '/auth/email/change',
  '/auth/email/verify',
  '/auth/sessions',
  '/auth/logout-all',
]) {
  assert.ok(routes.includes(endpoint), `Hiányzó admin biztonsági végpont: ${endpoint}`);
}

assert.match(authController, /whereIn\('role', \['admin', 'owner'\]\)/);
assert.match(authController, /where\('name', 'admin'\)->delete\(\)/);
assert.match(seeder, /environment\(\['local', 'testing'\]\)/);
assert.match(bootstrap, /->withCommands\(\)/);

console.log('Admin owner/security smoke: PASS');
