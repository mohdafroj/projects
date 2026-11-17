require('dotenv').config();
const app = require('./app');
const { connectDatabase } = require('./config/db');

async function start() {
  if (!process.env.JWT_SECRET) {
    throw new Error('JWT_SECRET is required');
  }
  await connectDatabase();
  const port = Number(process.env.PORT) || 4000;
  app.listen(port, () => console.log(`Employee management API listening on port ${port}`));
}

start().catch((error) => {
  console.error('Unable to start the API:', error);
  process.exitCode = 1;
});
