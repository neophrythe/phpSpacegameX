-- Galaxy Expansion Schema
-- This file contains the schema updates for the expanded galaxy system

-- Add resource bonus columns to planets table
ALTER TABLE planets 
    ADD COLUMN metal_bonus DECIMAL(5,2) NOT NULL DEFAULT 1.0,
    ADD COLUMN crystal_bonus DECIMAL(5,2) NOT NULL DEFAULT 1.0,
    ADD COLUMN deuterium_bonus DECIMAL(5,2) NOT NULL DEFAULT 1.0;

-- Create a table to track solar systems
CREATE TABLE IF NOT EXISTS solar_systems (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    galaxy TINYINT UNSIGNED NOT NULL,
    system SMALLINT UNSIGNED NOT NULL,
    planet_count TINYINT UNSIGNED NOT NULL,
    system_type VARCHAR(50) NOT NULL DEFAULT 'normal', -- normal, dense, sparse, resource-rich, etc.
    description TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY galaxy_system (galaxy, system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create a table to track galaxies
CREATE TABLE IF NOT EXISTS galaxies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    galaxy_number TINYINT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(100) NULL,
    description TEXT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update the planets table to allow more galaxies and systems
-- First we need to drop the existing coordinates constraint
ALTER TABLE planets DROP INDEX coords;

-- Then recreate it with the proper limits
ALTER TABLE planets 
    ADD UNIQUE KEY coords (galaxy, system, position),
    MODIFY galaxy TINYINT UNSIGNED NOT NULL,
    MODIFY system SMALLINT UNSIGNED NOT NULL, -- Up to 65535 systems
    MODIFY position TINYINT UNSIGNED NOT NULL; -- Up to 255 positions (plenty for 9-14 planets per system)
