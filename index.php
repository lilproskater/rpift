<?php
	$errors = [];
	$errors['upload_error'] = '';
	$CHILD_DIR = '';

	function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
						rrmdir($dir. DIRECTORY_SEPARATOR .$object);
					else
						unlink($dir. DIRECTORY_SEPARATOR .$object);
				}
			}
			rmdir($dir);
		}
	}

	function zip_directory($source, $destination) {
		if (!extension_loaded('zip')) {
			$errors[] = "Failed to load Zip extension!";
			return false;
		}
		if (!is_dir($source)) {
			$tree = explode('/', $source);
			$errors[] = 'Directory '.end($tree).'does not exists!';
			return false;
		}
		$zip = new ZipArchive();
		if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
			$errors[] = "Couldn't create zip file";
			return false;
		}
		$source = str_replace('\\', '/', realpath($source));
		if (is_dir($source) === true) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
			foreach ($files as $file) {
				$file = str_replace('\\', '/', $file);
				if( in_array(substr($file, strrpos($file, '/') + 1), ['.', '..']) )
					continue;
				$file = realpath($file);
				if (is_dir($file) === true)
					$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
				else if (is_file($file) === true)
					$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
			}
		}
		else if (is_file($source) === true)
			$zip->addFromString(basename($source), file_get_contents($source));
		return $zip->close();
	}

	/* CHECK IF CONFIG FILE EXISTS*/
	if (!file_exists('./rpift.conf')) {
		echo "Config file rpift.conf does not exists!";
		die();
	}

	/* READING CONFIG FILE */
	$conf = file_get_contents('./rpift.conf');
	if (strpos($conf, ';') !== false) {
		echo 'Invalid config file: Your config file should not contain ";" symbol';
		die();
	}
	$conf = explode("\n", $conf);
	if (!end($conf))
		array_pop($conf);
	for ($i = 0; $i < count($conf); ++ $i) {
		$this_config = explode('=', $conf[$i]);
		$conf[$this_config[0]] = $this_config[1];
		unset($conf[$i]);
	}

	/* VALIDATING CONFIG FILE */
	if (!isset($conf["EXPOSE_DIR"])) {
		echo "Invalid config: EXPOSE_DIR is not set!";
		die();
	}
	if (!isset($conf["OVERRIDE_FILES"])) {
		echo "Invalid config: OVERRIDE_FILES is not set!";
		die();
	}
	if (!in_array(strtolower($conf["OVERRIDE_FILES"]), ["true", "false"])) {
		echo 'Invalid config: OVERRIDE_FILES should be equal to true or false!';
		die();
	} else 
		$conf["OVERRIDE_FILES"] = strtolower($conf["OVERRIDE_FILES"]) == "true";
	if (substr($conf["EXPOSE_DIR"], -1) == '/')
		$conf["EXPOSE_DIR"] = substr($conf["EXPOSE_DIR"], 0, -1);

	/* APPLYING CONFIGS */
	if (!is_dir($conf["EXPOSE_DIR"]))
		mkdir($conf["EXPOSE_DIR"], 0755, true);

	/* GET REQUESTS */
	if ($_GET) {
		if (isset($_GET['directory'])) {
			if (is_dir($conf["EXPOSE_DIR"].'/'.$_GET['directory'])) {
				if (!str_replace('/', '', $_GET['directory']))
					header('Location: /');
				else
					$CHILD_DIR = $_GET['directory'];
			}
			else
				$errors[] = "Directory ".$_GET['directory']." does not exists!";
		}
		if (isset($_GET['download'])) {
			$fname = $conf["EXPOSE_DIR"].'/'.$_GET['download'];
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
			}
			else
				$errors[] = "File ".$_GET['download']." does not exists!";
		}
	}

	/* POST REQUESTS */
	if ($_POST) {
		if (isset($_POST['mknewdir'])) {
			$_POST['new_directory'] = trim($_POST['new_directory']);
			if (!$_POST['new_directory'])
				$errors[] = "Directory name cannot be empty";
			else {
				if (!is_dir($conf['EXPOSE_DIR'].$_POST['current_dir'].'/'.$_POST['new_directory']))
					mkdir($conf["EXPOSE_DIR"].$_POST['current_dir'].'/'.$_POST['new_directory'], 0755, true);
				else
					$errors[] = 'Directory '.$_POST['new_directory'].' already exsits!';
			}
		}
		if (isset($_POST['delete_directory'])) {
			if (is_dir($_POST['deleting_directory']))
				rrmdir($_POST['deleting_directory']);
			else {
				$tree = explode('/', $_POST['deleting_directory']);
				$errors[] = 'Directory '.end($tree).' does not exists!';
			}
		}
		if (isset($_POST['zip_directory']))
			zip_directory($_POST['zipping_directory'], $_POST['zipping_directory'].'.zip');
		if (isset($_POST['upload'])) {
			$files = array_filter($_FILES['upload_files']['name']);
			$total = count($_FILES['upload_files']['name']);
			for($i = 0; $i < $total; ++ $i) {
				$tmpFilePath = $_FILES['upload_files']['tmp_name'][$i];
				if ($tmpFilePath != "") {
					$newFilePath = $conf["EXPOSE_DIR"].$_POST['current_dir'].'/'.$_FILES['upload_files']['name'][$i];
					if (is_file($newFilePath) && !$conf["OVERRIDE_FILES"])
						$errors['upload_error'] .= 'File '.$_FILES['upload_files']['name'][$i].' already exist.<br>Change OVERRIDE_FILES=true in rpift.conf to allow override!<br>';
					else
						if(!move_uploaded_file($tmpFilePath, $newFilePath))
							$errors['upload_error'] .= 'Couldn\'t upload file '.$_FILES['upload_files']['name'][$i].'<br>';
				}
			}
			if (isset($errors['upload_error']))
				$errors[0] = substr($errors['upload_error'], 0, -4);
		}
		if (isset($_POST['delete_file'])) {
			$tree = explode('/', $_POST['deleting_file']);
			if (file_exists($_POST['deleting_file'])) {
				if (!unlink($_POST['deleting_file']))
					$errors[] = 'Couldn\'t delete file '.end($tree);
			} else
				$errors[] = 'File '.end($tree).' does not exists!';
		}
	}

	function current_dir() {
		global $CHILD_DIR;
		echo '<input hidden name="current_dir" value="'.$CHILD_DIR.'">';
	}
?>

<html>
	<head>
		<meta charset="UTF-8">
		<title>Raspberry Pi File transferer</title>
		<script type="text/javascript" src="/js/fontawesome_all.js"></script>
	</head>
	<style>
		body {
			font-family: Arial;
		}

		form {
			margin: 0;
		}

		a {
			margin-right: 10px;
		}

		table {
			margin-bottom: 50px;
		}

		thead th {
			text-align: left;
		}

		thead span {
			margin-left: 10px;
		}

		tr a {
			margin-top: 15px;
			margin-left: 10px;
		}

		.rpi_logo {
			width: 10%;
			margin-bottom: 50px;
		}

		.row {
			font-size: 20px;
			display: flex;
			align-items: center;
		}

		.button {
			font-size: 20px;
			margin-left: 10px;
			margin-top: 15px;
			background: #2b5388;
			padding: 10px;
			border: none;
			border-radius: 7px;
			color: #fff;
		}

		.fa-folder {
			color: #ffa700;
			font-size: 30px;
			margin: 0 10px;
		}

		.fa-file {
			color: #868686;
			font-size: 30px;
			margin: 0 10px;
		}

		.upload_form, .newdir_form {
			margin-left: 10px;
			margin-top: 25px;
		}

		.upload_form h3, .newdir_form h3 {
			margin: 0;
		}

		.input {
			padding: 5px;
			font-size: 20px;
			border-radius: 7px;
		}

		.input:focus {
			border: 2px solid #2b5388;
			outline-width: 0;
		}
	</style>
	<body>
		<img src="/image/RPi_logo.svg" class="rpi_logo">
		<table>
			<thead>
				<tr>
					<th><span>Type</span></th>
					<th><span>Name</span></th>
					<th><span>Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<?php
					$expose_dir = array_diff(scandir($conf['EXPOSE_DIR'].$CHILD_DIR), ['.', '..']);
					foreach ($expose_dir as $dc) {
						if (is_dir($conf['EXPOSE_DIR'].$CHILD_DIR.'/'.$dc)) {
							echo '<tr><td><i class="fas fa-folder"></i></td>';
							echo '<td><a href="/?directory='.$CHILD_DIR.'/'.$dc.'">'.$dc.'</a></td>';
							echo '<td class="row"><form method="POST" action="/" onsubmit="return confirm(\'Are you sure you want to delele folder '.$dc.'?\')">';
							echo '<input hidden name="deleting_directory" value="'.$conf['EXPOSE_DIR'].$CHILD_DIR.'/'.$dc.'">';
							echo '<button type="submit" name="delete_directory" class="button" title="delete"><i class="fas fa-trash"></i></button>';
							echo '</form><form method="POST" action="/"">';
							echo '<input hidden name="zipping_directory" value="'.$conf['EXPOSE_DIR'].$CHILD_DIR.'/'.$dc.'">';
							echo '<button type="submit" name="zip_directory" class="button" title="zip"><i class="far fa-file-archive"></i></button>';
							echo '</form></td></tr>';
						}
						else {
							echo '<tr><td><i class="fas fa-file"></i></td>';
							echo '<td><a href="/?download='.$CHILD_DIR.'/'.$dc.'">'.$dc.'</a></td>';
							echo '<td class="row"><form method="POST" action="/" onsubmit="return confirm(\'Are you sure you want to delele file '.$dc.'?\')">';
							echo '<input hidden name="deleting_file" value="'.$conf['EXPOSE_DIR'].$CHILD_DIR.'/'.$dc.'">';
							echo '<button type="submit" name="delete_file" class="button" title="delete"><i class="fas fa-trash"></i></button></td>';
							echo '</form></tr>';
						}
					}
				?>
			</tbody>
		</table>
		<form method="POST" action="/" enctype="multipart/form-data" class="upload_form">
			<h3>Upload Multiple Files</h3>
			<?php current_dir(); ?>
			<input name="upload_files[]" type="file" multiple="multiple">
			<button type="submit" name="upload" class="button" title="upload"><i class="fas fa-file-upload"></i></button>
		</form>
		<form method="POST" action="/" class="newdir_form">
			<h3>Create new directory</h3>
			<?php current_dir(); ?>
			<input class="input" name="new_directory" placeholder="Directory name">
			<button type="submit" name="mknewdir" class="button" title="create"><i class="fas fa-folder-plus"></i></button>
		</form>
		<?php unset($errors['upload_error']); if ($errors) echo '<p style="color: #f00; font-weight: bold; margin-left: 15px;">'.$errors[0].'</p>'; ?>
	</body>
</html>
