CREATE TABLE table_name (
  swp_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  swp_story_id bigint(20) unsigned NOT NULL,
  swp_story_href varchar(255) NOT NULL,
  swp_storyteller_email varchar(255) NOT NULL,
  swp_activation_key varchar(255) NOT NULL,
  swp_activation_timestamp timestamp NULL DEFAULT NULL,
  swp_post_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (swp_id)
) charset_collate;

