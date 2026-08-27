import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const routes = read('../../backend/routes/api.php');
const bootstrap = read('../../backend/bootstrap/app.php');
const frontendIndex = read('../index.php');
const rootHtaccess = read('../../.htaccess');
const bookingModel = read('../../backend/app/Models/Booking.php');
const publicBooking = read('../../backend/app/Http/Controllers/Api/PublicBookingController.php');
const adminBooking = read('../../backend/app/Http/Controllers/Api/AdminBookingController.php');
const statistics = read('../../backend/app/Services/AdminStatisticsService.php');
const migrationUrl = new URL('../../backend/database/migrations/2026_08_27_000013_add_price_snapshot_to_bookings.php', import.meta.url);

assert.match(routes, /businesses\/\{business:slug\}\/bookings[^;]+throttle:8,10/s);
assert.match(routes, /bookings\/\{booking:manage_token\}\/cancel[^;]+throttle:6,10/s);
assert.match(routes, /bookings\/\{booking:manage_token\}\/reschedule[^;]+throttle:6,10/s);
assert.match(bootstrap, /SecurityHeaders::class/);
assert.match(frontendIndex, /Content-Security-Policy:/);
assert.match(frontendIndex, /X-Frame-Options: SAMEORIGIN/);
assert.match(frontendIndex, /frame-ancestors 'self'/);
assert.match(frontendIndex, /Strict-Transport-Security: max-age=31536000/);
assert.match(rootHtaccess, /RewriteRule \^up\/\?\$ backend\/public\/index\.php/);
assert.ok(existsSync(migrationUrl), 'Hiányzik a price snapshot migráció.');
assert.match(bookingModel, /price_cents_snapshot/);
assert.match(bookingModel, /estimatedRevenueCents/);
assert.match(publicBooking, /'price_cents_snapshot' => \$service->price_cents/);
assert.match(adminBooking, /'price_cents_snapshot' => \$service->price_cents/);
assert.match(statistics, /estimatedRevenueCents\(\)/);

console.log('Release hardening smoke: PASS');
