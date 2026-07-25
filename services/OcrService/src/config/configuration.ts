export default () => ({
  port: parseInt(process.env.PORT || '3000', 10),
  mongodbUri: process.env.MONGODB_URI || 'mongodb://admin:admin123@localhost:27017/ocr_db?authSource=admin',
  redisUrl: process.env.REDIS_URL || 'redis://localhost:6379/0',
  opensearchUrl: process.env.OPENSEARCH_URL || 'http://localhost:9200',
  enableVectorSearch: process.env.ENABLE_VECTOR_SEARCH !== 'false',
});
