resource "local_file" "monitoring_plan" {
  filename = "${path.root}/.generated/monitoring-plan.json"
  content = jsonencode({
    enabled = var.enabled
    baseline = {
      grafana      = "13.0.1"
      prometheus   = "v0.90.1"
      loki         = "3.6.7"
      alertmanager = "v0.27.0"
    }
    note = "Dashboards, alert routes, log retention, and escalation ownership must be approved per environment."
  })
}
