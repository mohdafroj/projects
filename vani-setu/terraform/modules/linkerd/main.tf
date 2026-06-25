resource "helm_release" "linkerd_crds" {
  count = var.enabled ? 1 : 0

  name             = "linkerd-crds"
  namespace        = "linkerd"
  repository       = "https://helm.linkerd.io/stable"
  chart            = "linkerd-crds"
  create_namespace = true
}

resource "helm_release" "linkerd_control_plane" {
  count = var.enabled ? 1 : 0

  name       = "linkerd-control-plane"
  namespace  = "linkerd"
  repository = "https://helm.linkerd.io/stable"
  chart      = "linkerd-control-plane"

  depends_on = [helm_release.linkerd_crds]
}
