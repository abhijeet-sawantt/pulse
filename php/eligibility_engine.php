<?php

require_once __DIR__ . '/db_config.php';

function next_eligible_date(string $lastDonationDate, string $component): string {
    $days = strtolower($component) === 'plasma' ? PULSE_PLASMA_WAIT_DAYS : PULSE_BLOOD_WAIT_DAYS;
    $date = new DateTime($lastDonationDate);
    $date->modify("+{$days} days");
    return $date->format('Y-m-d');
}

function recalculate_donor_readiness(PDO $pdo, int $donorId): void {
    $stmt = $pdo->prepare('SELECT last_donation_date, plasma_eligible FROM donors WHERE donor_id = ?');
    $stmt->execute([$donorId]);
    $donor = $stmt->fetch();
    if (!$donor || !$donor['last_donation_date']) return;

    $component = $donor['plasma_eligible'] ? 'plasma' : 'blood';
    $next = next_eligible_date($donor['last_donation_date'], $component);
    $isAvailable = (new DateTime($next)) <= (new DateTime());

    $update = $pdo->prepare(
        'UPDATE donors SET next_eligible_date = ?, is_available = ? WHERE donor_id = ?'
    );
    $update->execute([$next, $isAvailable ? 1 : 0, $donorId]);
}

