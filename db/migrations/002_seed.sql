-- Track policies
INSERT INTO track_policies (track, slot_length_minutes, credits_per_slot, starting_credits)
VALUES
  ('security',   240, 40, 1600) ON CONFLICT (track) DO UPDATE SET slot_length_minutes=EXCLUDED.slot_length_minutes, credits_per_slot=EXCLUDED.credits_per_slot, starting_credits=EXCLUDED.starting_credits,
  starting_credits = EXCLUDED.starting_credits;

INSERT INTO track_policies (track, slot_length_minutes, credits_per_slot, starting_credits)
VALUES
  ('datacenter', 180, 30,  900) ON CONFLICT (track) DO UPDATE SET slot_length_minutes=EXCLUDED.slot_length_minutes, credits_per_slot=EXCLUDED.credits_per_slot, starting_credits=EXCLUDED.starting_credits;

-- Resources
INSERT INTO resources (name, track, kind, capacity, active) VALUES
  ('SEC Rack 1', 'security',   'rack', 1, TRUE),
  ('SEC Rack 2', 'security',   'rack', 1, TRUE),
  ('SEC Rack 3', 'security',   'rack', 1, TRUE),
  ('DC Rack',    'datacenter', 'rack', 1, TRUE)
ON CONFLICT DO NOTHING;

-- (Optional) Admin/trainer bootstrap — set your own values in production
-- INSERT INTO users (username,email,password_hash,status,role,track,credits)
-- VALUES ('admin','admin@example.com','$2y$...','approved','admin',NULL,NULL);
