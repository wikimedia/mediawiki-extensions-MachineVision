CREATE TABLE /*_*/machine_vision_provider (
    -- Numeric ID for the provider
    mvp_id int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Provider name
    mvp_name varbinary(255) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/mvp_name ON /*_*/machine_vision_provider (mvp_name);


CREATE TABLE /*_*/machine_vision_label (
    mvl_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- sha1 digest of the image
    mvl_image_sha1 varbinary(32) NOT NULL,
    -- Numeric ID of the machine vision label provider
    mvl_provider_id int(10) UNSIGNED NOT NULL,
    -- Wikidata ID (Q-number) identifying the item identified as depicted in the image
    mvl_wikidata_id varbinary(32) NOT  NULL,
    -- Review status: 0: not reviewed yet, 1: accepted, -1: rejected, -2: skipped
    mvl_review tinyint NOT NULL DEFAULT 0,
    -- Time of receiving the label
    mvl_timestamp binary(14) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/mvl_sha1_provider_wikidata ON /*_*/machine_vision_label (mvl_image_sha1, mvl_provider_id, mvl_wikidata_id);
CREATE INDEX /*i*/mvl_review_sha1 ON /*_*/machine_vision_label (mvl_review, mvl_image_sha1);
