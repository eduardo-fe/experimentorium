<?php 
/**
 * Match participants A and B for a given session and round.
 * Each A is matched with at least one B (and vice versa if asymmetric).
 * If more As than Bs → multiple As share Bs; if more Bs than As → multiple Bs share As.
 * Randomly match A and B participants for a given session and round.
 * Each participant is guaranteed at least one partner.
 */

function match_participants($conn, $session_id, $round_id) {
    // 1. Fetch A and B participants
    $participantsA = [];
    $participantsB = [];

    $result = $conn->query("SELECT participant_id, role FROM participants WHERE session_id = $session_id");
    while ($row = $result->fetch_assoc()) {
        if (strtoupper($row['role']) === 'A') {
            $participantsA[] = $row['participant_id'];
        } elseif (strtoupper($row['role']) === 'B') {
            $participantsB[] = $row['participant_id'];
        }
    }

    if (empty($participantsA) || empty($participantsB)) {
        return "No participants available for matching (missing A or B).";
    }

    // 2. Randomize order
    shuffle($participantsA);
    shuffle($participantsB);

    // 3. Delete previous matches for this round
    $conn->query("DELETE FROM matches WHERE round_id = $round_id");

    // 4. Build new matches
    $matches = [];
    $countA = count($participantsA);
    $countB = count($participantsB);

    if ($countA >= $countB) {
        // More A than B: cycle Bs
        foreach ($participantsA as $i => $a_id) {
            $b_id = $participantsB[$i % $countB];
            $matches[] = [$session_id, $round_id, $a_id, $b_id];
        }
    } else {
        // More B than A: cycle As
        foreach ($participantsB as $i => $b_id) {
            $a_id = $participantsA[$i % $countA];
            $matches[] = [$session_id, $round_id, $a_id, $b_id];
        }
    }

    // 5. Insert new matches
    $stmt = $conn->prepare("
        INSERT INTO matches (session_id, round_id, participant_a_id, participant_b_id)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($matches as $m) {
        $stmt->bind_param('iiii', $m[0], $m[1], $m[2], $m[3]);
        $stmt->execute();
    }
    $stmt->close();

    return "Randomly matched " . count($matches) . " pairs for outcome_realization.";
}



/* 
// Get the minimum count for initial matching
$minCount = min($countA, $countB);

// Shuffle both arrays to randomize the order
$shuffledA = $participantsA;
$shuffledB = $participantsB;
shuffle($shuffledA);
shuffle($shuffledB);

// Step 1: Match min($countA, $countB) participants
for ($i = 0; $i < $minCount; $i++) {
    $matches[] = [$session_id, $round_id, $shuffledA[$i], $shuffledB[$i]];
}

// Step 2: Match remaining participants from the larger group
if ($countA > $countB) {
    // More A than B: match remaining As with different Bs
    $availableB = $shuffledB; // Copy of B participants to track availability
    for ($i = $minCount; $i < $countA; $i++) {
        if (empty($availableB)) {
            // If no Bs left, reset to all Bs and shuffle for randomness
            $availableB = $shuffledB;
            shuffle($availableB);
        }
        // Select a random B from available ones
        $index = array_rand($availableB);
        $b_id = $availableB[$index];
        $matches[] = [$session_id, $round_id, $shuffledA[$i], $b_id];
        // Remove the used B to avoid reuse until necessary
        unset($availableB[$index]);
        $availableB = array_values($availableB); // Reindex array
    }
} elseif ($countB > $countA) {
    // More B than A: match remaining Bs with different As
    $availableA = $shuffledA; // Copy of A participants to track availability
    for ($i = $minCount; $i < $countB; $i++) {
        if (empty($availableA)) {
            // If no As left, reset to all As and shuffle for randomness
            $availableA = $shuffledA;
            shuffle($availableA);
        }
        // Select a random A from available ones
        $index = array_rand($availableA);
        $a_id = $availableA[$index];
        $matches[] = [$session_id, $round_id, $a_id, $shuffledB[$i]];
        // Remove the used A to avoid reuse until necessary
        unset($availableA[$index]);
        $availableA = array_values($availableA); // Reindex array
    }
}

*/
?>