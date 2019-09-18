module.exports = function (grunt) {
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-contrib-watch');

    grunt.initConfig({
        watch: {
            files: ["less/**/*.less"],
            tasks: ["less"]
        },
        less: {
            development: {
                options: {
                    outputSourceFiles: true,
                    sourceMap: true,
                    outputSourceFiles: true,
                    paths: ["less/"]
                },
                files: {
                    "css/style.css": "less/style.less",
                    "css/ckeditor-iframe.css": "less/ckeditor-iframe.less",
                    "css/ckeditor-dialog.css": "less/ckeditor-dialog.less",
                }
            }
        }
    });
    grunt.registerTask('default', ['less', 'watch']);
};
