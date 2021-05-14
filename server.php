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
		$destination = $conf['EXPOSE_DIR'].$current_dir.'/'.$destination;
		if (!extension_loaded('zip')) {
			$result_pattern['message'] = 'Failed to load Zip extension!';
			return $result_pattern;
		}
		if (!is_dir($conf['EXPOSE_DIR'].$current_dir.'/'.$source)) {
			$result_pattern['message'] = 'Directory '.$source.'does not exists!';
			return $result_pattern;
		}
		if (file_exists($destination) && !$conf['OVERRIDE_FILES']) {
			$errors[] = 'Zip file already exists.<br>Change OVERRIDE_FILES=true in '.$conf['CONF_FNAME'].' to allow override!<br>';
			return $result_pattern;
		}
		$zip = new ZipArchive();
		if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
			$result_pattern['message'] = 'Couldn\'t create zip file';
			return $result_pattern;
		}
		$abs_source = str_replace('\\', '/', realpath($conf['EXPOSE_DIR'].$current_dir.'/'.$source));
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
		if (!isset($conf['OVERRIDE_FILES']))
			return 'Invalid config: OVERRIDE_FILES is not set!';
		if (!in_array(strtolower($conf['OVERRIDE_FILES']), ['true', 'false']))
			return 'Invalid config: OVERRIDE_FILES should be equal to true or false!';
		$conf['OVERRIDE_FILES'] = strtolower($conf['OVERRIDE_FILES']) == 'true';
		$conf['CONF_FNAME'] = $config_file;
		if (in_array(substr($conf['EXPOSE_DIR'], -1), ['/', '\\']))
			$conf['EXPOSE_DIR'] = substr($conf['EXPOSE_DIR'], 0, -1);
		$conf['EXPOSE_DIR'] = realpath($conf['EXPOSE_DIR']);
		if (!is_dir($conf['EXPOSE_DIR']))
			mkdir($conf['EXPOSE_DIR'], 0755, true);
		return $conf;
	}


	function list_dir($current_dir, $dir) {
		global $conf, $result_pattern;
		while (in_array(substr($dir, -1), ['/', '\\']))
			$dir = substr($dir, 0, -1);
		if (is_dir($conf['EXPOSE_DIR'].$current_dir.'/'.$dir)) {
			$dir_content = scandir_sorted($conf['EXPOSE_DIR'].$current_dir.'/'.$dir);
			$result_pattern['status'] = 'ok';
			$result_pattern['current_dir'] = $current_dir.'/'.$dir;
			foreach ($dir_content as $dc) {
				if (is_dir($conf['EXPOSE_DIR'].$current_dir.'/'.$dir.'/'.$dc)) {
					$result_pattern['message'] .= '<tr><td><i class="fas fa-folder"></i></td>';
					$result_pattern['message'] .= '<td><a href="#" data-dir="'.$dc.'" onclick="return false;" class="directory">'.$dc.'</a></td>';
					$result_pattern['message'] .= '<td class="row"><form onsubmit="return false;">';
					$result_pattern['message'] .= '<button type="submit" name="delete_directory" class="button" title="delete">';
					$result_pattern['message'] .= '<i class="fas fa-trash"></i></button>';
					$result_pattern['message'] .= '</form><form onsubmit="return false;">';
					$result_pattern['message'] .= '<button type="submit" name="zip_directory" class="button" title="zip">';
					$result_pattern['message'] .= '<i class="far fa-file-archive"></i></button>';
					$result_pattern['message'] .= '</form></td></tr>';
				}
				else {
					$result_pattern['message'] .= '<tr><td><i class="fas fa-file"></i></td>';
					$result_pattern['message'] .= '<td><a href="#" data-file="'.$dc.'" onclick="return false;" class="file">'.$dc.'</a></td>';
					$result_pattern['message'] .= '<td class="row"><form onsubmit="return false;">';
					$result_pattern['message'] .= '<button type="submit" name="delete_file" class="button" title="delete">';
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
		$fname = $conf['EXPOSE_DIR'].$current_dir.'/'.$down_file;
		if (file_exists($fname)) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($fname));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: '.filesize($fname));
			ob_clean();
			flush();
			readfile($fname);
			$result_pattern['status'] = 'ok';
			$result_pattern['message'] = 'File is downloading';
		}
		else
			$result_pattern['message'] = 'File '.$down_file.' does not exists!';
		return $result_pattern;
	}


	function create_dir($current_dir, $new_dir) {
		global $config, $result_pattern;
		$new_dir = trim($new_dir);
		if (!$new_dir)
			$result_pattern['message'] = 'Directory name cannot be empty';
		else {
			if (!is_dir($conf['EXPOSE_DIR'].$current_dir.'/'.$new_dir)) {
				mkdir($conf['EXPOSE_DIR'].$current_dir.'/'.$new_dir, 0755, true);
				$result_pattern['status'] = 'ok';
				$result_pattern['message'] = 'Directory '.$new_dir.' has been created';
			}
			else
				$result_pattern['message'] = 'Directory '.$new_dir.' already exsits';
		}
		return $result_pattern;
	}


	function delete_dir($current_dir, $dir) {
		global $config, $result_pattern;
		if (is_dir($conf['EXPOSE_DIR'].$current_dir.'/'.$dir)) {
			rrmdir($conf['EXPOSE_DIR'].$current_dir.'/'.$dir);
			$result_pattern['status'] = 'ok';
			$result_pattern['message'] = 'Directory '.$dir.' has been removed';
		}
		else
			$result_pattern['message'] = 'Directory '.$dir.' has been removed';
		return $result_pattern;
	}


	function upload_files($current_dir, $files) {
		global $config, $result_pattern;
		$total = count($files['upload_files']['name']);
		for($i = 0; $i < $total; ++ $i) {
			$tmpFilePath = $files['upload_files']['tmp_name'][$i];
			if ($tmpFilePath != '') {
				$newFilePath = $conf['EXPOSE_DIR'].$current_dir.'/'.$files['upload_files']['name'][$i];
				if (is_file($newFilePath) && !$conf['OVERRIDE_FILES'])
					$result_pattern['message'] .= 'File '.$files['upload_files']['name'][$i].' already exist.<br>Change OVERRIDE_FILES=true in '.$conf['CONF_FNAME'].' to allow override!<br>';
				else
					if (!move_uploaded_file($tmpFilePath, $newFilePath))
						$result_pattern['message'] .= 'Couldn\'t upload file '.$files['upload_files']['name'][$i].'<br>';
			}
		}
		if (!$result_pattern['message']) {
			$result_pattern['status'] = 'ok';
			$result_pattern['message'] = $total == 1 ? 'All files are uploaded' : 'File is uploaded';
		}
		return $result_pattern;
	}


	function delete_file($current_dir, $file) {
		global $config, $result_pattern;
		if (file_exists($config['EXPOSE_DIR'].$current_dir.'/'.$file)) {
			if (!unlink($config['EXPOSE_DIR'].$current_dir.'/'.$file))
				$result_pattern['message'] = 'Couldn\'t delete file '.$file;
			else {
				$result_pattern['status'] = 'ok';
				$result_pattern['message'] = 'File is deleted';
			}
		} else
			$result_pattern['message'] = 'File '.$file.' does not exists!';
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
		if (isset($_GET['directory']))
			echo json_encode(list_dir($_GET['current_dir'], $_GET['directory']));
		if (isset($_GET['download']))
			echo json_encode(download_file($_GET['current_dir'], $_GET['download']));
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
	}
?>