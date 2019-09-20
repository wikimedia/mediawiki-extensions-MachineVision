-- Add a column to store the time at which the label suggestion was reviewed.
-- Timestamp is stored as unix format with microseconds, converted to an integer.
ALTER TABLE /*_*/machine_vision_label ADD COLUMN mvl_reviewed_time binary(14);
