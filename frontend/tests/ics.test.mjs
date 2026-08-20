import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../assets/shared.js', import.meta.url), 'utf8');
const sandbox = {
  window: {
    APPOINTMENT_CONFIG: {},
    location: { hostname: 'example.test' }
  },
  TextEncoder,
  URL,
  Blob,
  FormData,
  fetch,
  setTimeout
};
vm.runInNewContext(source, sandbox);

const body = sandbox.window.App.buildIcs({
  uid: 'booking-42',
  bookingId: 42,
  manageUrl: 'https://example.test/manage?token=abc',
  title: 'Próba, kezelés; extra',
  description: 'Első sor\nMásodik \\ sor',
  location: 'Miskolc, Fő utca 1.',
  dateKey: '2026-08-10',
  startTime: '10:00:00',
  endTime: '11:00:00',
  timezone: 'Europe/Budapest'
});

assert.match(body, /UID:booking-42@idovonal\r\n/);
assert.match(body, /DTSTAMP:\d{8}T\d{6}Z\r\n/);
assert.match(body, /DTSTART;TZID=Europe\/Budapest:20260810T100000\r\n/);
assert.match(body, /SUMMARY:Próba\\, kezelés\\; extra\r\n/);
const unfolded = body.replace(/\r\n /g, '');
assert.ok(unfolded.includes(String.raw`DESCRIPTION:Első sor\nMásodik \\ sor\nFoglalás kezelése: https://example.test/manage?token=abc`));
assert.ok(unfolded.split('\r\n').includes('LOCATION:Miskolc\\, Fő utca 1.'));
assert.ok(unfolded.split('\r\n').includes('URL:https://example.test/manage?token=abc'));
assert.ok(body.endsWith('\r\n'));

for (const line of body.split('\r\n').filter(Boolean)) {
  assert.ok(new TextEncoder().encode(line).length <= 75, `Túl hosszú ICS sor: ${line}`);
}

console.log('ICS RFC smoke test: OK');
