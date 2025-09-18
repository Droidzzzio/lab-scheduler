-- Enable useful extensions (optional)
CREATE EXTENSION IF NOT EXISTS pgcrypto; -- for digest/uuid if needed

-- Enums
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_status') THEN
    CREATE TYPE user_status AS ENUM ('pending','approved','rejected');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
    CREATE TYPE user_role AS ENUM ('student','trainer','admin');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_track') THEN
    CREATE TYPE user_track AS ENUM ('security','datacenter');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'resource_kind') THEN
    CREATE TYPE resource_kind AS ENUM ('rack');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'booking_status') THEN
    CREATE TYPE booking_status AS ENUM ('confirmed','cancelled');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'blackout_type') THEN
    CREATE TYPE blackout_type AS ENUM ('date','weekly');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'module_type') THEN
    CREATE TYPE module_type AS ENUM ('Nexus','UCS','ACI');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'approval_status') THEN
    CREATE TYPE approval_status AS ENUM ('pending','approved','rejected');
  END IF;
END $$;

-- Users
CREATE TABLE IF NOT EXISTS users (
  id            BIGSERIAL PRIMARY KEY,
  username      TEXT NOT NULL,
  email         TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  status        user_status NOT NULL DEFAULT 'pending',
  role          user_role   NOT NULL DEFAULT 'student',
  track         user_track  NULL,
  exam_date     DATE NULL,
  credits       INTEGER NULL,
  timezone      TEXT NOT NULL DEFAULT 'Asia/Kolkata',
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Case-insensitive uniqueness for username/email
CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique ON users (lower(username));
CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique    ON users (lower(email));

-- Resources (racks)
CREATE TABLE IF NOT EXISTS resources (
  id             BIGSERIAL PRIMARY KEY,
  name           TEXT NOT NULL UNIQUE,
  track          user_track NOT NULL,
  kind           resource_kind NOT NULL DEFAULT 'rack',
  capacity       INTEGER NOT NULL DEFAULT 1,
  active         BOOLEAN NOT NULL DEFAULT TRUE,
  attributes_json JSONB NOT NULL DEFAULT '{}',
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Track policies (slot length, credits per slot, starting credits)
CREATE TABLE IF NOT EXISTS track_policies (
  track              user_track PRIMARY KEY,
  slot_length_minutes INTEGER NOT NULL,
  credits_per_slot    INTEGER NOT NULL,
  starting_credits    INTEGER NOT NULL
);

-- Bookings
CREATE TABLE IF NOT EXISTS bookings (
  id            BIGSERIAL PRIMARY KEY,
  user_id       BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  resource_id   BIGINT NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
  date          DATE NOT NULL,
  slot_idx      SMALLINT NOT NULL, -- 0..7 or 0..5 depending on track policy
  start_ts      TIMESTAMPTZ NOT NULL,
  end_ts        TIMESTAMPTZ NOT NULL,
  status        booking_status NOT NULL DEFAULT 'confirmed',
  module        module_type NULL, -- only for Data Center
  attributes_json JSONB NOT NULL DEFAULT '{}',
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  cancelled_at  TIMESTAMPTZ NULL,
  cancelled_by  BIGINT NULL REFERENCES users(id)
);

-- Prevent double booking per resource/date/slot for confirmed bookings only
CREATE UNIQUE INDEX IF NOT EXISTS uniq_booking_slot
  ON bookings(resource_id, date, slot_idx)
  WHERE status = 'confirmed';

-- Blackouts
CREATE TABLE IF NOT EXISTS blackouts (
  id          BIGSERIAL PRIMARY KEY,
  resource_id BIGINT NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
  type        blackout_type NOT NULL,
  date        DATE NULL,
  weekly_dow  SMALLINT NULL, -- 0=Sun..6=Sat
  slot_idx    SMALLINT NULL,
  reason      TEXT NULL,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Credit ledger (authoritative credits history)
CREATE TABLE IF NOT EXISTS credit_ledger (
  id         BIGSERIAL PRIMARY KEY,
  user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  booking_id BIGINT NULL REFERENCES bookings(id) ON DELETE SET NULL,
  delta      INTEGER NOT NULL,
  reason     TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Approvals
CREATE TABLE IF NOT EXISTS approvals (
  id                 BIGSERIAL PRIMARY KEY,
  user_id            BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  requested_track    user_track NOT NULL,
  requested_exam_date DATE NOT NULL,
  status             approval_status NOT NULL DEFAULT 'pending',
  decided_by         BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
  decided_at         TIMESTAMPTZ NULL,
  note               TEXT NULL,
  created_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
  id            BIGSERIAL PRIMARY KEY,
  actor_user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
  action        TEXT NOT NULL,
  target_type   TEXT NOT NULL,
  target_id     BIGINT NULL,
  meta_json     JSONB NOT NULL DEFAULT '{}',
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ICS tokens (for calendar feeds)
CREATE TABLE IF NOT EXISTS ics_tokens (
  id         BIGSERIAL PRIMARY KEY,
  user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  revoked_at TIMESTAMPTZ NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS ics_tokens_user_token
  ON ics_tokens(user_id, token_hash);

-- Triggers to keep updated_at fresh
CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$
BEGIN NEW.updated_at = now(); RETURN NEW; END; $$ LANGUAGE plpgsql;

DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'users_updated_at_trg') THEN
    CREATE TRIGGER users_updated_at_trg BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'resources_updated_at_trg') THEN
    CREATE TRIGGER resources_updated_at_trg BEFORE UPDATE ON resources
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
  END IF;
END $$;
