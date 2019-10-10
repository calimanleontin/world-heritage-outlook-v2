module.exports = function (grunt) {
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-contrib-watch');

    grunt.initConfig({
        watch: {
            files: ["less/**/*.less"],
            tasks: ["less"]
        },
        less: {
              options: {
                  sourceMap: true,
                  outputSourceFiles: true,
                  paths: ["less/"]
              },
              style: {
                options: {
                  sourceMapURL: 'style.css.map',
                  sourceMapFilename: 'css/style.css.map'
                },
                src: "less/style.less",
                dest: "css/style.css"
              },
              ckeditorIframe: {
                options: {
                  sourceMapURL: 'ckeditor-iframe.css.map',
                  sourceMapFilename: 'css/ckeditor-iframe.css.map'
                },
                src: "less/ckeditor-iframe.less",
                dest: "css/ckeditor-iframe.css"
              },
              ckeditorDialog: {
                options: {
                  sourceMapURL: 'ckeditor-dialog.css.map',
                  sourceMapFilename: 'css/ckeditor-dialog.css.map'
                },
                src: "less/ckeditor-dialog.less",
                dest: "css/ckeditor-dialog.css"
              }
          }
    });
    grunt.registerTask('default', ['less', 'watch']);
};
