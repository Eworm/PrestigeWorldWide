var gulp = require('gulp'),
    elixir = require('laravel-elixir'),
    plugins = require('gulp-load-plugins')();

require('laravel-elixir-vue');

elixir(function(mix) {
    mix.webpack([
        './resources/assets/js/src/fieldtype.js'
    ], './resources/assets/js/fieldtype.js');
});
