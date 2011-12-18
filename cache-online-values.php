#!/usr/bin/env php
<?php
ini_set("default_socket_timeout", 5);
ini_set("error_reporting", E_ERROR|E_PARSE|E_NOTICE);

/* Check for OpenSSL extension */
if (!extension_loaded("openssl")) {
	die ("PHP extension missing: openssl.\n");
}

/* Only allow owner of script to run it */
if (getmyuid() != posix_geteuid()) {
	die ("You are not allowed to run this script.\n");
}

/* Connect to MySQL */
$mysql = mysql_connect("localhost", "xfce-goodies", "xfce-goodies");
if (mysql_select_db("xfce-goodies", $mysql) == false) {
	die ("Error connecting to MySQL.\n");
}

$ret = mysql_query("SELECT `id`, `project-name`, `classification`, `last-release-date`, `last-commit-date`, `last-commit-username`, `open-bugs` FROM `projects`", $mysql);
while (($row = mysql_fetch_array($ret)) != false) {
	$project_id = $row['id'];
	$project_name = $row['project-name'];
	$project_bugzilla_name = ucfirst($row['project-name']);
	$classification = $row['classification'];
	$last_release_date = $row['last-release-date'];
	$last_commit_date = $row['last-commit-date'];
	$last_commit_username = $row['last-commit-username'];
	$open_bugs = $row['open-bugs'];

	echo "Caching ${project_name}";

	/*** Cache CGit ***/

	$cgit_reader = new XMLReader();

	$retry = 0;
retry1:
	$found = false;
	$open = $cgit_reader->open("http://git.xfce.org/${classification}/${project_name}/atom/panel-plugin");
	usleep(250000);

	while ($cgit_reader->read()) {
		if ($cgit_reader->name == 'entry' && $cgit_reader->nodeType == XMLReader::ELEMENT) {
			$found = true;
			break;
		}
	}

	if ($found == false) {
		$open = $cgit_reader->open("http://git.xfce.org/${classification}/${project_name}/atom/src");
		usleep(250000);

		while ($cgit_reader->read()) {
			if ($cgit_reader->name == 'entry' && $cgit_reader->nodeType == XMLReader::ELEMENT) {
				break;
			}
		}
	}

	if ($retry == 3) {
		echo "; failed to cache CGit after 3 retries\n";
		continue;
	}
	else if ($open == false) {
		$retry++;
		goto retry1;
	}

	$found_published = false;
	$found_author = false;
	while ($cgit_reader->read()) {
		if ($cgit_reader->name == 'published' && $cgit_reader->nodeType == XMLReader::ELEMENT) {
			$cgit_reader->read();
			$date = date("Y-m-d", strtotime($cgit_reader->value));
			$found_published = true;
		}
		else if ($cgit_reader->name == 'name' && $cgit_reader->nodeType == XMLReader::ELEMENT) {
			$cgit_reader->read();
			$username = mysql_real_escape_string($cgit_reader->value);
			$found_author = true;
		}
		if (($found_published & $found_author) == true) {
			if ($last_commit_date != $date) {
				mysql_query("UPDATE projects SET `last-commit-date` = '${date}', `last-commit-username` = '${username}' WHERE `id` = ${project_id}");
			}
			break;
		}
	}

	$cgit_reader = null;

	/*** Cache Bugzilla ***/

	$bugzilla_reader = new XMLReader();

	$retry = 0;
retry2:
	$open = $bugzilla_reader->open("https://bugzilla.xfce.org/buglist.cgi?bug_status=NEW;bug_status=ASSIGNED;bug_status=REOPENED;bug_status=RESOLVED;product=${project_bugzilla_name};resolution=---;resolution=LATER;ctype=atom");
	usleep(250000);

	if ($retry == 3) {
		echo "; failed to cache Bugzilla after 3 retries\n";
		continue;
	}
	else if ($open == false) {
		$retry++;
		goto retry2;
	}

	$count = 0;
	while ($bugzilla_reader->read()) {
		if ($bugzilla_reader->name == 'entry' && $bugzilla_reader->nodeType == XMLReader::ELEMENT) {
			$count++;
		}
	}

	if ($count != $open_bugs) {
		mysql_query("UPDATE projects SET `open-bugs` = '${count}' WHERE `id` = ${project_id}");
	}

	$bugzilla_reader = null;

	/*** Cache Archive ***/

	$archive_reader = new XMLReader();

	$retry = 0;
retry3:
	$open = $archive_reader->open("http://archive.xfce.org/feeds/project/${project_name}");
	usleep(250000);

	if ($retry == 3) {
		echo "; failed to cache Archive after 3 retries\n";
		continue;
	}
	else if ($open == false) {
		$retry++;
		goto retry3;
	}

	$found_id = false;
	$found_published = false;
	while ($archive_reader->read()) {
		if ($archive_reader->name == 'id' && $archive_reader->nodeType == XMLReader::ELEMENT) {
			$archive_reader->read();
			$version = preg_replace("/.*-([\d.]+)/", "\\1", $archive_reader->value);
			$found_id = true;
		}
		else if ($archive_reader->name == 'published' && $archive_reader->nodeType == XMLReader::ELEMENT) {
			$archive_reader->read();
			$date = date("Y-m-d", strtotime($archive_reader->value));
			$found_published = true;
		}
		if (($found_id & $found_published) == true) {
			if ($last_release_date != $date) {
				$version = mysql_real_escape_string($version);
				mysql_query("UPDATE projects SET `last-release-date` = '${date}', `last-release-version` = '${version}' WHERE `id` = ${project_id}");
			}
			break;
		}
	}
	if ($found_id == false) {
		echo "; no information about version in Archive";
	}

	$archive_reader = null;

	echo "\n";
}

chdir(dirname(__FILE__));
touch("cache-check.stamp");
