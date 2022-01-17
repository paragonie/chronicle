CREATE TABLE chronicle_mirrors (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `url` TEXT NOT NULL,
    `publickey` TEXT NOT NULL,
    `comment` TEXT NULL,
    `sortpriority` INT NOT NULL DEFAULT 0,
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE NOW()
);

CREATE INDEX chronicle_mirrors_url_idx ON chronicle_mirrors(`url`);
