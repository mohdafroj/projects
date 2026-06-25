resource "helm_release" "cert_manager" {
  count = var.enabled ? 1 : 0

  name             = "cert-manager"
  namespace        = "cert-manager"
  repository       = "https://charts.jetstack.io"
  chart            = "cert-manager"
  version          = "v1.16.2"
  create_namespace = true

  set {
    name  = "crds.enabled"
    value = "true"
  }
}

resource "local_file" "issuer_plan" {
  filename = "${path.root}/.generated/cert-manager-issuer-plan.json"
  content = jsonencode({
    issuer_name = var.issuer_name
    version     = "v1.16.2"
    note        = "CA, DNS validation method, renewal owner, and TLS secret naming must be approved before production."
  })
}
