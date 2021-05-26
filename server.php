<?php
	function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (is_dir($dir.DIRECTORY_SEPARATOR.$object) && !is_link($dir.'/'.$object))
						rrmdir($dir.DIRECTORY_SEPARATOR.$object);
					else
						unlink($dir.DIRECTORY_SEPARATOR.$object);
				}
			}
			rmdir($dir);
		}
	}


	function str_starts_with( $haystack, $needle ) {
		$length = strlen( $needle );
		return substr($haystack, 0, $length) === $needle;
	}


	function copyr($source, $dest){
		if (is_dir($source)) {
			$dir_handle = opendir($source);
			while ($file = readdir($dir_handle)) {
				if ($file != "." && $file != "..") {
					if (is_dir($source."/".$file)) {
						if(!is_dir($dest."/".$file))
							mkdir($dest."/".$file);
						copyr($source."/".$file, $dest."/".$file);
					} else
						copy($source."/".$file, $dest."/".$file);
				}
			}
			closedir($dir_handle);
		} else
			copy($source, $dest);
	}


	function is_dir_empty($dir) {
		return count(scandir($dir)) == 2;
	}


	function is_in_blacklist($path) {
		global $conf;
		if (isset($conf['BLACKLIST'])) {
			foreach ($conf['BLACKLIST'] as $black_folder) {
				if (strpos($path, $black_folder) !== false)
					return true;
			}
		}
		return false;
	}


	function scandir_sorted($dir) {
		$result = array();
		$files  = array();
		$scan = scandir($dir);
		foreach($scan as $sc)
			if (($sc != '.') && ($sc != '..'))
				if (is_dir($dir.'/'.$sc))
					$result[] = $sc;
				else
					$files[] = $sc;
		return array_merge($result, $files);
	}


	function zip_directory($current_dir, $source, $destination) {
		global $conf, $result_pattern;
		if (!$conf['ALLOW_ZIP']) {
			$result_pattern['message'] = 'Zipping not allowed. You can allow it in '.$conf['CONF_FNAME'];
			return $result_pattern;
		}
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		$source = rtrim($source, '/\\');
		$path = $conf['EXPOSE_DIR'].$current_dir.$source;
		if (is_in_blacklist($path)) {
			$result_pattern['message'] = 'Permission denied. Directory in blacklist';
			return $result_pattern;
		}
		if (strpos($path, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
			return $result_pattern;
		}
		$destination = $conf['EXPOSE_DIR'].$current_dir.$destination;
		if (!extension_loaded('zip')) {
			$result_pattern['message'] = 'Failed to load Zip extension!';
			return $result_pattern;
		}
		if (!is_dir($path)) {
			$result_pattern['message'] = 'Directory '.$source.' does not exists!';
			return $result_pattern;
		}
		if (is_dir_empty($path)) {
			$result_pattern['message'] = 'Directory '.$source.' is empty!';
			return $result_pattern;
		}
		if (file_exists($destination) && !$conf['ALLOW_ZIP_OVERRIDE']) {
			$result_pattern['message'] = 'Zip file already exists. You can allow it in '.$conf['CONF_FNAME'];
			return $result_pattern;
		}
		$zip = new ZipArchive();
		if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
			$result_pattern['message'] = 'Couldn\'t create zip file';
			return $result_pattern;
		}

		$abs_source = str_replace('\\', '/', realpath($path));
		chdir(realpath($conf['EXPOSE_DIR'].$current_dir));
		if (is_dir($source)) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
			foreach ($files as $file) {
				$file = str_replace('\\', '/', $file);
				if (in_array(substr($file, strrpos($file, '/') + 1), ['.', '..']))
					continue;
				if (is_dir($file))
					$zip->addEmptyDir(str_replace($source.'/', '', $file.'/'));
				else if (is_file($file))
					$zip->addFromString(str_replace($source.'/', '', $file), file_get_contents($file));
			}
		}
		else if (is_file($source))
			$zip->addFromString(basename($source), file_get_contents($source));
		$zip->close();
		$result_pattern['status'] = 'ok';
		$result_pattern['message'] = 'Zipfile has been created';
		return $result_pattern;
	}


	function validate_config_file($config_file) {
		if (!file_exists($config_file))
			return 'Config file '.$config_file.' does not exists!';
		$conf = file_get_contents($config_file);
		if (strpos($conf, ';') !== false)
			return 'Invalid config file: Your config file should not contain ";" symbol';
		$all_confs = ['EXPOSE_DIR', 'ALLOW_UPLOAD_OVERRIDE', 'ALLOW_ZIP_OVERRIDE', 'ALLOW_DELETE', 'ALLOW_ZIP', 'ALLOW_UPLOAD', 'ALLOW_MOVE', 'ALLOW_COPY', 'ALLOW_NEWDIR', 'BLACKLIST', 'MAX_UPLOAD_SIZE', 'MAX_EXECUTION_TIME'];
		$mandatory_confs = ['EXPOSE_DIR', 'ALLOW_UPLOAD_OVERRIDE', 'ALLOW_ZIP_OVERRIDE', 'ALLOW_DELETE', 'ALLOW_ZIP', 'ALLOW_UPLOAD', 'ALLOW_MOVE', 'ALLOW_COPY', 'ALLOW_NEWDIR', 'MAX_UPLOAD_SIZE', 'MAX_EXECUTION_TIME'];
		$bool_confs = ['ALLOW_UPLOAD_OVERRIDE', 'ALLOW_ZIP_OVERRIDE', 'ALLOW_DELETE', 'ALLOW_ZIP', 'ALLOW_UPLOAD', 'ALLOW_MOVE', 'ALLOW_COPY', 'ALLOW_NEWDIR'];
		$conf = explode("\n", $conf);
		$conf_len = count($conf);
		for ($i = 0; $i < $conf_len; ++ $i) {
			$conf[$i] = trim(explode('#', $conf[$i], 2)[0]);
			if ($conf[$i]) {
				$this_config = explode('=', $conf[$i], 2);
				if (!in_array(trim($this_config[0]), $all_confs))
					return 'Invalid config: Unknown key '.trim($this_config[0]);
				$conf[trim($this_config[0])] = trim($this_config[1]);
			}
		}
		// Remove numeric indexes
		$conf = array_intersect_key($conf, array_flip(array_filter(array_keys($conf), 'is_string')));
		foreach ($mandatory_confs as $key)
			if (!isset($conf[$key]))
				return 'Invalid config: '.$key.' is not set!';
		foreach ($bool_confs as $key)
			if (!in_array(strtolower($conf[$key]), ['true', 'false']))
				return 'Invalid config: '.$key.' should be equal to true or false!';
			else
				$conf[$key] = strtolower($conf[$key]) == 'true';
		$max_upload_measure = substr($conf['MAX_UPLOAD_SIZE'], -1);
		$max_upload_size = substr($conf['MAX_UPLOAD_SIZE'], 0, -1);
		if (strpos('MG', $max_upload_measure) === false || !is_numeric($max_upload_size))
			return 'Invalid config: MAX_UPLOAD_SIZE should be numeric ending with M or G';
		$max_upload_size = intval($max_upload_size);
		if ($max_upload_measure == 'G')
			$max_upload_size *= 1024;
		ini_set('memory_limit', strval($max_upload_size + 1).'M');
		ini_set('post_max_size', strval($max_upload_size + 1).'M');
		ini_set('upload_max_filesize', strval($max_upload_size).'M');
		if (!is_numeric($conf['MAX_EXECUTION_TIME']))
			return 'Invalid config: MAX_EXECUTION_TIME should be numeric (seconds)';
		$conf['MAX_EXECUTION_TIME'] = intval($conf['MAX_EXECUTION_TIME']);
		ini_set('max_execution_time', $conf['MAX_EXECUTION_TIME']);
		$conf['MAX_UPLOAD_SIZE'] = strval($max_upload_size).'M';
		$conf['CONF_FNAME'] = $config_file;
		$conf['EXPOSE_DIR'] = rtrim($conf['EXPOSE_DIR'], '/\\');
		if (!is_dir($conf['EXPOSE_DIR']))
			mkdir($conf['EXPOSE_DIR'], 0755, true);
		$conf['EXPOSE_DIR'] = str_replace('\\', '/', realpath($conf['EXPOSE_DIR']));
		if (isset($conf['BLACKLIST'])) {
			if (!$conf['BLACKLIST'])
				return 'Invalid config: BLACKLIST cannot be empty if defined!';
			$conf['BLACKLIST'] = str_replace(', ', ',', $conf['BLACKLIST']);
			$conf['BLACKLIST'] = explode(',', $conf['BLACKLIST']);
			foreach ($conf['BLACKLIST'] as $index=>$black_folder) {
				$path = $conf['EXPOSE_DIR'].'/'.trim($black_folder, '/\\');
				if (!is_dir($path) && !file_exists($path))
					return 'Invalid config: Directory or file '.$black_folder.' is in BLACKLIST, but cannot be found<br>'.$path;
				else
					$conf['BLACKLIST'][$index] = $path;
			}
		}
		return $conf;
	}


	function list_dir($current_dir, $dir) {
		global $conf, $result_pattern;
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		$dir = rtrim($dir, '/\\');
		$path = $conf['EXPOSE_DIR'].$current_dir.$dir.'/';
		if (strpos($path, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
			return $result_pattern;
		}
		if (is_in_blacklist($path)) {
			$result_pattern['message'] = 'Permission denied. Directory in blacklist';
			return $result_pattern;
		}
		if (is_dir($path)) {
			$dir_content = scandir_sorted($conf['EXPOSE_DIR'].$current_dir.$dir);
			$result_pattern['status'] = 'ok';
			$result_pattern['current_dir'] = $current_dir.$dir;
			if (!count($dir_content))
				$result_pattern['message'] .= '<tr><td><p style="margin-left: 20px;">Directory is empty...</p></tr>';
			else
				foreach ($dir_content as $dc) {
					if (is_in_blacklist($path.$dc))
						continue;
					if (is_dir($path.$dc)) {
						$result_pattern['message'] .= '<tr><td><i class="fas fa-folder"></i></td>';
						$result_pattern['message'] .= '<td><a href="#" data-dir="'.$dc.'" onclick="return false;" class="directory">'.$dc.'</a></td>';
						$result_pattern['message'] .= '<td class="row">';
						if ($conf['ALLOW_DELETE']) {
							$result_pattern['message'] .= '<button type="submit" data-dir="'.$dc.'" class="button delete_directory" title="delete">';
							$result_pattern['message'] .= '<i class="fas fa-trash"></i></button>';
						}
						if ($conf['ALLOW_ZIP']) {
							$result_pattern['message'] .= '<button type="submit" data-dir="'.$dc.'" class="button zip_directory" title="zip">';
							$result_pattern['message'] .= '<i class="far fa-file-archive"></i></button>';
						}
						$result_pattern['message'] .= '</td></tr>';
					}
					else {
						$result_pattern['message'] .= '<tr><td><i class="fas fa-file"></i></td>';
						$result_pattern['message'] .= '<td><a href="#" data-file="'.$dc.'" onclick="return false;" class="file">'.$dc.'</a></td>';
						if ($conf['ALLOW_DELETE']) {
							$result_pattern['message'] .= '<td class="row">';
							$result_pattern['message'] .= '<button type="submit" data-file="'.$dc.'" class="button delete_file" title="delete">';
							$result_pattern['message'] .= '<i class="fas fa-trash"></i></button></td>';
						}
						$result_pattern['message'] .= '</tr>';
					}
				}
		}
		else
			$result_pattern['message'] = 'Directory '.$dir.' does not exists!';
		return $result_pattern;
	}


	function download_file($current_dir, $down_file) {
		global $conf, $result_pattern;
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		$path = $conf['EXPOSE_DIR'].$current_dir.$down_file;
		if (is_in_blacklist($path)) {
			$result_pattern['message'] = 'Permission denied. File in blacklist';
			header('Response: '.json_encode($result_pattern));
			return;
		}
		if (strpos($path, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
			header('Response: '.json_encode($result_pattern));
			return;
		}
		if (file_exists($path)) {
			$result_pattern['status'] = 'ok';
			$result_pattern['message'] = 'File '.$down_file.' is downloading';
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($path));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: '.filesize($path));
			header('Response: '.json_encode($result_pattern));
			ob_clean();
			flush();
			# readfile($path);
			$file = fopen($path, 'rb');
			while (!feof($file)) {
				echo fread($file, 1024*128);
				ob_flush();
				flush();
			}
		}
		else {
			$result_pattern['message'] = 'File '.$down_file.' does not exists!';
			header('Response: '.json_encode($result_pattern));
		}
	}


	function create_dir($current_dir, $new_dir) {
		global $conf, $result_pattern;
		if (!$conf['ALLOW_NEWDIR']) {
			$result_pattern['message'] = 'Creating directory not allowed. You can allow it in '.$conf['CONF_FNAME'];
			return $result_pattern;
		}
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		$new_dir = trim($new_dir);
		if (!$new_dir)
			$result_pattern['message'] = 'Directory name cannot be empty';
		else {
			$dir = $conf['EXPOSE_DIR'].$current_dir.$new_dir;
			if (is_in_blacklist($dir)) {
				$result_pattern['message'] = 'Permission denied. Directory in blacklist';
				return $result_pattern;
			}
			if (strpos($dir, '..') != false) {
				$result_pattern['message'] = 'Permission denied';
				return $result_pattern;
			}
			if (!is_dir($dir)) {
				mkdir($dir, 0755, true);
				$result_pattern['status'] = 'ok';
				$result_pattern['message'] = 'Directory '.$new_dir.' has been created';
			}
			else
				$result_pattern['message'] = 'Directory '.$new_dir.' already exsits';
		}
		return $result_pattern;
	}


	function delete_dir($current_dir, $dir) {
		global $conf, $result_pattern;
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		$path = $conf['EXPOSE_DIR'].$current_dir.$dir;
		if (is_in_blacklist($path)) {
			$result_pattern['message'] = 'Permission denied. Directory in blacklist';
			return $result_pattern;
		}
		if (strpos($path, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
			return $result_pattern;
		}
		if (!$conf['ALLOW_DELETE']) {
			$result_pattern['message'] = 'Delete not allowed.  You can allow it in '.$conf['CONF_FNAME'];
			return $result_pattern;
		}
		if (is_dir($path)) {
			rrmdir($path);
			$result_pattern['status'] = 'ok';
			$result_pattern['message'] = 'Directory '.$dir.' has been removed';
		}
		else
			$result_pattern['message'] = 'Directory '.$dir.' has not been removed';
		return $result_pattern;
	}


	function upload_files($current_dir, $files) {
		global $conf, $result_pattern;
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		if (!$conf['ALLOW_UPLOAD']) {
			$result_pattern['message'] = 'Uploading not allowed. You can allow it in '.$conf['CONF_FNAME'];
			return $result_pattern;
		}
		if (strpos($current_dir, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
			return $result_pattern;
		}
		$path = $conf['EXPOSE_DIR'].$current_dir;
		if (is_in_blacklist($path)) {
			$result_pattern['message'] = 'Permission denied. Directory in blacklist';
			return $result_pattern;
		}
		$total = count($files['upload_files']['name']);
		for($i = 0; $i < $total; ++ $i) {
			$tmpFilePath = $files['upload_files']['tmp_name'][$i];
			if ($tmpFilePath != '') {
				$newFilePath = $path.$files['upload_files']['name'][$i];
				if (is_file($newFilePath) && !$conf['ALLOW_UPLOAD_OVERRIDE'])
					$result_pattern['message'] .= 'File '.$files['upload_files']['name'][$i].' already exist. You can allow it in '.$conf['CONF_FNAME'];
				elseif (is_in_blacklist($newFilePath))
					$result_pattern['message'] .= 'Permission denied. File '.$files['upload_files']['name'][$i].' in blacklist';
				else
					if (!move_uploaded_file($tmpFilePath, $newFilePath))
						$result_pattern['message'] .= 'Couldn\'t upload file '.$files['upload_files']['name'][$i].'<br>';
			}
		}
		if (!$result_pattern['message']) {
			$result_pattern['status'] = 'ok';
			$result_pattern['message'] = $total == 1 ? 'File is uploaded' : 'All files are uploaded';
		}
		return $result_pattern;
	}


	function delete_file($current_dir, $file) {
		global $conf, $result_pattern;
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		$path = $conf['EXPOSE_DIR'].$current_dir.$file;
		if (is_in_blacklist($path)) {
			$result_pattern['message'] = 'Permission denied. File in blacklist';
			return $result_pattern;
		}
		if (strpos($path, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
			return $result_pattern;
		}
		if (!$conf['ALLOW_DELETE']) {
			$result_pattern['message'] = 'Delete not allowed.  You can allow it in '.$conf['CONF_FNAME'];
			return $result_pattern;
		}
		if (file_exists($path)) {
			if (!unlink($path))
				$result_pattern['message'] = 'Couldn\'t delete file '.$file;
			else {
				$result_pattern['status'] = 'ok';
				$result_pattern['message'] = 'File '.$file.' is deleted';
			}
		} else
			$result_pattern['message'] = 'File '.$file.' does not exists!';
		return $result_pattern;
	}

	function move_($target, $target_dir, $rename_to) {
		global $conf, $result_pattern;
		if (!$conf['ALLOW_MOVE']) {
			$result_pattern['message'] = 'Moving not allowed. You can allow it in '.$conf['CONF_FNAME'];
			return $result_pattern;
		}
		if (!$target || !$target_dir) {
			$result_pattern['message'] = 'Target or target directory is empty!';
			return $result_pattern;
		}
		if ($rename_to) {
			if (strpos($rename_to, '/') !== false || strpos($rename_to, '\\') !== false || strpos($rename_to, '..') !== false) {
				$result_pattern['message'] = 'Invalid rename value';
				return $result_pattern;
			}
		}
		$target = str_replace('\\', '/', $target);
		$target_dir = str_replace('\\', '/', $target_dir);
		$target = trim($target, '/');
		$target_dir = trim($target_dir, '/');
		$path = $conf['EXPOSE_DIR'].'/'.$target;
		$target_path = $conf['EXPOSE_DIR'].'/'.$target_dir;
		if (is_in_blacklist($path) || is_in_blacklist($target_path)) {
			$result_pattern['message'] = 'Permission denied. Target or target directory in Blacklist';
			return $result_pattern;
		}
		if (strpos($path, '..') != false || strpos($target_path, '..') != false ) {
			$result_pattern['message'] = 'Permission denied';
			return $result_pattern;
		}
		if (!is_dir($target_path)) {
			$result_pattern['message'] = 'Target directory '.$target_dir.' not found!';
			return $result_pattern;
		}
		if (file_exists($path) || is_dir($path)) {
			$split_path = explode('/', $path);
			$split_target_path = explode('/', $target_path);
			$final_rename = implode('/', $split_target_path).'/'.($rename_to ? $rename_to : end($split_path));
			set_error_handler(function($errno, $errstr, $errfile, $errline) {
				if (0 === error_reporting())
					return false;
				throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
			});
			try {
				if (file_exists($final_rename) || is_dir($final_rename)) {
					$result_pattern['status'] = 'error';
					$result_pattern['message'] = 'Target to write already exists!';
					return $result_pattern;
				}
				rename($path, $final_rename);
				$result_pattern['status'] = 'ok';
				$result_pattern['message'] = 'Target has been moved';
			}
			catch (ErrorException $e) {
				$result_pattern['status'] = 'error';
				$result_pattern['message'] = 'Could not move target';
			}
		}
		else
			$result_pattern['message'] = 'Target '.$target.' not found!';
		return $result_pattern;
	}

	function copy_($target, $target_dir, $rename_to) {
		global $conf, $result_pattern;
		if (!$conf['ALLOW_COPY']) {
			$result_pattern['message'] = 'Copying not allowed. You can allow it in '.$conf['CONF_FNAME'];
			return $result_pattern;
		}
		if (!$target || !$target_dir) {
			$result_pattern['message'] = 'Target or target directory is empty!';
			return $result_pattern;
		}
		if ($rename_to) {
			if (strpos($rename_to, '/') !== false || strpos($rename_to, '\\') !== false || strpos($rename_to, '..') !== false) {
				$result_pattern['message'] = 'Invalid rename value';
				return $result_pattern;
			}
		}
		$target = str_replace('\\', '/', $target);
		$target_dir = str_replace('\\', '/', $target_dir);
		$target = trim($target, '/');
		$target_dir = trim($target_dir, '/');
		$path = $conf['EXPOSE_DIR'].'/'.$target;
		$target_path = $conf['EXPOSE_DIR'].'/'.$target_dir;
		if (is_in_blacklist($path) || is_in_blacklist($target_path)) {
			$result_pattern['message'] = 'Permission denied. Target or target directory in blacklist';
			return $result_pattern;
		}
		if (strpos($path, '..') != false || strpos($target_path, '..') != false ) {
			$result_pattern['message'] = 'Permission denied';
			return $result_pattern;
		}
		if (!is_dir($target_path)) {
			$result_pattern['message'] = 'Target directory '.$target_dir.' not found!';
			return $result_pattern;
		}
		if (file_exists($path) || is_dir($path)) {
			$split_path = explode('/', $path);
			$split_target_path = explode('/', $target_path);
			$final_rename = implode('/', $split_target_path).'/'.($rename_to ? $rename_to : end($split_path));
			set_error_handler(function($errno, $errstr, $errfile, $errline) {
				if (0 === error_reporting())
					return false;
				throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
			});
			try {
				if (file_exists($final_rename) || is_dir($final_rename)) {
					$result_pattern['status'] = 'error';
					$result_pattern['message'] = 'Target to write already exists!';
					return $result_pattern;
				}
				if (!is_dir($final_rename) && is_dir($path))
					mkdir($final_rename, 0755, true);
			}
			catch (ErrorException $e) {
				$result_pattern['status'] = 'error';
				$result_pattern['message'] = 'Could not copy target';
			}
			try {
				copyr($path, $final_rename);
				$result_pattern['status'] = 'ok';
				$result_pattern['message'] = 'Target has been copied';
			}
			catch (ErrorException $e) {
				$result_pattern['status'] = 'error';
				$result_pattern['message'] = 'Could not copy target';
			}
		}
		else
			$result_pattern['message'] = 'Target '.$target.' not found!';
		return $result_pattern;
	}

	function get_upload_limit_size() {
		global $conf;
		return intval(substr($conf['MAX_UPLOAD_SIZE'], 0, -1));
	}

	/* ENTRY POINT */
	$config_path = 'rpift.conf';
	$result_pattern = ['status'=>'error', 'message'=>''];
	$conf = validate_config_file($config_path);
	if (is_string($conf)) {
		$result_pattern['message'] = $conf;
		echo json_encode($result_pattern);
		die();
	}

	/* GET REQUESTS */
	if ($_GET) {
		if (isset($_GET['list_dir']))
			echo json_encode(list_dir($_GET['current_dir'], $_GET['directory']));
		if (isset($_GET['download_file']))
			download_file($_GET['current_dir'], $_GET['file']);
		if (isset($_GET['upload_limit_size']))
			echo get_upload_limit_size();
	}

	/* POST REQUESTS */
	if ($_POST) {
		if (isset($_POST['create_directory']))
			echo json_encode(create_dir($_POST['current_dir'], $_POST['new_directory']));
		if (isset($_POST['delete_directory']))
			echo json_encode(delete_dir($_POST['current_dir'], $_POST['deleting_directory']));
		if (isset($_POST['zip_directory']))
			echo json_encode(zip_directory($_POST['current_dir'], $_POST['zipping_directory'], $_POST['zipping_directory'].'.zip'));
		if (isset($_POST['upload']))
			echo json_encode(upload_files($_POST['current_dir'], $_FILES));
		if (isset($_POST['delete_file']))
			echo json_encode(delete_file($_POST['current_dir'], $_POST['deleting_file']));
		if (isset($_POST['move']))
			echo json_encode(move_($_POST['target'], $_POST['target_dir'], $_POST['rename_to']));
		if (isset($_POST['copy']))
			echo json_encode(copy_($_POST['target'], $_POST['target_dir'], $_POST['rename_to']));
	}
?>