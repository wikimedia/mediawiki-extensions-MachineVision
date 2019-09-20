-- Add a column to store the user ID of the user who reviewed the label.
ALTER TABLE /*_*/machine_vision_label ADD COLUMN mvl_reviewer_id int(10) UNSIGNED;
