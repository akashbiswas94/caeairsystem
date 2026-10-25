USE aar_training;

-- Use this file if your existing aar_training database and dashboard tables
-- are already present. It adds only the fields required by the Unreal integration.

ALTER TABLE training_sessions
    ADD COLUMN IF NOT EXISTS started_at DATETIME(3) NULL AFTER session_date,
    ADD COLUMN IF NOT EXISTS ended_at DATETIME(3) NULL AFTER started_at;

UPDATE training_sessions
SET started_at = session_date
WHERE started_at IS NULL;

ALTER TABLE session_telemetry
    ADD COLUMN IF NOT EXISTS aircraft VARCHAR(80) NULL AFTER timestamp_ms,
    ADD COLUMN IF NOT EXISTS speed_mps DOUBLE NULL AFTER position_z,
    ADD COLUMN IF NOT EXISTS created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3);

CREATE TABLE IF NOT EXISTS session_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload LONGTEXT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    CONSTRAINT fk_session_events_session
        FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    INDEX idx_session_events_session_created (session_id, created_at),
    INDEX idx_session_events_type (event_type)
) ENGINE=InnoDB;
