<?php
/**
 * Rank A players by effort and mark top 50% (or 50%+1 if odd) as successful.
 * Requires table effort_tasks with columns: participant_id, round_id, effort_value, success (0/1)
 */
function rankEffortTaskResults($conn, $session_id, $round_id) {
    // Fetch all A players' efforts for this round
    $res = $conn->query("
        SELECT et.participant_id, et.effort_score
        FROM effort_tasks et
        JOIN participants p ON et.participant_id = p.participant_id
        WHERE et.round_id = $round_id AND p.role = 'A'
        ORDER BY et.effort_score DESC
    ");

    if ($res->num_rows === 0) {
        return "No A participants found for ranking.";
    }

    $a_players = [];
    while ($row = $res->fetch_assoc()) {
        $a_players[] = $row;
    }

    $n = count($a_players);
    if ($n === 0) return "No A participants found.";

    // Determine number of successes
    $num_success = intdiv($n, 2);
    if ($n % 2 !== 0) {
        $num_success += 1;
    }

    // Mark top players as success=1, rest as 0
    foreach ($a_players as $i => $player) {
        $success = ($i < $num_success) ? 1 : 0;
        $pid = (int)$player['participant_id'];
        $conn->query("UPDATE effort_tasks SET success = $success WHERE participant_id = $pid AND round_id = $round_id");
    }

    return "Ranked $n A players; top $num_success marked as successful.";
}
?>
