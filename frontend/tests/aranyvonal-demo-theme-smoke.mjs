import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..', '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const exists = (p) => fs.existsSync(path.join(root, p));
const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

const mainPhp = read('frontend/views/main/index.php');
const mainJs = read('frontend/views/main/index.js');
const mainCss = read('frontend/views/main/styles.css');
const demoCommand = read('backend/app/Console/Commands/RefreshDemoDataCommand.php');

assert(mainPhp.includes('aranyvonal-hero-visual'), 'Hiányzik az Aranyvonal hero vizuál helye.');
assert(mainJs.includes("classList.toggle('aranyvonal-theme'"), 'Hiányzik az Aranyvonal téma aktiválása.');
assert(mainCss.includes('body.aranyvonal-theme'), 'Hiányzik az Aranyvonal CSS téma.');
assert(demoCommand.includes("assets/brand/aranyvonal/logo.svg"), 'A demo parancs nem állítja be az Aranyvonal logót.');

for (const asset of [
  'frontend/assets/brand/aranyvonal/logo.svg',
  'frontend/assets/brand/aranyvonal/hero.svg',
  'frontend/assets/brand/aranyvonal/services/male-cut.svg',
  'frontend/assets/brand/aranyvonal/services/female-cut.svg',
  'frontend/assets/brand/aranyvonal/services/wash-dry.svg',
  'frontend/assets/brand/aranyvonal/services/coloring.svg',
  'frontend/assets/brand/aranyvonal/services/occasion.svg',
]) {
  assert(exists(asset), `Hiányzó demo asset: ${asset}`);
}

for (const image of ['male-cut.svg', 'female-cut.svg', 'wash-dry.svg', 'coloring.svg', 'occasion.svg']) {
  assert(demoCommand.includes(`assets/brand/aranyvonal/services/${image}`), `A demo seeder nem használja: ${image}`);
}

console.log('Aranyvonal demo theme smoke: PASS');
