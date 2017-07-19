CREATE TABLE chronicle_clients (
  id BIGSERIAL PRIMARY KEY,
  publicid TEXT,
  publickey TEXT,
  "isAdmin" BOOLEAN DEFAULT FALSE,
  comment TEXT,
  created TIMESTAMP,
  modified TIMESTAMP
);

CREATE INDEX chronicle_clients_clientid_idx ON chronicle_clients(publicid);

CREATE TABLE chronicle_chain (
  id BIGSERIAL PRIMARY KEY,
  data TEXT,
  prevhash TEXT,
  currhash TEXT,
  hashstate TEXT,
  summaryhash TEXT,
  publickey TEXT,
  signature TEXT,
  created TIMESTAMP
);

CREATE INDEX chronicle_chain_prevhash_idx ON chronicle_chain(prevhash);
CREATE INDEX chronicle_chain_currhash_idx ON chronicle_chain(currhash);
CREATE INDEX chronicle_chain_summaryhash_idx ON chronicle_chain(summaryhash);
