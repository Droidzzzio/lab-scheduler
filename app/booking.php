<?php
// app/Booking.php

class Booking
{
    /**
     * Create a booking (inserts and deducts credits when needed).
     * Returns [true, bookingId] on success, or [false, "error message"] on failure.
     */
    public static function create(array $opts)
    {
        // Expected keys in $opts:
        // uid, resource_id, date (Y-m-d), slot_idx (int),
        // startUtc (DateTime in UTC), endUtc (DateTime in UTC),
        // track ('datacenter' | 'security'), module_or_rack (nullable),
        // role ('student' | 'trainer' | 'admin'), cost (int credits)
        $required = ['uid','resource_id','date','slot_idx','startUtc','endUtc','track','role','cost'];
        foreach ($required as $k) {
            if (!array_key_exists($k, $opts)) {
                return [false, "Missing required option: {$k}"];
            }
        }

        $uid            = (int)$opts['uid'];
        $resource_id    = (int)$opts['resource_id'];
        $date           = $opts['date'];
        $slot_idx       = (int)$opts['slot_idx'];
        $startUtc       = $opts['startUtc'];   // DateTime (UTC)
        $endUtc         = $opts['endUtc'];     // DateTime (UTC)
        $track          = $opts['track'];
        $module_or_rack = $opts['module_or_rack'] ?? null;
        $role           = $opts['role'];
        $cost           = (int)$opts['cost'];

        try {
            DB::$pdo->beginTransaction();

            // datacenter: module must be one of Nexus/UCS/ACI
            $module = null;
            if ($track === 'datacenter') {
                if (!in_array($module_or_rack, ['Nexus','UCS','ACI'], true)) {
                    throw new Exception('Module required');
                }
                $module = $module_or_rack;
            }

            // Insert booking (unique key on confirmed prevents double-booking)
            $ins = DB::$pdo->prepare(
                "INSERT INTO bookings
                 (user_id, resource_id, date, slot_idx, start_ts, end_ts, status, module, attributes_json)
                 VALUES (:uid, :rid, :d, :idx, :start, :end, 'confirmed', :module, JSON_OBJECT())"
            );
            $ins->execute([
                ':uid'   => $uid,
                ':rid'   => $resource_id,
                ':d'     => $date,
                ':idx'   => $slot_idx,
                ':start' => $startUtc->format('Y-m-d H:i:s'),
                ':end'   => $endUtc->format('Y-m-d H:i:s'),
                ':module'=> $module,
            ]);
            $bid = (int)DB::$pdo->lastInsertId();

            // Deduct credits for students
            if ($role === 'student' && $cost > 0) {
                $u = DB::$pdo->prepare("UPDATE users SET credits = credits - :c WHERE id = :uid");
                $u->execute([':c' => $cost, ':uid' => $uid]);

                $led = DB::$pdo->prepare(
                    "INSERT INTO credit_ledger (user_id, booking_id, delta, reason)
                     VALUES (:uid, :bid, :delta, :reason)"
                );
                $led->execute([
                    ':uid'    => $uid,
                    ':bid'    => $bid,
                    ':delta'  => -$cost,
                    ':reason' => ($track === 'datacenter' ? 'dc booking' : 'security booking')
                ]);
            }

            DB::$pdo->commit();
            return [true, $bid];

        } catch (Throwable $e) {
            DB::$pdo->rollBack();
            return [false, $e->getMessage()];
        }
    }

    /**
     * Cancel a booking (enforces 72h rule; refunds if applicable).
     * Returns [true, null] or [false, "error"].
     */
    public static function cancel(array $user, int $booking_id)
    {
        $uid  = (int)$user['id'];
        $role = $user['role'] ?? 'student';

        DB::$pdo->beginTransaction();
        try {
            $st = DB::$pdo->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
            $st->execute([':id' => $booking_id]);
            $b = $st->fetch();
            if (!$b) throw new Exception('Booking not found');
            if ($b['status'] !== 'confirmed') throw new Exception('Already cancelled');

            $owner = ((int)$b['user_id'] === $uid);

            // 72h rule
            $now   = self::nowUtc();
            $start = new DateTime($b['start_ts'], new DateTimeZone('UTC'));
            $diffH = ($start->getTimestamp() - $now->getTimestamp()) / 3600;
            $canStudentCancel = $owner && $diffH >= 72;

            if (!($canStudentCancel || $role === 'trainer' || $role === 'admin')) {
                throw new Exception('Locked (<72h)');
            }

            // Cancel
            $upd = DB::$pdo->prepare(
                "UPDATE bookings
                 SET status='cancelled', cancelled_at=NOW(), cancelled_by=:by
                 WHERE id=:id"
            );
            $upd->execute([':by' => $uid, ':id' => $booking_id]);

            // Refund only if student owner and ≥72h
            if ($canStudentCancel) {
                $trk = DB::$pdo->prepare("SELECT track FROM users WHERE id = :uid");
                $trk->execute([':uid' => $b['user_id']]);
                $u = $trk->fetch();

                $pol  = self::trackPolicy($u['track']);
                $cost = (int)($pol['credits_per_slot'] ?? 0);

                if ($cost > 0) {
                    DB::$pdo->prepare("UPDATE users SET credits = credits + :c WHERE id = :uid")
                            ->execute([':c' => $cost, ':uid' => $b['user_id']]);

                    DB::$pdo->prepare(
                        "INSERT INTO credit_ledger (user_id, booking_id, delta, reason)
                         VALUES (:uid, :bid, :delta, 'refund (>=72h)')"
                    )->execute([
                        ':uid'   => $b['user_id'],
                        ':bid'   => $booking_id,
                        ':delta' => $cost
                    ]);
                }
            }

            DB::$pdo->commit();
            return [true, null];

        } catch (Throwable $e) {
            DB::$pdo->rollBack();
            return [false, $e->getMessage()];
        }
    }

    /** Helpers used by index.php */

    public static function slotsForDay($track)
    {
        $stmt = DB::$pdo->prepare(
            "SELECT slot_length_minutes FROM track_policies WHERE track=:t LIMIT 1"
        );
        $stmt->execute([':t' => $track]);
        $policy = $stmt->fetch();

        $slotLength = (int)($policy['slot_length_minutes'] ?? 60);
        $slots = [];
        $start = new DateTime("09:00");
        $end   = new DateTime("18:00");
        for ($idx = 0; $start < $end; $idx++) {
            $slots[] = ['idx' => $idx, 'time' => $start->format('H:i')];
            $start->modify("+{$slotLength} minutes");
        }
        return $slots;
    }

    public static function bookingsForDate($resourceId, $date)
    {
        $stmt = DB::$pdo->prepare(
            "SELECT b.id, b.user_id, u.username, b.slot_idx
             FROM bookings b
             JOIN users u ON u.id=b.user_id
             WHERE b.resource_id=:rid AND b.date=:d AND b.status='confirmed'"
        );
        $stmt->execute([':rid' => $resourceId, ':d' => $date]);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['slot_idx']] = $r;
        }
        return $map;
    }

    public static function resourcesForTrack($track)
    {
        $stmt = DB::$pdo->prepare("SELECT * FROM resources WHERE track=:t");
        $stmt->execute([':t' => $track]);
        return $stmt->fetchAll();
    }

    public static function trackPolicy($track)
    {
        $stmt = DB::$pdo->prepare(
            "SELECT credits_per_slot, slot_length_minutes FROM track_policies WHERE track=:t LIMIT 1"
        );
        $stmt->execute([':t' => $track]);
        return $stmt->fetch() ?: ['credits_per_slot' => 0, 'slot_length_minutes' => 60];
    }

    public static function nowUtc(): DateTime
    {
        return new DateTime('now', new DateTimeZone('UTC'));
    }
}
