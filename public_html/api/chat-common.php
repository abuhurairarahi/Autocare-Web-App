<?php
/**
 * chat-common.php
 * Contact rules and message queries shared by the Mechanic and VehicleOwner chat
 * pages and endpoints. A user may only read or send messages with their contacts:
 *   VehicleOwner: all Managers, the mechanics on their jobs, anyone already in a thread with them.
 *   Mechanic:     all Managers, the owners of their jobs, anyone already in a thread with them.
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

const CHAT_MAX_LENGTH = 2000;

/** Contacts with last message, its time and unread count; most recent conversation first. */
function chat_contacts(PDO $pdo, int $userId, string $role): array
{
    if ($role === 'VehicleOwner') {
        $related = "
            SELECT j.mechanic_id FROM JobCards j
              JOIN Appointments a ON j.appointment_id = a.appointment_id
             WHERE a.owner_id = :me1 AND j.mechanic_id IS NOT NULL";
        $relatedRole = 'Mechanic';
    } else {
        $related = "
            SELECT a.owner_id FROM JobCards j
              JOIN Appointments a ON j.appointment_id = a.appointment_id
             WHERE j.mechanic_id = :me1";
        $relatedRole = 'VehicleOwner';
    }

    $stmt = $pdo->prepare("
        SELECT u.user_id AS id, u.name, u.role, u.specialty, u.avatar,
               last.message_text AS last_message, last.created_at AS last_time,
               (SELECT COUNT(*) FROM ChatMessages c
                 WHERE c.sender_id = u.user_id AND c.receiver_id = :me2 AND c.is_read = 0) AS unread
          FROM Users u
          LEFT JOIN ChatMessages last ON last.message_id = (
                SELECT m.message_id FROM ChatMessages m
                 WHERE (m.sender_id = u.user_id AND m.receiver_id = :me3)
                    OR (m.sender_id = :me4 AND m.receiver_id = u.user_id)
                 ORDER BY m.created_at DESC, m.message_id DESC LIMIT 1)
         WHERE u.user_id <> :me5
           AND u.role IN ('Manager', 'Mechanic', 'VehicleOwner')
           AND (u.role = 'Manager'
                OR (u.role = :relatedRole AND u.user_id IN ({$related}))
                OR last.message_id IS NOT NULL)
         ORDER BY last.created_at IS NULL, last.created_at DESC, u.role, u.name
    ");
    $stmt->execute([
        'me1' => $userId, 'me2' => $userId, 'me3' => $userId, 'me4' => $userId, 'me5' => $userId,
        'relatedRole' => $relatedRole,
    ]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['unread'] = (int) $row['unread'];
        $row['avatar'] = safe_avatar_url($row['avatar']) ?: null;
        $row['initials'] = initials($row['name']);
    }
    return $rows;
}

/** The contact row for $contactId, or null when the user may not chat with them. */
function chat_contact(PDO $pdo, int $userId, string $role, int $contactId): ?array
{
    foreach (chat_contacts($pdo, $userId, $role) as $contact) {
        if ($contact['id'] === $contactId) {
            return $contact;
        }
    }
    return null;
}

/** Messages between the two users, oldest first; only those after $afterId when given. */
function chat_messages(PDO $pdo, int $userId, int $contactId, int $afterId = 0, int $limit = 200): array
{
    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT m.message_id AS id, m.sender_id, m.job_tag, m.message_text AS message,
                   m.attachment_url, m.created_at
              FROM ChatMessages m
             WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
               AND m.message_id > ?
             ORDER BY m.created_at DESC, m.message_id DESC
             LIMIT " . max(1, $limit) . "
        ) recent ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$userId, $contactId, $contactId, $userId, $afterId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['is_mine'] = (int) $row['sender_id'] === $userId;
        unset($row['sender_id']);

        // attachment_url holds a JSON array or a single path; keep only safe local images
        $attachments = [];
        if (!empty($row['attachment_url'])) {
            $decoded = json_decode($row['attachment_url'], true);
            foreach (is_array($decoded) ? $decoded : [$row['attachment_url']] as $url) {
                $safe = is_string($url) ? safe_image_url($url) : '';
                if ($safe !== '') {
                    $attachments[] = $safe;
                }
            }
        }
        $row['attachments'] = $attachments;
        unset($row['attachment_url']);
    }
    return $rows;
}

function chat_mark_read(PDO $pdo, int $userId, int $contactId): void
{
    $pdo->prepare("UPDATE ChatMessages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
        ->execute([$contactId, $userId]);
}

/** Short role line shown under a contact's name. */
function contact_role_label(array $contact): string
{
    if ($contact['role'] === 'Manager') return 'Service Manager';
    if ($contact['role'] === 'VehicleOwner') return 'Customer';
    return $contact['specialty'] ?: $contact['role'];
}

/** "3:05 PM" today, "Yesterday", otherwise "Oct 02". */
function contact_time(?string $when): string
{
    if (!$when) return '';
    $ts = strtotime($when);
    if (date('Y-m-d', $ts) === date('Y-m-d', now_ts())) return date('g:i A', $ts);
    if (date('Y-m-d', $ts) === date('Y-m-d', now_ts() - 86400)) return 'Yesterday';
    return date('M d', $ts);
}

/**
 * Shared GET/POST handling for api/<role>/chat.php.
 * GET  ?contact_id=ID[&after_id=N] -> { contacts, contact, messages } (marks the thread read)
 * GET  (no contact)                 -> { contacts }
 * POST { receiver_id, message }     -> the stored message
 */
function chat_endpoint(PDO $pdo, array $user): void
{
    $method = require_method(['GET', 'POST']);
    try {
        if ($method === 'GET') {
            $contacts = chat_contacts($pdo, $user['id'], $user['role']);
            if (!isset($_GET['contact_id'])) {
                json_ok(['contacts' => $contacts]);
            }
            $contactId = input_id($_GET['contact_id']);
            $contact = null;
            foreach ($contacts as $c) {
                if ($c['id'] === $contactId) {
                    $contact = $c;
                }
            }
            if (!$contact) {
                json_error('Conversation not found.', 404);
            }
            $afterId = input_id($_GET['after_id'] ?? null) ?? 0;
            $messages = chat_messages($pdo, $user['id'], $contactId, $afterId);
            chat_mark_read($pdo, $user['id'], $contactId);
            json_ok(['contacts' => $contacts, 'contact' => $contact, 'messages' => $messages]);
        }

        $input = read_json_body();
        $receiverId = input_id($input['receiver_id'] ?? null);
        $message = input_text($input['message'] ?? null, 1, CHAT_MAX_LENGTH);
        if ($message === null) {
            json_error('Message must be 1-' . CHAT_MAX_LENGTH . ' characters.', 422);
        }
        if (!$receiverId || !chat_contact($pdo, $user['id'], $user['role'], $receiverId)) {
            json_error('Conversation not found.', 404);
        }

        $pdo->prepare("INSERT INTO ChatMessages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)")
            ->execute([$user['id'], $receiverId, $message]);
        $id = (int) $pdo->lastInsertId();
        $stored = chat_messages($pdo, $user['id'], $receiverId, $id - 1, 1);
        json_ok($stored[0] ?? ['id' => $id], 201);
    } catch (PDOException $e) {
        json_server_error($e);
    }
}
