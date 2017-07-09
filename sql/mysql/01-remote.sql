CREATE TABLE chronicle_xsign_targets (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name TEXT,
  url TEXT,
  publickey TEXT,
  policy TEXT,
  lastrun TEXT
);

CREATE TABLE chronicle_replication_sources (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  uniqueid TEXT,
  name TEXT,
  url TEXT,
  publickey TEXT
);

CREATE TABLE chronicle_replication_chain (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source BIGINT UNSIGNED REFERENCES chronicle_replication_sources(id),
  data TEXT,
  prevhash TEXT,
  currhash TEXT,
  hashstate TEXT,
  summaryhash TEXT,
  publickey TEXT,
  signature TEXT,
  created DATETIME
);
