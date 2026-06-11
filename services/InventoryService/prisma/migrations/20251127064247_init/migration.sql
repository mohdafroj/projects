-- CreateEnum
CREATE TYPE "FeatureOn" AS ENUM ('web', 'mobile', 'both');

-- AlterTable
ALTER TABLE "ms_features" ADD COLUMN     "feature_on" "FeatureOn" NOT NULL DEFAULT 'web';
