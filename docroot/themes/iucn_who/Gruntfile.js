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
        map: {
          inline: false, // save all sourcemaps as separate files...
          annotation: 'css/' // ...to the specified directory
        }
      },
      screen: {
        options: {
          processors: [
            require('postcss-cssnext')({
              features: {
                rem: false
              }
            }),
            require('postcss-flexibility'),
            require('postcss-rtl'),
          ],
        },
        src: 'css/style.css'
      },
      print: {
        options: {
          processors: [
            require('rtlcss'),
          ]
        },
        src: 'css/print-style-rtl.css'
      }
    },
    cssmin: {
      print: {
        files: [{
          expand: true,
          cwd: 'css',
          src: ['print-style.css', 'print-style-rtl.css'],
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
        tasks: ['screen']
      },
      print: {
        files: ['less/print-style.less', 'css/print-style-rtl-only.css'],
        tasks: ['print']
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
      },
      print: {
        src: 'css/print-style.css',
        dest: 'css/print-style-rtl.css',
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
      },
      print: {
        src: [
          'css/print-style-rtl.css',
          'css/print-style-rtl-only.css'
        ],
        dest: 'css/print-style-rtl.css'
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

  grunt.registerTask('screen', ['less:screen', 'postcss:screen']);

  grunt.registerTask('print', ['less:print', 'copy:print', 'concat:print', 'postcss:print', 'cssmin:print']);

  grunt.registerTask('build', ['screen', 'print']);

  grunt.registerTask('js', ['concat', 'uglify']);

  grunt.registerTask('default', ['build', 'watch']);

};
