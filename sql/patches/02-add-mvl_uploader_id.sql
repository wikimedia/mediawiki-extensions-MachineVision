-- Add a column to store the local wiki ID of the image uploader, for efficient filtering.
ALTER TABLE /*_*/machine_vision_label ADD COLUMN mvl_uploader_id int(10) UNSIGNED NOT NULL DEFAULT 0;
CREATE INDEX /*i*/mvl_sha1_review_uploader ON /*_*/machine_vision_label (mvl_review, mvl_uploader_id);
