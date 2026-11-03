const { src, dest, watch, series, parallel } = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const sassCompiler = require('sass');
const fs = require('fs');
const path = require('path');
const sourcemaps = require('gulp-sourcemaps');
const plumber = require('gulp-plumber');
const concat = require('gulp-concat');
const terser = require('gulp-terser');
const rename = require('gulp-rename');

const paths = {
  scss: 'src/scss/**/*.scss',
  js: 'src/js/**/*.js',
  cssDest: 'public/build/css',
  jsDest: 'public/build/js'
};

function css() {
  return src('src/scss/app.scss')
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass({ style: 'compressed' }).on('error', sass.logError))
    .pipe(rename('app.css'))
    .pipe(sourcemaps.write('.'))
    .pipe(dest(paths.cssDest));
}

// CSS compartido: se carga en todas las páginas que usan el reparto por herramienta.
function cssBase() {
  return src('src/scss/app-base.scss')
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass({ style: 'compressed' }).on('error', sass.logError))
    .pipe(rename('app-base.css'))
    .pipe(sourcemaps.write('.'))
    .pipe(dest(paths.cssDest));
}

// Ajustes de móvil: van después de la hoja de la herramienta.
function cssMovil() {
  return src('src/scss/app-movil.scss')
    .pipe(plumber())
    .pipe(sass({ style: 'compressed' }).on('error', sass.logError))
    .pipe(rename('app-movil.css'))
    .pipe(dest(paths.cssDest));
}

// Una hoja por herramienta: el layout carga solo la que necesita la vista.
// Se compilan con la API de Sass porque gulp-sass ignora los parciales (_archivo.scss).
function cssPaginas(done) {
  const dir = 'src/scss/paginas';
  const out = paths.cssDest + '/paginas';
  fs.mkdirSync(out, { recursive: true });
  let n = 0;
  for (const f of fs.readdirSync(dir)) {
    if (!f.startsWith('_') || !f.endsWith('.scss')) continue;
    const nombre = f.slice(1, -5);
    try {
      const r = sassCompiler.compile(path.join(dir, f), { style: 'compressed', loadPaths: ['src/scss'] });
      fs.writeFileSync(path.join(out, nombre + '.css'), r.css);
      n++;
    } catch (e) {
      done(e);
      return;
    }
  }
  console.log(`   ${n} hojas de página en ${out}`);
  done();
}

function cssLanding() {
  return src('src/scss/landing.scss')
    .pipe(plumber())
    .pipe(sass({ style: 'compressed' }).on('error', sass.logError))
    .pipe(dest(paths.cssDest));
}

function js() {
  return src(paths.js)
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(concat('bundle.js'))
    .pipe(terser())
    .pipe(rename('bundle.min.js'))
    .pipe(sourcemaps.write('.'))
    .pipe(dest(paths.jsDest));
}

function dev() {
  watch(paths.scss, parallel(css, cssBase, cssMovil, cssPaginas, cssLanding));
  watch(paths.js, js);
}

exports.css = parallel(css, cssBase, cssMovil, cssPaginas, cssLanding);
exports.js = js;
exports.dev = parallel(css, cssBase, cssMovil, cssPaginas, cssLanding, js, dev);
exports.default = parallel(css, cssBase, cssMovil, cssPaginas, cssLanding, js);
