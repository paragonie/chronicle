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
  `data` TEXT NOT NULL,
  `prevhash` VARCHAR(128) NULL,
  `currhash` VARCHAR(128) NOT NULL,
  `hashstate` TEXT NOT NULL,
  `summaryhash` VARCHAR(128) NOT NULL,
  `publickey` TEXT NOT NULL,
  `signature` TEXT NOT NULL,
  `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(`prevhash`),
  INDEX(`currhash`),
  INDEX(`summaryhash`),
  FOREIGN KEY (`prevhash`) REFERENCES chronicle_chain(`currhash`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE(`prevhash`)
);

-- Prevent all types of update to all fields on the chain

CREATE TRIGGER chronicle_chain_trigger
BEFORE UPDATE ON chronicle_chain 
FOR EACH ROW SET  NEW.id = OLD.id,
                  NEW.data = OLD.data,
                  NEW.prevhash = OLD.prevhash,
                  NEW.currhash = OLD.currhash,
                  NEW.hashstate = OLD.hashstate,
                  NEW.summaryhash = OLD.summaryhash,
                  NEW.publickey = OLD.publickey,
                  NEW.signature = OLD.signature,
                  NEW.created = OLD.created;
