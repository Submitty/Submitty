CREATE USER contributors_reader WITH PASSWORD 'contributors_reader';
\connect contributors
GRANT SELECT ON ALL TABLES IN SCHEMA public TO contributors_reader;
