<?php

// Installation/upgrade file
define('VERSION', '5.2.2');
require 'inc/bootstrap.php';
loadConfig();

// Salt generators
class SaltGen {
	public $salt_length = 128;

	// Best function I could think of for non-SSL PHP 5
	private function generate_install_salt() {
		$ret = "";

		// This is bad! But what else can we do sans OpenSSL?
		for ($i = 0; $i < $this->salt_length; ++$i) {
			$s = pack("c", mt_rand(0,255));
			$ret = $ret . $s;
		}

		return base64_encode($ret);
	}

	// Best function of the lot. Works with any PHP version as long as OpenSSL extension is on
	private function generate_install_salt_openssl() {
		$ret = openssl_random_pseudo_bytes($this->salt_length, $strong);
		if (!$strong) {
			error(_("Misconfigured system: OpenSSL returning weak salts. Cannot continue."));
		}
		return base64_encode($ret);
	}

	private function generate_install_salt_php7() {
		return base64_encode(random_bytes($this->salt_length));
	}

	// TODO: Perhaps add mcrypt as an option? Maybe overkill.
	public function generate() {
		if (extension_loaded('openssl')) {
			return "OSSL." . $this->generate_install_salt_openssl();
		} else if (defined('PHP_MAJOR_VERSION') && PHP_MAJOR_VERSION >= 7) {
			return "PHP7." . $this->generate_install_salt_php7();
		} else {
			return "INSECURE." . $this->generate_install_salt();
		}
	}
}

$step = isset($_GET['step']) ? round($_GET['step']) : 0;
$page = array(
	'config' => $config,
	'title' => 'Install',
	'body' => '',
	'nojavascript' => true
);

// this breaks the display of licenses if enabled
$config['minify_html'] = false;

if (file_exists($config['has_installed'])) {

	// Check the version number
	$version = trim(file_get_contents($config['has_installed']));
	if (empty($version))
		$version = 'v0.9.1';

	function __query($sql) {
		sql_open();

		if (mysql_version() >= 50503)
			return query($sql);
		else
			return query(str_replace('utf8mb4', 'utf8', $sql));
	}

	$boards = listBoards();

	switch ($version) {
		case '4.4.97':
			if (!isset($_GET['confirm2'])) {
				$page['title'] = 'License Change';
				$page['body'] = '<p style="text-align:center">You are upgrading to a version which uses an amended license. The licenses included with vichan distributions prior to this version (4.4.98) are still valid for those versions, but no longer apply to this and newer versions.</p>' .
					'<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" disabled>' . htmlentities(file_get_contents('LICENSE.md')) . '</textarea>
					<p style="text-align:center">
						<a href="?confirm2=1">I have read and understood the agreement. Proceed to upgrading.</a>
					</p>';

				file_write($config['has_installed'], '4.4.97');

				break;
			}
		case '4.4.98-pre':
			if (!$twig) load_twig();
			$twig->clearCacheFiles();
		case '4.4.98':
		case '4.5.0':
		case '4.5.1':
		case '4.5.2':
			if (!isset($_GET['confirm3'])) {
				$page['title'] = 'Breaking change';
				$page['body'] = '<p style="text-align:center">You are upgrading to the 5.0 branch of vichan. Please back up your database, because the process is irreversible. At the current time, if you want a very stable vichan experience, please use the 4.5 branch. This warning will be lifted as soon as we all agree that 5.0 branch is stable enough</p>
					<p style="text-align:center">
						<a href="?confirm3=1">I have read and understood the warning. Proceed to upgrading.</a>
					</p>';

				file_write($config['has_installed'], '4.5.2');

				break;
			}

			foreach ($boards as &$board) {
				query(sprintf('ALTER TABLE ``posts_%s`` ADD `files` text DEFAULT NULL AFTER `bump`;', $board['uri'])) or error(db_error());
				query(sprintf('ALTER TABLE ``posts_%s`` ADD `num_files` int(11) DEFAULT 0 AFTER `files`;', $board['uri'])) or error(db_error());
				query(sprintf('UPDATE ``posts_%s`` SET `files` = CONCAT(\'[{"file":"\',`file`,\'", "thumb":"\',`thumb`,\'", "filename":"\',`filename`,\'", "size":"\',`filesize`,\'",  "width":"\',`filewidth`,\'","height":"\',`fileheight`,\'","thumbwidth":"\',`thumbwidth`,\'","thumbheight":"\',`thumbheight`,\'"}]\') WHERE `file` IS NOT NULL', $board['uri'], $board['uri'], $board['uri'])) or error(db_error());
				query(sprintf('UPDATE ``posts_%s`` SET `num_files` = 1 WHERE `file` IS NOT NULL', $board['uri'])) or error(db_error());
				query(sprintf('ALTER TABLE ``posts_%s`` DROP COLUMN `thumb`, DROP COLUMN `thumbwidth`, DROP COLUMN `thumbheight`, DROP COLUMN `file`, DROP COLUMN `fileheight`, DROP COLUMN `filesize`, DROP COLUMN `filewidth`, DROP COLUMN `filename`', $board['uri'])) or error(db_error());
				query(sprintf('REPAIR TABLE ``posts_%s``', $board['uri']));
			}
		case '4.9.90':
		case '4.9.91':
		case '4.9.92':
						foreach ($boards as &$board) {
								query(sprintf('ALTER TABLE ``posts_%s`` ADD `slug` VARCHAR(255) DEFAULT NULL AFTER `embed`;', $board['uri'])) or error(db_error());
			}
				case '4.9.93':
						query('ALTER TABLE ``mods`` CHANGE `password` `password` VARCHAR(255) NOT NULL;') or error(db_error());
						query('ALTER TABLE ``mods`` CHANGE `salt` `salt` VARCHAR(64) NOT NULL;') or error(db_error());
		case '5.0.0':
			query('ALTER TABLE ``mods`` CHANGE `salt` `version` VARCHAR(64) NOT NULL;') or error(db_error());
		case '5.0.1':
		case '5.1.0':
			query('CREATE TABLE IF NOT EXISTS ``pages`` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `board` varchar(255) DEFAULT NULL,
			  `name` varchar(255) NOT NULL,
			  `title` varchar(255) DEFAULT NULL,
			  `type` varchar(255) DEFAULT NULL,
			  `content` text,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `u_pages` (`name`,`board`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;') or error(db_error());
		case '5.1.1':
						foreach ($boards as &$board) {
								query(sprintf("ALTER TABLE ``posts_%s`` ADD `cycle` int(1) NOT NULL AFTER `locked`", $board['uri'])) or error(db_error());
						}
		case '5.1.2':
			query('CREATE TABLE IF NOT EXISTS ``nntp_references`` (
				  `board` varchar(60) NOT NULL,
				  `id` int(11) unsigned NOT NULL,
				  `message_id` varchar(255) CHARACTER SET ascii NOT NULL,
				  `message_id_digest` varchar(40) CHARACTER SET ascii NOT NULL,
				  `own` tinyint(1) NOT NULL,
				  `headers` text,
				  PRIMARY KEY (`message_id_digest`),
				  UNIQUE KEY `message_id` (`message_id`),
				  UNIQUE KEY `u_board_id` (`board`, `id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
			') or error(db_error());
		case '5.1.3':
			query('CREATE TABLE IF NOT EXISTS ``captchas`` (
			  	`cookie` varchar(50),
			  	`extra` varchar(200),
			  	`text` varchar(255),
			  	`created_at` int(11),
			  	PRIMARY KEY (`cookie`,`extra`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;') or error(db_error());
		case false:
			// TODO: enhance Tinyboard -> vichan upgrade path.
			query("CREATE TABLE IF NOT EXISTS ``search_queries`` (  `ip` varchar(39) NOT NULL,  `time` int(11) NOT NULL,  `query` text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;") or error(db_error());

			// Update version number
			file_write($config['has_installed'], VERSION);

			$page['title'] = 'Upgraded';
			$page['body'] = '<p style="text-align:center">Successfully upgraded from ' . $version . ' to <strong>' . VERSION . '</strong>.</p>';
			break;
		default:
			$page['title'] = 'Unknown version';
			$page['body'] = '<p style="text-align:center">vichan was unable to determine what version is currently installed.</p>';
			break;
		case VERSION:
			$page['title'] = 'Already installed';
			$page['body'] = '<p style="text-align:center">It appears that vichan is already installed (' . $version . ') and there is nothing to upgrade! Delete <strong>' . $config['has_installed'] . '</strong> to reinstall.</p>';
			break;
	}

	die(Element('page.html', $page));
}

function create_config_from_array(&$instance_config, &$array, $prefix = '') {
	foreach ($array as $name => $value) {
		if (is_array($value)) {
			$instance_config .= "\n";
			create_config_from_array($instance_config, $value, $prefix . '[\'' . addslashes($name) . '\']');
			$instance_config .= "\n";
		} else {
			$instance_config .= '	$config' . $prefix . '[\'' . addslashes($name) . '\'] = ';

			if (is_numeric($value))
				$instance_config .= $value;
			else
				$instance_config .= "'" . addslashes($value) . "'";

			$instance_config .= ";\n";
		}
	}
}

session_start();

if ($step == 0) {
	// Agreement
	$page['body'] = '
	<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" disabled>' . htmlentities(file_get_contents('LICENSE.md')) . '</textarea>
	<p style="text-align:center">
		<a href="?step=1">I have read and understood the agreement. Proceed to installation.</a>
	</p>';

	echo Element('page.html', $page);
} elseif ($step == 1) {
	// The HTTPS check doesn't work properly when in those arrays, so let's run it here and pass along the result during the actual check.
	$httpsvalue = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	$page['title'] = 'Pre-installation test';

	$can_exec = true;
	if (!function_exists('shell_exec'))
		$can_exec = false;
	elseif (in_array('shell_exec', array_map('trim', explode(', ', ini_get('disable_functions')))))
		$can_exec = false;
	elseif (ini_get('safe_mode'))
		$can_exec = false;
	elseif (trim(shell_exec('echo "TEST"')) !== 'TEST')
		$can_exec = false;

	if (!defined('PHP_VERSION_ID')) {
		$version = explode('.', PHP_VERSION);
		define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
	}

	// Required extensions
	$extensions = array(
		'PDO' => array(
			'installed' => extension_loaded('pdo'),
			'required' => true
		),
		'GD' => array(
			'installed' => extension_loaded('gd'),
			'required' => true
		),
		'Imagick' => array(
			'installed' => extension_loaded('imagick'),
			'required' => false
		),
		'OpenSSL' => array(
			'installed' => extension_loaded('openssl'),
			'required' => false
		)
	);

	$tests = array(
		array(
			'category' => 'PHP',
			'name' => 'PHP &ge; 7.4',
			'result' => PHP_VERSION_ID >= 50400,
			'required' => true,
			'message' => 'vichan requires PHP 7.4 or better.',
		),
		array(
			'category' => 'PHP',
			'name' => 'mbstring extension installed',
			'result' => extension_loaded('mbstring'),
			'required' => true,
			'message' => 'You must install the PHP <a href="http://www.php.net/manual/en/mbstring.installation.php">mbstring</a> extension.',
		),
		array(
			'category' => 'PHP',
			'name' => 'OpenSSL extension installed or PHP &ge; 7.0',
			'result' => (extension_loaded('openssl') || (defined('PHP_MAJOR_VERSION') && PHP_MAJOR_VERSION >= 7)),
			'required' => false,
			'message' => 'It is highly recommended that you install the PHP <a href="http://www.php.net/manual/en/openssl.installation.php">OpenSSL</a> extension and/or use PHP version 7 or above. <strong>If you do not, it is possible that the IP addresses of users of your site could be compromised &mdash; see <a href="https://github.com/vichan-devel/vichan/issues/284">vichan issue #284.</a></strong> Installing the OpenSSL extension allows vichan to generate a secure salt automatically for you.',
		),
		array(
			'category' => 'Database',
			'name' => 'PDO extension installed',
			'result' => extension_loaded('pdo'),
			'required' => true,
			'message' => 'You must install the PHP <a href="http://www.php.net/manual/en/intro.pdo.php">PDO</a> extension.',
		),
		array(
			'category' => 'Database',
			'name' => 'MySQL PDO driver installed',
			'result' => extension_loaded('pdo') && in_array('mysql', PDO::getAvailableDrivers()),
			'required' => true,
			'message' => 'The required <a href="http://www.php.net/manual/en/ref.pdo-mysql.php">PDO MySQL driver</a> is not installed.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'GD extension installed',
			'result' => extension_loaded('gd'),
			'required' => true,
			'message' => 'You must install the PHP <a href="http://www.php.net/manual/en/intro.image.php">GD</a> extension. GD is a requirement even if you have chosen another image processor for thumbnailing.',
		),
		array(
		 	'category' => 'Image processing',
		 	'name' => 'GD: JPEG',
			'result' => function_exists('imagecreatefromjpeg'),
			'required' => true,
			'message' => 'imagecreatefromjpeg() does not exist. This is a problem.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'GD: PNG',
			'result' => function_exists('imagecreatefrompng'),
			'required' => true,
			'message' => 'imagecreatefrompng() does not exist. This is a problem.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'GD: GIF',
			'result' => function_exists('imagecreatefromgif'),
			'required' => true,
			'message' => 'imagecreatefromgif() does not exist. This is a problem.',
		),
		array(
			'category' => 'Image processing',
			'name' => '`convert` (command-line ImageMagick)',
			'result' => $can_exec && shell_exec('which convert'),
			'required' => false,
			'message' => '(Optional) `convert` was not found or executable; command-line ImageMagick image processing cannot be enabled.',
			'effect' => function (&$config) { $config['thumb_method'] = 'convert'; },
		),
		array(
			'category' => 'Image processing',
			'name' => '`identify` (command-line ImageMagick)',
			'result' => $can_exec && shell_exec('which identify'),
			'required' => false,
			'message' => '(Optional) `identify` was not found or executable; command-line ImageMagick image processing cannot be enabled.',
		),
		array(
			'category' => 'Image processing',
			'name' => '`gm` (command-line GraphicsMagick)',
			'result' => $can_exec && shell_exec('which gm'),
			'required' => false,
			'message' => '(Optional) `gm` was not found or executable; command-line GraphicsMagick (faster than ImageMagick) cannot be enabled.',
			'effect' => function (&$config) { $config['thumb_method'] = 'gm'; },
		),
		array(
			'category' => 'Image processing',
			'name' => '`gifsicle` (command-line animted GIF thumbnailing)',
			'result' => $can_exec && shell_exec('which gifsicle'),
			'required' => false,
			'message' => '(Optional) `gifsicle` was not found or executable; you may not use `convert+gifsicle` for better animated GIF thumbnailing.',
			'effect' => function (&$config) { if ($config['thumb_method'] == 'gm')      $config['thumb_method'] = 'gm+gifsicle';
							  if ($config['thumb_method'] == 'convert') $config['thumb_method'] = 'convert+gifsicle'; },
		),
		array(
			'category' => 'Image processing',
			'name' => '`md5sum` (quick file hashing on GNU/Linux)',
			'prereq' => '',
			'result' => $can_exec && shell_exec('echo "vichan" | md5sum') == "141225c362da02b5c359c45b665168de  -\n",
			'required' => false,
			'message' => '(Optional) `md5sum` was not found or executable; file hashing for multiple images will be slower. Ignore if not using Linux.',
			'effect' => function (&$config) { $config['gnu_md5'] = true; },
		),
		array(
			'category' => 'Image processing',
			'name' => '`/sbin/md5` (quick file hashing on BSDs)',
			'result' => $can_exec && shell_exec('echo "vichan" | /sbin/md5 -r') == "141225c362da02b5c359c45b665168de\n",
			'required' => false,
			'message' => '(Optional) `/sbin/md5` was not found or executable; file hashing for multiple images will be slower. Ignore if not using BSD.',
			'effect' => function (&$config) { $config['bsd_md5'] = true; },
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd(),
			'result' => is_writable('.'),
			'required' => true,
			'message' => 'vichan does not have permission to create directories (boards) here. You will need to <code>chmod</code> (or operating system equivalent) appropriately.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/templates/cache',
			'result' => is_dir('templates/cache/') && is_writable('templates/cache/'),
			'required' => true,
			'message' => 'You must give vichan permission to create (and write to) the <code>templates/cache</code> directory or performance will be drastically reduced.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/tmp/cache',
			'result' => is_dir('tmp/cache/') && is_writable('tmp/cache/'),
			'required' => true,
			'message' => 'You must give vichan permission to write to the <code>tmp/cache</code> directory.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/inc/secrets.php',
			'result' => is_writable('inc/secrets.php'),
			'required' => false,
			'message' => 'vichan does not have permission to make changes to <code>inc/secrets.php</code>. To complete the installation, you will be asked to manually copy and paste code into the file instead.'
		),
		array(
			'category' => 'Misc',
			'name' => 'HTTPS being used',
			'result' => $httpsvalue,
			'required' => false,
			'message' => 'You are not currently using https for vichan, or at least for your backend server. If this intentional, add "$config[\'cookies\'][\'secure_login_only\'] = 0;" (or 1 if using a proxy) on a new line under "Additional configuration" on the next page.'
		),
		array(
			'category' => 'Misc',
			'name' => 'Caching available (APCu, Memcached or Redis)',
			'result' => extension_loaded('apcu') || extension_loaded('memcached') || extension_loaded('redis'),
			'required' => false,
			'message' => 'You will not be able to enable the additional caching system, designed to minimize SQL queries and significantly improve performance. <a href="https://www.php.net/manual/en/book.apcu.php">APCu</a> is the recommended method of caching, but <a href="http://www.php.net/manual/en/intro.memcached.php">Memcached</a> and <a href="http://pecl.php.net/package/redis">Redis</a> are also supported.'
		),
		array(
			'category' => 'Misc',
			'name' => 'vichan installed using git',
			'result' => is_dir('.git'),
			'required' => false,
			'message' => 'vichan is still beta software and it\'s not going to come out of beta any time soon. As there are often many months between releases yet changes and bug fixes are very frequent, it\'s recommended to use the git repository to maintain your vichan installation. Using git makes upgrading much easier.'
		)
	);

	$config['font_awesome'] = true;

	$additional_config = array();
	foreach ($tests as $test) {
		if ($test['result'] && isset($test['effect'])) {
			$test['effect']($additional_config);
		}
	}
	$more = '';
	create_config_from_array($more, $additional_config);
	$_SESSION['more'] = $more;

	echo Element('page.html', array(
		'body' => Element('installer/check-requirements.html', array(
			'extensions' => $extensions,
			'tests' => $tests,
			'config' => $config,
		)),
		'title' => 'Checking environment',
		'config' => $config,
	));
} elseif ($step == 2) {
	$page['title'] = 'Configuration';
	$sg = new SaltGen();

	// Initialize configuration with defaults and override with environment variables
	$config['cookies'] = array(
		'mod' => getenv('VICHAN_COOKIES_MOD') !== false ? getenv('VICHAN_COOKIES_MOD') : 'mod',
		'salt' => $sg->generate(),
	);

	$config['flood_time'] = getenv('VICHAN_FLOOD_TIME') !== false ? (int)getenv('VICHAN_FLOOD_TIME') : 30;
	$config['flood_time_ip'] = getenv('VICHAN_FLOOD_TIME_IP') !== false ? (int)getenv('VICHAN_FLOOD_TIME_IP') : 120;
	$config['flood_time_same'] = getenv('VICHAN_FLOOD_TIME_SAME') !== false ? (int)getenv('VICHAN_FLOOD_TIME_SAME') : 3600;
	$config['max_body'] = getenv('VICHAN_MAX_BODY') !== false ? (int)getenv('VICHAN_MAX_BODY') : 1800;
	$config['reply_limit'] = getenv('VICHAN_REPLY_LIMIT') !== false ? (int)getenv('VICHAN_REPLY_LIMIT') : 250;
	$config['max_links'] = getenv('VICHAN_MAX_LINKS') !== false ? (int)getenv('VICHAN_MAX_LINKS') : 20;
	
	$config['max_filesize'] = getenv('VICHAN_IMAGES_MAX_FILESIZE') !== false ? (int)getenv('VICHAN_IMAGES_MAX_FILESIZE') : 10485760; // This is 10MB
	$config['thumb_width'] = getenv('VICHAN_IMAGES_THUMB_WIDTH') !== false ? (int)getenv('VICHAN_IMAGES_THUMB_WIDTH') : 250;
	$config['thumb_height'] = getenv('VICHAN_IMAGES_THUMB_HEIGHT') !== false ? (int)getenv('VICHAN_IMAGES_THUMB_HEIGHT') : 250;
	$config['max_width'] = getenv('VICHAN_IMAGES_MAX_WIDTH') !== false ? (int)getenv('VICHAN_IMAGES_MAX_WIDTH') : 10000;
	$config['max_height'] = getenv('VICHAN_IMAGES_MAX_HEIGHT') !== false ? (int)getenv('VICHAN_IMAGES_MAX_HEIGHT') : 10000;
	
	$config['threads_per_page'] = getenv('VICHAN_DISPLAY_THREADS_PER_PAGE') !== false ? (int)getenv('VICHAN_DISPLAY_THREADS_PER_PAGE') : 10;
	$config['max_pages'] = getenv('VICHAN_DISPLAY_MAX_PAGES') !== false ? (int)getenv('VICHAN_DISPLAY_MAX_PAGES') : 11;
	$config['threads_preview'] = getenv('VICHAN_DISPLAY_THREADS_PREVIEW') !== false ? (int)getenv('VICHAN_DISPLAY_THREADS_PREVIEW') : 5;
	
	$config['root'] = getenv('VICHAN_DIRECTORIES_ROOT') !== false ? getenv('VICHAN_DIRECTORIES_ROOT') : '/';
	
	$config['secure_trip_salt'] = $sg->generate();
	$config['secure_password_salt'] = $sg->generate();
	
	// Set database configuration from Docker environment variables, leave empty if not found
	$config['db'] = array(
		'type' => 'mysql', // Default, required for MySQL
		'server' => getenv('VICHAN_MYSQL_HOST') !== false ? getenv('VICHAN_MYSQL_HOST') : '',
		'database' => getenv('VICHAN_MYSQL_NAME') !== false ? getenv('VICHAN_MYSQL_NAME') : '',
		'user' => getenv('VICHAN_MYSQL_USER') !== false ? getenv('VICHAN_MYSQL_USER') : '',
		'password' => getenv('VICHAN_MYSQL_PASSWORD') !== false ? getenv('VICHAN_MYSQL_PASSWORD') : '',
	);
	
	// Append secure_login_only to $_SESSION['more'] if VICHAN_SECURE_LOGIN_ONLY is set
	if (getenv('VICHAN_SECURE_LOGIN_ONLY') !== false) {
		$secure_login_only = (int)getenv('VICHAN_SECURE_LOGIN_ONLY');
		$_SESSION['more'] .= "\n\$config['cookies']['secure_login_only'] = $secure_login_only;";
	}

	// Configuration notice at the top
	$page['body'] = '<div class="ban"><h2>Configuration Note</h2>' .
					'<p style="text-align:center;">The following settings can still be configured later. For more customization options, <a href="https://github.com/vichan-devel/vichan/wiki/config" target="_blank" rel="noopener noreferrer">check the Vichan configuration wiki.</a></p></div>';

	// Append the configuration form
	$page['body'] .= Element('installer/config.html', array(
		'config' => $config,
		'more' => $_SESSION['more'],
	));

	echo Element('page.html', array(
		'body' => $page['body'],
		'title' => 'Configuration',
		'config' => $config
	));
} elseif ($step == 3) {
	$more = $_POST['more'];
	unset($_POST['more']);

	$instance_config =
'<'.'?php

/*
*  Instance Configuration
*  ----------------------
*  Edit this file and not config.php for imageboard configuration.
*
*  You can copy values from config.php (defaults) and paste them here.
*/

';

	create_config_from_array($instance_config, $_POST);

	$instance_config .= "\n";
	$instance_config .= $more;
	$instance_config .= "\n";

	if (@file_put_contents('inc/secrets.php', $instance_config)) {
		// flushes opcache if php >= 5.5.0 or opcache is installed via PECL
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate('inc/secrets.php');
		}
		header('Location: ?step=4', true, $config['redirect_http']);
	} else {
		$page['title'] = 'Manual installation required';
		$page['body'] = '
			<p>I couldn\'t write to <strong>inc/secrets.php</strong> with the new configuration, probably due to a permissions error.</p>
			<p>Please complete the installation manually by copying and pasting the following code into the contents of <strong>inc/secrets.php</strong>:</p>
			<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black">' . htmlentities($instance_config) . '</textarea>
			<p style="text-align:center">
				<a href="?step=4">Once complete, click here to complete installation.</a>
			</p>
		';
		echo Element('page.html', $page);
	}
} elseif ($step == 4) {
	buildJavascript();

	$sql = @file_get_contents('install.sql') or error("Couldn't load install.sql.");

	sql_open();
	$mysql_version = mysql_version();

	// This code is probably horrible, but what I'm trying
	// to do is find all of the SQL queires and put them
	// in an array.
	preg_match_all("/(^|\n)((SET|CREATE|INSERT).+)\n\n/msU", $sql, $queries);
	$queries = $queries[2];

	$queries[] = Element('posts.sql', array('board' => 'b'));

	$sql_errors = '';
	$sql_err_count = 0;
	foreach ($queries as $query) {
		if ($mysql_version < 50503)
		$query = preg_replace('/(CHARSET=|CHARACTER SET )utf8mb4/', '$1utf8', $query);
		$query = preg_replace('/^([\w\s]*)`([0-9a-zA-Z$_\x{0080}-\x{FFFF}]+)`/u', '$1``$2``', $query);
		if (!query($query)) {
			$sql_err_count++;
			$error = db_error();
			$sql_errors .= "<li>$sql_err_count<ul><li>$query</li><li>$error</li></ul></li>";
		}
	}

	$page['title'] = 'Installation complete';
	$page['body'] = '<p style="text-align:center">Thank you for using vichan. <a href="https://github.com/vichan-devel/vichan/issues/new/choose" target="_blank" rel="noopener noreferrer">Please report any bugs you discover.</a></p>' .
					'<p style="text-align:center">If you are new to vichan, <a href="https://github.com/vichan-devel/vichan/wiki" target="_blank" rel="noopener noreferrer">please check out the documentation.</a></p>';

	// Admin panel notice
	$page['body'] .= '<div class="ban"><h2>Next Steps</h2>' .
					 '<p>You can now log in to the admin panel at <strong>/mod.php</strong> using the default credentials:</p>' .
					 '<p><strong>Username:</strong> admin</p>' .
					 '<p><strong>Password:</strong> password</p>' .
					 '<p><strong>Important:</strong> For security, please change the administrator password immediately after logging in.</p>' .
					 '<p style="text-align:center"><a href="/mod.php"><button>Go to Admin Panel</button></a></p></div>';


	if (!empty($sql_errors)) {
		$page['body'] .= '<div class="ban"><h2>SQL errors</h2><p>SQL errors were encountered when trying to install the database. This may be the result of using a database which is already occupied with a vichan installation; if so, you can probably ignore this.</p><p>The errors encountered were:</p><ul>' . $sql_errors . '</ul>' .
						 '<p style="text-align:center;color:#d00"><strong>Warning:</strong> Ignoring errors is not recommended and may cause installation issues.</p>' .
						 '<p style="text-align:center"><a href="?step=5"><button>Next</button></a></p></div>';
	} else {
		$boards = listBoards();
		foreach ($boards as &$_board) {
			setupBoard($_board);
			buildIndex();
		}

		file_write($config['has_installed'], VERSION);
		/*if (!file_unlink(__FILE__)) {
			$page['body'] .= '<div class="ban"><h2>Delete install.php!</h2><p>I couldn\'t remove <strong>install.php</strong>. You will have to remove it manually.</p></div>';
		}*/
	}

	echo Element('page.html', $page);
} elseif ($step == 5) {
	$page['title'] = 'Installation complete';
	$page['body'] = '<p style="text-align:center">Thank you for using vichan. Please report any bugs you discover.</p>' .
					'<p style="text-align:center">If you are new to vichan, <a href="https://github.com/vichan-devel/vichan/wiki">please check out the documentation</a>.</p>';

	// Admin panel notice
	$page['body'] .= '<div class="ban"><h2>Next Steps</h2>' .
					'<p>You can now log in to the admin panel at <strong>/mod.php</strong> using the default credentials:</p>' .
					'<p><strong>Username:</strong> admin</p>' .
					'<p><strong>Password:</strong> password</p>' .
					'<p><strong>Important:</strong> For security, please change the administrator password immediately after logging in.</p>' .
					'<p style="text-align:center"><a href="/mod.php"><button>Go to Admin Panel</button></a></p></div>';

	$boards = listBoards();
	foreach ($boards as &$_board) {
		setupBoard($_board);
		buildIndex();
	}

	file_write($config['has_installed'], VERSION);
	if (!file_unlink(__FILE__)) {
		$page['body'] .= '<div class="ban"><h2>Delete install.php!</h2><p>I couldn\'t remove <strong>install.php</strong>. You will have to remove it manually.</p></div>';
	}

	echo Element('page.html', $page);
}
