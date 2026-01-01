-- Create rl_field_visits table if not exists
CREATE TABLE IF NOT EXISTS `rl_field_visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lease_id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `officers_visited` varchar(150) NOT NULL,
  `visite_status` varchar(50) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `recodrd_on` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lease_id` (`lease_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


