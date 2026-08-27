import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');

const env = read('backend/.env.example');
const schedule = read('backend/routes/console.php');
const backupConfig = read('backend/config/backup.php');
const readme = read('README.md');

for (const key of [
  'BACKUP_ENABLED=',
  'BACKUP_PATH=',
  'BACKUP_RETENTION_DAYS=',
  'BACKUP_MYSQLDUMP_BINARY=',
  'BACKUP_GZIP_BINARY=',
]) {
  if (!env.includes(key)) throw new Error(`Missing backup env key: ${key}`);
}

if (!schedule.includes("Schedule::command('app:backup')")) throw new Error('Scheduled app:backup missing');
if (!schedule.includes("->dailyAt('01:30')")) throw new Error('Backup schedule is not 01:30');
if (!backupConfig.includes("'include_media'")) throw new Error('Media backup config missing');
for (const command of ['app:bootstrap-client', 'app:production-check', 'app:backup-verify']) {
  if (!readme.includes(command)) throw new Error(`README missing ${command}`);
}

console.log('Production operations smoke: PASS');
