-- Create/recreate tables for ABR

-- Drop
-- DROP TABLE requests;
-- DROP TABLE keyblock;
-- DROP TABLE process;
-- DROP TABLE zip_queue;

-- Create 
CREATE TABLE requests (
    rid INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_study varchar(16) NOT NULL,
    id varchar(16) NOT NULL,
    idsuffix varchar(5) DEFAULT '_', -- Instructions are translated to id suffix (e.g., R_, _0, L_3, R_14, LR_4, LR_23)
    live TINYINT(1) DEFAULT 0,
    origin varchar(24) NOT NULL,
    destination varchar(24) NOT NULL,
    departure_datetime INT UNSIGNED DEFAULT NULL,
    mode ENUM('driving', 'walking', 'bicycling', 'transit') NOT NULL,
    priority TINYINT(1) UNSIGNED NOT NULL,
    apikey varchar(39) NOT NULL,
    rundate DATETIME DEFAULT NULL,
    error varchar(30) DEFAULT NULL,
    PRIMARY KEY (rid)
);

CREATE TABLE keyblock (
    apikey varchar(39) NOT NULL,
    block_type ENUM('temporary', 'banned'),
    unblock_datetime DATETIME DEFAULT NULL,
    PRIMARY KEY (apikey)
);

CREATE TABLE zip_queue (
    case_study varchar(16) NOT NULL,
    PRIMARY KEY (case_study)
);

CREATE TABLE process (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    state TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id)
);
INSERT INTO process (state) VALUES (0);

