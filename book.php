<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/seat_logic.php";
$user = require_login($pdo);

$stations = stations_in_order($pdo);
$trains = $pdo->query("SELECT * FROM train")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Book a Seat";
require __DIR__ . "/includes/header.php";
?>
<h2>Book a seat</h2>
<p class="hint">Pick your route below. You can select multiple seats for family or group travel on the same route and date. The seat map updates live and only shows seats that are actually free for your exact segment &mdash; including seats freed up by earlier passengers who already exited.</p>

<form id="booking-form" method="POST" action="book_confirm.php">
    <div class="route-picker">
        <label>Train
            <select id="train_id">
                <?php foreach ($trains as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> &mdash; <?= htmlspecialchars(departure_period_label($t['departure_time'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>From
            <select id="origin_id">
                <?php foreach ($stations as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>To
            <select id="destination_id">
                <?php foreach ($stations as $i => $s): ?>
                <option value="<?= $s['id'] ?>" <?= $i === count($stations) - 1 ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Travel date
            <input type="date" id="travel_date" name="travel_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
        </label>
    </div>

    <input type="hidden" name="train_id" id="hidden_train_id">
    <input type="hidden" name="origin_id" id="hidden_origin_id">
    <input type="hidden" name="destination_id" id="hidden_destination_id">
    <input type="hidden" name="travel_date" id="hidden_travel_date">
    <input type="hidden" name="seat_ids" id="hidden_seat_ids">

    <div class="fare-line">Estimated fare: <strong id="fare-display">-- TZS</strong></div>
    <div id="travel-summary" class="hint">Trip summary will appear here.</div>

    <div id="seat-map" class="seat-map empty-state" aria-live="polite">
        <div class="seat-map-placeholder">Choose a train, origin and destination to load the seat map.</div>
    </div>
    <p id="seat-hint" class="hint">Select a valid route to view available seats.</p>

    <button type="submit" id="confirm-btn" class="btn-primary" disabled>Confirm booking</button>
</form>

<script src="assets/js/booking.js"></script>
<?php require __DIR__ . "/includes/footer.php"; ?>
