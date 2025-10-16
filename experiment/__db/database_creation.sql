-- ===============================
-- 1. SESSIONS: one per experiment run
-- ===============================
CREATE TABLE sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    current_stage_id INT DEFAULT NULL,  -- No foreign key here yet
    session_status ENUM(
        'ongoing',
        'completed'
    )
);

-- ===============================
-- 2. STAGES (controls experiment flow)
-- ===============================
CREATE TABLE stages (
    stage_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT, -- No foreign key here yet
    stage_name ENUM(
        'instructions',
        'instructions_type2',
        'instructions_type3',
        'strategy_method',
        'effort_task',
        'outcome_realization',
        'payoff_computation',
        'end_of_round'
    ) NOT NULL,
    stage_order INT NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    started_at DATETIME DEFAULT NULL,
    ended_at DATETIME DEFAULT NULL
);

-- ===============================
-- 3. ROUNDS (defines rounds)
-- ===============================
CREATE TABLE rounds (
    round_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT,
    round_number INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(session_id),
    UNIQUE (session_id, round_number) -- Ensure unique round numbers per session
);

-- ===============================
-- 4. PARTICIPANTS
-- ===============================
CREATE TABLE participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT,
    participant_code VARCHAR(50) UNIQUE,
    role ENUM('A', 'B') NOT NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(session_id)
);

-- ===============================
-- 5. MATCHES (linking A and B)
-- ===============================
CREATE TABLE matches (
    match_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT,
    round_id INT,
    participant_a_id INT,
    participant_b_id INT,
    FOREIGN KEY (session_id) REFERENCES sessions(session_id),
    FOREIGN KEY (round_id) REFERENCES rounds(round_id),
    FOREIGN KEY (participant_a_id) REFERENCES participants(participant_id),
    FOREIGN KEY (participant_b_id) REFERENCES participants(participant_id)
);

-- ===============================
-- 6. DECISIONS (strategy method, etc.)
-- ===============================
CREATE TABLE decisions (
    decision_id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT,
    match_id INT,
    round_id INT,
    decision_type VARCHAR(50),  -- e.g. 'if_success_request', 'if_fail_suggest', etc.
    decision_value VARCHAR(50), -- e.g. 'accept', 'reject', 'request', 'do_nothing'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES participants(participant_id),
    FOREIGN KEY (match_id) REFERENCES matches(match_id),
    FOREIGN KEY (round_id) REFERENCES rounds(round_id)
);

-- ===============================
-- 7. EFFORT TASK RESULTS
-- ===============================
CREATE TABLE effort_tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT,
    match_id INT,
    round_id INT,
    success BOOLEAN,
    effort_score FLOAT,
    penalty_probability ENUM('high', 'low') DEFAULT NULL, -- High (0.7) or Low (0.2)
    penalty_amount FLOAT DEFAULT NULL, -- 8 for high, 2 for low, based on lottery
    completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES participants(participant_id),
    FOREIGN KEY (match_id) REFERENCES matches(match_id),
    FOREIGN KEY (round_id) REFERENCES rounds(round_id)
);

-- ===============================
-- 8. PAYOFFS
-- ===============================
CREATE TABLE payoffs (
    payoff_id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT,
    round_id INT,
    participant_a_id INT,
    participant_b_id INT,
    charity_payoff FLOAT,
    payoff_a FLOAT,
    payoff_b FLOAT,
    penalty_applied_a BOOLEAN DEFAULT FALSE, -- Penalty applied to participant A
    penalty_amount_a FLOAT DEFAULT 0, -- Penalty amount for participant A
    penalty_applied_b BOOLEAN DEFAULT FALSE, -- Penalty applied to participant B
    penalty_amount_b FLOAT DEFAULT 0, -- Penalty amount for participant B
    computed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(match_id),
    FOREIGN KEY (round_id) REFERENCES rounds(round_id),
    FOREIGN KEY (participant_a_id) REFERENCES participants(participant_id),
    FOREIGN KEY (participant_b_id) REFERENCES participants(participant_id)
);

-- ===============================
-- 9. Add foreign keys for stages and sessions
-- ===============================
ALTER TABLE stages
ADD CONSTRAINT fk_stage_session
FOREIGN KEY (session_id) REFERENCES sessions(session_id);

ALTER TABLE sessions
ADD CONSTRAINT fk_session_stage
FOREIGN KEY (current_stage_id) REFERENCES stages(stage_id);


ALTER TABLE rounds 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE,
ADD COLUMN ended_at DATETIME DEFAULT NULL;

ALTER TABLE participants
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE stages
ADD COLUMN round_id INT AFTER stage_id,
ADD CONSTRAINT fk_stages_round
  FOREIGN KEY (round_id) REFERENCES rounds(round_id);


CREATE TABLE participant_stage_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    session_id INT NOT NULL,
    round_id INT NOT NULL,
    stage_name VARCHAR(50) NOT NULL,
    completed BOOLEAN DEFAULT 0,
    completed_at DATETIME DEFAULT NULL,
    UNIQUE KEY(participant_id, round_id, stage_name),
    FOREIGN KEY (participant_id) REFERENCES participants(participant_id),
    FOREIGN KEY (session_id) REFERENCES sessions(session_id),
    FOREIGN KEY (round_id) REFERENCES rounds(round_id)
);
