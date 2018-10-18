CREATE TABLE chronicle_clients (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  publicid TEXT,
  publickey TEXT,
  isAdmin BOOLEAN NOT NULL DEFAULT FALSE,
  comment TEXT,
  created DATETIME,
  modified DATETIME
);

CREATE INDEX chronicle_clients_clientid_idx ON chronicle_clients(publicid);

CREATE TABLE chronicle_chain (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  data TEXT,
  prevhash TEXT NULL,
  currhash TEXT,
  hashstate TEXT,
  summaryhash TEXT,
  publickey TEXT,
  signature TEXT,
  created DATETIME,
  FOREIGN KEY (currhash) REFERENCES chronicle_chain(prevhash),
  UNIQUE(prevhash)
);

CREATE INDEX chronicle_chain_prevhash_idx ON chronicle_chain(prevhash);
CREATE INDEX chronicle_chain_currhash_idx ON chronicle_chain(currhash);
CREATE INDEX chronicle_chain_summaryhash_idx ON chronicle_chain(summaryhash);
