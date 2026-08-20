import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const shared = read('../assets/shared.js');
const sharedCss = read('../assets/styles.css');
const mainHtml = read('../views/main/index.php');
const mainJs = read('../views/main/index.js');
const manageHtml = read('../views/manage/index.php');
const adminHtml = read('../views/admin/index.php');
const adminJs = read('../views/admin/index.js');
const accountHtml = read('../views/account/index.php');
const routes = read('../../backend/routes/api.php');
const calendarService = read('../../backend/app/Services/CalendarInviteService.php');

const sandbox = {
  window: {
    APPOINTMENT_CONFIG: { apiBase: '/api/v1' },
    location: { href: 'https://booking.example.test/app/', hostname: 'booking.example.test' }
  },
  TextEncoder,
  URL,
  URLSearchParams,
  Blob,
  FormData,
  fetch,
  setTimeout
};
vm.runInNewContext(shared, sandbox);

assert.equal(
  sandbox.window.App.calendarDownloadUrl('token with space'),
  'https://booking.example.test/api/v1/bookings/token%20with%20space/calendar.ics'
);

const googleUrl = sandbox.window.App.googleCalendarUrl({
  title: 'Konzultáció',
  dateKey: '2026-08-10',
  startTime: '10:00',
  endTime: '10:30',
  timezone: 'Europe/Budapest'
});
assert.match(googleUrl, /^https:\/\/calendar\.google\.com\/calendar\/render\?/);
assert.match(decodeURIComponent(googleUrl), /dates=20260810T080000Z\/20260810T083000Z/);

assert.match(shared, /const PasswordInput/);
assert.match(shared, /Jelszó megjelenítése/);
assert.match(sharedCss, /\.password-toggle/);
assert.match(accountHtml, /<password-input/);
assert.match(adminHtml, /<password-input/);
assert.match(mainHtml, /Google Naptár/);
assert.match(mainHtml, /Apple \/ Outlook Naptár/);
assert.match(manageHtml, /Google Naptár/);
assert.match(mainJs, /calendarDownloadUrl/);
assert.match(routes, /calendar\.ics/);
assert.match(calendarService, /text\/calendar|BEGIN:VCALENDAR/);
assert.match(adminHtml, /Rögzítés ideje/);
assert.match(adminHtml, /formatBookingCreatedAt\(selectedBooking\.created_at\)/);
assert.match(adminJs, /timeZone: this\.business\.timezone/);

console.log('Calendar, password visibility and booking metadata smoke: PASS');
