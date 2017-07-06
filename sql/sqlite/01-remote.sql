CREATE TABLE chronicle_xsign_targets (
  id INTEGER PRIMARY KEY ASC,
  name TEXT,
  url TEXT,
  publickey TEXT,
  policy TEXT,
  lastrun TEXT
);

CREATE TABLE chronicle_replication_sources (
  id INTEGER PRIMARY KEY ASC,
  uniqueid TEXT,
  name TEXT,
  url TEXT,
  policy TEXT,
  publickey TEXT
);

CREATE TABLE chronicle_replication_chain (
  id INTEGER PRIMARY KEY ASC,
  source INTEGER,
  data TEXT,
  prevhash TEXT,
  currhash TEXT,
  hashstate TEXT,
  summaryhash TEXT,
  publickey TEXT,
  signature TEXT,
  created DATETIME
);
