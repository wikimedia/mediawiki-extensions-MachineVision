-- Add a column to store the timestamp of the last time the label represented by the row was served.
-- Timestamp is stored as unix format with microseconds, converted to an integer.
ALTER TABLE /*_*/machine_vision_label ADD COLUMN mvl_suggested_time binary(14) NOT NULL;
CREATE INDEX /*i*/mvl_suggested_time ON /*_*/machine_vision_label (mvl_suggested_time);
