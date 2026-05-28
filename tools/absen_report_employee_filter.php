<?php

declare(strict_types=1);

function parseEmployeeCodes(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }

    $codes = preg_split('/[,\s]+/', trim($raw));
    $codes = array_values(array_unique(array_filter(array_map('trim', $codes), static fn($code) => $code !== '')));
    return $codes;
}

function fetchEmployeesByCodes(mysqli $db, array $codes, string $from, string $to): array
{
    if (!$codes) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $types = 'ss' . str_repeat('s', count($codes));
    $params = array_merge([$from, $to], $codes);
    $rows = queryAll($db, "
        SELECT
            u.id,
            u.employee_code,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            b.id AS branch_id,
            b.branch_code,
            b.branch_name,
            COALESCE(sd.subdivision_name, '-') AS division_name,
            COALESCE(p.position_name, '-') AS position_name,
            u.join_date,
            usc.shift_cluster_id,
            sc.cluster_code,
            sc.cluster_name,
            usc.shift_applies,
            COUNT(usa.id) AS schedule_rows
        FROM users u
        JOIN position p ON p.id = u.position_id
        JOIN branch b ON b.id = p.branch_id
        LEFT JOIN subdivision sd ON sd.id = u.subdivision_id
        LEFT JOIN users_shift_cluster usc
            ON usc.user_id = u.id
           AND usc.id = (
                SELECT MAX(usc2.id)
                FROM users_shift_cluster usc2
                WHERE usc2.user_id = u.id
                  AND usc2.deleted_at IS NULL
           )
        LEFT JOIN shift_cluster sc ON sc.id = usc.shift_cluster_id
        LEFT JOIN users_shift_additional usa
            ON usa.user_id = u.id
           AND usa.additional_date BETWEEN ? AND ?
           AND usa.deleted_at IS NULL
        WHERE u.active = 1
          AND u.employee_code IN ({$placeholders})
        GROUP BY
            u.id, u.employee_code, employee_name, b.id, b.branch_code, b.branch_name,
            sd.subdivision_name, p.position_name, u.join_date, usc.shift_cluster_id,
            sc.cluster_code, sc.cluster_name, usc.shift_applies
    ", $types, $params);

    $byCode = [];
    foreach ($rows as $row) {
        $byCode[$row['employee_code']][] = $row;
    }

    $selected = [];
    foreach ($codes as $code) {
        if (empty($byCode[$code])) {
            continue;
        }

        usort($byCode[$code], static function ($a, $b) {
            $scheduleCompare = (int) $b['schedule_rows'] <=> (int) $a['schedule_rows'];
            if ($scheduleCompare !== 0) {
                return $scheduleCompare;
            }

            return (int) $a['branch_id'] <=> (int) $b['branch_id'];
        });

        $selected[] = $byCode[$code][0];
    }

    return $selected;
}
