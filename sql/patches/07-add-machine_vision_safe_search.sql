CREATE TABLE /*_*/machine_vision_safe_search (
    mvss_image_sha1 varbinary(32) NOT NULL PRIMARY KEY,
    mvss_adult tinyint NOT NULL DEFAULT 0,
    mvss_spoof tinyint NOT NULL DEFAULT 0,
    mvss_medical tinyint NOT NULL DEFAULT 0,
    mvss_violence tinyint NOT NULL DEFAULT 0,
    mvss_racy tinyint NOT NULL DEFAULT 0
) /*$wgDBTableOptions*/;
