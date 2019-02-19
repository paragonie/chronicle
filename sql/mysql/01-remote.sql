CREATE TABLE chronicle_xsign_targets (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `name` TEXT NOT NULL,
  `url` TEXT NOT NULL,
  `clientid` TEXT NOT NULL,
  `publickey` TEXT NOT NULL,
  `policy` TEXT NOT NULL,
  `lastrun` TEXT
);

CREATE TABLE chronicle_replication_sources (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `uniqueid` TEXT NOT NULL,
  `name` TEXT NOT NULL,
  `url` TEXT NOT NULL,
  `publickey` TEXT NOT NULL
);

CREATE TABLE chronicle_replication_chain (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `source` BIGINT UNSIGNED NOT NULL REFERENCES chronicle_replication_sources(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  `data` TEXT NOT NULL,
  `prevhash` VARCHAR(128) NULL,
  `currhash` VARCHAR(128) NOT NULL,
  `hashstate` TEXT NOT NULL,
  `summaryhash` VARCHAR(128),
  `publickey` TEXT NOT NULL,
  `signature` TEXT NOT NULL,
  `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `replicated` TIMESTAMP NULL,
  INDEX(`prevhash`),
  INDEX(`currhash`),
  FOREIGN KEY (`prevhash`) REFERENCES chronicle_replication_chain(`currhash`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE(`source`, `prevhash`)
);

CREATE INDEX chronicle_replication_chain_prevhash_idx ON chronicle_replication_chain(`source`, `prevhash`);
CREATE INDEX chronicle_replication_chain_currhash_idx ON chronicle_replication_chain(`source`, `currhash`);
CREATE INDEX chronicle_replication_chain_summaryhash_idx ON chronicle_replication_chain(`source`, `summaryhash`);
