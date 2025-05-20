-- SpacegameX Database Schema
-- Target: MySQL

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `spacegamex`
--
-- CREATE DATABASE IF NOT EXISTS `spacegamex` DEFAULT CHARACTER SET utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- USE `spacegamex`;

-- --------------------------------------------------------

--
-- Table structure for table `players`
--
CREATE TABLE players (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  savemail_address VARCHAR(100) NULL UNIQUE, -- Added for account recovery, should be settable once
  registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_date TIMESTAMP NULL DEFAULT NULL,
  points_research INT UNSIGNED NOT NULL DEFAULT 0,
  points_building INT UNSIGNED NOT NULL DEFAULT 0,
  points_fleet INT UNSIGNED NOT NULL DEFAULT 0,
  points_defense INT UNSIGNED NOT NULL DEFAULT 0,
  battle_points INT UNSIGNED NOT NULL DEFAULT 0,
  is_admin BOOLEAN NOT NULL DEFAULT FALSE,
  alliance_id INT UNSIGNED NULL DEFAULT NULL,
  alliance_rank ENUM('member', 'admin', 'test_member') NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX idx_username (username),
  KEY fk_players_alliance_id (alliance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `galaxies`
--
CREATE TABLE galaxies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  galaxy_number TINYINT UNSIGNED NOT NULL UNIQUE,
  name VARCHAR(100) NULL,
  description TEXT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `solar_systems`
--
CREATE TABLE solar_systems (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  galaxy TINYINT UNSIGNED NOT NULL,
  system SMALLINT UNSIGNED NOT NULL,
  planet_count TINYINT UNSIGNED NOT NULL,
  system_type VARCHAR(50) NOT NULL DEFAULT 'normal', -- normal, dense, sparse, resource-rich, etc.
  description TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY galaxy_system (galaxy, system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `planets`
--
CREATE TABLE planets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  player_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL DEFAULT 'Heimatplanet',
  galaxy TINYINT UNSIGNED NOT NULL,
  system SMALLINT UNSIGNED NOT NULL,
  position TINYINT UNSIGNED NOT NULL,
  is_capital BOOLEAN NOT NULL DEFAULT FALSE,
  diameter MEDIUMINT UNSIGNED NOT NULL, -- km
  temperature_min SMALLINT NOT NULL, -- °C
  temperature_max SMALLINT NOT NULL, -- °C
  last_resource_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  eisen DECIMAL(20,4) NOT NULL DEFAULT 500.0000,
  silber DECIMAL(20,4) NOT NULL DEFAULT 500.0000,
  uderon DECIMAL(20,4) NOT NULL DEFAULT 100.0000,
  wasserstoff DECIMAL(20,4) NOT NULL DEFAULT 0.0000,
  energie DECIMAL(20,4) NOT NULL DEFAULT 0.0000,
  eisen_bonus DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  silber_bonus DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  uderon_bonus DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  wasserstoff_bonus DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  blockade_until DATETIME NULL DEFAULT NULL,
  blockade_strength INT UNSIGNED NULL DEFAULT NULL,
  blockade_player_id INT UNSIGNED NULL DEFAULT NULL,
  blockading_fleet_id INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY coords (galaxy, system, position),
  KEY fk_planets_player_id (player_id),
  KEY fk_planets_blockade_player_id (blockade_player_id),
  -- KEY fk_planets_blockading_fleet_id (blockading_fleet_id), -- Temporarily removed
  CONSTRAINT fk_planets_player_id FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_planets_blockade_player_id FOREIGN KEY (blockade_player_id) REFERENCES players (id) ON DELETE SET NULL ON UPDATE CASCADE
  -- CONSTRAINT fk_planets_blockading_fleet_id FOREIGN KEY (blockading_fleet_id) REFERENCES fleets (id) ON DELETE SET NULL ON UPDATE CASCADE -- Temporarily removed
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `static_building_types`
--
CREATE TABLE static_building_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  internal_name VARCHAR(50) NOT NULL UNIQUE, -- e.g., eisenmine, forschungszentrum
  name_de VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NULL,
  description_de TEXT NULL,
  description_en TEXT NULL,
  base_cost_eisen INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_silber INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_uderon INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_wasserstoff INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_energie INT UNSIGNED NOT NULL DEFAULT 0,
  cost_factor DECIMAL(5,2) NOT NULL DEFAULT 1.5, -- Factor by which costs increase per level
  base_production_eisen DECIMAL(10,4) NULL DEFAULT 0.0000, -- per hour at level 1
  base_production_silber DECIMAL(10,4) NULL DEFAULT 0.0000,
  base_production_uderon DECIMAL(10,4) NULL DEFAULT 0.0000,
  base_production_wasserstoff DECIMAL(10,4) NULL DEFAULT 0.0000,
  base_production_energie DECIMAL(10,4) NULL DEFAULT 0.0000,
  production_formula VARCHAR(255) NULL, -- e.g., '10 * level * pow(1.1, level)'
  base_consumption_wasserstoff DECIMAL(10,4) NULL DEFAULT 0.0000,
  base_consumption_energie DECIMAL(10,4) NULL DEFAULT 0.0000,
  consumption_formula VARCHAR(255) NULL,
  max_level SMALLINT UNSIGNED DEFAULT NULL, -- Max buildable level
  base_build_time INT UNSIGNED NOT NULL, -- Base time in seconds to build level 1
  build_time_factor DECIMAL(5,2) NOT NULL DEFAULT 1.5, -- Factor by which build time increases per level
  requirements_json JSON NULL, -- e.g., {"building": {"zentrale": 5}, "research": {"energietechnik": 2}}
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_buildings`
--
CREATE TABLE player_buildings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  planet_id INT UNSIGNED NOT NULL,
  building_type_id INT UNSIGNED NOT NULL,
  level SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY planet_building (planet_id, building_type_id),
  KEY fk_pb_planet_id (planet_id),
  KEY fk_pb_building_type_id (building_type_id),
  CONSTRAINT fk_pb_planet_id FOREIGN KEY (planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pb_building_type_id FOREIGN KEY (building_type_id) REFERENCES static_building_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `static_research_types`
--
CREATE TABLE static_research_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  internal_name VARCHAR(50) NOT NULL UNIQUE, -- e.g., energietechnik, lasertechnik
  name_de VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NULL,
  description_de TEXT NULL,
  description_en TEXT NULL,
  base_cost_eisen INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_silber INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_uderon INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_wasserstoff INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_energie INT UNSIGNED NOT NULL DEFAULT 0,
  cost_factor DECIMAL(5,2) NOT NULL DEFAULT 2.0, -- Factor by which costs increase per level
  base_research_time INT UNSIGNED NOT NULL, -- Base time in seconds for level 1
  research_time_factor DECIMAL(5,2) NOT NULL DEFAULT 1.5, -- Factor by which research time increases per level
  requirements_json JSON NULL, -- e.g., {"building": {"forschungszentrum": 5}, "research": {"energietechnik": 2}}
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_research`
--
CREATE TABLE player_research (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  player_id INT UNSIGNED NOT NULL,
  research_type_id INT UNSIGNED NOT NULL,
  level SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_under_research TINYINT(1) DEFAULT 0 NULL,
  research_finish_time TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY player_research (player_id, research_type_id),
  KEY fk_pr_player_id (player_id),
  KEY fk_pr_research_type_id (research_type_id),
  CONSTRAINT fk_pr_player_id FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pr_research_type_id FOREIGN KEY (research_type_id) REFERENCES static_research_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `static_ship_types`
--
CREATE TABLE static_ship_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  internal_name VARCHAR(50) NOT NULL UNIQUE, -- e.g., moskitojet, kontainerschiff
  name_de VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NULL,
  description_de TEXT NULL,
  description_en TEXT NULL,
  base_cost_eisen INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_silber INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_uderon INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_wasserstoff INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_energie INT UNSIGNED NOT NULL DEFAULT 0,
  base_build_time INT UNSIGNED NOT NULL, -- Base time in seconds to build one ship
  build_time_factor DECIMAL(5,2) NOT NULL DEFAULT 1.5, -- Factor by which build time increases per level (Werft level)
  speed INT UNSIGNED NOT NULL, -- Base speed in units per hour
  cargo_capacity INT UNSIGNED NOT NULL DEFAULT 0, -- How many resource units this ship can carry
  fuel_consumption SMALLINT UNSIGNED NOT NULL DEFAULT 1, -- Fuel (wasserstoff) consumption per 10k distance units
  weapon_power INT UNSIGNED NOT NULL DEFAULT 0,
  shield_power INT UNSIGNED NOT NULL DEFAULT 0,
  hull_strength INT UNSIGNED NOT NULL DEFAULT 0,
  requirements_json JSON NULL, -- e.g., {"building": {"werft": 5}, "research": {"verbrennungsantrieb": 2}}
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_ships`
--
CREATE TABLE player_ships (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  planet_id INT UNSIGNED NOT NULL, -- Where the ships are stationed
  ship_type_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY planet_ship (planet_id, ship_type_id),
  KEY fk_ps_planet_id (planet_id),
  KEY fk_ps_ship_type_id (ship_type_id),
  CONSTRAINT fk_ps_planet_id FOREIGN KEY (planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ps_ship_type_id FOREIGN KEY (ship_type_id) REFERENCES static_ship_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fleets`
--
CREATE TABLE fleets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  player_id INT UNSIGNED NOT NULL,
  start_planet_id INT UNSIGNED NOT NULL,
  target_planet_id INT UNSIGNED NULL, -- Null if returning to start planet
  target_galaxy TINYINT UNSIGNED NULL,
  target_system SMALLINT UNSIGNED NULL,
  target_position TINYINT UNSIGNED NULL,
  mission_type ENUM('attack', 'transport', 'colonize', 'station', 'espionage', 'harvest', 'destroy', 'expedition', 'deploy_alliance_building', 'blockade') NOT NULL,
  start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  arrival_time TIMESTAMP NOT NULL,
  return_time TIMESTAMP NULL DEFAULT NULL,
  eisen_cargo DECIMAL(20,4) NOT NULL DEFAULT 0,
  silber_cargo DECIMAL(20,4) NOT NULL DEFAULT 0,
  uderon_cargo DECIMAL(20,4) NOT NULL DEFAULT 0,
  wasserstoff_cargo DECIMAL(20,4) NOT NULL DEFAULT 0,
  energie_cargo DECIMAL(20,4) NOT NULL DEFAULT 0,
  is_returning BOOLEAN NOT NULL DEFAULT FALSE,
  is_completed BOOLEAN NOT NULL DEFAULT FALSE,
  blockade_duration_hours INT UNSIGNED NULL DEFAULT NULL,
  blockade_strength INT UNSIGNED NULL DEFAULT NULL,
  is_active_blockade BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (id),
  KEY fk_fleet_player_id (player_id),
  KEY fk_fleet_start_planet_id (start_planet_id), -- Index kept for now, constraint will be added later
  KEY fk_fleet_target_planet_id (target_planet_id), -- Index kept for now, constraint will be added later
  CONSTRAINT fk_fleet_player_id FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE ON UPDATE CASCADE
  -- CONSTRAINT fk_fleet_start_planet_id FOREIGN KEY (start_planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE, -- Temporarily removed
  -- CONSTRAINT fk_fleet_target_planet_id FOREIGN KEY (target_planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE -- Temporarily removed
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fleet_ships`
--
CREATE TABLE fleet_ships (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fleet_id INT UNSIGNED NOT NULL,
  ship_type_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY fleet_ship (fleet_id, ship_type_id),
  KEY fk_fs_fleet_id (fleet_id),
  KEY fk_fs_ship_type_id (ship_type_id),
  CONSTRAINT fk_fs_fleet_id FOREIGN KEY (fleet_id) REFERENCES fleets (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fs_ship_type_id FOREIGN KEY (ship_type_id) REFERENCES static_ship_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `static_defense_types`
--
CREATE TABLE static_defense_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  internal_name VARCHAR(50) NOT NULL UNIQUE, -- e.g., maschinenkanonenturm_1, impulskanonenturm_3
  name_de VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NULL,
  description_de TEXT NULL,
  description_en TEXT NULL,
  base_cost_eisen INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_silber INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_uderon INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_wasserstoff INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_energie INT UNSIGNED NOT NULL DEFAULT 0,
  base_build_time INT UNSIGNED NOT NULL, -- Base time in seconds to build one defense unit
  build_time_factor DECIMAL(5,2) NOT NULL DEFAULT 1.5, -- Factor by which build time increases per level (Raumstation level)
  weapon_power INT UNSIGNED NOT NULL DEFAULT 0,
  shield_power INT UNSIGNED NOT NULL DEFAULT 0,
  hull_strength INT UNSIGNED NOT NULL DEFAULT 0,
  requirements_json JSON NULL, -- e.g., {"building": {"raumstation": 1}, "research": {"prallschirm": 1}}
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_defense`
--
CREATE TABLE player_defense (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  planet_id INT UNSIGNED NOT NULL, -- Where the defense units are stationed
  defense_type_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY planet_defense (planet_id, defense_type_id),
  KEY fk_pd_planet_id (planet_id),
  KEY fk_pd_defense_type_id (defense_type_id),
  CONSTRAINT fk_pd_planet_id FOREIGN KEY (planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pd_defense_type_id FOREIGN KEY (defense_type_id) REFERENCES static_defense_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliances`
--
CREATE TABLE alliances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  tag VARCHAR(10) NOT NULL UNIQUE,
  description TEXT NULL,
  creation_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  leader_player_id INT UNSIGNED NULL, -- Optional: Link to the founding player
  PRIMARY KEY (id),
  KEY fk_alliances_leader_player_id (leader_player_id),
  CONSTRAINT fk_alliances_leader_player_id FOREIGN KEY (leader_player_id) REFERENCES players (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key to players table
ALTER TABLE players ADD CONSTRAINT fk_players_alliance_id FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `static_alliance_building_types`
--
CREATE TABLE static_alliance_building_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  internal_name VARCHAR(50) NOT NULL UNIQUE, -- e.g., minenfeld, handelszentrum
  name_de VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NULL,
  description_de TEXT NULL,
  description_en TEXT NULL,
  base_cost_eisen INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_silber INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_uderon INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_wasserstoff INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_energie INT UNSIGNED NOT NULL DEFAULT 0,
  base_build_time INT UNSIGNED NOT NULL, -- Base time in seconds to build one alliance building
  build_time_factor DECIMAL(5,2) NOT NULL DEFAULT 1.5, -- Factor by which build time increases per level
  requirements_json JSON NULL, -- e.g., {"alliance_research": {"allianzlogistik": 5}}
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_buildings`
--
CREATE TABLE alliance_buildings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alliance_id INT UNSIGNED NOT NULL,
  solar_system_id INT UNSIGNED NOT NULL, -- Where the alliance building is located
  building_type_id INT UNSIGNED NOT NULL,
  level SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY alliance_system_building (alliance_id, solar_system_id, building_type_id),
  KEY fk_ab_alliance_id (alliance_id),
  KEY fk_ab_solar_system_id (solar_system_id),
  KEY fk_ab_building_type_id (building_type_id),
  CONSTRAINT fk_ab_alliance_id FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ab_solar_system_id FOREIGN KEY (solar_system_id) REFERENCES solar_systems (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ab_building_type_id FOREIGN KEY (building_type_id) REFERENCES static_alliance_building_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `static_alliance_research_types`
--
CREATE TABLE static_alliance_research_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  internal_name VARCHAR(50) NOT NULL UNIQUE, -- e.g., allianzwaffentechnik, allianzlogistik
  name_de VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NULL,
  description_de TEXT NULL,
  description_en TEXT NULL,
  base_cost_eisen INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_silber INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_uderon INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_wasserstoff INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost_energie INT UNSIGNED NOT NULL DEFAULT 0,
  cost_factor DECIMAL(5,2) NOT NULL DEFAULT 2.0, -- Factor by which costs increase per level
  base_research_time INT UNSIGNED NOT NULL, -- Base time in seconds for level 1
  research_time_factor DECIMAL(5,2) NOT NULL DEFAULT 1.5, -- Factor by which research time increases per level
  requirements_json JSON NULL, -- e.g., {"alliance_building": {"allianzforschungszentrum": 1}}
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_research`
--
CREATE TABLE alliance_research (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alliance_id INT UNSIGNED NOT NULL,
  research_type_id INT UNSIGNED NOT NULL,
  level SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY alliance_research (alliance_id, research_type_id),
  KEY fk_ar_alliance_id (alliance_id),
  KEY fk_ar_research_type_id (research_type_id),
  CONSTRAINT fk_ar_alliance_id FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ar_research_type_id FOREIGN KEY (research_type_id) REFERENCES static_alliance_research_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trade_offers`
--
CREATE TABLE trade_offers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  player_id INT UNSIGNED NOT NULL,
  sell_resource_id INT UNSIGNED NULL, -- FK to a static resource type table if we had one, or use ENUM/VARCHAR
  sell_resource_type VARCHAR(50) NOT NULL, -- e.g., 'eisen', 'silber'
  sell_quantity DECIMAL(20,4) NOT NULL,
  buy_resource_id INT UNSIGNED NULL,
  buy_resource_type VARCHAR(50) NOT NULL, -- e.g., 'silber', 'eisen'
  buy_quantity DECIMAL(20,4) NOT NULL,
  planet_id INT UNSIGNED NOT NULL, -- Planet where resources are located
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_to_player_id (player_id),
  KEY fk_to_planet_id (planet_id),
  CONSTRAINT fk_to_player_id FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_to_planet_id FOREIGN KEY (planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `construction_queue`
--
CREATE TABLE construction_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  player_id INT UNSIGNED NOT NULL,
  planet_id INT UNSIGNED NULL, -- Null if it's a research item or alliance research/building
  alliance_id INT UNSIGNED NULL, -- Null if it's a player item
  item_type ENUM('building', 'research', 'ship', 'defense', 'alliance_building', 'alliance_research') NOT NULL,
  item_id INT UNSIGNED NOT NULL, -- Corresponds to static type table id
  target_level_or_quantity INT UNSIGNED NOT NULL,
  start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  end_time TIMESTAMP NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY fk_cq_player_id (player_id),
  KEY fk_cq_planet_id (planet_id),
  KEY fk_cq_alliance_id (alliance_id),
  CONSTRAINT fk_cq_player_id FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cq_planet_id FOREIGN KEY (planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cq_alliance_id FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `battle_reports`
--
CREATE TABLE battle_reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    attacker_id INT UNSIGNED NOT NULL,
    defender_id INT UNSIGNED NOT NULL,
    battle_time DATETIME NOT NULL,
    target_planet_id INT UNSIGNED NOT NULL,
    target_coordinates VARCHAR(20) NOT NULL,
    report_data LONGTEXT NOT NULL, -- JSON data containing all battle details
    PRIMARY KEY (id),
    KEY attacker_id (attacker_id),
    KEY defender_id (defender_id),
    KEY target_planet_id (target_planet_id),
    KEY battle_time (battle_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `espionage_reports`
--
CREATE TABLE espionage_reports (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id INT(11) UNSIGNED NOT NULL,
    target_planet_id INT(11) UNSIGNED NOT NULL,
    report_time DATETIME NOT NULL,
    report_data LONGTEXT NOT NULL, -- JSON data containing all espionage details
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY player_id (player_id),
    KEY target_planet_id (target_planet_id),
    KEY report_time (report_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_notifications`
--
CREATE TABLE player_notifications (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id INT(11) UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL, -- Added for direct links from notifications
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY player_id (player_id),
    KEY type (type),
    KEY is_read (is_read),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_diplomacy`
--
CREATE TABLE alliance_diplomacy (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alliance_id_1 INT UNSIGNED NOT NULL,
  alliance_id_2 INT UNSIGNED NOT NULL,
  diplomacy_type ENUM('nap', 'bündnis', 'kriegserklärung') NOT NULL,
  start_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  end_date TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY alliance_pair_type (alliance_id_1, alliance_id_2, diplomacy_type),
  KEY fk_ad_alliance_id_1 (alliance_id_1),
  KEY fk_ad_alliance_id_2 (alliance_id_2),
  CONSTRAINT fk_ad_alliance_id_1 FOREIGN KEY (alliance_id_1) REFERENCES alliances (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ad_alliance_id_2 FOREIGN KEY (alliance_id_2) REFERENCES alliances (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add deferred foreign keys
ALTER TABLE planets
  ADD KEY fk_planets_blockading_fleet_id (blockading_fleet_id),
  ADD CONSTRAINT fk_planets_blockading_fleet_id FOREIGN KEY (blockading_fleet_id) REFERENCES fleets (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE fleets
  ADD CONSTRAINT fk_fleet_start_planet_id FOREIGN KEY (start_planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_fleet_target_planet_id FOREIGN KEY (target_planet_id) REFERENCES planets (id) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

-- Espionage Reports Table
CREATE TABLE IF NOT EXISTS `espionage_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `attacker_id` INT NOT NULL,
    `defender_id` INT NOT NULL,
    `defender_planet_id` INT NOT NULL,
    `report_data` TEXT NOT NULL, -- JSON encoded data of the espionage findings
    `report_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_read_attacker` BOOLEAN DEFAULT FALSE,
    `is_read_defender` BOOLEAN DEFAULT FALSE, -- If defender is notified (e.g. counter-espionage)
    FOREIGN KEY (`attacker_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`defender_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`defender_planet_id`) REFERENCES `planets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Alliance Messages Table
CREATE TABLE IF NOT EXISTS `alliance_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `alliance_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- message_type ENUM('global', 'officer') DEFAULT 'global', -- Optional for future expansion
    FOREIGN KEY (`alliance_id`) REFERENCES `alliances`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alliance Message Recipients Table (for read tracking)
CREATE TABLE IF NOT EXISTS `alliance_message_recipients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `alliance_message_id` INT NOT NULL,
    `recipient_id` INT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`alliance_message_id`) REFERENCES `alliance_messages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recipient_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_message_recipient` (`alliance_message_id`, `recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `player_messages`
--
CREATE TABLE player_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id INT UNSIGNED NULL, -- NULL for system messages
  player_id INT UNSIGNED NOT NULL, -- The recipient
  subject VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read BOOLEAN NOT NULL DEFAULT FALSE,
  message_type ENUM('player', 'system', 'combat_report', 'espionage_report', 'system_internal') NOT NULL DEFAULT 'player',
  related_id INT UNSIGNED NULL, -- e.g., combat_report_id, espionage_report_id
  deleted_by_sender BOOLEAN NOT NULL DEFAULT FALSE,
  deleted_by_recipient BOOLEAN NOT NULL DEFAULT FALSE, -- If you want soft deletes for recipients too
  PRIMARY KEY (id),
  KEY fk_pm_sender_id (sender_id),
  KEY fk_pm_player_id (player_id),
  KEY idx_pm_player_id_is_read (player_id, is_read),
  CONSTRAINT fk_pm_sender_id FOREIGN KEY (sender_id) REFERENCES players (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pm_player_id FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
