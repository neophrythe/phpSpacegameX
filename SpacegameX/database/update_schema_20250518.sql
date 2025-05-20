-- Update script for SpacegameX database - 2025-05-18

-- Add last_capital_change column to players table
ALTER TABLE players
ADD COLUMN last_capital_change TIMESTAMP NULL DEFAULT NULL AFTER alliance_rank;

-- Create player_messages table if it doesn't exist
CREATE TABLE IF NOT EXISTS player_messages (
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
  deleted_by_recipient BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (id),
  KEY fk_pm_sender_id (sender_id),
  KEY fk_pm_player_id (player_id),
  KEY idx_pm_player_id_is_read (player_id, is_read),
  CONSTRAINT fk_pm_sender_id FOREIGN KEY (sender_id) REFERENCES players (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pm_player_id FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

