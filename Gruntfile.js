module.exports = function(grunt) {
  require('load-grunt-tasks')(grunt);

  grunt.initConfig({
    less: {
      development: {
        options: {
          compress: false,
          yuicompress: false,
          optimization: 2
        },
        files: {
          // target.css file: source.less file
          'lib/css/admin.css': 'lib/less/admin.less'
        }
      },
      production: {
        options: {
          compress: true,
          yuicompress: true,
          optimization: 2
        },
        files: {
          // target.css file: source.less file
          'lib/css/admin.css': 'lib/less/admin.less'
        }
      }
    },
    watch: {
      options: {
        livereload: true,
      },
      styles: {
        files: ['lib/less/**/*.less'], // which files to watch
        tasks: ['less'],
        options: {
          nospawn: true
        }
      },
      html: {
        files: ['lib/html/*.html']
      },
      mjml: {
        files: ['lib/mjml/*.mjml'],
        tasks: ['mjml']
      }
    },
    mjml: {
      options: {},
      your_target: {
        files: [{
          expand: true,
          cwd: 'lib/mjml/',
          src: ['**/*.mjml'],
          dest: 'lib/html/',
          ext: '.html',
          extDot: 'last'
        }]
      }
    }
  });

  grunt.registerTask('default', ['watch']);
  grunt.registerTask('builddev', ['less:development']);
  grunt.registerTask('build', ['less:production']);
};