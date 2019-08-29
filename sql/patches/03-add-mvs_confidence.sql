-- Add a column to store the confidence scores provided with suggested labels
ALTER TABLE /*_*/machine_vision_suggestion ADD COLUMN mvs_confidence FLOAT UNSIGNED NOT NULL DEFAULT 0;
