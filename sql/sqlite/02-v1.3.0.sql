CREATE TABLE chronicle_mirrors (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  url TEXT NOT NULL,
  publickey TEXT NOT NULL,
  comment TEXT NULL,
  sortpriority INTEGER NOT NULL DEFAULT 0,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX chronicle_mirrors_url_idx ON chronicle_mirrors(url);
