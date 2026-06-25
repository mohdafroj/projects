locals {
  remote_changes_allowed = var.environment == "prod" ? var.production_apply_approved && var.enable_remote_changes : var.enable_remote_changes

  docker_services = [
    {
      group        = "shared"
      name         = "sds-common-core-api"
      image        = "sds-common-core-api:0.1.0"
      host         = var.shared_services_host_ip
      ports        = ["8081:8080/tcp"]
      compose_hint = "deploy/two-server-split/132/common-core/docker-compose.yml"
    },
    {
      group        = "tijori"
      name         = "sds-tijori-api"
      image        = "sds-tijori-api:0.13.0-dev1"
      host         = var.shared_services_host_ip
      ports        = ["8002:8000/tcp"]
      compose_hint = "deploy/two-server-split/132/tijori-storage-ocr/docker-compose.yml"
    },
    {
      group        = "reporting"
      name         = "sds-reporting-superset"
      image        = "sds-reporting-superset:6.0.0"
      host         = var.shared_services_host_ip
      ports        = ["8089:8088/tcp"]
      compose_hint = "deploy/two-server-split/132/reporting/docker-compose.yml"
    },
    {
      group        = "reporting"
      name         = "sds-reporting-pipeline"
      image        = "sds-reporting-pipeline:0.1.0"
      host         = var.shared_services_host_ip
      ports        = ["8090:8000/tcp"]
      compose_hint = "deploy/two-server-split/132/reporting/docker-compose.yml"
    },
    {
      group        = "observability"
      name         = "sds-observability-grafana"
      image        = "grafana/grafana:11.2.2"
      host         = var.shared_services_host_ip
      ports        = ["3000:3000/tcp"]
      compose_hint = "deploy/two-server-split/132/observability/docker-compose.yml"
    },
    {
      group        = "parichay"
      name         = "sds-fake-parichay"
      image        = "sds-fake-parichay:0.1.0"
      host         = var.shared_services_host_ip
      ports        = ["18443:18443/tcp"]
      compose_hint = "deploy/two-server-split/132/identity-simulators/docker-compose.yml"
    },
  ]
}

module "host" {
  source = "./modules/host"

  environment    = var.environment
  ssh_user       = var.ssh_user
  ssh_key_path   = var.ssh_key_path
  app_host_ip    = var.app_host_ip
  shared_host_ip = var.shared_services_host_ip
  enable_changes = local.remote_changes_allowed
  required_ports = [8081, 8002, 8090, 8089, 9000, 18443, 3100]
  required_tools = ["docker", "docker compose", "k3s", "kubectl", "ufw", "chrony", "fail2ban", "auditd"]
}

module "k3s" {
  source = "./modules/k3s"

  enabled      = var.enable_k3s && local.remote_changes_allowed
  environment  = var.environment
  cluster_name = var.cluster_name
  node_ip      = var.node_ip
  node_name    = var.node_name
  ssh_user     = var.ssh_user
  ssh_key_path = var.ssh_key_path
  k3s_version  = var.k3s_version
}

module "namespaces" {
  source = "./modules/namespaces"

  enabled     = var.enable_namespaces
  environment = var.environment
  namespaces  = var.namespaces
}

module "storage" {
  source = "./modules/storage"

  environment        = var.environment
  storage_class_name = var.storage_class_name
}

module "cert_manager" {
  source = "./modules/cert-manager"

  enabled     = var.enable_cert_manager
  issuer_name = var.tls_issuer_name

  depends_on = [module.namespaces]
}

module "argocd" {
  source = "./modules/argocd"

  enabled            = var.enable_argocd
  hostname           = var.argocd_hostname
  gitops_repo_url    = var.gitops_repo_url
  gitops_repo_branch = var.gitops_repo_branch

  depends_on = [module.cert_manager]
}

module "linkerd" {
  source  = "./modules/linkerd"
  enabled = var.enable_linkerd
}

module "vault" {
  source = "./modules/vault"

  enabled           = var.enable_vault
  vault_path_prefix = var.vault_path_prefix
}

module "harbor" {
  source = "./modules/harbor"

  enabled                 = var.enable_harbor
  harbor_registry_project = var.harbor_registry_project
}

module "monitoring" {
  source  = "./modules/monitoring"
  enabled = var.enable_monitoring
}

module "docker_services" {
  source = "./modules/docker-services"

  environment     = var.environment
  enable_recreate = var.enable_docker_service_recreation && local.remote_changes_allowed
  ssh_user        = var.ssh_user
  ssh_key_path    = var.ssh_key_path
  services        = local.docker_services
}

module "gitops_apps" {
  source = "./modules/gitops-apps"

  enabled            = var.enable_argocd
  environment        = var.environment
  gitops_repo_url    = var.gitops_repo_url
  gitops_repo_branch = var.gitops_repo_branch
}
