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
    -- Wikidata ID (Q-number) identifying the item identified as depicted in the image
    mvl_wikidata_id varbinary(32) NOT NULL,
    -- Review status: 0: not reviewed yet, 1: accepted, -1: rejected
    mvl_review tinyint NOT NULL DEFAULT 0,
    -- Local user ID of the user who uploaded the file
    mvl_uploader_id int(10) UNSIGNED NOT NULL DEFAULT 0,
    -- Timestamp of the last time the label represented by the row was served.
    -- Represented in unix format with microseconds, converted to an integer.
    mvl_suggested_time binary(14) NOT NULL,
    -- Local user ID of the user who reviewed the label
    mvl_reviewer_id int(10) UNSIGNED,
    -- Timestamp representing the time at which the label suggestion was reviewed.
    -- Represented in unix format with microseconds, converted to an integer.
    mvl_reviewed_time binary(14)
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/mvl_sha1_wikidata ON /*_*/machine_vision_label (mvl_image_sha1, mvl_wikidata_id);
CREATE INDEX /*i*/mvl_review_sha1 ON /*_*/machine_vision_label (mvl_review, mvl_image_sha1(10));
CREATE INDEX /*i*/mvl_uploader_review ON /*_*/machine_vision_label (mvl_uploader_id, mvl_review);
CREATE INDEX /*i*/mvl_suggested_time ON /*_*/machine_vision_label (mvl_suggested_time);


CREATE TABLE /*_*/machine_vision_suggestion (
    mvs_mvl_id int NOT NULL,
    -- Numeric ID of the machine vision label provider
    mvs_provider_id int(10) UNSIGNED NOT NULL,
    -- Time of receiving the label
    mvs_timestamp binary(14) NOT NULL,
    -- Confidence score provided with the suggested label
    mvs_confidence FLOAT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (mvs_mvl_id, mvs_provider_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/machine_vision_freebase_mapping (
    mvfm_freebase_id varbinary(32) NOT NULL,
    mvfm_wikidata_id varbinary(32) NOT NULL,
    PRIMARY KEY (mvfm_freebase_id, mvfm_wikidata_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/machine_vision_safe_search (
    mvss_image_sha1 varbinary(32) NOT NULL PRIMARY KEY,
    mvss_adult tinyint NOT NULL DEFAULT 0,
    mvss_spoof tinyint NOT NULL DEFAULT 0,
    mvss_medical tinyint NOT NULL DEFAULT 0,
    mvss_violence tinyint NOT NULL DEFAULT 0,
    mvss_racy tinyint NOT NULL DEFAULT 0
) /*$wgDBTableOptions*/;
