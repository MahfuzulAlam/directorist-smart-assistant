/**
 * Build Package Script
 * Creates an installable zip file for the WordPress plugin
 */

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

// Get plugin name from package.json
const packageJson = require('./package.json');
const pluginName = packageJson.name;
const pluginVersion = packageJson.version || '1.0.0';

// Directories and files to exclude from the zip
const excludePatterns = [
	'node_modules',
	'.git',
	'.gitignore',
	'.DS_Store',
	'.idea',
	'.vscode',
	'*.swp',
	'*.swo',
	'*~',
	'npm-debug.log',
	'yarn-error.log',
	'package-lock.json',
	'yarn.lock',
	'composer.lock',
	'composer.json',
	'package.json',
	'webpack.config.js',
	'.wp-env.json',
	'build-package.js',
	'assets/src', // Exclude source files, only include built files
	'*.log',
	'.env',
	'.env.local',
	'phpunit.xml',
	'phpunit.xml.dist',
	'tests',
	'.phpcs.xml',
	'.phpcs.xml.dist',
	'.eslintrc',
	'.eslintrc.js',
	'.eslintignore',
	'.prettierrc',
	'.prettierignore',
	'README.md',
	'FEATURES.md',
	'INSTALLATION.md',
	'.editorconfig',
	'.gitattributes',
];

/**
 * Check if a file/directory should be excluded
 */
function shouldExclude(filePath) {
	const relativePath = path.relative(process.cwd(), filePath).replace(/\\/g, '/');
	const fileName = path.basename(filePath);

	// Check against exclude patterns
	for (const pattern of excludePatterns) {
		// Handle wildcard patterns
		if (pattern.includes('*')) {
			const regex = new RegExp('^' + pattern.replace(/\*/g, '.*') + '$');
			if (regex.test(fileName) || regex.test(relativePath)) {
				return true;
			}
		} else {
			// Exact match or starts with
			if (
				fileName === pattern ||
				relativePath === pattern ||
				relativePath.startsWith(pattern + '/') ||
				relativePath.startsWith(pattern + path.sep)
			) {
				return true;
			}
		}
	}

	// Exclude source files in assets/src
	if (relativePath.startsWith('assets/src')) {
		return true;
	}

	return false;
}

/**
 * Create the zip package
 */
function createPackage() {
	return new Promise((resolve, reject) => {
		const outputFileName = `${pluginName}-v${pluginVersion}.zip`;
		const outputPath = path.join(process.cwd(), outputFileName);

		// Remove existing zip file if it exists
		if (fs.existsSync(outputPath)) {
			fs.unlinkSync(outputPath);
			console.log(`🗑️  Removed existing package: ${outputFileName}`);
		}

		const output = fs.createWriteStream(outputPath);
		const archive = archiver('zip', {
			zlib: { level: 9 }, // Maximum compression
		});

		output.on('close', () => {
			const sizeInMB = (archive.pointer() / 1024 / 1024).toFixed(2);
			console.log(`\n✅ Package created successfully!`);
			console.log(`📦 File: ${outputFileName}`);
			console.log(`📊 Size: ${sizeInMB} MB`);
			console.log(`📁 Location: ${outputPath}\n`);
			resolve(outputPath);
		});

		archive.on('error', (err) => {
			console.error('❌ Error creating package:', err);
			reject(err);
		});

		archive.on('warning', (err) => {
			if (err.code === 'ENOENT') {
				console.warn('⚠️  Warning:', err);
			} else {
				reject(err);
			}
		});

		archive.pipe(output);

		// Add files and directories with filtering
		const pluginDir = process.cwd();

		// Function to recursively add files
		function addFiles(dir = '') {
			const fullPath = dir ? path.join(pluginDir, dir) : pluginDir;
			
			if (!fs.existsSync(fullPath)) {
				return;
			}

			const entries = fs.readdirSync(fullPath);

			for (const entry of entries) {
				const entryPath = dir ? path.join(dir, entry) : entry;
				const fullEntryPath = path.join(pluginDir, entryPath);

				// Skip if should be excluded
				if (shouldExclude(fullEntryPath)) {
					continue;
				}

				const stat = fs.statSync(fullEntryPath);

				if (stat.isFile()) {
					// Add file with relative path
					archive.file(fullEntryPath, { name: entryPath });
				} else if (stat.isDirectory()) {
					// Recursively add directory contents
					addFiles(entryPath);
				}
			}
		}

		// Start adding files from root
		addFiles();

		archive.finalize();
	});
}

// Run the build
console.log('🚀 Building plugin package...\n');
createPackage().catch((error) => {
	console.error('❌ Failed to create package:', error);
	process.exit(1);
});
