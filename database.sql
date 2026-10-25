CREATE DATABASE IF NOT EXISTS aar_training
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE aar_training;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'trainee',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scenarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scenario_name VARCHAR(160) NOT NULL,
    difficulty VARCHAR(40) NOT NULL DEFAULT 'Training',
    description TEXT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS training_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trainee_id BIGINT UNSIGNED NOT NULL,
    scenario_id BIGINT UNSIGNED NOT NULL,
    session_date DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    started_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    ended_at DATETIME(3) NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    overall_score DECIMAL(6,2) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    CONSTRAINT fk_training_sessions_trainee
        FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_training_sessions_scenario
        FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE RESTRICT,
    INDEX idx_training_sessions_trainee_date (trainee_id, session_date),
    INDEX idx_training_sessions_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS session_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL UNIQUE,
    alignment_accuracy DECIMAL(6,2) NULL,
    position_stability DECIMAL(6,2) NULL,
    approach_accuracy DECIMAL(6,2) NULL,
    safety_score DECIMAL(6,2) NULL,
    reaction_time DECIMAL(10,3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    CONSTRAINT fk_session_metrics_session
        FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS session_telemetry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    timestamp_ms BIGINT UNSIGNED NOT NULL,
    aircraft VARCHAR(80) NULL,
    position_x DOUBLE NULL,
    position_y DOUBLE NULL,
    position_z DOUBLE NULL,
    speed_mps DOUBLE NULL,
    rotation_pitch DOUBLE NULL,
    rotation_yaw DOUBLE NULL,
    rotation_roll DOUBLE NULL,
    joystick_x DOUBLE NULL,
    joystick_y DOUBLE NULL,
    joystick_z DOUBLE NULL,
    throttle DOUBLE NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    CONSTRAINT fk_session_telemetry_session
        FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    INDEX idx_session_telemetry_session_time (session_id, timestamp_ms),
    INDEX idx_session_telemetry_created (created_at)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    feedback_text TEXT NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    CONSTRAINT fk_feedback_session
        FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (first_name, last_name, email, password, role)
SELECT 'Demo', 'Trainee', 'trainee@test.com', '123456', 'trainee'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'trainee@test.com'
);

INSERT INTO scenarios (scenario_name, difficulty, description)
SELECT 'Main World - Air Refuelling', 'Training', 'CAE Unreal Engine boom/refuelling training scenario.'
WHERE NOT EXISTS (
    SELECT 1 FROM scenarios WHERE scenario_name = 'Main World - Air Refuelling'
);
