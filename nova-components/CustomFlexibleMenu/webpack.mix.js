let mix = require('laravel-mix')
let path = require('path')
let NovaExtension = require('laravel-nova-devtool')

mix.extend('nova', new NovaExtension())

mix
  .setPublicPath('dist')
  .js('resources/js/asset.js', 'js')
  .js('resources/js/flexible-form-override.js', 'js')
  .vue({ version: 3 })
  .css('resources/css/asset.css', 'css')
  .alias({
    '@': path.join(__dirname, 'resources/js/'),
    'nova-flexible-internal': path.join(
      __dirname,
      '../../vendor/whitecube/nova-flexible-content/resources/js',
    ),
    'laravel-nova': path.join(
      __dirname,
      '../../vendor/laravel/nova/resources/js',
    ),
  })
  .nova('medov/custom-flexible-menu')
  .version()
