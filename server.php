<?php
	function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir.'/'.$object))
						rrmdir($dir. DIRECTORY_SEPARATOR .$object);
					else
						unlink($dir. DIRECTORY_SEPARATOR .$object);
				}
			}
			rmdir($dir);
		}
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
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		while (in_array(substr($source, -1), ['/', '\\']))
			$source = substr($source, 0, -1);
		$path = $conf['EXPOSE_DIR'].$current_dir.$source;
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
		$conf = explode("\n", $conf);
		if (!end($conf))
			array_pop($conf);
		for ($i = 0; $i < count($conf); ++ $i) {
			$this_config = explode('=', $conf[$i]);
			$conf[$this_config[0]] = $this_config[1];
			unset($conf[$i]);
		}
		if (!isset($conf['EXPOSE_DIR']))
			return 'Invalid config: EXPOSE_DIR is not set!';
		if (!isset($conf['ALLOW_UPLOAD_OVERRIDE']))
			return 'Invalid config: ALLOW_UPLOAD_OVERRIDE is not set!';
		if (!isset($conf['ALLOW_ZIP_OVERRIDE']))
			return 'Invalid config: ALLOW_ZIP_OVERRIDE is not set!';
		if (!isset($conf['ALLOW_DELETE']))
			return 'Invalid config: ALLOW_DELETE is not set!';
		if (!in_array(strtolower($conf['ALLOW_ZIP_OVERRIDE']), ['true', 'false']))
			return 'Invalid config: ALLOW_ZIP_OVERRIDE should be equal to true or false!';
		if (!in_array(strtolower($conf['ALLOW_UPLOAD_OVERRIDE']), ['true', 'false']))
			return 'Invalid config: ALLOW_UPLOAD_OVERRIDE should be equal to true or false!';
		if (!in_array(strtolower($conf['ALLOW_DELETE']), ['true', 'false']))
			return 'Invalid config: ALLOW_DELETE should be equal to true or false!';
		$conf['ALLOW_UPLOAD_OVERRIDE'] = strtolower($conf['ALLOW_UPLOAD_OVERRIDE']) == 'true';
		$conf['ALLOW_ZIP_OVERRIDE'] = strtolower($conf['ALLOW_ZIP_OVERRIDE']) == 'true';
		$conf['ALLOW_DELETE'] = strtolower($conf['ALLOW_DELETE']) == 'true';
		$conf['CONF_FNAME'] = $config_file;
		if (in_array(substr($conf['EXPOSE_DIR'], -1), ['/', '\\']))
			$conf['EXPOSE_DIR'] = substr($conf['EXPOSE_DIR'], 0, -1);
		if (!is_dir($conf['EXPOSE_DIR']))
			mkdir($conf['EXPOSE_DIR'], 0755, true);
		$conf['EXPOSE_DIR'] = realpath($conf['EXPOSE_DIR']);
		return $conf;
	}


	function list_dir($current_dir, $dir) {
		global $conf, $result_pattern;
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		while (in_array(substr($dir, -1), ['/', '\\']))
			$dir = substr($dir, 0, -1);
		$path = $conf['EXPOSE_DIR'].$current_dir.$dir;
		if (strpos($path, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
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
					if (is_dir($conf['EXPOSE_DIR'].$current_dir.$dir.'/'.$dc)) {
						$result_pattern['message'] .= '<tr><td><i class="fas fa-folder"></i></td>';
						$result_pattern['message'] .= '<td><a href="#" data-dir="'.$dc.'" onclick="return false;" class="directory">'.$dc.'</a></td>';
						$result_pattern['message'] .= '<td class="row"><form onsubmit="return false;">';
						$result_pattern['message'] .= '<button type="submit" data-dir="'.$dc.'" class="button delete_directory" title="delete">';
						$result_pattern['message'] .= '<i class="fas fa-trash"></i></button>';
						$result_pattern['message'] .= '</form><form onsubmit="return false;">';
						$result_pattern['message'] .= '<button type="submit" data-dir="'.$dc.'" class="button zip_directory" title="zip">';
						$result_pattern['message'] .= '<i class="far fa-file-archive"></i></button>';
						$result_pattern['message'] .= '</form></td></tr>';
					}
					else {
						$result_pattern['message'] .= '<tr><td><i class="fas fa-file"></i></td>';
						$result_pattern['message'] .= '<td><a href="#" data-file="'.$dc.'" onclick="return false;" class="file">'.$dc.'</a></td>';
						$result_pattern['message'] .= '<td class="row"><form onsubmit="return false;">';
						$result_pattern['message'] .= '<button type="submit" data-file="'.$dc.'" class="button delete_file" title="delete">';
						$result_pattern['message'] .= '<i class="fas fa-trash"></i></button></td>';
						$result_pattern['message'] .= '</form></tr>';
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
		$fname = $conf['EXPOSE_DIR'].$current_dir.$down_file;
		if (strpos($fname, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
			header('Response: '.json_encode($result_pattern));
			return;
		}
		if (file_exists($fname)) {
			$result_pattern['status'] = 'ok';
			$result_pattern['message'] = 'File '.$down_file.' is downloading';
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($fname));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: '.filesize($fname));
			header('Response: '.json_encode($result_pattern));
			ob_clean();
			flush();
			readfile($fname);
		}
		else {
			$result_pattern['message'] = 'File '.$down_file.' does not exists!';
			header('Response: '.json_encode($result_pattern));
		}
	}


	function create_dir($current_dir, $new_dir) {
		global $conf, $result_pattern;
		$current_dir .= substr($current_dir, -1) == '/' ? '' : '/';
		$new_dir = trim($new_dir);
		if (!$new_dir)
			$result_pattern['message'] = 'Directory name cannot be empty';
		else {
			$dir = $conf['EXPOSE_DIR'].$current_dir.$new_dir;
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
		if (strpos($current_dir, '..') != false) {
			$result_pattern['message'] = 'Permission denied';
			return $result_pattern;
		}
		$total = count($files['upload_files']['name']);
		for($i = 0; $i < $total; ++ $i) {
			$tmpFilePath = $files['upload_files']['tmp_name'][$i];
			if ($tmpFilePath != '') {
				$newFilePath = $conf['EXPOSE_DIR'].$current_dir.$files['upload_files']['name'][$i];
				if (is_file($newFilePath) && !$conf['ALLOW_UPLOAD_OVERRIDE'])
					$result_pattern['message'] .= 'File '.$files['upload_files']['name'][$i].' already exist. You can allow it in '.$conf['CONF_FNAME'];
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
		$conf['EXPOSE_DIR'] = str_replace('\\', '/', $conf['EXPOSE_DIR']);
		$target = str_replace('\\', '/', $target);
		$target_dir = str_replace('\\', '/', $target_dir);
		while (substr($target, -1) == '/')
			$target = substr($target, 0, -1);
		while (substr($target_dir, -1) == '/')
			$target_dir = substr($target_dir, 0, -1);
		$path = $conf['EXPOSE_DIR'].$target;
		$target_path = $conf['EXPOSE_DIR'].$target_dir;
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
		$conf['EXPOSE_DIR'] = str_replace('\\', '/', $conf['EXPOSE_DIR']);
		$target = str_replace('\\', '/', $target);
		$target_dir = str_replace('\\', '/', $target_dir);
		while (substr($target, -1) == '/')
			$target = substr($target, 0, -1);
		while (substr($target_dir, -1) == '/')
			$target_dir = substr($target_dir, 0, -1);
		$path = $conf['EXPOSE_DIR'].$target;
		$target_path = $conf['EXPOSE_DIR'].$target_dir;
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

	/* ENTRY POINT */
	$result_pattern = ['status'=>'error', 'message'=>''];
	$conf = validate_config_file('rpift.conf');
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