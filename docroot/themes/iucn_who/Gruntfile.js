module.exports = function (grunt) {
  'use strict';

  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-contrib-cssmin');
  grunt.loadNpmTasks('grunt-postcss');

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    less: {
      options: {
        outputSourceFiles: true,
        sourceMap: true,
        relativeUrls: false
      },
      screen: {
        files: {
          'css/style.css': 'less/style.less'
        }
      },
      print: {
        options: {
          outputSourceFiles: false,
          sourceMap: false
        },
        files: {
          'css/print-style.css': 'less/print-style.less'
        }
      }
    },
    postcss: {
      options: {
        processors: [
          require('postcss-cssnext')({
            features: {
              rem: false
            }
          }),
          require('postcss-flexibility')
        ]
      },
      dist: {
        src: 'css/style.css'
      }
    },
    cssmin: {
      print: {
        files: [{
          expand: true,
          cwd: 'css',
          src: ['print-style.css', '!print-style.min.css'],
          dest: 'css',
          ext: '.css'
        }]
      }
    },
    watch: {
      configFiles: {
        options: {
          reload: true
        },
        files: ['Gruntfile.js', 'package.json']
      },
      screen: {
        files: ['less/**/*.less', '!less/print-style.less', 'images/*.svg'],
        tasks: ['less:screen', 'less:print', 'cssmin:print']
      },
      print: {
        files: ['less/print-style.less'],
        tasks: ['less:print', 'cssmin:print']
      }
    },
    copy: {
      node_modules: {
        expand: true,
        cwd: 'node_modules',
        dest: './libraries/node_modules',
        src: [
            'headroom.js/**',
            'simplebar/**',
            'flexibility/**',
        ]
      }
    },
    concat: {
      bootstrap: {
        src: [
          'bootstrap/js/transition.js',
          'bootstrap/js/alert.js',
          'bootstrap/js/button.js',
          'bootstrap/js/carousel.js',
          'bootstrap/js/collapse.js',
          'bootstrap/js/dropdown.js',
          'bootstrap/js/modal.js',
          'bootstrap/js/tooltip.js',
          'bootstrap/js/popover.js',
          'bootstrap/js/scrollspy.js',
          'bootstrap/js/tab.js',
          'bootstrap/js/affix.js'
        ],
        dest: 'libraries/bootstrap/bootstrap.js'
      }
    },
    uglify: {
      options: {
        compress: {
          warnings: false
        },
        mangle: true,
        preserveComments: /^!|@preserve|@license|@cc_on/i
      },
      core: {
        src: '<%= concat.bootstrap.dest %>',
        dest: 'libraries/bootstrap/bootstrap.min.js'
      },
    },
  });

  grunt.registerTask('nodemodules', ['copy:node_modules']);

  grunt.registerTask('css', ['less', 'cssmin', 'postcss']);

  grunt.registerTask('js', ['concat', 'uglify']);

  grunt.registerTask('prod', ['css']);

  grunt.registerTask('default', ['less', 'cssmin', 'watch']);

};
