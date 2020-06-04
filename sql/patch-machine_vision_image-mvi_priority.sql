ALTER TABLE /*_*/machine_vision_image ADD COLUMN mvi_priority tinyint(3) DEFAULT 0;
CREATE INDEX /*i*/mvi_priority ON /*_*/machine_vision_image (mvi_priority);
