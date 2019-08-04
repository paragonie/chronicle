CREATE TABLE chronicle_xsign_targets (
  id BIGSERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  url TEXT NOT NULL,
  clientid TEXT NOT NULL,
  publickey TEXT NOT NULL,
  policy TEXT NOT NULL,
  lastrun TEXT
);

CREATE TABLE chronicle_replication_sources (
  id BIGSERIAL PRIMARY KEY,
  uniqueid TEXT NOT NULL,
  name TEXT NOT NULL,
  url TEXT NOT NULL,
  publickey TEXT NOT NULL
);

CREATE TABLE chronicle_replication_chain (
  id BIGSERIAL PRIMARY KEY,
  source BIGINT NOT NULL REFERENCES chronicle_replication_sources(id),
  data TEXT NOT NULL,
  prevhash TEXT NULL,
  currhash TEXT NOT NULL,
  hashstate TEXT NOT NULL,
  summaryhash TEXT NOT NULL,
  publickey TEXT NOT NULL,
  signature TEXT NOT NULL,
  created TIMESTAMP,
  replicated TIMESTAMP,
  UNIQUE(currhash),
  UNIQUE(prevhash),
  UNIQUE(source, prevhash)
);

CREATE INDEX chronicle_replication_chain_prevhash_idx ON chronicle_replication_chain(source, prevhash);
CREATE INDEX chronicle_replication_chain_currhash_idx ON chronicle_replication_chain(source, currhash);
CREATE INDEX chronicle_replication_chain_summaryhash_idx ON chronicle_replication_chain(source, summaryhash);