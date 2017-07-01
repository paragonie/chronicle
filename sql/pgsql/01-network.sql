CREATE TABLE chronicle_xsign_targets (
  id BIGSERIAL PRIMARY KEY,
  name TEXT,
  url TEXT,
  publickey TEXT,
  policy TEXT,
  lastrun TEXT
);

CREATE TABLE chronicle_replication_sources (
  id BIGSERIAL PRIMARY KEY,
  name TEXT,
  url TEXT,
  publickey TEXT
);

CREATE TABLE chronicle_replication_chain (
  id BIGSERIAL PRIMARY KEY,
  source BIGINT REFERENCES chronicle_replication_sources(id),
  data TEXT,
  prevhash TEXT,
  currhash TEXT,
  hashstate TEXT,
  summaryhash TEXT,
  publickey TEXT,
  signature TEXT,
  created TIMESTAMP
);
