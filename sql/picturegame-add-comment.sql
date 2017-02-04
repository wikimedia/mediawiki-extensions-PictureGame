-- Creates row in table `picturegame_images` which keep reasons of flagging
ALTER TABLE /*_*/picturegame_images
  ADD COLUMN comment VARCHAR(255) DEFAULT '';
