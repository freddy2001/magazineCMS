<?php
# Verwaltungsmodul
# (c) 2017 IK

require './base.php';

class verwaltung {
	function __construct() {
		$global_pagevars = [
			'PROJECTNAME' => 'Kolbepost',
		];
		$this->tpl = new Template(PATH.'/templates', PATH.'/cache/tpl', $global_pagevars);

		$this->DB = new DB();
		$this->config = require('config.php');
		if(!isset($_SESSION['loggedin']) OR ($_SESSION['loggedin'] !== true)) {
			 if(isset($_POST['user']) && isset($_POST['password'])) {
				if($this->dologin($_POST['user'], $_POST['password']) == "disabled") {
					$this->GUIshowlogin("<div class='alert alert-danger'><b>Fehler beim Login:</b> Dieses Konto wurde deaktiviert.</div>");
				} else if($this->dologin($_POST['user'], $_POST['password']) == "nein") {
					$this->GUIshowlogin("<div class='alert alert-danger'><b>Fehler beim Login:</b> Leider ist diese Kombination nicht bekannt.</div>");
					// Show error at login
				} else {
					// Show Dashboard
					$device = $_SERVER['HTTP_USER_AGENT'];
					$device = $this->DB->real_escape_string($device);
					$ip = $_SERVER['REMOTE_ADDR'];
					$ip = $this->DB->real_escape_string($ip);
					$timestamp = time();
					$sql = "INSERT INTO " . $this->config['databaseprefix'] . "logins (id, userid, timestamp, ip, device) VALUES (NULL, '" . $_SESSION['id'] . "', '" . $timestamp . "', '" . $ip . "', '" . $device . "');";
					$this->DB->query($this->DB->escape($sql));
					$this->GUIshowDashboard();
				}
				// Log in
			} else {
				$this->GUIshowlogin();
			}
			//Show login
		} else {
			// Login successfull
			if(!isset($_GET['page'])) {
				$this->GUIshowDashboard();
			} else {
				switch($_GET['page']) {
					case 'logout':
						$this->dologout();
						break;
					case 'newarticle':
						$this->GUInewarticle();
						break;
					case 'mydrafts':
						$this->GUImyDrafts();
						break;
					case 'menu':
						$this->GUImenu();
						break;
					case 'ausgaben':
						$this->GUIausgaben();
						break;
					case 'staff':
						$this->GUIstaff();
						break;
					case 'disableuser':
						$this->GUIdisableUser();
						break;
					case 'recoverpw':
						$this->GUIrecoverpw();
						break;
					case 'register':
						$this->GUIregister();
						break;
					case 'viewdraft':
						$this->GUIviewDraft();
						break;
					case 'deldraft':
						$this->GUIdelDraft();
						break;
					case 'editdraft':
						$this->GUIeditDraft();
						break;
					case 'settings':
						$this->GUIuserSettings();
						break;
					case 'viewfiles':
						$this->GUIlistFiles();
						break;
					default:
						$this->GUIshowDashboard();
						break;
				}
			}
		}
	}

	public function dologin($username, $password) {
		if($username == "") {
			return(false);
		} else if ($password == "") {
			return(false);
		}
		$password = sha1($password . $this->config['hashsecret']);
		$username = $this->DB->escape($username);
		$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "accounts WHERE user = '" . $username . "'";
		$this->DB->query($sql);
		$userdb = null;
		$passworddb = null;
		$disableddb = null;
		if($this->DB->count() > 0) {
			$result = $this->DB->fetchRow();
			$userdb = $result['user'];
			$passworddb = $result['password'];
			$disableddb = $result['disabled'];
			$namedb = $result['vorname'];
			$iddb = $result['id'];
		}
		$login = true;
		$disabled = false;
		if($username != $userdb) {
			$login = false;
		}
		if($password != $passworddb) {
			$login = false;
		}
		if($disableddb == "1") {
			$disabled = true;
			$login = false;
		} else {
			$disabled = false;
		}
		if($disabled) {
			return("disabled");
		}
		if($login == false) {
			return("nein");
		} else if ($login == true) {
			$_SESSION['loggedin'] = true;
			$_SESSION['vorname'] = $namedb[0];
			$_SESSION['id'] = $iddb[0];
			return ("ja");
		}
		// Logge in
	}

	public function dologout() {
		unset($_SESSION['loggedin']);
		unset($_SESSION['vorname']);
		unset($_SESSION['id']);
		header('Location: index.php');
	}

	public function showMyDrafts() {
		$id = $this->DB->escape($_SESSION['id']);
		$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "drafts WHERE userid = '" . $id . "'  ORDER BY lastmod DESC;";
		$this->DB->query($sql);
		$result = $this->DB->fetchAllRows();

		$return = "";
		for($i=0; $i < $k; $i++) {
			$return .= "<tr><th>" . "$result[$i]['title']</th><th>$result[$i]['created']</th><th>" . date('d.m.Y',$result[$i]['lastmod']) . "</th><th>$result[$i]['teaser']</th><th><a href='?page=viewdraft&id=" . $result[$i]['id'] . "'>Bearbeiten</a>" . "</tr>\n";
		}
		if($return != "") {
			return($return);
		} else {
			return("Du hast keine Entwürfe angelegt.");
		}
	}

	//ToDo: New Database driver
	public function showAusgaben() {
		$gespeicherttext = false;
		if(isset($_POST['save'])) {
			if($_POST['save'] == "1") {
				$this->writeLog("User " . $_SESSION['id'] . " (" . $_SESSION['vorname'] . ") changed the issues", "changeissue");
				$gespeicherttext = true;
				for($i = 0; $i < 4; $i++) {
					if($_POST["title" . $i] == "") {
						$sql = "DELETE FROM " . $this->config['databaseprefix'] . "issue WHERE id = $i+1"; 
						$this->DB->modify($sql);
						// Leerer Titel
					} else {
						$sql = "DELETE FROM " . $this->config['databaseprefix'] . "issue WHERE id = $i+1"; 
						$this->DB->modify($sql);
						$temp = $_POST["title" . $i];
						$templink = $_POST["link" . $i];
						$tempnr = $i+1;
						$tempnr = $this->DB->real_escape_string($tempnr);
						$temp = $this->DB->real_escape_string($temp);
						$templink = $this->DB->real_escape_string($templink);
						$sql = "INSERT INTO " . $this->config['databaseprefix'] . "issue (id, title, link) VALUES ('$tempnr', '$temp', '$templink')"; 
						$this->DB->modify($sql);
						// Voller Titel
					}
				}
			}
		}
		$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "issue ORDER BY id ASC";
		$result = $this->DB->query($sql);
		if($result === 0) {
		   $num = 0;
		} else {
			$num = mysqli_num_rows($result);
		}
		 $k = 0;
		if($num > 0) {
			while ($row = mysqli_fetch_assoc($result)) {
				$iddb[$k] = $row["id"];
				$titledb[$k] = $row["title"];
				$linkdb[$k] = $row["link"];
				$k++;
			}
		}
		$return = "";
		if($gespeicherttext) {
			$return .= "<div class='alert alert-success'>Gespeichert</div><br>";
		}
		for($i=0; $i < $k; $i++) {
			$return .= "<li>Titel:<input value='$titledb[$i]' name='title" . $i . "'> Link:<input value='$linkdb[$i]' name='link" . $i . "'></li>";
		}
		for($j=$i; $j < 4; $j++) {
			$return .= "<li>Titel:<input value='' name='title" . $j . "'> Link:<input value='' name='link" . $j . "'></li>";
		}
		$return .= "</ul><input type='hidden' name='save'  value='1'><input class='btn btn-primary' type='submit' value='Menü speichern'></form>";
		return($return);
	}

	public function showMenu() {
		$gespeicherttext = false;
		if(isset($_POST['save'])) {
			if($_POST['save'] == "1") {
				$this->writeLog("User " . $_SESSION['id'] . " (" . $_SESSION['vorname'] . ") changed the menu", "changemenu");
				$gespeicherttext = true;
				for($i = 0; $i < 4; $i++) {
					if($_POST["title" . $i] == "") {
						$sql = "DELETE FROM " . $this->config['databaseprefix'] . "menu WHERE id = $i+1"; 
						$sql = $this->DB->escape($sql);
						$this->DB->query($sql);
						// Leerer Titel
					} else {
						$sql = "DELETE FROM " . $this->config['databaseprefix'] . "menu WHERE id = $i+1"; 
						$this->DB->query($sql);
						$temp = $_POST["title" . $i];
						$templink = $_POST["link" . $i];
						$tempnr = $i+1;
						$tempnr = $this->DB->escape($tempnr);
						$temp = $this->DB->escape($temp);
						$templink = $this->DB->escape($templink);
						$sql = "INSERT INTO " . $this->config['databaseprefix'] . "menu (id, title, link) VALUES ('$tempnr', '$temp', '$templink')"; 
						$this->DB->query($sql);
						// Voller Titel
					}
				}
			}
		}
		$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "menu ORDER BY id ASC";
		$this->DB->query($sql);
		$result = $this->DB->fetchAllRows();
		if($num > 0) {
			while ($row = mysqli_fetch_assoc($result)) {
				$iddb[$k] = $row["id"];
				$titledb[$k] = $row["title"];
				$linkdb[$k] = $row["link"];
				$k++;
			}
		}
		$return = "";
		if($gespeicherttext) {
			$return .= "<div class='alert alert-success'>Gespeichert</div><br>";
		}
		$return .= "<form method='post'><ul><li>Titel:<input value='Startseite' disabled='disabled'> Link:<input value='/' disabled='disabled'></li>";
		for($i=0; $i < $k; $i++) {
			$return .= '<li>Titel:<input value="' . $result[$i]['title'] . 'name="title' . $i . '"> Link:<input value="' . $result[$i]['link'] . ' name="link' . $i . '"></li>';
		}
		for($j=$i; $j < 4; $j++) {
			$return .= "<li>Titel:<input value='' name='title" . $j . "'> Link:<input value='' name='link" . $j . "'></li>";
		}
		$return .= "</ul><input type='hidden' name='save'  value='1'><input class='btn btn-primary' type='submit' value='Menü speichern'></form>";
		return($return);
	}

	// ToDo: Ab hier weiter Datenbank
	public function getStaff() {
		$sql = "SELECT id, user, vorname, email, disabled FROM " . $this->config['databaseprefix'] . "accounts";
		$this->DB->query($sql);
		$result = $this->DB->fetchAllRows();

		$return = "";
		for($i = 0; $i < count($result); $i++) {
			$sql = "SELECT timestamp FROM " . $this->config['databaseprefix'] . "logins WHERE userid = '" . $result[$i]['id'] . "' ORDER BY timestamp DESC LIMIT 1;";
			$this->DB->query($sql);
			if($this->DB->count() > 0) {
				$lastseendb = $this->DB->fetchRow();
			} else {
				$lastseendb = 1;
			}
			// Wenn nicht geblockt
			if($result[$i]['disabled'] == "0") { 
				$return .= '<tr><th><b>' . $result[$i]['vorname'] . '</b></th><th>' . $result[$i]['email'] . '</th><th>' . date("d. F Y, H:i", $lastseendb) . '</th><th><a href="?page=disableuser&uid=' . $result[$i]['id'] . '">Deaktiveren</a>, <a href="?page=recoverpw&uid=' . $result[$i]['id'] . '">PW vergessen</a ></th></td>';
			} 
		}
		if($return != "") {
			return($return);
		} else {
			return("Das Redaktionsmodul ist nicht aktiv");
		}
	}

	public function disableUser($uid) {
		$uid = $this->DB->escape($uid);
		$sql = "UPDATE " . $this->config['databaseprefix'] . "accounts SET disabled = '1' WHERE id = " . $uid; 
		$this->DB->query($sql);
	}

	public function passwordRecovery($uid) {
		$uid = $this->DB->escape($uid);
		$sql = "SELECT id, user, email, password FROM " . $this->config['databaseprefix'] . "accounts WHERE id = " . $uid;
		$this->DB->query($sql);
		$result = $this->DB->fetchRow();
		mail($result['email'], "Zugriff auf " . $this->config['host'], "Dir wurde ein ein neues Passwort auf " . $this->config['host'] . " angefragt. Klicke folgenden Link, um es festzulegen: https://" . $this->config['host'] . "/admin/resetpw.php?n=" . $result['user'] . "&p=" . substr($result['password'], 0, 16));
	}

	public function createNewUser($name, $email) {
		$name = $this->DB->escape($name);
		$email = $this->DB->escape($email);
		$geheimerhash = $this->config['hashsecret'];
		$hash = sha1($name . microtime() . $geheimerhash);
		$username = 'user' .  date('ydmhis');
		$sql = "INSERT INTO " . $this->config['databaseprefix'] . "accounts (id, user, password, vorname, email, disabled, sendmailna, sendmailnu) VALUES (NULL, '$username', '$hash', '$name', '$email', '0', '0', '0');"; 
		$this->DB->query($sql);
		$mailtext = "Hallo " . $name . "!\nEs wurde soeben ein Account auf " . $this->config['host'] . " für dich angelgt. Bitte verwende den folgenden Link um deinen Account einzurichten: https://" . $this->config['host'] . "/admin/register.php?n=" . substr($username, 4) . "&p=" . substr($hash, 0, 16) . " .\nWenn du nicht weißt, wovon diese Mail handelt, ignoriere sie einfach.";
		mail($email, "Dein Zugriff auf " . $this->config['host'], $mailtext);
	}

	public function checkIfMailExists($email) {
		$email = $this->DB->escape($email);
		$sql = "SELECT id, email, disabled FROM " . $this->config['databaseprefix'] . "accounts WHERE email = '" . $email . "';";
		$this->DB->query();
		if($this->DB->count() == 0) {
			return false;
		} else {
			return true;
		}
	}

	public function writeLog($message, $typeofaction) {
		$message = $this->DB->escape($message);
		$typeofaction = $this->DB->escape($typeofaction);
		$userid = $this->DB->escape($_SESSION['id']);
		$time = time();
		$sql = "INSERT INTO " . $this->config['databaseprefix'] . "log (id, timestamp, message, typeofaction, userid) VALUES (NULL, '$time', '$message', '$typeofaction', '$userid');"; 
		$this->DB->query($sql);
	}

	public function broadcastMail($mailtext, $type) {
		$sql = "SELECT vorname, email FROM " . $this->config['databaseprefix'] . "accounts  WHERE email LIKE '%@%' AND disabled = '0';";
		$this->DB->query($sql);
		$result = $this->DB->fetchAllRows();
		for($i = 0; $i < count($result); $i++) {
			if($type == "newArticle") {
				$text = "";
				$text .= "Hallo " . $result[$i]['vorname'] . "!\nEs wurde der folgende Artikel auf " . $this->config['host'] . " angelegt:\n";
				$text .= $mailtext . "\nFreundliche Grüße, dein " . $this->config['host'] . " Benachrichtgungssystem.";
				mail($result[$i]['email'], $this->config['host'] . ": Es wurde ein neuer Artikel angelegt.", $text);
			}
		}
	}

	public function showFiles() {
		$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "files;";
		$this->DB->query($sql);
		$files = $this->DB->fetchAllRows();
		if(count($files) == 0) {
			echo "Keine Dateien";
		} else {
			foreach($files['title'] as $file) {
				echo $file;
			}
		}
	}

	public function GUIshowlogin($error = "") {
		$this->LAYOUTtop();
		echo '<div class="container">
			  <form method="post" action="index.php" class="form-signin" style="max-width: 330px;
			  padding: 15px;
			  margin: 0 auto;">
				<h2 class="form-signin-heading">Anmelden</h2>
				<div>Bitte gib deine Zugangsdaten an:</div>
				<label for="user" class="sr-only">Benutzername</label>
				<input type="text" name="user" id="user" class="form-control" placeholder="Benutzername" required autofocus>
				<label for="password" class="sr-only">Passwort</label>
				<input type="password" name="password" id="password" class="form-control" placeholder="Passwort" required>
				<div class="checkbox">';
				echo "<span style='color:red;'>$error</span>";
				echo '</div>
				<button class="btn btn-lg btn-primary btn-block" type="submit">Einloggen</button>
			  ';
		echo "<br/><small><a onClick=" . '"' . "document.getElementById('pwvergessen').style.display = 'block'" . '"' . ">Passwort vergessen?</a></small><div id='pwvergessen' style='display:none' class='alert alert-warning'>Es besteht keine Möglichkeit dir selbst das Passwort zurückzusetzen. Bitte frage jemanden aus dem Redaktionsteam, um das Passwort zurückzusetzten.</div></form> </div>";
		echo "</div><footer class='footer'><div class='container'><p class='text-muted'>&copy; 2017 " . $this->config['devname'] . " &bull; <a href='https://github.com/freddy2001/magazineCMS' target='_blank'>Fork me on GitHub!</a></p>
		</div>
	  </footer>";
		$this->LAYOUTfooter();
	} 

	public function GUIeditDraft() {
		$id = $_GET['id'];
		$id = $this->DB->escape($id);
		$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "drafts WHERE id = '" . $id . "';";
		$this->DB->query($sql);
		$result = $this->DB->fetchRow();
		$iddb = $result['id'];
		$titledb = $result['title'];
		$teaserdb = $result['teaser'];
		$createddb = $result['created'];
		$urldb = $result['url'];
		$aktuelldb = $result['aktuell'];
		$detailsdb = $result['details'];
		$textdb = $result['text'];

		$meldung = "";
		if(isset($_POST['title']) && isset($_POST['date']) && isset($_POST['aktuelles']) && isset($_POST['text']) && isset($_POST['id'])) {
			if(isset($_POST['change'])) {
				// Entwurf
				$userid = $_SESSION['id'];
				$title = $_POST['title'];
				$teaser = $_POST['teaser'];
				$created = $_POST['date'];
				$id = $_POST['id'];
				$lastmod = time();
				if($_POST['aktuelles'] == "yes")  {
					$aktuell = 1;
				} else {
					$aktuell = 0;
				}
				if($_POST['details'] == "yes")  {
					$details = 1;
				} else {
					$details = 0;
				}
				$text = $_POST['text'];
				$url = strtolower($title);
				$vokale = array("a", "o", "u", "a", "o", "u", "-", "ss");
				$umlaute = array("A", "Ö", "Ü", "ä", "ö", "ü", " ", "ß");
				$url = str_replace($umlaute, $vokale, $url);
				$url = $this->DB->escape($url);
				$userid = $this->DB->escape($userid);
				$title = $this->DB->escape($title);
				$teaser = $this->DB->escape($teaser);
				$id = $this->DB->escape($id);
				$created = $this->DB->escape($created);
				$lastmod = $this->DB->escape($lastmod);
				$aktuell = $this->DB->escape($aktuell);
				$details = $this->DB->escape($details);
				$text = $this->DB->escape($text);
				$sql = "UPDATE " . $this->config['databaseprefix'] . "drafts SET title = '" . $title . "',  teaser = '" . $teaser . "', lastmod = '" . $lastmod . "', created = '" . $created . "', aktuell = '" . $aktuell . "', details = '" . $details . "', text = '" . $text . "', url = '" . $url . "' WHERE id = '" . $id . "';"; 
				$this->DB->query($sql);
				header("Location: index.php?page=viewdraft&id=" . $id . "&msg=save");
				$this->LAYOUTtop();
				// ToDo in Datenbank
				echo "<div class='alert alert-success'>Entwurf geichert !</div>";
				echo "Meine Entwürfe:
				<table class='table'>
				<thead>
				<tr>
				<th>Name</th><th>Erstellt</th><th>Letzte Änderung</th><th>Teaser</th><th>Aktion</th>
				</tr>
				</thead>
				<tbody>
				" . $this->showMyDrafts() . "</tbody></table>";
			} 
		} else {
		$this->LAYOUTtop();
		echo "<a href='index.php?page=mydrafts' class='btn btn-secondary'>← Zurück zur Übersicht</a><br />
		";
		$meldung = "";
		echo "Einen Artikel bearbeiten: <br />$meldung
		<form method='post'>
		<input type='hidden' name='id' value='" . $iddb . "'>

		<div class='form-group'>
		  <label for='title'>Titel des Artikels</label>
			<input type='text' class='form-control' id='title' name='title' value='" . $titledb . "' placeholder='Gebe hier einen aussagekräftigen Titel an'>
		</div>
		<div class='form-group'>
		<label for='teaser'>Teaser</label>
		  <input type='text' class='form-control' id='teaser' name='teaser' value='" . $teaserdb . "' placeholder='Gebe hier einen knappe Zusammenfassung an'>
	  </div>
		<div class='form-group'>
			<label for='date'>Datum</label> 
			<input class='form-control' value='" . $createddb . "' name='date'>
		</div>
		<div class='form-check'>
			<label for='aktuelles'>Erscheint unter Aktuelles:</label><br />
			<input type='radio' name='aktuelles' value='yes' id='yes1'";
		 //   $aktuelldb = $aktuelldb[0];
		 //   $detailsdb = $detailsdb[0];
			if($aktuelldb == 1) {
				echo "checked='checked'";
			}
			echo "><label for='yes1'> Ja</label> <input type='radio' name='aktuelles' id='no1' value='no'";
			if($aktuelldb == 0) {
				echo "checked='checked'";
			}
			echo "><label for='no1'> Nein</label><br />
		</div>
		<div class='form-check'>
		<label for='details'>Details (Autor, Veröffentlichung) anzeigen:</label><br />
		<input type='radio' name='details' value='yes' id='yes2'";
		if($detailsdb == 1) {
			echo "checked='checked'";
		}
		echo "><label for='yes2'> Ja</label> <input type='radio' name='details' id='no2' value='no'";
		if($detailsdb == 0) {
			echo "checked='checked'";
		}
		echo "><label for='no2'> Nein</label><br />
	</div>
		<div class='form-group'>
		<label for='ausgabe'>Artikel gehört zu Ausgabe</label>
		<select class='form-control' id='ausgabe'>
		  <option>-Bitte auswählen / Keine bestimmte Ausgabe-</option>

		  <option>2</option>
		  <option>3</option>
		  <option>4</option>
		  <option>5</option>
		</select>
	  </div>

		<div class='form-group'>
			<label for='text'>Artikeltext</label>
			<textarea class='form-control' id='text' name='text' rows='5' placeholder='Verfasse hier deinen Artikel'>" . $textdb . "</textarea>
			<small>Formartierungshilfe: &lt;b&gt;<b>Fetter Text</b>&lt;/b&gt;, &lt;i&gt;<i>Kusiver Text</i>&lt;/i&gt;, &lt;u&gt;<u>Untersrichener Text</u>&lt;/u&gt;, &lt;big&gt;<big>Großer Text</big>&lt;/big&gt;</small>
		</div>
		<input type='submit' class='btn btn-primary' name='change' value='Entwurf ändern'>
		</form>
		";
		}
		$this->LAYOUTfooter();
	}


	public function GUInewArticle() {
		$this->LAYOUTtop();
		echo "<a href='index.php' class='btn btn-secondary'>← Zurück zur Auswahl</a><br />
		";
		$meldung = "";
		if(isset($_POST['title']) && isset($_POST['date']) && isset($_POST['aktuelles']) && isset($_POST['text'])) {
			if(isset($_POST['draft'])) {
				// Entwurf
				$userid = $_SESSION['id'];
				$title = $_POST['title'];
				$teaser = $_POST['teaser'];
				$created = $_POST['date'];
				$lastmod = time();
				if($_POST['aktuelles'] == "yes")  {
					$aktuell = 1;
				} else {
					$aktuell = 0;
				}
				if($_POST['details'] == "yes")  {
					$details = 1;
				} else {
					$details = 0;
				}
				$text = $_POST['text'];
				$url = strtolower($title);
				$vokale = array("a", "o", "u", "a", "o", "u", "-", "ss");
				$umlaute = array("A", "Ö", "Ü", "ä", "ö", "ü", " ", "ß");
				$url = str_replace($umlaute, $vokale, $url);
				$url = $this->DB->escape($url);
				$userid = $this->DB->escape($userid);
				$title = $this->DB->escape($title);
				$teaser = $this->DB->escape($teaser);
				$created = $this->DB->escape($created);
				$lastmod = $this->DB->escape($lastmod);
				$aktuell = $this->DB->escape($aktuell);
				$details = $this->DB->escape($details);
				$text = $this->DB->escape($text);
				$sql = "INSERT INTO " . $this->config['databaseprefix'] . "drafts (id, userid, title, teaser, created, lastmod, url, aktuell, details, text) VALUES (NULL, '" . $userid . "', '" . $title . "', '" . $teaser . "', '" . $created . "', '" . $lastmod . "', '" . $url . "', '" . $aktuell . "', '" . $details . "', '" . $text . "');";
				$this->DB->query($sql);
				$this->writeLog("Draft " . $title . " was created by " . $_SESSION['id'] . " (" . $_SESSION['vorname'] . ").", "createdraft");
				// ToDo in Datenbank
				echo "<div class='alert alert-success'>Entwurf angelegt!</div>";
				echo "Meine Entwürfe:
				<table class='table'>
				<thead>
				<tr>
				<th>Name</th><th>Erstellt</th><th>Letzte Änderung</th><th>Teaser</th><th>Aktion</th>
				</tr>
				</thead>
				<tbody>
				" . $this->showMyDrafts() . "</tbody></table>";
			} 
		} else {
		echo "Einen neuen Artikel veröffentlichen: <br />$meldung
		<form method='post'>

		<div class='form-group'>
		  <label for='title'>Titel des Artikels</label>
			<input type='text' class='form-control' id='title' name='title' placeholder='Gebe hier einen aussagekräftigen Titel an'>
		</div>
		<div class='form-group'>
		<label for='teaser'>Teaser</label>
		  <input type='text' class='form-control' id='teaser' name='teaser' placeholder='Gebe hier einen knappe Zusammenfassung an'>
	  </div>
		<div class='form-group'>
			<label for='date'>Datum</label> 
			<input class='form-control' value='" . date('d.m.Y') . "' name='date'>
		</div>
		<div class='form-check'>
			<label for='aktuelles'>Erscheint unter Aktuelles:</label><br />
			<input type='radio' name='aktuelles' value='yes' id='yes' checked='checked'><label for='yes'> Ja</label> <input type='radio' name='aktuelles' id='no' value='no'><label for='no'> Nein</label><br />
		</div>
		<div class='form-check'>
		<label for='details'>Details (Autor, Veröffentlichung) anzeigen:</label><br />
		<input type='radio' name='details' value='yes' id='yes' checked='checked'><label for='yes'> Ja</label> <input type='radio' name='details' id='no' value='no'><label for='no'> Nein</label><br />
	</div>
		<div class='form-group'>
		<label for='ausgabe'>Artikel gehört zu Ausgabe</label>
		<select class='form-control' id='ausgabe'>
		  <option>-Bitte auswählen / Keine bestimmte Ausgabe-</option>

		  <option>2</option>
		  <option>3</option>
		  <option>4</option>
		  <option>5</option>
		</select>
	  </div>

		<div class='form-group'>
			<label for='text'>Artikeltext</label>
			<textarea class='form-control' id='text' name='text' rows='5' placeholder='Verfasse hier deinen Artikel'></textarea>
			<small>Formartierungshilfe: &lt;b&gt;<b>Fetter Text</b>&lt;/b&gt;, &lt;i&gt;<i>Kusiver Text</i>&lt;/i&gt;, &lt;u&gt;<u>Untersrichener Text</u>&lt;/u&gt;, &lt;big&gt;<big>Großer Text</big>&lt;/big&gt;</small>
		</div>
		<input type='submit' class='btn btn-primary' name='draft' value='Entwurf anlegen'>
		
		</form> 
		
		";
		}
		$this->LAYOUTfooter();
	}

	public function GUImyDrafts() {
		$this->LAYOUTtop();
		echo "<a href='index.php' class='btn btn-secondary'>← Zurück zur Auswahl</a><br />
		";
		if(isset($_GET['msg'])) {
			if($_GET['msg'] == "del") {
				echo "<div class='alert alert-success'>Entwurf gelöscht!</div>";
			}
		}
		echo "Meine Entwürfe:
		<table class='table'>
		<thead>
		<tr>
		<th>Name</th><th>Erstellt</th><th>Letzte Änderung</th><th>Teaser</th><th>Aktion</th>
		</tr>
		</thead>
		<tbody>
		" . $this->showMyDrafts() . "</tbody></table>";
		$this->LAYOUTfooter();
	}

	public function GUIshowDashboard() {
		$this->LAYOUTtop();
		$this->tpl->render('dashboard', [
			'VORNAME' => $_SESSION['vorname'],
			'STATUSER' => $this->config['statisticsuser'] ,
			'STATUSERPW' => $this->config['statisticspassword'],
			'STATURL' => $this->config['statisticsurl'],
		]);
	}

	public function GUImenu() {
		$this->LAYOUTtop();
		$vorname = $_SESSION['vorname'];
		echo "<a href='index.php' class='btn btn-secondary'>← Zurück zur Auswahl</a><br />" . $this->showMenu();
		$this->LAYOUTfooter();
	}

	public function GUIstaff() {
		$this->LAYOUTtop();
		$this->tpl->render('staff', [
			'STAFF' => $this->getStaff(),
		]);
		$this->LAYOUTfooter();
	}

	public function GUIregister() {
		$this->LAYOUTtop();
		$successmsg = false;
		$errormsg = false;
		if(isset($_POST['name']) AND isset($_POST['email'])) {
			// Register
			$exists = $this->checkIfMailExists($_POST['email']);
			if($exists == false) {
				$this->createNewUser($_POST['name'], $_POST['email']);
				$successmsg = true;
				$success = "Der Benutzer <b>" . $_POST['name'] . "</b> wurde gerade im System angelegt! Er/Sie erhält nun eine E-Mail mit allen weiteren Anweisungen um sich einzuloggen.";
			} else {
				$errormsg = true;
				$error = "Ein Benutzer mit der gleichen E-Mail Adresse existiert im System bereits";

			}
		}
		echo "<a href='index.php?page=staff' class='btn btn-secondary'>← Zurück zur Übersicht</a><br />
		Neues Mitglied.
		Bitte gebe hier die Daten für das Neumitglied an. <br />Weitere Informationen werden dann an die dort angegebene E-Mail Adresse gesendet. ";
		if($successmsg) {
			echo "<div class='alert alert-success'>" . $success . "</div>";
		} else if ($errormsg) {
			echo "<div class='alert alert-danger'>" . $error . "</div><form method='post'>
		Name: <input type='' name='name'>
		E-Mail Adresse (für Passwort benötigt):  <input type='email' name='email'>
		<input type='submit' class='btn btn-primary' value='Benutzer anlegen'>
		</form>";
		} else {
			echo "<form method='post'>
		Name: <input type='' name='name'>
		E-Mail Adresse (für Passwort benötigt):  <input type='email' name='email'>
		<input type='submit' class='btn btn-primary' value='Benutzer anlegen'>
		</form>";
		}
		$this->LAYOUTfooter();
	}

	public function GUIdisableUser() {
		$this->LAYOUTtop();
		echo "<a href='index.php?page=staff' class='btn btn-secondary'>← Zurück zur Übersicht</a><br />";
		if(!isset($_GET['uid'])) {
			echo "Es fehlt der Benutzer-Parameter";
		} else {
			if($_GET['uid'] != "") {
				$uid = $this->DB->escape($_GET['uid']);
				$sql = "SELECT id, vorname FROM " . $this->config['databaseprefix'] . "accounts WHERE id = " . $uid;
				$this->DB->query($sql);
				$result = $this->DB->fetchRow();
				if($result['id'] == $_SESSION['id']) {
					echo "<b>Du bist im Begriff dein eigenes Konto zu sperren.</b><br />";
				} else {
					echo "Du sperrst " . @$result['vorname'] . "!<br/>";
				}
				echo "Dies ist eine extrem harte Aktion die du durchführt. Der betreffene Benutzer kann dann NICHT mehr auf die Seite zugreifen.<br/>";
				 if(isset($_GET['confirm'])) {
					if($_GET['confirm'] == 1) {
						$this->disableUser($_GET['uid']);
						echo "<b>Erfolgreich gesperrt</b>";
					}
				} else {
					echo "Möchtest du wirklich fortfahren? 
				   <a href='index.php'>Nein</a> <a href='index.php'>Nein</a> <a href='index.php'>Nein</a> <a href='index.php'>Nein</a> <a href='index.php?page=disableuser&uid=" . $_GET['uid'] . "&confirm=1'>Ja</a> <a href='index.php'>Nein</a> <a href='index.php'>Nein</a>";
				}
			}
		}
		$this->LAYOUTfooter();
	}

	public function GUIrecoverpw() {
		$this->LAYOUTtop();
		echo "<a href='index.php?page=staff' class='btn btn-secondary'>← Zurück zur Übersicht</a><br />";
		if(isset($_GET['confirm']) && $_GET['confirm'] == 1) {
			$this->passwordRecovery($_GET['uid']);
			echo "Mail versendet";
		} else {
			echo "Der betreffende Benutzer bekommt eine Mail mit einem Link, wo er sein Passwort zurücksetzten kann an die hier hinterlegte EMail Adresse gesendet. Fortfahren? <a href='index.php?page=recoverpw&uid=" . $_GET['uid'] . "&confirm=1'><input type='button' class='btn btn-primary' value='Weiter'></a>";
		}
		$this->LAYOUTfooter();
	}

	public function GUIviewDraft() {
		$this->LAYOUTtop();
		echo "<a href='index.php?page=mydrafts' class='btn btn-secondary'>← Zurück zur Übersicht</a>";
		if(isset($_GET['id'])) {
			$id = $this->DB->real_escape_string($_GET['id']);
			$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "drafts WHERE id = " . $id . ";";
			$this->DB->query($sql);
			$result = $this->DB->fetchRow();
		
			$iddb = $result['id'];
			$useriddb = $result['userid'];
			$titledb = $result['title'];
			$teaserdb = $result['teaser'];
			$createddb = $result['created'];
			$lastmoddb = $result['lastmod'];
			$urldb = $result['url'];
			$aktuelldb = $result['aktuell'];
			$detailsdb = $result['details'];
			$textdb = $result['text'];

			$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "accounts WHERE id = " . $useriddb . ";";
			$this->DB->query($sql);
			$vornamedb = $this->DB->fetchRow()['vorname'];
			$message = "";
			$disablebuttons = false;

			if(isset($_GET['action'])) {
				if($_GET['action'] == "publish") {
					//Publish here
					$sql = "SELECT * FROM " . $this->config['databaseprefix'] . "articles WHERE url = '" . $urldb . "';";
					$this->DB->query($sql);
					$num = $this->DB->count();
					if($urldb == "" OR $num > 0) {
						// URL existiert bereits
						$message .= "<div class='alert alert-danger'>Fehler: Der Artikel wurde nicht gepseichert: Die URL existiert bereits! Wähle einen anderen Titel!</div>";
					} else {
						$this->writeLog("Article: " . $titledb . ", URL: " . $urldb, "publisharticle");
						$sql = "INSERT INTO " . $this->config['databaseprefix'] . "articles (id, userid, title, teaser, created, lastmod, url, aktuell, details, text, disabled) VALUES (NULL, '" . $useriddb . "', '" . $titledb . "', '" . $teaserdb . "', '" . $createddb . "', '" . $lastmoddb . "', '" . $urldb . "', '" . $aktuelldb . "', '" . $detailsdb . "', '" . $textdb . "', '0');";
						$this->DB->query($sql);
						$sql = "DELETE FROM " . $this->config['databaseprefix'] . "drafts WHERE id = '" . $id . "';";
						$this->DB->query($sql);
						$this->broadcastMail($titledb . " –  " . $this->config['host'] . "/a/" . $urldb, "newArticle");
						$message .= "<div class='alert alert-success'>Artikel wurde veröffentlicht</div>";
						$disablebuttons = true;
					}
				}
			}
			if($aktuelldb == 1) {
				$aktuelltext = "ja";
			} else {
				$aktuelltext = "nein";
			}
			if($detailsdb == 1) {
				$detailstext = "ja";
			} else {
				$detailstext = "nein";
			}
			if(isset($_GET['msg'])) {
				if($_GET['msg'] == "save") {
					$message .= "<div class='alert alert-success'>Änderungen gespeichert</div>";
				}
			}
			echo "<p>$message<h2>" . $titledb . "</h2><h4>Verfasst am $createddb von $vornamedb<br /><small>URL: /$urldb | Erscheint unter aktuelles: $aktuelltext | Details anzeigen: $detailstext | Letzte Änderung: " . date('d.m.Y' , $lastmoddb) . "</small></h4>";
			if($disablebuttons == false) {
				echo "<small><a href='index.php?page=editdraft&id=" . $iddb . "' class='btn btn-primary'>Bearbeiten</a> <a onclick='" . 'document.getElementById("publishwarning").style.display = "block"' . "' class='btn btn-primary'>Veröffentlichen</a> <a href='?page=deldraft&id=" . $iddb . "'  class='btn btn-primary'>Löschen</a></small></p>";
			}
			echo "<div id='publishwarning' style='display:none' class='alert alert-info'>Nach der Veröffentlichung besteht keine Möglichkeit mehr, den Artikel zu bearbeiten. Klicke weiter, wenn alles entgültig okay ist.<br /><a href='?page=viewdraft&id=" . $id . "&action=publish' class='btn btn-primary'>Okay, verstanden! Weiter &gt;</a></div>";
			echo "<div>" . $textdb . "</div>";
		} else {
			echo "Bitte ID des Entwurfes angeben";
		}
		$this->LAYOUTfooter(); 
	}

	public function GUIdelDraft() {
		if(isset($_GET['id'])) {
			if(isset($_GET['confirm'])) {
				$id = $this->DB->escape($_GET['id']);
				$sql = "INSERT INTO " . $this->config['databaseprefix'] . "draftsold (userid, title, teaser, created, lastmod, url, aktuell, details, text) SELECT userid, title, teaser, created, lastmod, url, aktuell, details, text FROM " . $this->config['databaseprefix'] . "drafts WHERE id LIKE " . $id . ";";
				$this->DB->query($sql);
				$sql = "DELETE FROM " . $this->config['databaseprefix'] . "drafts WHERE id LIKE " .  $id . ";";
				$this->DB->query($sql);
				header('Location: index.php?page=mydrafts&msg=del');
			} else {
			$this->LAYOUTtop();
				echo "<div class='alert alert-danger'><b>Achtung!</b> Dies wird den Entwurf unwiederruflich Löschen! Möchtest du Fortfahren?<br />
				<a href='?page=deldraft&id=" . $_GET['id'] . "&confirm' class='btn btn-success'>Ja</a> <a href='?page=viewdraft&id=" . $_GET['id'] . "' class='btn btn-danger'>Nein</a></div>";
			}
		} else {
			echo "Bitte die ID des Entwurfes angeben";
		}
		$this->LAYOUTfooter();
	}

	public function GUIuserSettings() {
		if(isset($_POST['name'])) {
			$id = $_SESSION['id'];
			if($_SESSION['vorname'] != $_POST['name']){
				$vorname = $this->DB->escape($_POST['name']);
				$sql = "UPDATE " . $this->config['databaseprefix'] . "accounts SET vorname = '" . $vorname . "' WHERE id = '" . $id . "'";
				$this->DB->query($sql);
				$_SESSION['vorname'] = $vorname;
			}

			if(isset($_POST['neueraccountemail'])) {
				if($_POST['neueraccountemail'] == 1) {
					// Enable
					$sql = "UPDATE " . $this->config['databaseprefix'] . "accounts SET sendmailnu = '1' WHERE id = '" . $id . "'";
					$this->DB->query($sql);
				}
			} else {
				// Disable
				$sql = "UPDATE " . $this->config['databaseprefix'] . "accounts SET sendmailnu = '0' WHERE id = '" . $id . "'";
				$this->DB->query($sql);
			}
			if(isset($_POST['neuerartikelemail'])) {
				if($_POST['neuerartikelemail'] == 1) {
					// Enable
					$sql = "UPDATE " . $this->config['databaseprefix'] . "accounts SET sendmailna = '1' WHERE id = '" . $id . "'";
					$this->DB->query($sql);
				}
			} else {
				// Disable
				$sql = "UPDATE " . $this->config['databaseprefix'] . "accounts SET sendmailna = '0' WHERE id = '" . $id . "'";
				$this->DB->query($sql);
			}
		}

		$this->LAYOUTtop();
		echo "<a href='index.php' class='btn btn-secondary'>← Zurück zur Auswahl</a>";
		echo  "<div class='row'>
		<div class='col-md-6 col-md-offset-3'>
		<legend class='text-center'>Allgemeine Einstellungen</legend>
		<form class='form-horizontal' method='post' action='?page=settings'>
		<div class='form-group'>
		<label class='col-md-3 control-label' for='name'>Name</label>
		<div class='col-md-9'>
		<input id='name' name='name' type='text' placeholder='Your name' class='form-control' value='" . $_SESSION['vorname'] . "'>
		</div>
		</div><!--
		<div class='form-group'>
		<label class='col-md-3 control-label' for='email'>E-Mail</label>
		<div class='col-md-9'>
		<input id='email' name='email' type='email' placeholder='E-Mail' class='form-control' value=''>
		</div>
		</div>-->
		<div class='form-group'>
		<label class='col-md-3 control-label' for='pwchange'>Passwort</label>
		<div class='col-md-9'>
		<a onClick=" . '"' . "document.getElementById('pwchangeform').style.display = 'inline'" . '"' . " id='pwchange' class='btn btn-primary'>Passwort ändern</a>
		<div id='pwchangeform' style='display:none'>
		<input name='pwold' type='password' class='form-control' placeholder='Dein aktuelles Passwort'>
		<input name='pwnew' type='password' class='form-control' placeholder='Dein neues Passwort'>
		<input name='pwconfirm' type='password' class='form-control' placeholder='Bestätige dein neues Passwort'>
		<input type='button' value='Ändern'>
		</div>
		</div>
		</div>
		<div class='form-group'>
		<label class='col-md-3 control-label'>Letzter Login</label>
		<div class='col-md-9'>
		<span class='align-middle'>Am ";
		$sql = "SELECT timestamp, device FROM " . $this->config['databaseprefix'] . "logins WHERE userid = '" . $_SESSION['id'] . "' ORDER BY timestamp DESC LIMIT 1 OFFSET 1;";
		$this->DB->query($sql);
		if($this->DB->count() > 0) {
			$result = $this->DB->fetchRow();
			$lastseendb = $result['timestamp'];
			$device = $result['device'];
		} else {
			$lastseendb = 1;
			$device = "";
		}
		echo date('d.m.Y H:i',$lastseendb); 
		echo " auf $device</span>
		</div>
		</div>
		<legend class='text-center'>Benachrichtigungen</legend>
		<p class='text-center'>Erhalte Benachrichtigunegen per E-Mail für:<br /></p>
		<div class='form-group'>
		<label class='col-md-3 control-label' for='na'>Neuer Artikel</label>
		<div class='col-md-9'>
		<label style='font-weight:normal'>
		<input type='checkbox' id='na' name='neuerartikelemail' value='1'";
		$sql = "SELECT sendmailna FROM " . $this->config['databaseprefix'] . "accounts WHERE id = '" . $_SESSION['id'] . "';";
		$this->DB->query($sql);
		$checked = $this->DB->fetchRow()['sendmailna'];

		if($checked) {
			echo " checked";
		}
		echo ">
		Benachrichtige mich, wenn ein neuer Artikel veröffentlicht wurde.
		</label>
		</div>
		</div>
		<div class='form-group'>
		<label class='col-md-3 control-label' for='email'>Neuer Benutzer</label>
		<div class='col-md-9'>
		<label style='font-weight:normal'>
		<input type='checkbox' name='neueraccountemail' value='1'";
		$sql = "SELECT sendmailnu FROM " . $this->config['databaseprefix'] . "accounts WHERE id = '" . $_SESSION['id'] . "';";
		$this->DB->query($sql);
		$checked = $this->DB->fetchRow()['sendmailnu'];

		if($checked) {
			echo " checked";
		}
		echo ">
		Benachrichtige mich, wenn ein neuer Account angelegt wurde.
		</label>
		</div>
		</div>
		<div class='text-center'><br /><br />
		<input class='btn btn-success text-center' type='submit' value='Einstellungen ändern'></div>
		</div>
		</form>";
		$this->LAYOUTfooter();
	}

	public function GUIausgaben() {
		$this->LAYOUTtop();
		$vorname = $_SESSION['vorname'];
		echo "<a href='index.php' class='btn btn-secondary'>← Zurück zur Auswahl</a><br />" . $this->showAusgaben();
		$this->LAYOUTfooter();
	}

	public function GUIlistFiles() {
		$this->LAYOUTtop();
		echo "<a href='index.php' class='btn btn-secondary'>← Zurück zur Auswahl</a><br />" . $this->showFiles();
		$this->LAYOUTfooter();
	}

	public function LAYOUTtop() {
		// ToDo: HTML-Top
		header('Content-Type: text/html; charset=utf-8');
		$vorname = 0;
		if(isset($_SESSION['vorname'])) {
			$vorname = $_SESSION['vorname'];
		}
		$this->tpl->render('header', [
			'NAME' => $this->config['name'],
			'ACCENT' => $this->config['accentcolor'],
			'VORNAME' => $vorname,
		]);
	}

	public function LAYOUTfooter() {
		$this->tpl->render('footer');
	}
}

new verwaltung();
