var gulp = require('gulp');
// Requires the gulp-sass plugin
var sass = require('gulp-sass');
// var bower = require('gulp-bower');
const autoprefixer = require('gulp-autoprefixer');

gulp.task('prefix', () =>
    gulp.src('src/assets/css/**/*.css')
        .pipe(autoprefixer({
            browsers: ['last 2 versions'],
            cascade: false
        }))
        .pipe(gulp.dest('src/assets/dist/css/'))
);


gulp.task('sass', function() {
    return gulp.src('src/assets/sass/**/*.scss') // Gets all files ending with .scss in app/scss and children dirs
        .pipe(sass())
        .pipe(autoprefixer({
            browsers: ['last 2 versions'],
            cascade: false
        }))
        .pipe(gulp.dest('src/assets/css'))
});

// gulp.task('bower', function() {
//     return bower({ directory: './vendor' })
// });

gulp.task('watch', function(){
    gulp.watch('src/assets/sass/**/*.scss', ['sass']);
    // Other watchers
});