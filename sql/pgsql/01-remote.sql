CREATE TABLE chronicle_xsign_targets (
  id BIGSERIAL PRIMARY KEY,
  name TEXT,
  url TEXT,
  clientid TEXT,
  publickey TEXT,
  policy TEXT,
  lastrun TEXT
);

CREATE TABLE chronicle_replication_sources (
  id BIGSERIAL PRIMARY KEY,
  uniqueid TEXT,
  name TEXT,
  url TEXT,
  publickey TEXT
);

CREATE TABLE chronicle_replication_chain (
  id BIGSERIAL PRIMARY KEY,
  source BIGINT REFERENCES chronicle_replication_sources(id),
  data TEXT,
  prevhash TEXT NULL,
  currhash TEXT,
  hashstate TEXT,
  summaryhash TEXT,
  publickey TEXT,
  signature TEXT,
  created TIMESTAMP,
  replicated TIMESTAMP,
  FOREIGN KEY (currhash) REFERENCES chronicle_replication_chain(prevhash),
  UNIQUE(source, prevhash)
);

CREATE INDEX chronicle_replication_chain_prevhash_idx ON chronicle_replication_chain(source, prevhash);
CREATE INDEX chronicle_replication_chain_currhash_idx ON chronicle_replication_chain(source, currhash);
CREATE INDEX chronicle_replication_chain_summaryhash_idx ON chronicle_replication_chain(source, summaryhash);
