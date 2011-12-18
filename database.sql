CREATE TABLE `projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project-name` varchar(64) NOT NULL,
  `classification` enum('apps','bindings','libs','panel-plugins','thunar-plugins','xfce') NOT NULL,
  `last-release-version` varchar(16) NOT NULL,
  `last-release-date` date NOT NULL,
  `last-commit-date` date NOT NULL,
  `last-commit-username` varchar(64) NOT NULL,
  `open-bugs` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project-name` (`project-name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `reviews` (
  `project-id` int(10) unsigned NOT NULL,
  `last-user-id` int(10) unsigned NOT NULL,
  `last-review-date` date NOT NULL,
  `comments` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password` varchar(64) NOT NULL,
  `email` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

