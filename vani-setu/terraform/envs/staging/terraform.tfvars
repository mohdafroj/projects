environment       = "staging"
environment_owner = "pending"

cluster_name            = "sds-staging"
node_name               = "sds-staging1"
node_ip                 = "10.21.217.132"
app_host_ip             = "10.21.217.17"
shared_services_host_ip = "10.21.217.132"

ssh_user        = "sds-dev"
ssh_key_path    = "~/.ssh/id_rsa"
kubeconfig_path = "~/.kube/config"

k3s_version        = "v1.35.4+k3s1"
storage_class_name = "local-path"
internal_domain    = "sds.internal"

public_hosts = [
  "app.staging.sds.internal",
]

gitops_repo_url    = "https://git.example.local/sds/platform-gitops.git"
gitops_repo_branch = "staging"
argocd_hostname    = "argocd.staging.sds.internal"
tls_issuer_name    = "sds-ca-issuer"

vault_path_prefix       = "kv/staging"
harbor_registry_project = "sds-staging"

enable_remote_changes            = false
enable_k3s                       = false
enable_namespaces                = false
enable_cert_manager              = false
enable_argocd                    = false
enable_linkerd                   = false
enable_vault                     = true
enable_harbor                    = true
enable_monitoring                = true
enable_docker_service_recreation = false
production_apply_approved        = false

namespaces = [
  "argocd",
  "cert-manager",
  "linkerd",
  "vault",
  "harbor",
  "monitoring",
  "sds-staging-infra",
]
