resource "local_file" "harbor_plan" {
  filename = "${path.root}/.generated/harbor-plan.json"
  content = jsonencode({
    enabled                 = var.enabled
    baseline_version        = "v2.12.4"
    harbor_registry_project = var.harbor_registry_project
    note                    = "Confirm Harbor URL, projects, robot accounts, immutable tags, and image rewrite policy before apply."
  })
}
