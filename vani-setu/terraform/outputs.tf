output "environment" {
  value = var.environment
}

output "remote_changes_allowed" {
  value = local.remote_changes_allowed
}

output "kubeconfig_hint" {
  value = module.k3s.kubeconfig_hint
}

output "docker_service_plan_file" {
  value = module.docker_services.plan_file
}
