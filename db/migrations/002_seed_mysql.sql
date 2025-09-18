```sql
INSERT INTO track_policies (track, slot_length_minutes, credits_per_slot, starting_credits) VALUES
  ('security',   240, 40, 1600)
ON DUPLICATE KEY UPDATE slot_length_minutes=VALUES(slot_length_minutes), credits_per_slot=VALUES(credits_per_slot), starting_credits=VALUES(starting_credits);

INSERT INTO track_policies (track, slot_length_minutes, credits_per_slot, starting_credits) VALUES
  ('datacenter', 180, 30,  900)
ON DUPLICATE KEY UPDATE slot_length_minutes=VALUES(slot_length_minutes), credits_per_slot=VALUES(credits_per_slot), starting_credits=VALUES(starting_credits);

INSERT INTO resources (name, track, kind, capacity, active, attributes_json) VALUES
  ('SEC Rack 1', 'security',   'rack', 1, 1, JSON_OBJECT()),
  ('SEC Rack 2', 'security',   'rack', 1, 1, JSON_OBJECT()),
  ('SEC Rack 3', 'security',   'rack', 1, 1, JSON_OBJECT()),
  ('DC Rack',    'datacenter', 'rack', 1, 1, JSON_OBJECT())
ON DUPLICATE KEY UPDATE track=VALUES(track), kind=VALUES(kind), capacity=VALUES(capacity), active=VALUES(active);
```
