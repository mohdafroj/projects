resource "kubernetes_namespace" "managed" {
  for_each = var.enabled ? toset(var.namespaces) : toset([])

  metadata {
    name = each.value

    labels = {
      environment = var.environment
      managed_by  = "terraform"
    }
  }
}
