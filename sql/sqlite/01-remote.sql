CREATE TABLE chronicle_xsign_targets (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  name TEXT NOT NULL,
  url TEXT NOT NULL,
  clientid TEXT NOT NULL,
  publickey TEXT NOT NULL,
  policy TEXT NOT NULL,
  lastrun TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE chronicle_replication_sources (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  uniqueid TEXT NOT NULL,
  name TEXT NOT NULL,
  url TEXT NOT NULL,
  publickey TEXT NOT NULL
);

CREATE TABLE chronicle_replication_chain (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  source INTEGER NOT NULL,
  data TEXT NOT NULL,
  prevhash TEXT NULL,
  currhash TEXT NOT NULL,
  hashstate TEXT NOT NULL,
  summaryhash TEXT NOT NULL,
  publickey TEXT NOT NULL,
  signature TEXT NOT NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  replicated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(currhash),
  UNIQUE(source, prevhash),
  FOREIGN KEY (source) REFERENCES chronicle_replication_sources(id),
  FOREIGN KEY (prevhash) REFERENCES chronicle_replication_chain(currhash)
);

CREATE INDEX chronicle_replication_chain_prevhash_idx ON chronicle_replication_chain(source, prevhash);
CREATE INDEX chronicle_replication_chain_currhash_idx ON chronicle_replication_chain(source, currhash);
CREATE INDEX chronicle_replication_chain_summaryhash_idx ON chronicle_replication_chain(source, summaryhash);