ALTER TABLE prefix_users CHANGE `b_tech_queue` `b_tech_queue` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';
ALTER TABLE prefix_planets DROP  `b_hangar_plus`;
ALTER TABLE prefix_planets CHANGE `b_building_id` `b_building_id` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `b_hangar_id` `b_hangar_id` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';