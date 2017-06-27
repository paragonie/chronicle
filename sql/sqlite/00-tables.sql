CREATE TABLE chronicle_clients (
  id INTEGER PRIMARY KEY ASC,
  publicid TEXT,
  publickey TEXT,
  isAdmin INTEGER,
  comment TEXT,
  created TEXT,
  modified TEXT
);

CREATE INDEX chronicle_clients_clientid_idx ON chronicle_clients(publicid);

CREATE TABLE chronicle_chain (
  id INTEGER PRIMARY KEY ASC,
  data TEXT,
  prevhash TEXT,
  currhash TEXT,
  hashstate TEXT,
  summaryhash TEXT,
  publickey TEXT,
  signature TEXT,
  created TEXT
);

CREATE INDEX chronicle_chain_prevhash_idx ON chronicle_chain(prevhash);
CREATE INDEX chronicle_chain_currhash_idx ON chronicle_chain(currhash);
CREATE INDEX chronicle_chain_summaryhash_idx ON chronicle_chain(summaryhash);
