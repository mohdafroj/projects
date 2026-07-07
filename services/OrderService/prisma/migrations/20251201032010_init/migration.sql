/*
  Warnings:

  - You are about to drop the `ms_module_feature_permissions` table. If the table is not empty, all the data it contains will be lost.

*/
-- DropForeignKey
ALTER TABLE "ms_module_feature_permissions" DROP CONSTRAINT "ms_module_feature_permissions_feature_id_fkey";

-- DropForeignKey
ALTER TABLE "ms_module_feature_permissions" DROP CONSTRAINT "ms_module_feature_permissions_module_id_fkey";

-- DropForeignKey
ALTER TABLE "ms_module_feature_permissions" DROP CONSTRAINT "ms_module_feature_permissions_permission_id_fkey";

-- DropTable
DROP TABLE "ms_module_feature_permissions";

-- CreateTable
CREATE TABLE "ms_mfp" (
    "id" SERIAL NOT NULL,
    "module_id" INTEGER NOT NULL,
    "feature_id" INTEGER NOT NULL,
    "permission_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ms_mfp_pkey" PRIMARY KEY ("id")
);

-- AddForeignKey
ALTER TABLE "ms_mfp" ADD CONSTRAINT "ms_mfp_module_id_fkey" FOREIGN KEY ("module_id") REFERENCES "ms_modules"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ms_mfp" ADD CONSTRAINT "ms_mfp_feature_id_fkey" FOREIGN KEY ("feature_id") REFERENCES "ms_features"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ms_mfp" ADD CONSTRAINT "ms_mfp_permission_id_fkey" FOREIGN KEY ("permission_id") REFERENCES "ms_permissions"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
