/*
  Warnings:

  - A unique constraint covering the columns `[name]` on the table `ms_roles` will be added. If there are existing duplicate values, this will fail.
  - Added the required column `module_id` to the `ms_features` table without a default value. This is not possible if the table is not empty.

*/
-- AlterTable
ALTER TABLE "ms_features" ADD COLUMN     "module_id" INTEGER NOT NULL;

-- CreateIndex
CREATE UNIQUE INDEX "ms_roles_name_key" ON "ms_roles"("name");

-- AddForeignKey
ALTER TABLE "ms_features" ADD CONSTRAINT "ms_features_module_id_fkey" FOREIGN KEY ("module_id") REFERENCES "ms_modules"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
