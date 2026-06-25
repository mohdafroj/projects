resource "local_file" "gitops_plan" {
  filename = "${path.root}/.generated/${var.environment}-gitops-plan.json"
  content = jsonencode({
    enabled            = var.enabled
    gitops_repo_url    = var.gitops_repo_url
    gitops_repo_branch = var.gitops_repo_branch
    root_path          = "environments/${var.gitops_repo_branch}"
    note               = "Argo CD deploys Kubernetes application workloads from approved Git repositories."
  })
}
