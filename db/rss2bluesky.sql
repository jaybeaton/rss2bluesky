CREATE TABLE `rss2bluesky_posts` (
  `feed` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `permalink` varchar(500) COLLATE utf8mb4_bin DEFAULT NULL,
  `blurb` text COLLATE utf8mb4_bin DEFAULT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_bin DEFAULT NULL,
  `is_posted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `post_timestamp` int(11) unsigned NOT NULL DEFAULT '0',
  `timestamp` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`permalink`),
  KEY `is_posted` (`is_posted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE `rss2bluesky_key_value` (
    `key` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    `value` text COLLATE utf8mb4_bin DEFAULT NULL,
    `timestamp` int(11) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
