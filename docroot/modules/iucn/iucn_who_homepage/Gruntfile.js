module.exports = function (grunt) {
  'use strict';

  grunt.loadNpmTasks('grunt-image-resize');
  grunt.loadNpmTasks('grunt-spritesmith');
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-imagemin');

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    image_resize: {
      resize_markers: {
        options: {
          width: '27',
          height: '27',
          overwrite: true
        },
        files: [{
          expand: true,
          cwd: 'images',
          src: 'marker*.png',
          dest: 'images/out/',
        }]
      }
    },
    sprite: {
      all: {
        src: 'images/out/*.png',
        dest: 'dist/spritesheet.png',
        destCss: 'dist/sprites.json',
      }
    },
    clean: {
      markers_temp: {
        src: ['images/out/**']
      }
    },
    imagemin: {
        default: {
            files: {
                'dist/spritesheet.png': 'dist/spritesheet.png',
            }
        },
    },
  });

  grunt.registerTask('default', ['image_resize:resize_markers', 'sprite', 'clean:markers_temp', 'imagemin']);

};
