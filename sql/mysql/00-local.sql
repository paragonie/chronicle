CREATE TABLE chronicle_clients (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `publicid` VARCHAR(128),
  `publickey` TEXT,
  `isAdmin` BOOLEAN NOT NULL DEFAULT FALSE,
  `comment` TEXT,
  `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE NOW()
);

CREATE INDEX chronicle_clients_clientid_idx ON chronicle_clients(`publicid`);

CREATE TABLE chronicle_chain (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `data` TEXT,
  `prevhash` VARCHAR(128) NOT NULL,
  `currhash` VARCHAR(128) NOT NULL,
  `hashstate` TEXT,
  `summaryhash` VARCHAR(128),
  `publickey` TEXT,
  `signature` TEXT,
  `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(`prevhash`),
  INDEX(`currhash`),
  FOREIGN KEY (`currhash`) REFERENCES chronicle_chain(`prevhash`) ON DELETE RESTRICT,
  UNIQUE(`prevhash`)
);

CREATE INDEX chronicle_chain_prevhash_idx ON chronicle_chain(`prevhash`);
CREATE INDEX chronicle_chain_currhash_idx ON chronicle_chain(`currhash`);
CREATE INDEX chronicle_chain_summaryhash_idx ON chronicle_chain(`summaryhash`);
