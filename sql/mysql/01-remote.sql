CREATE TABLE chronicle_xsign_targets (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  name TEXT,
  url TEXT,
  publickey TEXT,
  policy TEXT,
  lastrun TEXT
);

CREATE TABLE chronicle_replication_sources (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  uniqueid TEXT,
  name TEXT,
  url TEXT,
  policy TEXT,
  publickey TEXT
);

CREATE TABLE chronicle_replication_chain (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  source INT REFERENCES chronicle_replication_sources(id),
  data TEXT,
  prevhash TEXT,
  currhash TEXT,
  hashstate TEXT,
  summaryhash TEXT,
  publickey TEXT,
  signature TEXT,
  created DATETIME
);
