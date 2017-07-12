module.exports = function (grunt) {
  'use strict';

  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-postcss');

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    less: {
      theme: {
        options: {
          outputSourceFiles: true,
          sourceMap: true,
          strictMath: true,
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
  });

  grunt.registerTask('css', ['less', 'postcss']);

  grunt.registerTask('build', ['css', 'watch']);

  grunt.registerTask('default', ['less', 'watch']);

};
