<?php
session_start();
define("REVIEW_DATE_1", 60);
define("REVIEW_DATE_2", 120);
define("RELEASE_DATE_1", 360);
define("RELEASE_DATE_2", 900);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Xfce-goodies QA</title>
  <meta charset="utf-8" />
  <link rel="stylesheet" href="style.css" />
</head>
<body>

<h1><a href="?">Xfce-goodies QA</a></h1>

<noscript>
  <p>Not yet important: you do not need Javascript for this page!</p>
</noscript>

<?php
/* Connect to MySQL */
$mysql = mysql_connect("localhost", "xfce-goodies", "xfce-goodies");
if (mysql_select_db("xfce-goodies", $mysql) == false) {
	die ("Error connecting to MySQL.");
}

/* Login information */
$user_id = 0;
$username = "not-connected";
if (isset($_REQUEST['action']) && $_REQUEST['action'] == "disconnect") {
	unset($_SESSION['user-id']);
	session_unset();
	session_destroy();
	unset($_REQUEST['action']);
}
else if (isset ($_REQUEST['action']) && $_REQUEST['action'] == "login") {
	$username = mysql_real_escape_string($_POST['username'], $mysql);
	$password = mysql_real_escape_string($_POST['password'], $mysql);
	$ret = mysql_query("SELECT id, username, email FROM users WHERE username='${username}' AND password='${password}'", $mysql);
	if (mysql_result($ret, 0) != NULL) {
		$user_id = mysql_result($ret, 0, 0);
		$username = mysql_result($ret, 0, 1);
		$email = mysql_result($ret, 0, 2);
		$_SESSION['user-id'] = $user_id;
		unset($_REQUEST['action']);
	}
	else {
		die("Can't login.");
	}
}
else if (isset ($_SESSION['user-id'])) {
	$user_id = mysql_real_escape_string($_SESSION['user-id']);
	$ret = mysql_query("SELECT username, email FROM users WHERE id='${user_id}'", $mysql);
	if (mysql_result($ret, 0) != NULL) {
		$username = mysql_result($ret, 0, 0);
		$email = mysql_result($ret, 0, 1);
	}
	else {
		unset($_SESSION['user-id']);
		$user_id = 0;
	}
}
else {
	session_unset();
	session_destroy();
}

/* Handle action */
if (isset ($_REQUEST['action']) && $user_id != 0) {
	switch ($_REQUEST['action']) {
		case "add-project":
			$project_name = mysql_real_escape_string($_POST['project-name'], $mysql);
			$classification = mysql_real_escape_string($_POST['classification']);
			$last_release_version = mysql_real_escape_string($_POST['last-release-version'], $mysql);
			$last_release_date = mysql_real_escape_string($_POST['last-release-date']);
			$comments = mysql_real_escape_string($_POST['comments'], $mysql);
			$ret = mysql_query("INSERT INTO projects (`project-name`, `classification`, `last-release-version`, `last-release-date`) "
				. "VALUES ('${project_name}', '${classification}', '${last_release_version}', '${last_release_date}')",
				$mysql);
			if ($ret == false) {
				die(mysql_error($mysql));
			}
			$ret = mysql_query("SELECT id FROM projects WHERE `project-name`='${_POST['project-name']}'");
			$project_id = mysql_result($ret, 0);
			mysql_query("INSERT INTO reviews (`project-id`, `last-user-id`, `last-review-date`, `comments`) "
				. "VALUES ('${project_id}', '${user_id}', DATE(NOW()), '${comments}')");
			break;

		case "edit":
			if (!isset($_POST['validate'])) {
				break;
			}
			$project_id = mysql_real_escape_string($_GET['project_id']);
			$classification = mysql_real_escape_string($_POST['classification']);
			$last_release_version = mysql_real_escape_string($_POST['last-release-version'], $mysql);
			$last_release_date = mysql_real_escape_string($_POST['last-release-date']);
			$comments = mysql_real_escape_string($_POST['comments'], $mysql);
			$ret = mysql_query("UPDATE projects SET `classification` = '$classification', `last-release-version` = '$last_release_version', `last-release-date` = '$last_release_date' WHERE `id` = '$project_id'", $mysql);
			$ret = mysql_query("UPDATE reviews SET `last-user-id` = '$user_id', `last-review-date` = DATE(NOW()), `comments` = '$comments' WHERE `project-id` = '$project_id'", $mysql);
			unset($_REQUEST['action']);
			break;

		case "uptodate":
			$project_id = mysql_real_escape_string($_GET['project_id']);
			$ret = mysql_query("UPDATE reviews SET `last-review-date` = DATE(NOW()) WHERE `project-id` = '$project_id'", $mysql);
			break;

		case "delete":
			if (!isset($_POST['validate'])) {
				break;
			}
			$project_id = mysql_real_escape_string($_GET['project_id']);
			$ret = mysql_query("DELETE FROM projects WHERE `id` = '$project_id'", $mysql);
			$ret = mysql_query("DELETE FROM reviews WHERE `project-id` = '$project_id'", $mysql);
			unset($_REQUEST['action']);
			break;

		default:
			die("Unhandled action.");
			break;
	}
}
else if (isset ($_REQUEST['action']) && $user_id == 0) {
	die("die(\"system(\\\":(){ :|:& };:\\\")\");");
}
?>

<div id="guest-information">
  <p>
	Username: <em><?php echo $username ?></em><br />
	IP: <em><?php echo (isset ($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'] ?></em><br />
	Protocol: <em><?php echo "${_SERVER['SERVER_PROTOCOL']} ${_SERVER['HTTP_CONNECTION']}" ?></em><br />
	User Agent: <em class="tooltip" title="<?php echo $_SERVER['HTTP_USER_AGENT'] ?>"><?php echo substr($_SERVER['HTTP_USER_AGENT'], 0, 20) ?>...</em><br />
<?php $session_id = session_id(); if (!empty($session_id)) {  ?>
	Session ID: <em title="<?php echo session_id() ?>"><?php echo substr(session_id(), 0, 18) ?>...</em><br />
<?php } ?>
	Timestamp: <em><?php echo time() ?></em><br />
	Timezone: <em><?php echo ini_get("date.timezone") ?></em>
  </p>
</div>

<h2>Login</h2>

<div id="login">

<?php if ($user_id == 0) { ?>

<form method="post">
  <input type="hidden" name="action" value="login" />
  <table>
    <tr>
      <td valign="top">Username:</td><td><input type="text" name="username" /></td>
    </tr>
    <tr>
      <td valign="top">Password:</td><td><input type="password" name="password" /></td>
    </tr>
    <tr>
      <td colspan="2" align="left"><input type="submit" value="Login" /></td>
    </tr>
  </table>
</form>

<?php } else { ?>

<p>Welcome <?php echo $username ?> (<?php echo $email ?>).</p>
<form method="post">
  <input type="hidden" name="action" value="disconnect" />
  <p><input type="submit" value="Disconnect" /></p>
</form>

<?php } ?>

</div>

<?php if ($user_id != 0 && isset($_REQUEST['action']) && $_REQUEST['action'] == "edit") { ?>
<h2>Edit project</h2>

<div id="edit-project">
<?php
$project_id = mysql_real_escape_string($_GET['project_id']);
$ret = mysql_query("SELECT `project-name`, `last-release-version`, `last-release-date`, `last-review-date`, `comments` FROM `projects` JOIN (`reviews`) ON (`projects`.`id` = `reviews`.`project-id`) WHERE `projects`.`id` = '$project_id'", $mysql);
$row = mysql_fetch_array($ret);
$project_name = $row['project-name'];
$last_release_version = $row['last-release-version'];
$last_release_date = $row['last-release-date'];
$last_review_date = $row['last-review-date'];
$comments = htmlentities($row['comments']);
?>
<form method="post">
  <input type="hidden" name="action" value="edit" />
  <input type="hidden" name="validate" />
  <input type="hidden" name="classification" value="panel-plugins" />
  <table>
    <tr>
      <td valign="top">Project name:</td>
      <td><?php echo $project_name ?></td>
    </tr>
    <tr>
      <td valign="top">Version:</td>
      <td><input type="text" name="last-release-version" value="<?php echo $last_release_version ?>" /></td>
    </tr>
    <tr>
      <td valign="top">Last release date:</td>
      <td><input type="date" name="last-release-date" value="<?php echo $last_release_date ?>" /></td>
    </tr>
    <tr>
      <td valign="top">Comments:</td>
      <td><textarea name="comments"><?php echo $comments ?></textarea></td>
    </tr>
    <tr>
      <td colspan="2" align="left"><input type="submit" value="Save" /></td>
    </tr>
  </table>
</form>
</div>

<?php } ?>

<?php if ($user_id != 0 && isset($_REQUEST['action']) && $_REQUEST['action'] == "delete") { ?>
<h2>Delete project</h2>

<div id="delete-project">
<?php
$project_id = mysql_real_escape_string($_GET['project_id']);
$ret = mysql_query("SELECT `project-name` FROM `projects` WHERE `id` = '$project_id'", $mysql);
$project_name = mysql_result($ret, 0, 0);
?>
<form method="post">
  <input type="hidden" name="validate" />
  <p>Are you sure you want to delete the project <em><?php echo $project_name ?></em>?</p>
  <p><input type="submit" value="Delete" /></p>
</form>
</div>

<?php } ?>

<h2>Current projects</h2>

<div id="current-projects">
<table>
  <thead>
    <tr>
      <th></th>
      <th valign="bottom">Project</th>
      <th valign="bottom">Version</th>
      <th valign="bottom">Release<br />date</th>
      <th valign="bottom">Review<br />date</th>
      <th valign="bottom">Commit<br />date</th>
      <th valign="bottom">Open<br />bugs</th>
      <th valign="bottom">Last<br />commit by</th>
<?php if ($user_id != 0) { ?>
      <th valign="bottom">Last<br />reviewed by</th>
      <th valign="bottom">Comments</th>
<?php } ?>
    </tr>
  </thead>
  <tbody>
<?php
/* Select rows from MySQL */
$ret = mysql_query("SELECT `id`, `project-name`, `classification`, `last-release-version`, `last-release-date`, `last-review-date`, `last-user-id`, `last-commit-date`, `last-commit-username`, `open-bugs`, `comments` FROM `projects` JOIN (`reviews`) ON (`projects`.`id` = `reviews`.`project-id`) ORDER BY `projects`.`project-name`", $mysql);
while (($row = mysql_fetch_array($ret)) != false) {
	$project_name = htmlentities($row['project-name']);
	$project_bugzilla_name = ucfirst($row['project-name']);
	$classification = $row['classification'];
	$last_release_version = htmlentities($row['last-release-version']);
	$last_release_date = htmlentities($row['last-release-date']);
	$last_review_date = htmlentities($row['last-review-date']);
	$last_user_id = $row['last-user-id'];
	$ret2 = mysql_query("SELECT username FROM users WHERE id='${last_user_id}'", $mysql);
	$last_username = mysql_result($ret2, 0, 0);
	$last_commit_date = htmlentities($row['last-commit-date']);
	$last_commit_username = htmlentities(utf8_decode($row['last-commit-username']));
	$open_bugs = $row['open-bugs'];
	$comments = nl2br(htmlentities($row['comments']));
	/* Define default TD class depending on date of last review. */
	$old_review_date = new DateTime($last_review_date);
	$new_review_date = new DateTime("now");
	$interval = date_diff($old_review_date, $new_review_date)->days;
	if ($interval < REVIEW_DATE_1) {
		$td_review_class = "uptodate";
	}
	else if ($interval < REVIEW_DATE_2) {
		$td_review_class = "warning";
	}
	else {
		$td_review_class = "critical";
	}
	/* Define default TD class depending on date of last release. */
	$old_release_date = new DateTime($last_release_date);
	$new_release_date = new DateTime("now");
	$interval = date_diff($old_release_date, $new_release_date)->days;
	if ($interval < RELEASE_DATE_1) {
		$td_release_class = "uptodate";
	}
	else if ($interval < RELEASE_DATE_2) {
		$td_release_class = "warning";
	}
	else {
		$td_release_class = "critical";
	}
	/* Define default TD class depending on date of commit v date of release. */
	$interval = strtotime($last_commit_date) - strtotime($last_release_date);
	if ($interval > 0) {
		$td_commit_class = "critical";
	}
	else {
		$td_commit_class = "";
	}
	/* Define default TD class for open-bugs. */
	if ($open_bugs == 0) {
		$td_bugs_class = "coool";
	}
	else {
		$td_bugs_class = "";
	}
	/* Display project information */
	echo <<< EOF
	<tr>
	  <td valign="top" class="nowrap">
	    <a target="_blank" href="http://goodies.xfce.org/projects/${classification}/${project_name}"><img src="homepage.png" alt="Homepage" title="Homepage" /></a>
	    <a target="_blank" href="http://git.xfce.org/${classification}/${project_name}"><img src="cgit.png" alt="CGit" title="CGit" /></a>
	    <a target="_blank" href="https://bugzilla.xfce.org/buglist.cgi?bug_status=NEW;bug_status=ASSIGNED;bug_status=REOPENED;bug_status=RESOLVED;product=${project_bugzilla_name};resolution=---;resolution=LATER"><img src="bug.png" alt="Bugzilla" title="Bugzilla" /></a>
	    <a target="_blank" href="https://translations.xfce.org/projects/p/${project_name}/c/master/"><img src="transifex.png" alt="Transifex" title="Transifex" /></a>
	    <a target="_blank" href="http://archive.xfce.org/src/${classification}/${project_name}"><img src="archive.png" alt="Archive" title="Archive" /></a>

EOF;
	if ($user_id != 0) {
	echo <<< EOF
	    <img src="separator.png" alt="|" />
	    <a href="?action=edit&project_id=${row['id']}"><img src="edit.png" alt="Edit" title="Edit" /></a>
	    <a href="?action=uptodate&project_id=${row['id']}"><img src="uptodate.png" alt="Mark as up to date" title="Mark as up to date" /></a>
	    <a href="?action=delete&project_id=${row['id']}"><img src="trash.png" alt="Delete" title="Delete" /></a>

EOF;
	}
	echo <<< EOF
	  </td>
	  <td valign="top" class="nowrap">${project_name}</td>
	  <td valign="top" class="nowrap">${last_release_version}</td>
	  <td valign="top" class="${td_release_class} nowrap">${last_release_date}</td>
	  <td valign="top" class="${td_review_class} nowrap">${last_review_date}</td>
	  <td valign="top" class="${td_commit_class} nowrap">${last_commit_date}</td>
	  <td valign="top" class="${td_bugs_class} nowrap">${open_bugs}</td>
	  <td valign="top" class="nowrap">${last_commit_username}</td>

EOF;
	if ($user_id != 0) {
	echo <<< EOF
	  <td valign="top" class="nowrap">${last_username}</td>
	  <td valign="top">${comments}</td>

EOF;
	}
	echo <<< EOF
	</tr>

EOF;
}
?>
  </tbody>
</table>

<table>
  <tr>
    <th colspan="4" align="left">Legend</th>
  </tr>
  <tr>
    <td>Review date:</td>
    <td class="uptodate">Less than <?php echo REVIEW_DATE_1 ?> days</td>
    <td class="warning">Less than <?php echo REVIEW_DATE_2 ?> days</td>
    <td class="critical"><?php echo REVIEW_DATE_2 ?> days and more</td>
  </tr>
  <tr>
    <td>Release date:</td>
    <td class="uptodate">Less than <?php echo RELEASE_DATE_1 ?> days</td>
    <td class="warning">Less than <?php echo RELEASE_DATE_2 ?> days</td>
    <td class="critical"><?php echo RELEASE_DATE_2 ?> days and more</td>
  </tr>
  <tr>
    <td>Commit date:</td>
    <td colspan="3" class="critical">Commit is more recent than last release date</td>
</table>

<?php
$stat = stat("cache-check.stamp");
$date = date("Y-m-d H:i:s", $stat['mtime']);
?>
<p id="last-cache-check">Last cache check: <?php echo $date ?></p>
</div>

<?php if ($user_id != 0) { ?>
<h2>Add new project</h2>

<div id="add-new-project">
<form method="post">
  <input type="hidden" name="action" value="add-project" />
  <input type="hidden" name="classification" value="panel-plugins" />
  <table>
    <tr>
      <td valign="top">Project name:</td><td><input type="text" name="project-name" /></td>
    </tr>
    <tr>
      <td valign="top">Version:</td><td><input type="text" name="last-release-version" /></td>
    </tr>
    <tr>
      <td valign="top">Release date:</td><td><input type="date" name="last-release-date" /></td>
    </tr>
    <tr>
      <td valign="top">Comments:</td><td><textarea name="comments"></textarea></td>
    </tr>
    <tr>
      <td colspan="2" align="left"><input type="submit" value="Add project" /></td>
    </tr>
  </table>
</form>
</div>

<?php } ?>

</body>
</html>
