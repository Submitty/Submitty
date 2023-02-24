psql -tc "SELECT 1 FROM pg_user WHERE usename = 'submitty_dbuser'" | grep -q 1 || psql -c "CREATE ROLE submitty_dbuser WITH SUPERUSER CREATEDB CREATEROLE LOGIN PASSWORD 'submitty_dbuser';"
psql -tc "SELECT 1 FROM pg_user WHERE usename = 'vagrant'" | grep -q 1 || psql -c "CREATE ROLE vagrant WITH SUPERUSER CREATEDB CREATEROLE LOGIN PASSWORD 'vagrant';"
