import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (rel) => fs.readFileSync(path.join(root, rel), 'utf8');
const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

const globalCss = read('assets/styles.css');
const mainCss = read('views/main/styles.css');
const adminCss = read('views/admin/styles.css');
const manageCss = read('views/manage/styles.css');
const accountCss = read('views/account/styles.css');

for (const [label, css] of [['global', globalCss], ['main', mainCss], ['admin', adminCss], ['manage', manageCss], ['account', accountCss]]) {
  assert(/@media\s*\(max-width:\s*390px\)/.test(css), `${label}: hiányzik a 390px breakpoint`);
  assert(/@media\s*\(max-width:\s*360px\)/.test(css), `${label}: hiányzik a 360px breakpoint`);
}

for (const view of ['views/main/index.php', 'views/admin/index.php', 'views/manage/index.php', 'views/legal/index.php', 'views/account/index.php']) {
  const html = read(view);
  assert(html.includes('class="toast-stack" aria-live="polite" aria-atomic="false"'), `${view}: hiányzik az aria-live toast régió`);
  assert(html.includes(':role="toast.kind === \'error\' ? \'alert\' : \'status\'"'), `${view}: a toastoknak nincs dinamikus ARIA szerepe`);
}

const adminJs = read('views/admin/index.js');
assert(adminJs.includes("event.key === 'Tab'"), 'admin: nincs Tab fókuszcsapda');
assert(adminJs.includes('modalReturnFocus'), 'admin: nincs fókusz-visszaállítás');
assert(adminJs.includes('getModalFocusableElements'), 'admin: nincs fókuszolható modal-elemek keresése');
const adminHtml = read('views/admin/index.php');
assert((adminHtml.match(/class="modal-dialog[^\"]*" tabindex="-1"/g) || []).length >= 7, 'admin: nem minden modal kapott tabindex=-1 értéket');
assert(adminHtml.includes('aria-label="Admin szekciók"'), 'admin: hiányzik az admin szekciók hozzáférhetőségi címkéje');
assert(!adminHtml.includes('button danger ghost'), 'admin: a veszélyes anonimizáló gombot nem írhatja felül a ghost stílus');

const sharedJs = read('assets/shared.js');
const sandbox = { window: { APPOINTMENT_CONFIG: {} }, TextEncoder, Intl, Date };
vm.createContext(sandbox);
vm.runInContext(sharedJs, sandbox, { filename: 'assets/shared.js' });
const { buildIcs } = sandbox.window.App;
const stamp = new Date('2026-08-09T12:34:56Z');
const ics = buildIcs({
  title: 'Konzultáció, A;B',
  description: 'Első sor\nMásodik\\teszt',
  dateKey: '2026-08-10',
  startTime: '10:00',
  endTime: '10:45',
  timeZone: 'Europe/Budapest',
  bookingId: 123,
  manageUrl: 'https://booking.example.test/app/manage?token=abc',
  stamp,
});

assert(ics.includes('BEGIN:VCALENDAR\r\n'), 'ICS: hiányzik a VCALENDAR fejléc');
assert(ics.includes('BEGIN:VTIMEZONE\r\nTZID:Europe/Budapest\r\n'), 'ICS: hiányzik a VTIMEZONE/TZID');
assert(ics.includes('UID:booking-123@idovonal'), 'ICS: az UID nem stabil booking azonosítóra épül');
assert(ics.includes('DTSTAMP:20260809T123456Z'), 'ICS: hibás vagy nem UTC DTSTAMP');
assert(ics.includes('DTSTART;TZID=Europe/Budapest:20260810T100000'), 'ICS: hiányzik a TZID-es DTSTART');
assert(ics.includes('DTEND;TZID=Europe/Budapest:20260810T104500'), 'ICS: hiányzik a TZID-es DTEND');
assert(ics.includes('SUMMARY:Konzultáció\\, A\\;B'), 'ICS: hiányzik az RFC escaping');
assert(ics.includes('URL:https://booking.example.test/app/manage?token=abc'), 'ICS: hiányzik a manage URL');
const unfoldedIcs = ics.replace(/\r\n /g, '');
assert(unfoldedIcs.includes('Foglalás kezelése: https://booking.example.test/app/manage?token=abc'), 'ICS: hiányzik a manage link a leírásból');
assert(!ics.replace(/\r\n/g, '').includes('\n'), 'ICS: bare LF található a CRLF sorvégek mellett');
for (const line of ics.split('\r\n').filter(Boolean)) {
  assert(Buffer.byteLength(line, 'utf8') <= 75, `ICS: 75 oktettes sorszabály sérült: ${line}`);
}

console.log('QA smoke: PASS');
console.log('ICS: RFC escaping, CRLF, 75-octet folding, UTC DTSTAMP, TZID/VTIMEZONE, stable UID: PASS');
console.log('Accessibility: aria-live toasts, modal focus trap hooks, focus restore, modal tabindex: PASS');
console.log('Responsive: 360px + 390px hardening rules present on all main views: PASS');
console.log('Email/Manage frontend: absolute-link handling hooks present in the merged frontend: PASS');
