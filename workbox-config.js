module.exports = {
	globDirectory: 'site/',
	globPatterns: [
		'**/*.{twig,html}'
	],
	swDest: 'site/public/sw.js',
	ignoreURLParametersMatching: [
		/^utm_/,
		/^fbclid$/
	]
};