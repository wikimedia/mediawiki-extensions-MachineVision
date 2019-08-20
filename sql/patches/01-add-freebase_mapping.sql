CREATE TABLE /*_*/machine_vision_freebase_mapping (
    mvfm_freebase_id varbinary(32) NOT NULL,
    mvfm_wikidata_id varbinary(32) NOT NULL,
    PRIMARY KEY (mvfm_freebase_id, mvfm_wikidata_id)
) /*$wgDBTableOptions*/;
