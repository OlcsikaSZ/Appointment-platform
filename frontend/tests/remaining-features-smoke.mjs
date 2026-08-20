import assert from 'node:assert/strict';
import fs from 'node:fs';

const read = (path) => fs.readFileSync(new URL(path, import.meta.url), 'utf8');
const apiRoutes = read('../../backend/routes/api.php');
const consoleRoutes = read('../../backend/routes/console.php');
const router = read('../index.php');
const main = read('../views/main/index.php');
const mainCss = read('../views/main/styles.css');
const account = read('../views/account/index.php');
const accountJs = read('../views/account/index.js');
const accountCss = read('../views/account/styles.css');
const admin = read('../views/admin/index.php');
const adminJs = read('../views/admin/index.js');
const adminCss = read('../views/admin/styles.css');
const customerAuth = read('../../backend/app/Http/Controllers/Api/CustomerAuthController.php');
const xlsxWriter = read('../../backend/app/Services/SimpleXlsxWriter.php');

for (const needle of ['reminder-logs', 'reminders/dispatch', '/customers', 'customer-auth/register', 'customer-auth/login', 'verify-registration', "prefix('customer')", "Route::get('/bookings'", "Route::delete('/account'", '/exports/bookings', '/exports/statistics']) {
  assert.match(apiRoutes, new RegExp(needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
}
assert.match(consoleRoutes, /reminders:dispatch/);
assert.match(consoleRoutes, /everyMinute\(\)/);
assert.match(router, /\/fiokom/);
assert.match(main, />Fiókom</);
assert.match(main, /customer_phone/);
assert.match(account, /Jelentkezz be/);
assert.match(account, /Add meg a hatjegyű kódot/);
assert.match(account, /data-code-group="registration"/);
assert.match(account, /data-code-group="reset"/);
assert.match(account, /Korábbi foglalások/);
assert.match(account, /Fiókom törlése/);
assert.match(account, /role="alertdialog"/);
assert.match(account, /Igen, véglegesen törlöm/);
assert.match(accountJs, /CUSTOMER_TOKEN_KEY/);
assert.match(accountJs, /customer-auth\/verify-registration/);
assert.match(accountJs, /customer\/sessions/);
assert.match(accountJs, /handleCodePaste/);
assert.match(accountJs, /openDeleteDialog/);
assert.match(accountJs, /handleDeleteDialogKeydown/);
assert.doesNotMatch(accountJs, /window\.confirm\(/);
assert.match(accountCss, /verification-code-inputs/);
assert.match(accountCss, /account-delete-modal/);
assert.match(main, /confirm-manage-link/);
assert.match(mainCss, /\.confirm-manage-link/);
assert.match(admin, />Ügyfelek</);
assert.match(admin, />Statisztikák</);
assert.match(admin, /Excel export/);
assert.match(admin, /Belső megjegyzés/);
assert.match(admin, /24 órás emlékeztető/);
assert.match(admin, /2 órás emlékeztető/);
assert.match(adminJs, /rebookCustomer/);
assert.match(adminJs, /loadReminderLogs/);
assert.match(adminCss, /\.statistics-grid > \.panel \{ overflow:hidden; \}/);
assert.match(customerAuth, /customer_verification_active_codes/);
assert.match(xlsxWriter, /<mergeCells count=/);

const passwordResetBackend = customerAuth.slice(
  customerAuth.indexOf('public function resetPassword'),
  customerAuth.indexOf('public function logout')
);
assert.match(passwordResetBackend, /tokens\(\)->delete\(\)/);
assert.match(passwordResetBackend, /Jelentkezz be az új jelszavaddal/);
assert.doesNotMatch(passwordResetBackend, /issueToken/);

const passwordResetFrontend = accountJs.slice(
  accountJs.indexOf('async resetPassword()'),
  accountJs.indexOf('async loadAccount()')
);
assert.match(passwordResetFrontend, /this\.clearSession\(\)/);
assert.match(passwordResetFrontend, /this\.authMode = 'login'/);
assert.match(passwordResetFrontend, /autocomplete="current-password"/);
assert.doesNotMatch(passwordResetFrontend, /this\.saveSession\(/);
assert.doesNotMatch(passwordResetFrontend, /this\.redirectToBooking\(/);

const normalLoginFrontend = accountJs.slice(
  accountJs.indexOf('async login()'),
  accountJs.indexOf('async forgotPassword()')
);
assert.match(normalLoginFrontend, /this\.saveSession\(response\)/);
assert.match(normalLoginFrontend, /this\.returnToBooking/);
assert.match(normalLoginFrontend, /this\.redirectToBooking\(\)/);

console.log('Remaining features smoke: PASS');
