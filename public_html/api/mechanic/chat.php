<?php
/**
 * GET  [?contact_id=ID[&after_id=N]] -> contacts, and the thread with one contact (marked read).
 * POST { receiver_id, message }      -> send a message to one of the user's contacts.
 * Contact rules live in ../chat-common.php.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../chat-common.php';

$user = require_api_role('Mechanic');
chat_endpoint($pdo, $user);
