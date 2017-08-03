module.exports = function (grunt) {
  'use strict';

  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-postcss');

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    less: {
      theme: {
        options: {
          outputSourceFiles: true,
          sourceMap: true,
          relativeUrls: false
        },
        files: {
          'css/style.css': 'less/style.less'
        }
      }
    },
    postcss: {
      options: {
        processors: [
          require('autoprefixer')({browsers: 'last 4 versions'}), // add vendor prefixes
          require('postcss-flexibility')
        ]
      },
      dist: {
        src: 'css/**/*.css'
      }
    },
    watch: {
      configFiles: {
        options: {
          reload: true
        },
        files: ['Gruntfile.js', 'package.json']
      },
      less: {
        files: 'less/**/*.less',
        tasks: 'less'
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

  grunt.registerTask('css', ['less', 'postcss']);

  grunt.registerTask('js', ['concat', 'uglify']);

  grunt.registerTask('prod', ['css']);

  grunt.registerTask('default', ['less', 'watch']);

};
