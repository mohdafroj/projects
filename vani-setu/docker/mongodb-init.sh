#!/bin/sh
set -eu

mongosh --quiet \
  --username "${MONGO_INITDB_ROOT_USERNAME}" \
  --password "${MONGO_INITDB_ROOT_PASSWORD}" \
  --authenticationDatabase "${MONGO_INITDB_DATABASE}" <<'EOF'
const dbName = process.env.MONGODB_APP_DATABASE || 'tijori_setu';
const username = process.env.MONGODB_APP_USERNAME || 'tijori';
const password = process.env.MONGODB_APP_PASSWORD;

if (!password) {
  throw new Error('MONGODB_APP_PASSWORD must be set');
}

const appDb = db.getSiblingDB(dbName);
const existing = appDb.getUser(username);

if (!existing) {
  appDb.createUser({
    user: username,
    pwd: password,
    roles: [
      { role: 'readWrite', db: dbName },
    ],
  });
}
EOF
