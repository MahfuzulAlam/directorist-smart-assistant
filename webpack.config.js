const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		'admin': './assets/src/admin/index.js',
		'chat-widget': './assets/src/chat-widget/index.js',
	},
	output: {
		...defaultConfig.output,
		path: require('path').resolve(__dirname, 'assets/build'),
	},
};

