resource "local_file" "vault_plan" {
  filename = "${path.root}/.generated/vault-plan.json"
  content = jsonencode({
    enabled           = var.enabled
    vault_path_prefix = var.vault_path_prefix
    baseline_version  = "1.7.0"
    note              = "This module records the approved Vault path prefix. Secret values must remain outside Terraform state."
  })
}
