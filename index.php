<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
$__user = current_user($pdo);
$page_title = "SGR SeatFlow - Autonomous Seat Management";
require __DIR__ . "/includes/header.php";
?>
<section class="hero">
    <h1>Pay only for the distance you actually travel.</h1>
    <p>SGR SeatFlow re-allocates your seat automatically the moment you exit early &mdash;
       so the seat you free up doesn't sit empty for the rest of the trip.</p>
    <?php if (!$__user): ?>
        <div class="hero-actions">
            <a href="register.php" class="btn-primary">Create an account</a>
            <a href="login.php" class="btn-outline">Log in</a>
        </div>
    <?php else: ?>
        <div class="hero-actions">
            <a href="book.php" class="btn-primary">Book a seat now</a>
        </div>
    <?php endif; ?>
</section>

<section class="feature-grid">
    <div class="feature-card">
        <h3>Live Seat Map</h3>
        <p>See exactly which seats are free for your route, updated instantly as you change stations.</p>
    </div>
    <div class="feature-card">
        <h3>Automatic Seat Harvesting</h3>
        <p>When a passenger exits early, their unused seat segment is freed and re-sold in real time.</p>
    </div>
    <div class="feature-card">
        <h3>Fare Equity</h3>
        <p>Early exits trigger an automatic refund for the distance not travelled &mdash; no manual paperwork.</p>
    </div>
</section>
<?php require __DIR__ . "/includes/footer.php"; ?>
