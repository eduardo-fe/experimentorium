-- Insert a session
INSERT INTO sessions (session_name) VALUES ('Experiment 1');

-- Insert a stage
INSERT INTO stages (session_id, stage_name, stage_order, is_active, started_at)
VALUES (1, 'waiting_for_participants', 1, TRUE, NOW());

-- Update session to reference the stage
UPDATE sessions SET current_stage_id = 1 WHERE session_id = 1;

-- Insert a round
INSERT INTO rounds (session_id, round_number) VALUES (1, 1);

-- Insert participants
INSERT INTO participants (session_id, participant_code, role)
VALUES
    (1, 'P1', 'A'),
    (1, 'P2', 'B');

-- Insert a match for round 1
INSERT INTO matches (session_id, round_id, participant_a_id, participant_b_id)
VALUES (1, 1, 1, 2);

-- Insert effort task results with penalties
-- Assume participant A gets 'high' probability (0.7) and lottery results in penalty (8)
-- Assume participant B gets 'low' probability (0.2) and lottery results in no penalty
INSERT INTO effort_tasks (participant_id, match_id, round_id, success, effort_score, penalty_probability, penalty_amount, completed_at)
VALUES
    (1, 1, 1, TRUE, 0.85, 'high', 8, NOW()), -- Participant A: penalty applied
    (2, 1, 1, TRUE, 0.90, 'low', 0, NOW());   -- Participant B: no penalty

-- Insert payoffs with penalties applied
INSERT INTO payoffs (match_id, round_id, participant_a_id, participant_b_id, charity_payoff, payoff_a, payoff_b, penalty_applied_a, penalty_amount_a, penalty_applied_b, penalty_amount_b)
VALUES (1, 1, 1, 2, 10.0, 20.0 - 8.0, 20.0, TRUE, 8, FALSE, 0);