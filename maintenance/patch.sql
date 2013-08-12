delimiter $$

CREATE TABLE `page_guests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_guests_idx` (`page_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8$$