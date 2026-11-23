<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/eligibility_engine.php';

header('Content-Type: application/json; charset=utf-8');

function body(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : $_POST;
}

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function requireLogin(): int
{
    if (empty($_SESSION['user_id'])) {
        respond(['error' => 'Login required.'], 401);
    }
    return (int) $_SESSION['user_id'];
}

function requireRole(string ...$roles): int
{
    $userId = requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        respond(['error' => 'You do not have permission for this action.'], 403);
    }
    return $userId;
}

function required(array $data, string ...$fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            respond(['error' => "Missing field: {$field}."], 422);
        }
    }
}

function validEnum(string $value, array $allowed, string $field): void
{
    if (!in_array($value, $allowed, true)) {
        respond(['error' => "Invalid {$field}."], 422);
    }
}

function validDate(string $value, string $field): string
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        respond(['error' => "Invalid {$field}."], 422);
    }
    return $value;
}

$action = $_GET['action'] ?? '';
$data = body();

try {
    switch ($action) {
        case 'register':
            required($data, 'name', 'email', 'password', 'role');
            $role = $data['role'] === 'hospital' ? 'recipient' : $data['role'];
            validEnum($role, ['donor', 'recipient'], 'role');
            if (strlen((string) $data['password']) < 8) {
                respond(['error' => 'Password must be at least 8 characters.'], 422);
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                respond(['error' => 'Invalid email address.'], 422);
            }
            if ($role === 'donor') {
                required($data, 'blood_group');
                validEnum($data['blood_group'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], 'blood group');
                if (!empty($data['last_donation_date'])) {
                    $lastDonationDate = validDate($data['last_donation_date'], 'last donation date');
                    if ($lastDonationDate > date('Y-m-d')) {
                        respond(['error' => 'Last donation date cannot be in the future.'], 422);
                    }
                }
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, phone, location)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim((string) $data['name']),
                trim((string) $data['email']),
                password_hash((string) $data['password'], PASSWORD_DEFAULT),
                $role,
                $data['phone'] ?? null,
                $data['location'] ?? ($data['address'] ?? null),
            ]);
            $userId = (int) $pdo->lastInsertId();

            if ($role === 'donor') {
                $donor = $pdo->prepare(
                    'INSERT INTO donors (user_id, blood_group, last_donation_date, next_eligible_date)
                     VALUES (?, ?, ?, ?)'
                );
                $lastDonation = $data['last_donation_date'] ?: null;
                $next = $lastDonation ? next_eligible_date($lastDonation, 'blood') : null;
                $donor->execute([$userId, $data['blood_group'], $lastDonation, $next]);
            }
            $pdo->commit();
            respond(['message' => 'Account created.']);

        case 'login':
            required($data, 'email', 'password');
            $stmt = $pdo->prepare('SELECT user_id, name, password_hash, role FROM users WHERE email = ?');
            $stmt->execute([trim((string) $data['email'])]);
            $user = $stmt->fetch();
            if (!$user || !password_verify((string) $data['password'], $user['password_hash'])) {
                respond(['error' => 'Invalid email or password.'], 401);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['role'] = $user['role'];
            respond(['user' => ['id' => (int) $user['user_id'], 'name' => $user['name'], 'role' => $user['role']]]);

        case 'logout':
            $_SESSION = [];
            session_destroy();
            respond(['message' => 'Logged out.']);

        case 'me':
            $userId = requireLogin();
            $stmt = $pdo->prepare(
                'SELECT u.user_id AS id, u.name, u.email, u.role, u.phone, u.location,
                        d.donor_id, d.blood_group, d.plasma_eligible, d.last_donation_date,
                        d.next_eligible_date, d.is_available, d.blood_points
                 FROM users u LEFT JOIN donors d ON d.user_id = u.user_id WHERE u.user_id = ?'
            );
            $stmt->execute([$userId]);
            respond(['user' => $stmt->fetch()]);

        case 'donors':
            requireRole('recipient', 'admin');
            $group = $_GET['blood_group'] ?? 'all';
            $sql = 'SELECT d.donor_id AS id, u.name, d.blood_group, d.plasma_eligible, u.location
                    FROM donors d JOIN users u ON u.user_id = d.user_id
                    WHERE d.is_available = TRUE
                      AND (d.next_eligible_date IS NULL OR d.next_eligible_date <= CURDATE())';
            $params = [];
            if ($group !== 'all') {
                $compatibility = [
                    'O-' => ['O-'],
                    'O+' => ['O-', 'O+'],
                    'A-' => ['O-', 'A-'],
                    'A+' => ['O-', 'O+', 'A-', 'A+'],
                    'B-' => ['O-', 'B-'],
                    'B+' => ['O-', 'O+', 'B-', 'B+'],
                    'AB-' => ['O-', 'A-', 'B-', 'AB-'],
                    'AB+' => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
                ];
                if (!isset($compatibility[$group])) {
                    respond(['error' => 'Invalid blood group filter.'], 422);
                }
                $placeholders = implode(',', array_fill(0, count($compatibility[$group]), '?'));
                $sql .= " AND d.blood_group IN ($placeholders)";
                $params = $compatibility[$group];
            }
            $stmt = $pdo->prepare($sql . ' ORDER BY u.name');
            $stmt->execute($params);
            respond(['donors' => $stmt->fetchAll()]);

        case 'requests':
            $userId = requireLogin();
            $sql = 'SELECT r.request_id AS id, u.name AS hospital, r.blood_group, r.component,
                           r.urgency, r.status, r.hospital_address, r.posted_at AS posted
                    FROM donation_requests r JOIN users u ON u.user_id = r.recipient_id';
            if ($_SESSION['role'] === 'recipient') {
                $stmt = $pdo->prepare($sql . ' WHERE r.recipient_id = ? ORDER BY r.posted_at DESC');
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->query($sql . ' ORDER BY r.posted_at DESC');
            }
            respond(['requests' => $stmt->fetchAll()]);

        case 'create_request':
            $userId = requireRole('recipient');
            required($data, 'blood_group', 'component', 'urgency');
            validEnum($data['blood_group'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], 'blood group');
            validEnum($data['component'], ['Blood', 'Plasma'], 'component');
            validEnum($data['urgency'], ['Normal', 'Critical'], 'urgency');
            $stmt = $pdo->prepare(
                'INSERT INTO donation_requests (recipient_id, blood_group, component, urgency, hospital_address)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $data['blood_group'], $data['component'], $data['urgency'], $data['hospital_address'] ?? null]);
            respond(['message' => 'Request posted.', 'id' => (int) $pdo->lastInsertId()], 201);

        case 'rewards':
            requireLogin();
            respond(['rewards' => $pdo->query('SELECT * FROM rewards ORDER BY points_required')->fetchAll()]);

        case 'leaderboard':
            requireLogin();
            respond(['donors' => $pdo->query(
                'SELECT u.name, d.blood_points AS points
                 FROM donors d JOIN users u ON u.user_id = d.user_id
                 ORDER BY d.blood_points DESC, u.name LIMIT 10'
            )->fetchAll()]);

        case 'log_donation':
            $userId = requireRole('donor');
            required($data, 'component', 'donation_date');
            validEnum($data['component'], ['Blood', 'Plasma'], 'component');
            $donationDate = validDate($data['donation_date'], 'donation date');
            if ($donationDate > date('Y-m-d')) {
                respond(['error' => 'Donation date cannot be in the future.'], 422);
            }
            $pdo->beginTransaction();
            $donor = $pdo->prepare(
                'SELECT donor_id, plasma_eligible, next_eligible_date
                 FROM donors WHERE user_id = ? FOR UPDATE'
            );
            $donor->execute([$userId]);
            $donor = $donor->fetch();
            if (!$donor) {
                $pdo->rollBack();
                respond(['error' => 'Donor profile not found.'], 404);
            }
            if ($data['component'] === 'Plasma' && !$donor['plasma_eligible']) {
                $pdo->rollBack();
                respond(['error' => 'This donor is not eligible for plasma donation.'], 422);
            }
            if ($donor['next_eligible_date'] && $donor['next_eligible_date'] > date('Y-m-d')) {
                $pdo->rollBack();
                respond(['error' => 'The donor is not currently eligible to donate.'], 422);
            }
            $stmt = $pdo->prepare(
                'INSERT INTO donations (donor_id, component, donation_date) VALUES (?, ?, ?)'
            );
            $stmt->execute([(int) $donor['donor_id'], $data['component'], $donationDate]);
            $nextDate = next_eligible_date($donationDate, $data['component']);
            $pdo->prepare(
                'UPDATE donors SET last_donation_date = ?, next_eligible_date = ?, is_available = FALSE
                 WHERE donor_id = ?'
            )->execute([$donationDate, $nextDate, (int) $donor['donor_id']]);
            $pdo->commit();
            respond(['message' => 'Donation submitted for verification.'], 201);

        case 'admin_overview':
            requireRole('admin');
            respond([
                'donors' => (int) $pdo->query('SELECT COUNT(*) FROM donors')->fetchColumn(),
                'open_requests' => (int) $pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status = 'Open'")->fetchColumn(),
                'pending' => (int) $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'Pending'")->fetchColumn(),
                'points' => (int) $pdo->query('SELECT COALESCE(SUM(blood_points), 0) FROM donors')->fetchColumn(),
                'pending_donations' => $pdo->query(
                    "SELECT x.donation_id AS id, u.name AS donor, x.component, x.donation_date AS date
                     FROM donations x JOIN donors d ON d.donor_id = x.donor_id
                     JOIN users u ON u.user_id = d.user_id WHERE x.status = 'Pending'
                     ORDER BY x.donation_date"
                )->fetchAll(),
            ]);

        case 'verify_donation':
            requireRole('admin');
            required($data, 'donation_id');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT x.donor_id FROM donations x WHERE x.donation_id = ? AND x.status = 'Pending' FOR UPDATE"
            );
            $stmt->execute([(int) $data['donation_id']]);
            $donation = $stmt->fetch();
            if (!$donation) {
                $pdo->rollBack();
                respond(['error' => 'Pending donation not found.'], 404);
            }
            $pdo->prepare("UPDATE donations SET status = 'Verified' WHERE donation_id = ?")
                ->execute([(int) $data['donation_id']]);
            $pdo->prepare('UPDATE donors SET blood_points = blood_points + ? WHERE donor_id = ?')
                ->execute([PULSE_DONATION_POINTS, (int) $donation['donor_id']]);
            $pdo->commit();
            respond(['message' => 'Donation verified and points awarded.']);

        case 'redeem':
            $userId = requireRole('donor');
            required($data, 'reward_id');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT d.donor_id, d.blood_points, r.points_required, r.coupon_code
                 FROM donors d JOIN rewards r ON r.reward_id = ? WHERE d.user_id = ? FOR UPDATE'
            );
            $stmt->execute([(int) $data['reward_id'], $userId]);
            $reward = $stmt->fetch();
            if (!$reward || $reward['blood_points'] < $reward['points_required']) {
                $pdo->rollBack();
                respond(['error' => 'Not enough points or reward not found.'], 422);
            }
            $pdo->prepare('UPDATE donors SET blood_points = blood_points - ? WHERE donor_id = ?')
                ->execute([$reward['points_required'], $reward['donor_id']]);
            $pdo->prepare('INSERT INTO redeemed_coupons (donor_id, reward_id) VALUES (?, ?)')
                ->execute([$reward['donor_id'], (int) $data['reward_id']]);
            $pdo->commit();
            respond(['message' => 'Reward redeemed.', 'coupon_code' => $reward['coupon_code']]);

        default:
            respond(['error' => 'Unknown action.'], 404);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((int) $e->errorInfo[1] === 1062) {
        respond(['error' => 'That email is already registered.'], 409);
    }
    respond(['error' => 'Database operation failed.'], 500);
}
