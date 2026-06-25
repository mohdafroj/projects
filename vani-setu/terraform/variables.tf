variable "environment" {
  type = string

  validation {
    condition     = contains(["staging", "uat", "prod"], var.environment)
    error_message = "environment must be one of staging, uat, or prod."
  }
}

variable "environment_owner" {
  type    = string
  default = "pending"
}

variable "cluster_name" {
  type = string
}

variable "node_name" {
  type = string
}

variable "node_ip" {
  type = string
}

variable "app_host_ip" {
  type = string
}

variable "shared_services_host_ip" {
  type = string
}

variable "ssh_user" {
  type    = string
  default = "sds-dev"
}

variable "ssh_key_path" {
  type    = string
  default = "~/.ssh/id_rsa"
}

variable "kubeconfig_path" {
  type    = string
  default = "~/.kube/config"
}

variable "k3s_version" {
  type    = string
  default = "v1.35.4+k3s1"
}

variable "storage_class_name" {
  type    = string
  default = "local-path"
}

variable "internal_domain" {
  type    = string
  default = "sds.internal"
}

variable "public_hosts" {
  type    = list(string)
  default = []
}

variable "gitops_repo_url" {
  type = string
}

variable "gitops_repo_branch" {
  type = string
}

variable "argocd_hostname" {
  type = string
}

variable "tls_issuer_name" {
  type    = string
  default = "sds-ca-issuer"
}

variable "vault_path_prefix" {
  type = string
}

variable "harbor_registry_project" {
  type = string
}

variable "enable_remote_changes" {
  type    = bool
  default = false
}

variable "enable_k3s" {
  type    = bool
  default = false
}

variable "enable_namespaces" {
  type    = bool
  default = false
}

variable "enable_cert_manager" {
  type    = bool
  default = false
}

variable "enable_argocd" {
  type    = bool
  default = false
}

variable "enable_linkerd" {
  type    = bool
  default = false
}

variable "enable_vault" {
  type    = bool
  default = false
}

variable "enable_harbor" {
  type    = bool
  default = false
}

variable "enable_monitoring" {
  type    = bool
  default = false
}

variable "enable_docker_service_recreation" {
  type    = bool
  default = false
}

variable "production_apply_approved" {
  type    = bool
  default = false

  validation {
    condition     = var.environment != "prod" || var.production_apply_approved == false || var.enable_remote_changes == true
    error_message = "production_apply_approved is meaningful only when enable_remote_changes is also true."
  }
}

variable "namespaces" {
  type = list(string)
  default = [
    "argocd",
    "cert-manager",
    "linkerd",
    "vault",
    "harbor",
    "monitoring",
    "sds-staging-infra",
    "sds-uat-infra",
    "sds-prod-infra",
  ]
}
