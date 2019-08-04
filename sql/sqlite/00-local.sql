CREATE TABLE chronicle_clients (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  publicid TEXT NOT NULL,
  publickey TEXT NOT NULL,
  isAdmin INTEGER NOT NULL DEFAULT 0,
  comment TEXT,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX chronicle_clients_clientid_idx ON chronicle_clients(publicid);
CREATE INDEX chronicle_clients_publickey_idx ON chronicle_clients(publickey);

CREATE TABLE chronicle_chain (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  data TEXT NOT NULL,
  prevhash TEXT NULL,
  currhash TEXT NOT NULL,
  hashstate TEXT NOT NULL,
  summaryhash TEXT NOT NULL,
  publickey TEXT NOT NULL,
  signature TEXT NOT NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (prevhash) REFERENCES chronicle_chain(currhash),
  FOREIGN KEY (publickey) REFERENCES chronicle_clients(publickey),
  UNIQUE(prevhash),
  UNIQUE(currhash),
  UNIQUE(signature)
);

CREATE INDEX chronicle_chain_prevhash_idx ON chronicle_chain(prevhash);
CREATE INDEX chronicle_chain_currhash_idx ON chronicle_chain(currhash);
CREATE INDEX chronicle_chain_summaryhash_idx ON chronicle_chain(summaryhash);