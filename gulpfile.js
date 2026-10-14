const { src, dest, watch, series, parallel } = require('gulp');
const sass = require('gulp-sass')(require('sass'));
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
  watch(paths.scss, parallel(css, cssLanding));
  watch(paths.js, js);
}

exports.css = parallel(css, cssLanding);
exports.js = js;
exports.dev = parallel(css, cssLanding, js, dev);
exports.default = parallel(css, cssLanding, js);
