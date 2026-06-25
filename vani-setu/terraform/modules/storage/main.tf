resource "local_file" "storage_plan" {
  filename = "${path.root}/.generated/${var.environment}-storage-plan.json"
  content = jsonencode({
    environment        = var.environment
    storage_class_name = var.storage_class_name
    restore_required   = true
    note               = "PVC sizing, restore order, retention, and backup ownership must be approved before production apply."
  })
}
