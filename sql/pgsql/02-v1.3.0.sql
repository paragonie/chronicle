CREATE TABLE chronicle_mirrors (
  id BIGSERIAL PRIMARY KEY,
  url TEXT NOT NULL,
  publickey TEXT NOT NULL,
  comment TEXT NULL,
  sortpriority INTEGER NOT NULL DEFAULT 0,
  created TIMESTAMP,
  modified TIMESTAMP
);

CREATE INDEX chronicle_mirrors_url_idx ON chronicle_mirrors(url);
