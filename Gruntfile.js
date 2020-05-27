/* eslint-env node */

module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				extensions: [ '.js', '.json', '.vue' ],
				cache: true
			},
			all: [
				'**/*.{js,json,vue}',
				'!{vendor,node_modules}/**'
			]
		},
		banana: conf.MessagesDirs,
		stylelint: {
			all: [
				'**/*.{css,less}',
				'!node_modules/**',
				'!vendor/**',
				'!lib/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'banana', 'stylelint' ] );
	grunt.registerTask( 'default', 'test' );
};
