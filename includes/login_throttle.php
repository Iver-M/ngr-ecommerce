<?php

/** Update the shared attempt store while holding an exclusive lock. */
function login_throttle_update(string $path, int $now, callable $update): array
{
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Login attempt storage is unavailable.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Cannot lock login attempt storage.');
        }

        $json = stream_get_contents($handle, 1048577);
        if ($json === false || strlen($json) > 1048576) {
            throw new RuntimeException('Invalid login attempt storage.');
        }
        $records = $json === '' ? [] : json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($records)) {
            throw new RuntimeException('Invalid login attempt records.');
        }
        foreach ($records as $key => $record) {
            if (!is_array($record) || !isset($record['count'], $record['expires'])
                || !is_int($record['count']) || $record['count'] < 1
                || !is_int($record['expires'])) {
                throw new RuntimeException('Invalid login attempt record.');
            }
            if ($record['expires'] <= $now) {
                unset($records[$key]);
            }
        }

        $result = $update($records);
        $encoded = json_encode($records, JSON_THROW_ON_ERROR);
        if (count($records) > 10000 || strlen($encoded) > 1048576) {
            throw new RuntimeException('Login attempt storage is full.');
        }

        rewind($handle);
        $offset = 0;
        while ($offset < strlen($encoded)) {
            $written = fwrite($handle, substr($encoded, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Cannot save login attempts.');
            }
            $offset += $written;
        }
        if (!ftruncate($handle, strlen($encoded)) || !fflush($handle)) {
            throw new RuntimeException('Cannot persist login attempts.');
        }

        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** Reserve before checking credentials so concurrent requests cannot bypass limits. */
function login_throttle_reserve(string $path, string $email, string $ip, ?int $now = null): array
{
    $now = $now ?? time();
    $limits = [
        hash('sha256', 'account:' . strtolower(trim($email))) => 5,
        hash('sha256', 'ip:' . $ip) => 20,
    ];

    return login_throttle_update($path, $now, function (array &$records) use ($limits, $now): array {
        $retryAfter = 0;
        foreach ($limits as $key => $limit) {
            if (isset($records[$key]) && $records[$key]['count'] >= $limit) {
                $retryAfter = max($retryAfter, $records[$key]['expires'] - $now);
            }
        }
        if ($retryAfter > 0) {
            return ['allowed' => false, 'retry_after' => $retryAfter];
        }

        foreach ($limits as $key => $limit) {
            if (!isset($records[$key])) {
                $records[$key] = ['count' => 0, 'expires' => $now + 900];
            }
            $records[$key]['count']++;
        }
        return ['allowed' => true, 'retry_after' => 0];
    });
}

/** Successful authentication resets the account limit but preserves the IP budget. */
function login_throttle_clear_account(string $path, string $email, ?int $now = null): void
{
    $key = hash('sha256', 'account:' . strtolower(trim($email)));
    login_throttle_update($path, $now ?? time(), function (array &$records) use ($key): array {
        unset($records[$key]);
        return [];
    });
}
