/*
  Warnings:

  - You are about to drop the `ms_module_feature_permisions` table. If the table is not empty, all the data it contains will be lost.
  - You are about to drop the `ms_permisions` table. If the table is not empty, all the data it contains will be lost.

*/
-- DropForeignKey
ALTER TABLE "ms_module_feature_permisions" DROP CONSTRAINT "ms_module_feature_permisions_feature_id_fkey";

-- DropForeignKey
ALTER TABLE "ms_module_feature_permisions" DROP CONSTRAINT "ms_module_feature_permisions_module_id_fkey";

-- DropForeignKey
ALTER TABLE "ms_module_feature_permisions" DROP CONSTRAINT "ms_module_feature_permisions_permision_id_fkey";

-- DropTable
DROP TABLE "ms_module_feature_permisions";

-- DropTable
DROP TABLE "ms_permisions";

-- CreateTable
CREATE TABLE "ms_permissions" (
    "id" SERIAL NOT NULL,
    "name" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ms_permissions_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ms_module_feature_permissions" (
    "id" SERIAL NOT NULL,
    "module_id" INTEGER NOT NULL,
    "feature_id" INTEGER NOT NULL,
    "permission_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ms_module_feature_permissions_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "ms_permissions_slug_key" ON "ms_permissions"("slug");

-- AddForeignKey
ALTER TABLE "ms_module_feature_permissions" ADD CONSTRAINT "ms_module_feature_permissions_module_id_fkey" FOREIGN KEY ("module_id") REFERENCES "ms_modules"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ms_module_feature_permissions" ADD CONSTRAINT "ms_module_feature_permissions_feature_id_fkey" FOREIGN KEY ("feature_id") REFERENCES "ms_features"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ms_module_feature_permissions" ADD CONSTRAINT "ms_module_feature_permissions_permission_id_fkey" FOREIGN KEY ("permission_id") REFERENCES "ms_permissions"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
