-- Ejecutado automáticamente en el primer arranque de Postgres
-- (docker-entrypoint-initdb.d solo corre cuando el volumen está vacío)

-- public ya existe por defecto
CREATE SCHEMA IF NOT EXISTS tini_raw;
CREATE SCHEMA IF NOT EXISTS pherce_intel;

-- permisos al usuario de la app sobre los 3 schemas
GRANT USAGE, CREATE ON SCHEMA public        TO ferreteria;
GRANT USAGE, CREATE ON SCHEMA tini_raw      TO ferreteria;
GRANT USAGE, CREATE ON SCHEMA pherce_intel  TO ferreteria;

-- search_path default: public primero, luego intel, luego raw
ALTER DATABASE ferreteria SET search_path TO public, pherce_intel, tini_raw;

-- comentarios descriptivos (útiles al inspeccionar con \dn+ en psql)
COMMENT ON SCHEMA public       IS 'System base: users, roles, branches, auth, cache, jobs';
COMMENT ON SCHEMA tini_raw     IS 'Read-only replica of TINI .dat files (ETL-only writes)';
COMMENT ON SCHEMA pherce_intel IS 'Pherce intelligence layer: stock states, thresholds, alerts, confirmations';
