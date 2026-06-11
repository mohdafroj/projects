/*
  Warnings:

  - You are about to drop the column `slug` on the `ms_features` table. All the data in the column will be lost.
  - You are about to drop the column `slug` on the `ms_modules` table. All the data in the column will be lost.
  - You are about to drop the column `slug` on the `ms_permissions` table. All the data in the column will be lost.
  - A unique constraint covering the columns `[feature_code]` on the table `ms_features` will be added. If there are existing duplicate values, this will fail.
  - A unique constraint covering the columns `[module_code]` on the table `ms_modules` will be added. If there are existing duplicate values, this will fail.
  - A unique constraint covering the columns `[permission_code]` on the table `ms_permissions` will be added. If there are existing duplicate values, this will fail.
  - Added the required column `feature_code` to the `ms_features` table without a default value. This is not possible if the table is not empty.
  - Added the required column `module_code` to the `ms_modules` table without a default value. This is not possible if the table is not empty.
  - Added the required column `permission_code` to the `ms_permissions` table without a default value. This is not possible if the table is not empty.

*/
-- DropIndex
DROP INDEX "ms_features_slug_key";

-- DropIndex
DROP INDEX "ms_modules_slug_key";

-- DropIndex
DROP INDEX "ms_permissions_slug_key";

-- AlterTable
ALTER TABLE "ms_features" DROP COLUMN "slug",
ADD COLUMN     "feature_code" TEXT NOT NULL;

-- AlterTable
ALTER TABLE "ms_modules" DROP COLUMN "slug",
ADD COLUMN     "module_code" TEXT NOT NULL;

-- AlterTable
ALTER TABLE "ms_permissions" DROP COLUMN "slug",
ADD COLUMN     "permission_code" TEXT NOT NULL;

-- CreateIndex
CREATE UNIQUE INDEX "ms_features_feature_code_key" ON "ms_features"("feature_code");

-- CreateIndex
CREATE UNIQUE INDEX "ms_modules_module_code_key" ON "ms_modules"("module_code");

-- CreateIndex
CREATE UNIQUE INDEX "ms_permissions_permission_code_key" ON "ms_permissions"("permission_code");
