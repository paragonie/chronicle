CREATE TABLE chronicle_xsign_targets (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `name` TEXT,
  `url` TEXT,
  `clientid` TEXT,
  `publickey` TEXT,
  `policy` TEXT,
  `lastrun` TEXT
);

CREATE TABLE chronicle_replication_sources (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `uniqueid` TEXT,
  `name` TEXT,
  `url` TEXT,
  `publickey` TEXT
);

CREATE TABLE chronicle_replication_chain (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `source` BIGINT UNSIGNED REFERENCES chronicle_replication_sources(`id`) ON DELETE RESTRICT,
  `data` TEXT,
  `prevhash` VARCHAR(128) NOT NULL,
  `currhash` VARCHAR(128) NOT NULL,
  `hashstate` TEXT,
  `summaryhash` VARCHAR(128),
  `publickey` TEXT,
  `signature` TEXT,
  `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `replicated` TIMESTAMP NULL,
  INDEX(`prevhash`),
  INDEX(`currhash`),
  FOREIGN KEY (`currhash`) REFERENCES chronicle_replication_chain(`prevhash`) ON DELETE RESTRICT,
  UNIQUE(`source`, `prevhash`)
);

CREATE INDEX chronicle_replication_chain_prevhash_idx ON chronicle_replication_chain(`source`, `prevhash`);
CREATE INDEX chronicle_replication_chain_currhash_idx ON chronicle_replication_chain(`source`, `currhash`);
CREATE INDEX chronicle_replication_chain_summaryhash_idx ON chronicle_replication_chain(`source`, `summaryhash`);
